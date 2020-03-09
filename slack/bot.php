<?php

error_reporting(E_ALL);
ini_set('display_errors', 'on');

header('Content-Type: application/json');

require_once('../dbconnect.php');
require_once('../functions.php');

// create new slack object
$slack = new Slack($conn);

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
        $json
    );
}
// when select changes on modal
else if ($eventPayload['type'] == 'block_actions') {
    $user_id = $eventPayload['user']['id'];

    // echo $db->userLookup($eventPayload['user']['id']);

    echo $db->userUpdate($user_id, array(
        'slack_username' => $eventPayload['user']['username'],
        'house_id' => $eventPayload['actions'][0]['selected_option']['value'],
        'committee_id' => 3
    ));

    //update usergroup
    echo $slack->apiCall(
        $WRITE_TOKEN,
        'usergroups.users.update',
        array(
            'usergroup' => $eventPayload['actions'][0]['selected_option']['value'],
            'users' => $eventPayload['user']['id']
        )
    );

    echo $slack->apiCall(
        $WRITE_TOKEN,
        'users.profile.set',
        array(
            'fields' => array(
                // pronouns
                'XfRPC4V6EP' => array(
                    'value' => 'she/her'
                ),
                // committee
                'XfQYNRUN1W' => array(
                    'value' => 'Finance & Development'
                )
            )
        )
    );


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

