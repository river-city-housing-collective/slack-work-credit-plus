<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/functions.php');

$slack = signInWithSlack($conn, true, true);

if (isset($_GET['action'])) {
    $action = $_GET['action'];
}
if (isset($_GET['method'])) {
    $method = $_GET['method'];
}

switch($action) {
    case 'updateDoorCode':
        if ($method === 'save') {
            $slack->sqlInsert('sl_houses', $_POST);

            // event details for logging
            $details = 'Door code for ' . $_POST['slack_group_id'] . ' was changed';
        }
        else {
            echo json_encode($slack->sqlSelect("select * from sl_houses order by name asc"));
        }

        break;
    case 'syncSlackUsers':
        echo $slack->importSlackUsersToDb();

        break;
    case 'adjustRequirements':
        if ($method === 'save') {
            $mods = array();
            $categories = ['hour', 'other'];

            foreach ($categories as $category) {
                foreach ($_POST[$category . '_type_id'] as $index => $id) {
                    $mods[] = array(
                        'slack_user_id' => $_POST['user_id'],
                        $category . '_type_id' => $id,
                        'qty_modifier' => $_POST[$category . '_qty_modifier'][$index]
                    );
                }
            }

            foreach ($mods as $mod) {
                $slack->sqlInsert('wc_user_req_modifiers', $mod);
            }

            // event details for logging
            $details = "Updated Work Credit requirements for " . $_POST['user_id'];

            echo "Successfully updated user's requirements.";
        }
        else if ($method === 'load_user') {
            $user_id = $_POST['user_id'];

            $modRecords = $slack->sqlSelect("select * from wc_user_req_modifiers where slack_user_id = '$user_id'", false, true);

            $data = array(
                'ok' => false
            );

            if ($modRecords) {
                foreach($modRecords as $modRecord) {
                    if ($modRecord['hour_type_id'] != 0) {
                        $hourTypeId = $modRecord['hour_type_id'];

                        $data['data']['hour'][$hourTypeId] = $modRecord['qty_modifier'];
                    }
                    else if ($modRecord['other_type_id'] != 0) {
                        $otherTypeId = $modRecord['other_type_id'];

                        $data['data']['other'][$otherTypeId] = $modRecord['qty_modifier'];
                    }
                }

                $data['ok'] = true;
            }

//            header('Content-Type', 'application/json');
            echo json_encode($data);
        }
        else {
            $users = $slack->sqlSelect("
                select
                    u.slack_user_id,
                    u.real_name,
                    h.name as 'house'
                from sl_users u
                left join sl_houses as h on h.slack_group_id = u.house_id
                where is_guest = 0 and wc_only = 0 and deleted = 0 and house_id is not null
                order by h.name, u.real_name asc
            ");

            foreach($users as $user) {
                $house = $user['house'];
                unset($user['house']);

                $data['users'][$house][] = $user;
            }

            $data['types']['hour'] = $slack->sqlSelect("select * from wc_lookup_hour_types");
            $data['types']['other'] = $slack->sqlSelect("select * from wc_lookup_other_req_types");

            echo json_encode($data);
        }

        break;
    case 'deleteTimeRecord':
        $id = $_POST['id'];

        $recordData = $slack->sqlSelect("select * from wc_time_credits where id = $id");
        $details = json_encode($recordData);

        $stmt = $slack->conn->prepare("delete from wc_time_credits where id = $id");
        $stmt->execute();

        break;
}

if (isset($details)) {
    // log event in db
    $slack->sqlInsert('event_logs', array(
        'slack_user_id' => $slack->userId,
        'event_type' => $action,
        'details' => $details
    ));
}