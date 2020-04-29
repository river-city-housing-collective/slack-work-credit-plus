<?php

header('Content-Type: application/json');

require_once('../functions.php');

// create new slack object with bot token
$slack = new Slack($conn);

parse_str(file_get_contents("php://input"), $payload);

$user_id = $payload['user_id'];
$channel_id = $payload['channel_id'];

if ($payload['command'] == '/code') {
    if ($payload['text'] != '') {
        $house = $payload['text'];

        $sql = "
            select name, door_code from sl_houses
            where name = '$house'
        ";
    }
    else {
        $sql = "
            select name, door_code from sl_houses
            left join sl_users su on sl_houses.slack_group_id = su.house_id
            where slack_user_id = '$user_id'
        ";
    }

    $houseInfo = $slack->sqlSelect($sql);

    echo 'I got you! The door code for ' . $houseInfo['name'] . ' is ' . $houseInfo['door_code'] . '.';
}
else if ($payload['command'] == '/hours') {
    $msgJson = json_decode(file_get_contents('messages/work-credit-report.json'), TRUE);

    $reportData = $slack->getWorkCreditData($payload['user_id']);
    $userData = $reportData['userData'];
    $hourTypes = $reportData['hourTypes'];

    $reportStr = array();

    foreach ($reportData['userData']['hours_credited'] as $type => $hours_credited) {
        $emoji = $hourTypes[$type]['slack_emoji'];
        $label = $hourTypes[$type]['name'];
        $next_debit_qty = $userData['next_debit_qty'][$type];

        if ($hours_credited >= $next_debit_qty) {
            $emoji = ':white_check_mark:';
        }

        $reportStr[] = "$emoji *$label Hours:* $hours_credited / $next_debit_qty";
    }

    $reportStr = implode("\n", $reportStr);

    // header
    $date = date('F Y');
    $msgJson['blocks'][0]['text']['text'] = "Here's your work credit progress for *$date*!";

    // hours
    $msgJson['blocks'][2]['text']['text'] = $reportStr;

    echo json_encode($msgJson);
}