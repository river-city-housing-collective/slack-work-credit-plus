<?php

header('Content-Type: application/json');

require_once('../functions.php');

// create new slack object with bot token
$slack = new Slack($conn);

parse_str(file_get_contents("php://input"), $payload);

$user_id = $payload['user_id'];
$channel_id = $payload['channel_id'];
$command = $payload['command'];
$params = explode(' ', $payload['text']);
$is_admin = $slack->sqlSelect("select is_admin from sl_users where slack_user_id = '$user_id'");

function hourReport($slack, $user_id, $lookupUser = null) {
    $reportData = $slack->getWorkCreditData($lookupUser);
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
    $headerStr = "Here's your work credit progress for *$date*!";

    // admin only
    if (isset($lookupUser) && $lookupUser !== $user_id) {
        $msgJson = json_decode(file_get_contents('messages/work-credit-admin.json'), TRUE);

        $headerStr = "Here's the current work credit progress for *" . $userData['real_name'] . "*!";

        $msgJson['private_metadata'] = $lookupUser;
    }
    else {
        $msgJson = json_decode(file_get_contents('messages/work-credit-report.json'), TRUE);

        $msgJson['blocks'][sizeof($msgJson['blocks']) - 1]['elements'][1]['url'] =
            (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/members-only/work-credit#submissions';
    }

    $msgJson['blocks'][0]['text']['text'] = $headerStr;

    // hours
    $msgJson['blocks'][2]['text']['text'] = $reportStr;

    return json_encode($msgJson);
}

if ($command == '/code') {
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
else if ($command == '/hours') {
    $lookup_user_id = $user_id;

    if ($params[0] !== '') {
        // only admins can lookup other ppl's hours
        if ($is_admin) {

            // no real accounts on dev - just pass it an id for testing
            if ($slack->config['DEBUG_MODE'] == 1) {
                $lookup_user_id = $slack->sqlSelect("select slack_user_id from sl_users where display_name = 'izzy'");
            }
            else {
                $lookup_user_id = ltrim(explode('|', $params[0])[0], '<@');
            }

            $slack->conn->query("
                insert into sl_view_states (slack_user_id, sl_key, sl_value)
                    values ('$user_id', 'work-credit-admin', '$lookup_user_id')
            ");
        }
        else {
            exit();
        }
    }

    echo hourReport($slack, $user_id, $lookup_user_id);
}
//else if ($command == '/test') {
//    $msgJson = json_decode(file_get_contents('messages/new-user-greeting.json'), true);
//
//    echo json_encode($slack->apiCall(
//        'chat.postMessage',
//        array(
//            'channel' => $user_id,
//            'text' => ' ',
//            'blocks' => $msgJson
//        ),
//        'bot'
//    ));
//}