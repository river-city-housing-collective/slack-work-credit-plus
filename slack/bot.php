<?php
error_reporting(E_ALL);
ini_set('display_errors', 'on');

header('Content-Type: application/json');

require_once('../dbconnect.php');
require_once('../functions.php');

// create new slack object
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

// display modal
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

    $users = $slack->apiCall(
        'usergroups.users.list',
        'usergroup=SQKUQC4F4',
        'read',
        true
    );

    echo $users;

    //update slack usergroup
    // echo $slack->apiCall(
    //     $slack->config['WRITE_TOKEN'],
    //     'usergroups.users.update',
    //     array(
    //         'usergroup' => $eventPayload['actions'][0]['selected_option']['value'],
    //         'users' => $eventPayload['user']['id']
    //     )
    // );

    // echo json_encode($eventPayload);
}
// on modal submit
else if ($eventPayload['type'] == 'view_submission') {
    $user_id = $eventPayload['user']['id'];

    // save user info to db
    // echo $slack->updateUserDb($user_id, array(
    //     'slack_username' => $eventPayload['user']['username'],
    //     'house_id' => $eventPayload['actions'][0]['selected_option']['value'],
    //     'committee_id' => 3
    // ));

    $blocks = array();

    foreach ($eventPayload['view']['blocks'] as $block) {
        if (isset($block['element'])) {
            $blocks[$block['block_id']] = array(
                'block_id' => $block['block_id'],
                'action_id' => $block['element']['action_id']
            );
        }
    }

    // update slack profile
    $slack->apiCall(
        'users.profile.set',
        array(
            'user' => $eventPayload['user']['id'],
            'profile' => array(
                'fields' => array(
                    // pronouns
                    'XfRPC4V6EP' => array(
                        'value' => $eventPayload
                        ['view']
                        ['state']
                        ['values']
                        [$blocks['pronouns']['block_id']]
                        [$blocks['pronouns']['action_id']]
                        ['value']
                    ),
                    // committee
                    'XfQYNRUN1W' => array(
                        'value' => 'Finance & Development'
                    )
                )
            )
        ),
        'write'
    );

    header("HTTP/1.1 204 NO CONTENT");
}

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

