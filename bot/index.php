<?php

// error reporting (local only)
if ($_SERVER["REMOTE_ADDR"] == '127.0.0.1' || $_SERVER["REMOTE_ADDR"] == '::1') {
    error_reporting(E_ALL);
    ini_set('display_errors', 'on');
}

header('Content-Type: application/json');

require_once('../functions.php');

// create new slack object with bot token
$slack = new Slack($conn);

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
    $view_id = $eventPayload['view']['id'];
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
        $viewJson['blocks'][0]['element']['options'] = array_merge($viewJson['blocks'][0]['element']['options'], $slack->getOptions('sl_houses'));
        $viewJson = $slack->setInputValues($viewJson, array('body' => $eventPayload['message']['text']));

        echo json_encode($slack->apiCall(
            'views.open',
            array(
                'view' => $viewJson,
                'trigger_id' => $trigger_id
            ),
            'bot'
        ));
    }
}
else if ($type == 'block_actions') {
    if (isset($eventPayload['actions'])) {
        $actionData = $eventPayload['actions'][0];

        $actionKey = $actionData['block_id'];
        $actionValue = isset($actionData['selected_option']) ? $actionData['selected_option']['value'] : $actionData['value'];
    }

    $profileData = $slack->sqlSelect("select * from sl_users where slack_user_id = '$user_id'");

    $profileData['pronouns'] = $slack->apiCall('users.profile.get', 'user=' . $user_id, 'read', true)
        ['profile']['fields'][$slack->config['PRONOUNS_FIELD_ID']]['value'];

    // opening modals on app home
    if ($callback_id == 'app-home') {
        $view = $eventPayload['actions'][0]['value'];

        // todo restricting WIP stuff to admins for now (also hide stuff for guests)
        if (!$profileData['is_admin'] && in_array($view, array('send-email-modal'))) {
            $view = 'under-construction';
        }

        if ($view == 'edit-profile-modal') {
            $viewJson = $slack->buildProfileModal($profileData);
        }
        else if ($view == 'submit-time-modal') {
            $workCreditCheck = $slack->sqlSelect("
                select * from wc_time_debits
                where slack_user_id = '$user_id'
                order by date_effective desc limit 1
            ");

            if (!$workCreditCheck) {
                $view = 'work-credit-warning';
            }

            $viewJson = json_decode(file_get_contents('views/' . $view . '.json'), TRUE);

            // todo hide/disable other hour types for boarders
            if (!$profileData['is_boarder']) {
                $otherReqsQuestion = json_decode(file_get_contents('views/time-resident-addon.json'), TRUE);
                array_push($viewJson['blocks'], $otherReqsQuestion);
            }
        }
        else {
            $viewJson = json_decode(file_get_contents('views/' . $view . '.json'), TRUE);

            if ($view == 'send-email-modal') {
                $viewJson['blocks'][0]['element']['options'] = array_merge($viewJson['blocks'][0]['element']['options'], $slack->getOptions('sl_houses'));
            }
        }

        // open view
        if (isset($viewJson)) {
            echo json_encode($slack->apiCall(
                'views.open',
                array(
                    'view' => $viewJson,
                    'trigger_id' => $trigger_id
                ),
                'bot'
            ));
        }
    }
    // updating edit profile view
    else if ($callback_id == 'edit-profile-modal') {

        $lastHouseId = $profileData['house_id'];

        // get previously submitted field updates
        $storedValues = $slack->getStoredViewData($user_id, $view_id);

        if ($storedValues) {
            foreach ($storedValues as $field => $value) {
                $profileData[$field] = $value;
            }
        }

        // get current field update
        $profileData[$actionKey] = $actionValue;

        if ($actionKey == 'room_number') {
            foreach ($profileData as $key => $value) {
                if ($key != 'slack_user_id' && $key != 'room_number') {
                    $data = array(
                        'slack_view_id' => $view_id,
                        'slack_user_id' => $user_id,
                        'sl_key' => $key,
                        'sl_value' => $value
                    );

                    $slack->sqlInsert('sl_view_states', $data);
                }
            }

            $viewJson = json_decode(file_get_contents('views/profile-room-popup.json'), TRUE);
            $viewJson['blocks'][0]['element']['options'] = $slack->getOptions('sl_rooms', $profileData['house_id']);
            $viewJson = $slack->setInputValues($viewJson, $profileData);

            echo json_encode($slack->apiCall(
                'views.push',
                array(
                    'view' => $viewJson,
                    'trigger_id' => $trigger_id
                ),
                'bot'
            ));
        }
        else if ($actionKey == 'is_boarder') {
            if ($profileData['is_boarder'] = '1') {
                $profileData['room_id'] = null;
            }

            $profileData['is_boarder'] = $actionValue;

            $viewJson = $slack->buildProfileModal($profileData);

            echo json_encode($slack->apiCall(
                'views.update',
                array(
                    'view' => $viewJson,
                    'view_id' => $view_id
                ),
                'bot'
            ));
        }
        else if ($actionKey == 'house_id') {
            // wipe out room number if house changed
            if ($lastHouseId != $profileData['house_id']) {
                $slack->conn->query("
                    insert into sl_view_states (slack_user_id, slack_view_id, sl_key, sl_value)
                        values ('$user_id', '$view_id', 'room_id', NULL)
                ");

                unset($profileData['room_id']);

                $viewJson = $slack->buildProfileModal($profileData);

                echo json_encode($slack->apiCall(
                    'views.update',
                    array(
                        'view' => $viewJson,
                        'view_id' => $view_id
                    ),
                    'bot'
                ));
            }
        }
    }

    $slack->conn->query("
        insert into sl_view_states (slack_user_id, slack_view_id, sl_key, sl_value)
            values ('$user_id', '$view_id', '$actionKey', '$actionValue')
    ");
}
// on modal submit
else if ($type == 'view_submission') {
    // get all input fields
    $inputValues = $slack->getInputValues($eventPayload['view']['state']['values']);

    if ($callback_id == 'send-email-modal') {
        // todo add true flag to enable for production
        $slack->sendEmail($user_id, $inputValues['subject'], $inputValues['body'], $inputValues['house_id']);
    }
    else if ($callback_id == 'submit-time-modal') {
        $decimalCheck = $inputValues['hours_credited'] != 0.25 ? fmod($inputValues['hours_credited'], 0.25) != 0 : false;

        // if hours are not valid, throw error
        if (!is_numeric($inputValues['hours_credited']) || $decimalCheck) {
            echo json_encode(array(
                'response_action' => 'errors',
                'errors' => array(
                    'hours_credited' => 'Please enter your hours in increments of 0.25'
                )
            ));
        }
        else if (intval($inputValues['hours_credited']) >= 24) {
            echo json_encode(array(
                'response_action' => 'errors',
                'errors' => array(
                    'hours_credited' => 'There are only 24 hours in a day!'
                )
            ));
        }
        else if ($inputValues['hours_credited'] <= 0) {
            echo json_encode(array(
                'response_action' => 'errors',
                'errors' => array(
                    'hours_credited' => 'Please enter a positive number'
                )
            ));
        }
        // no errors, submit
        else {
            $inputValues['slack_user_id'] = $user_id;
            $inputValues['submit_source'] = 1; // submitting from slack

            $inputValues = $slack->getStoredViewData($user_id, $view_id, $inputValues);

            if (!isset($inputValues['hour_type_id'])) {
                $inputValues['hour_type_id'] = '1';
            }
            if (!isset($inputValues['other_req_id'])) {
                $inputValues['other_req_id'] = '0';
            }

            $slack->sqlInsert('wc_time_credits', $inputValues);
        }
    }
    // save room_id to views table and update profile modal
    else if ($callback_id == 'profile-room-popup') {
        $view_id = $eventPayload['view']['root_view_id'];
        $room_id = $inputValues['room_id'];

        $slack->conn->query("
            insert into sl_view_states (slack_user_id, slack_view_id, sl_key, sl_value)
                values ('$user_id', '$view_id', 'room_id', '$room_id')
        ");

        $inputValues = $slack->getStoredViewData($user_id, $view_id, $inputValues);
        //todo ?
        $inputValues['room_id'] = $room_id;

        $viewJson = $slack->buildProfileModal($inputValues);

        $slack->apiCall(
            'views.update',
            array(
                'view' => $viewJson,
                'view_id' => $view_id
            ),
            'bot'
        );
    }
    // submit updated profile data
    else if ($callback_id == 'edit-profile-modal') {
        $inputValues = $slack->getStoredViewData($user_id, $view_id, $inputValues);

        // attempt to update slack profile and db
        $slack->updateUserProfile($user_id, $inputValues);

        // update usergroup associations
        // todo testing channel associations - prob works

        if (isset($inputValues['house_id'])) {
            $slack->removeFromUsergroups($user_id, $inputValues['house_id'], 'sl_houses');
            $slack->addToUsergroup($user_id, $inputValues['house_id']);
        }

        if (isset($inputValues['committee_id'])) {
            $slack->removeFromUsergroups($user_id, $inputValues['committee_id'], 'sl_committees');
            $slack->addToUsergroup($user_id, $inputValues['committee_id']);
        }
    }

    header("HTTP/1.1 204 NO CONTENT");
}