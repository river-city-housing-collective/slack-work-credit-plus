<?php

// error reporting (local only)
if ($_SERVER["REMOTE_ADDR"] == '127.0.0.1' || $_SERVER["REMOTE_ADDR"] == '::1') {
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

// get requesting user id, event type
$user_id = $eventPayload['user']['id'];
$type = $eventPayload['type'];

// to validate url
if ($type == 'url_verification') {
    echo $eventPayload['challenge'];
}

// get callback id if set
if (isset($eventPayload['view'])) {
    $callback_id = $eventPayload['view']['callback_id'];
}
else if (isset($eventPayload['callback_id'])) {
    $callback_id = $eventPayload['callback_id'];
}

// for testing - just return the payload
// echo 'PAYLOAD SENT - ' . json_encode($eventPayload);
// exit();

if ($type == 'message_action') {
    $params = array(
        'body' => $eventPayload['message']['text'],
        'channel_id' => $eventPayload['channel']['id']
    );

    if ($callback_id == 'email') {
        // todo enable for production
        $slack->openModal($eventPayload['trigger_id'], 'send-email-modal', $params);
    }
}
else if ($type == 'block_actions') {
    if ($callback_id == 'app-home') {
        $slack->openModal($eventPayload['trigger_id'], $eventPayload['actions'][0]['value']);
    }
    else {
        $view_id = $eventPayload['view']['id'];
        $actionData = $eventPayload['actions'][0];
    
        $viewJson[$actionData['block_id']] = $actionData['selected_option']['value'];
        $viewJson = json_encode($viewJson);
    
        // save input data to db
        $sql = "
            insert into sl_view_states (slack_user_id, slack_view_id, json)
                values ('$user_id', '$view_id', '$viewJson')
        ";
        $slack->conn->query($sql);
    }
    // todo change these to static?
    // else {
        // $block_id = $eventPayload['actions'][0]['block_id'];
        // $value = $eventPayload['actions'][0]['selected_option']['value'];
    
        // if ($block_id == 'house') {
        //     $sql = "
        //         insert into sl_users (slack_user_id, house_id)
        //             values ('$user_id', $value)
        //         on duplicate key update
        //             house_id = values(house_id)
        //     ";
        // }
        // else if ($block_id == 'committee') {
        //     $sql = "
        //         insert into sl_users (slack_user_id, committee_id)
        //             values ('$user_id', $value)
        //         on duplicate key update
        //             committee_id = values(committee_id)
        //     ";
        // }
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
        // $slack->conn->query($sql);
    // }
}
// on modal submit
else if ($type == 'view_submission') {
    //todo make sure everything has block/action ids
    // get all input fields
    $inputValues = $slack->getInputValues($eventPayload['view']['state']['values']);

    if ($callback_id == 'send-email-modal') {
        $slack->sendEmail($user_id, $inputValues['subject'], $inputValues['body'], $inputValues['house_id']);
    }
    else if ($callback_id == 'submit-time-modal') {
        // if hours are not valid, throw error
        if (!is_numeric($inputValues['hours_qty']) || !(fmod($inputValues['hours_qty'], 0.25) == 0)) {
            echo json_encode(array(
                'response_action' => 'errors',
                'errors' => array(
                    'hours_qty' => 'Please enter your hours in increments of 0.25'
                )
            ));
        }

        // todo submit work credit to db
    }
    else if ($callback_id == 'edit-profile-modal') { 
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
                'user' => $user_id,
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

