<?php

// error reporting (local only)
if ($_SERVER["REMOTE_ADDR"] == '127.0.0.1') {
    error_reporting(E_ALL);
    ini_set('display_errors', 'on');
}

header('Content-Type: application/json');

require_once('../dbconnect.php');
require_once('../functions.php');
// create new slack object with bot token
$slack = new Slack($conn, 'bot');

// get incoming object to work with
if (isset($_POST['payload'])) {
    $eventPayload = json_decode($_POST['payload'], TRUE);
}
else {
    $eventPayload = json_decode(file_get_contents("php://input"), TRUE);
}

// for testing - just return the payload
// echo json_encode($eventPayload);
// exit();

// push latest app home view
// todo better way of doing this
$json = file_get_contents('views/app-home.json');
$slack->apiCall(
    'views.publish',
    array(
        "user_id" => 'UPC9446BB',
        "view" => $json
    ),
    'bot'
);

// to validate url
if ($eventPayload['type'] == 'url_verification') {
    echo $eventPayload['challenge'];
}

// if ($eventPayload['event']['user'] == 'UPC9446BB') {
//     exit();
// }

// display onboarding modal
// todo should happen on new user join (maybe updatable via second modal?)
if ($eventPayload['type'] == 'message_action') {
    $json = array(
        'trigger_id' => $eventPayload['trigger_id'],
        'view' => json_decode(file_get_contents('views/onboard-modal.json'), TRUE)
    );

    echo $slack->apiCall(
        'views.open',
        $json,
        'bot'
    );
}
// todo add app home stuff?
// else if ($eventPayload['type'] == 'app_home_opened') {
//     echo json_encode($eventPayload);
// }
else if ($eventPayload['type'] == 'block_actions') {
    if ($eventPayload['actions'][0]['value'] == 'edit_profile') {
        $json = array(
            'trigger_id' => $eventPayload['trigger_id'],
            'view' => json_decode(file_get_contents('views/onboard-modal.json'), TRUE)
        );

        echo $slack->apiCall(
            'views.open',
            $json,
            'bot'
        );
    }
    else if ($eventPayload['actions'][0]['value'] == 'send_email') {
        $json = array(
            'trigger_id' => $eventPayload['trigger_id'],
            'view' => json_decode(file_get_contents('views/email-modal.json'), TRUE)
        );

        echo $slack->apiCall(
            'views.open',
            $json,
            'bot'
        );
    }
    else {
        $user_id = $eventPayload['user']['id'];
        $block_id = $eventPayload['actions'][0]['block_id'];
        $value = $eventPayload['actions'][0]['selected_option']['value'];
    
        if ($block_id == 'house') {
            $sql = "
                insert into sl_users (slack_user_id, house_id)
                    values ('$user_id', $value)
                on duplicate key update
                    house_id = values(house_id)
            ";
        }
        else if ($block_id == 'committee') {
            $sql = "
                insert into sl_users (slack_user_id, committee_id)
                    values ('$user_id', $value)
                on duplicate key update
                    committee_id = values(committee_id)
            ";
        }
        // todo populate list from db
        // else if ($block_id == 'room') {
        //     $result = $slack->conn->query("select id from sl_rooms where room = '$value'");
        //     $value = $result->fetch_assoc()['id'];
    
        //     $sql = "
        //         insert into sl_users (slack_user_id, room_id)
        //             values ('$user_id', $value)
        //         on duplicate key update
        //             room_id = values(room_id)
        //     ";
        // }
        // save user info to db
        $slack->conn->query($sql);
    }
}
// on modal submit
else if ($eventPayload['type'] == 'view_submission') {
    $user_id = $eventPayload['user']['id'];

    // get all input fields
    $inputValues = array();
    foreach ($eventPayload['view']['blocks'] as $block) {
        if (isset($block['element'])) {
            $block_id = $block['block_id'];
            $action_id = $block['element']['action_id'];

            $value = $eventPayload
                ['view']
                ['state']
                ['values']
                [$block_id]
                [$action_id];

            $value = isset($value['selected_option']) ? $value['selected_option']['value'] : $value['value'];

            $inputValues[$block['block_id']] = $value;
        }
    }

    if ($eventPayload['view']['callback_id'] == 'email_modal') {
        $slack->sendEmail($inputValues['house_id'], $inputValues['subject'], $inputValues['body']);
    }
    // todo add callback ids
    else {    
        // get user info from slack
        $slackUserInfo = json_decode($slack->apiCall('users.profile.get',
            array('user' => $user_id)
        ), true)['profile'];
        $first_name = $slackUserInfo['first_name'];
        $last_name = $slackUserInfo['last_name'];
        $email = $slackUserInfo['email'];
    
        // get room id
        $room = $inputValues['room'];
        $result = $slack->conn->query("select id from sl_rooms where room = '$room'");
        $room = $result->fetch_assoc()['id'];
    
        // save all that junk to db
        $sql = "
            insert into sl_users (slack_user_id, room_id, first_name, last_name, email)
                values ('$user_id', $room, '$first_name', '$last_name', '$email')
            on duplicate key update
                room_id = values(room_id),
                first_name = values(first_name),
                last_name = values(last_name),
                email = values(email)
        ";
        $result = $slack->conn->query($sql);
    
        // if room was invalid, throw error
        if (!$result) {
            echo json_encode(array(
                'response_action' => 'errors',
                'errors' => array(
                    'room' => 'Please enter a valid room number.'
                )
            ));
        }
    
        // get user info from db
        $sql = "
            select
                u.house_id,
                u.committee_id,
                h.slack_group_id as 'slack_house_id',
                c.slack_group_id as 'slack_committee_id',
                c.name as 'committee_name'
            from sl_users as u
            left join sl_houses as h on h.`id` = u.`house_id`
            left join sl_committees as c on c.`id` = u.`committee_id`
            where u.slack_user_id = '$user_id'
        ";
        $result = $slack->conn->query($sql);
        $userDbInfo = $result->fetch_assoc();
    
        // update slack profile
        $slack->apiCall(
            'users.profile.set',
            array(
                'user' => $eventPayload['user']['id'],
                'profile' => array(
                    'fields' => array(
                        // pronouns
                        $slack->config['PRONOUNS_FIELD_ID'] => array(
                            'value' => $inputValues['pronouns']
                        ),
                        // committee
                        $slack->config['COMMITTEE_FIELD_ID'] => array(
                            'value' => $userDbInfo['committee_name']
                        )
                    )
                )
            ),
            'write'
        );
    
        $slack->addToUsergroup($user_id, $userDbInfo['slack_house_id']);
    
        if ($userDbInfo['committee_id']) {
            $slack->addToUsergroup($user_id, $userDbInfo['slack_committee_id']);
        }
    }

    header("HTTP/1.1 204 NO CONTENT");
}
// todo on cancel - delete user from db....or at least wipe out usergroup associations

if (isset($eventPayload['event'])) {
    // testing - if 'baby yoda' do stuff
    if ($eventPayload['event']['type'] == 'message') {
        if ($eventPayload['event']['text'] == 'baby yoda') {
            echo $slack->apiCall(
                'chat.postMessage',
                array(
                    'channel' => $eventPayload['event']['channel'],
                    'text' => "i'm the baby gotta love me"
                )
            );
        }
    };
}

