<?php

header('Content-Type: application/json');

require_once('../dbconnect.php');
require_once('../functions.php');

// create new slack object with bot token
$slack = new Slack($conn, 'bot');

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
    $json = file_get_contents('views/app-home.json');

    echo $slack->apiCall(
        'views.publish',
        array(
            "user_id" => $user_id,
            "view" => $json
        ),
        'bot'
    );
}