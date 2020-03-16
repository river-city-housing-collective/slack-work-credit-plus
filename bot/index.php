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
$trigger_id = $eventPayload['trigger_id'];

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
    if ($callback_id == 'email') {
        $viewJson = json_decode(file_get_contents('views/send-email-modal.json'), TRUE);
        $viewJson = $slack->setInputValues($viewJson, array('body' => $eventPayload['message']['text']));

        $slack->openView($trigger_id, $viewJson);
    }
}
else if ($type == 'block_actions') {
    $view_id = $eventPayload['view']['id'];

    if (isset($eventPayload['actions'])) {
        $actionData = $eventPayload['actions'][0];

        $actionKey = $actionData['block_id'];
        $actionValue = isset($actionData['selected_option']) ? $actionData['selected_option']['value'] : $actionData['value'];
    }

    $profileData = $slack->sqlSelect("select * from sl_users where slack_user_id = '$user_id'");

    $profileData['pronouns'] = $slack->apiCall('users.profile.get', array('user' => $user_id))
        ['profile']['fields'][$slack->config['PRONOUNS_FIELD_ID']]['value'];

    // opening modals on app home
    if ($callback_id == 'app-home') {
        $view = $eventPayload['actions'][0]['value'];

        if ($view == 'edit-profile-modal') {
            $viewJson = $slack->buildProfileModal($profileData);
            $slack->openView($trigger_id, $viewJson);
        }
        else {
            $viewJson = json_decode(file_get_contents('views/' . $view . '.json'), TRUE);
            $slack->openView($trigger_id, $viewJson);
        }
    }
    // updating edit profile view
    else if ($callback_id == 'edit-profile-modal') {
        // get previously submitted field updates
        $storedValues = $slack->getStoredViewData($user_id, $view_id);

        $lastHouseId = $profileData['house_id'];

        if ($storedValues) {
            foreach ($storedValues as $field => $value) {
                $profileData[$field] = $value;
            }
        }

        // get current field update
        $profileData[$actionKey] = $actionValue;

        // wipe out room number if house changed
        // if ($lastHouseId != $profileData['house_id']) {
        //     unset($profileData['room_id']);
        // }

        $viewJson = $slack->buildProfileModal($profileData);
        $slack->openView($trigger_id, $viewJson, $view_id);
    }

    $slack->conn->query("
        insert into sl_view_states (slack_user_id, slack_view_id, `key`, `value`)
            values ('$user_id', '$view_id', '$actionKey', '$actionValue')
    ");
}
// on modal submit
else if ($type == 'view_submission') {
    $view_id = $eventPayload['view']['id'];

    // get all input fields
    $inputValues = $slack->getInputValues($eventPayload['view']['state']['values']);
    $storedValues = $slack->getStoredViewData($user_id, $view_id);

    if ($storedValues) {
        foreach ($storedValues as $field => $value) {
            $inputValues[$field] = $value;
        }
    }

    if ($callback_id == 'send-email-modal') {
        // todo add true flag to enable for production
        $slack->sendEmail($user_id, $inputValues['subject'], $inputValues['body'], $inputValues['house_id']);
    }
    else if ($callback_id == 'submit-time-modal') {
        // if hours are not valid, throw error
        if (!is_numeric($inputValues['hours_completed']) || !(fmod($inputValues['hours_completed'], 0.25) == 0)) {
            echo json_encode(array(
                'response_action' => 'errors',
                'errors' => array(
                    'hours_completed' => 'Please enter your hours in increments of 0.25'
                )
            ));
        }

        $inputValues['slack_user_id'] = $user_id;

        if (!isset($inputValues['hour_type_id'])) {
            $inputValues['hour_type_id'] = '1';
        }

        $slack->sqlInsert('wc_time_records', $inputValues);
    }
    else if ($callback_id == 'edit-profile-modal') {

        // attempt to update slack profile and db
        $slack->updateUserProfile($user_id, $inputValues);
    
        // get usergroup info from db
        // $sql = "
        //     select
        //         u.house_id,
        //         u.committee_id,
        //         h.slack_group_id as 'slack_house_id',
        //         c.slack_group_id as 'slack_committee_id',
        //         c.name as 'committee_name'
        //     from sl_users as u
        //     left join sl_houses as h on h.`id` = u.`house_id`
        //     left join sl_committees as c on c.`id` = u.`committee_id`
        //     where u.slack_user_id = '$user_id'
        // ";
        // $result = $slack->conn->query($sql);
        // $userDbInfo = $result->fetch_assoc();

        // todo remove from old usergroups here?
    
        // add to house and committee groups
        // $slack->addToUsergroup($user_id, $userDbInfo['slack_house_id']);
        // if ($userDbInfo['committee_id']) {
        //     $slack->addToUsergroup($user_id, $userDbInfo['slack_committee_id']);
        // }
    }

    header("HTTP/1.1 204 NO CONTENT");
}