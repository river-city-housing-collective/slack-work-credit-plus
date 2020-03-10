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
        'view' => json_decode(file_get_contents('modals/onboard.json'), TRUE)
    );

    echo $slack->apiCall(
        'views.open',
        $json,
        'bot'
    );
}
else if ($eventPayload['type'] == 'block_actions') {
    $user_id = $eventPayload['user']['id'];

    if ($eventPayload['block_id'] == 'house') { // todo prob wrong
        $sql = "
            insert into sl_users (slack_user_id, house_id)
                values ('$user_id', $id)
            on duplicate key update
                house_id = values(house_id)
        ";
    }
    else if ($eventPayload['block_id'] == 'committee') { // todo prob wrong
        $sql = "
            insert into sl_users (slack_user_id, committee_id)
                values ('$user_id', $id)
            on duplicate key update
                committee_id = values(committee_id)
        ";
    }
    else if ($eventPayload['block_id'] == 'room') { // todo prob wrong
        $sql = "
            insert into sl_users (slack_user_id, room_id)
                values ('$user_id', $id)
            on duplicate key update
                room_id = values(room_id)
        ";
    }
    // save user info to db
    $slack->conn->query($sql);
}
// on modal submit
else if ($eventPayload['type'] == 'view_submission') {
    $user_id = $eventPayload['user']['id'];

    $blocks = array();

    // todo prob just need pronouns
    foreach ($eventPayload['view']['blocks'] as $block) {
        if (isset($block['element'])) {
            $blocks[$block['block_id']] = array(
                'block_id' => $block['block_id'],
                'action_id' => $block['element']['action_id']
            );
        }
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
                        'value' => $eventPayload
                        ['view']
                        ['state']
                        ['values']
                        [$blocks['pronouns']['block_id']]
                        [$blocks['pronouns']['action_id']]
                        ['value']
                    ),
                    // committee
                    $slack->config['COMMITTEE_FIELD_ID'] => array(
                        'value' => $userDbInfo['committee_name'] // todo make sure this is string and not coded?
                    )
                )
            )
        ),
        'write'
    );

    $slack->addToUsergroup($user_id, $userDbInfo['slack_house_id']);
    $slack->addToUsergroup($user_id, $userDbInfo['slack_committee_id']);

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

