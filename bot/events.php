<?php

header('Content-Type: application/json');

require_once('../functions.php');

// create new slack object with bot token
$slack = new Slack($conn);

// get incoming object to work with
$eventPayload = json_decode(file_get_contents("php://input"), TRUE);

// to validate url
if ($eventPayload['type'] == 'url_verification') {
    echo $eventPayload['challenge'];
}
else {
    $eventPayload = $eventPayload['event'];
}

// get requesting user id, event type
$user_id = $eventPayload['user'];
$type = $eventPayload['type'];

if ($type == 'app_home_opened') {
    $json = json_decode(file_get_contents('views/app-home.json'), true);

    // get image and bot name from config
    $json['blocks'][0]['image_url'] = $slack->config['BOT_HOME_IMAGE'];

    echo json_encode($slack->apiCall(
        'views.publish',
        array(
            "user_id" => $user_id,
            "view" => json_encode($json)
        ),
        'bot'
    ));
}
else if ($type == 'team_join') { // team_join
    $msgJson = json_decode(file_get_contents('messages/new-user-greeting.json'), true);

    echo json_encode($slack->apiCall(
        'chat.postMessage',
        array(
            'channel' => $user_id,
            'text' => ' ',
            'blocks' => $msgJson
        ),
        'bot'
    ));
}
else if ($type = 'user_change') {
    $userData = $eventPayload['user'];

    $userData['email'] = $slack->apiCall(
        'users.profile.get',
        'user=' . $userData['id'],
        'read',
        true
    )['profile']['email'];

    $slack->importSlackUsersToDb(array($userData));
}

//todo cron reminders to submit hours at end of month