<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/functions.php');

$slack = signInWithSlack($conn, true, true);

if (isset($_POST['action'])) {
    $action = $_POST['action'];
}

switch($action) {
    case 'syncSlackUsers':
        echo $slack->importSlackUsersToDb();

        break;
    case 'getUserRequirements':
        $users = $slack->sqlSelect("
            select
                u.slack_user_id,
                u.real_name,
                h.name as 'house'
            from sl_users u
            left join sl_houses as h on h.slack_group_id = u.house_id
            where is_guest = 0 and wc_only = 0 and deleted = 0 and house_id is not null
            order by h.name, u.real_name asc
        "); // todo handling for adhoc users

        foreach($users as $user) {
            $house = $user['house'];
            unset($user['house']);

            $data['users'][$house][] = $user;
        }

        $data['types']['hour'] = $slack->sqlSelect("select * from wc_lookup_hour_types");
        $data['types']['other'] = $slack->sqlSelect("select * from wc_lookup_other_req_types");

        echo json_encode($data);

        break;
    case 'getRequirementMods':
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

        header('Content-Type', 'application/json');
        echo json_encode($data);

        break;
    case 'saveRequirmentMods':
        // todo

        break;
}