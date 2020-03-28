<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions.php');

// page is only accessible if authorized via slack
// $slack is available for additional API calls
$slack = signInWithSlack($conn);

// build query to get hour counts
$hourTypes = $slack->sqlSelect('select * from wc_lookup_hour_types');
$hourFields = array(
    'hours_credited' => 'wc_time_credits',
    'hours_debited' => 'wc_time_debits'
);

foreach($hourTypes as $type) {
    $label = $type['name'];

    $reportData['hourTypesLookup'][$type['id']] = $label;;

    $combinedFields[] = array(
        'key' => strtolower($label) . '_hours',
        'label' => $label
    );

    foreach($hourFields as $field => $table) {
        $id = $type['id'];
        $newField = $field . '_' . $id;
    
        // get lifetime credit/debit totals
        $hourSubQueries[] = "
            (select
                sum(case when hour_type_id = $id
                    then $field
                    else 0 end)
                from $table
                where slack_user_id = u.slack_user_id
            ) as '$newField'
        ";

        if ($table == 'wc_time_debits') {
            $newField = 'next_debit_qty' . '_' . $id;

            // get most recent hours due
            $hourSubQueries[] = "
                (select
                    $field
                    from $table
                    where slack_user_id = u.slack_user_id
                    and hour_type_id = $id
                    order by date_effective desc limit 1
                ) as '$newField'
            ";
        }
    }
}

$hourSubQueries = implode(', ', $hourSubQueries);

$sql = "
    select
        u.slack_user_id,
        u.real_name,
        h.name as 'house',
        case when r.room is null
            then 'Boarder'
            else r.room end
        as 'room',
        $hourSubQueries
    from sl_users u
    left join sl_houses as h on h.slack_group_id = u.house_id
    left join sl_rooms as r on r.id = u.room_id and r.house_id = u.house_id
    where u.deleted <> 1 and u.is_guest <> 1 and u.house_id is not null
    group by u.slack_user_id
    order by h.name, isnull(r.id), r.id, u.real_name
";

// get user info (w/ hours)
$memberData = $slack->sqlSelect($sql);

// get required hours and modifiers (probably don't need this?)
// $reqModifiers = $slack->sqlSelect('select * from wc_user_req_modifiers');

// if ($reqModifiers) {
//     foreach($reqModifiers as $row) {
//         $user_id = $row['slack_user_id'];
//         $hourTypeId = $row['hour_type_id'];
    
//         if ($hourTypeId != 0) {
//             $modLookup[$user_id][$hourTypeId] = $row['qty_modifier'];
//         }
//     }
// }

$hourFields = array_keys($hourFields);

foreach($memberData as $data) {
    $user_id = $data['slack_user_id'];
    $house = $data['house'];
    unset($data['house']);

    foreach($hourTypes as $type) {
        $hourTypeId = $type['id'];
        $hourTypeLabel = $type['name'];

        $creditField = $hourFields[0];
        $debitField = $hourFields[1];
        $nextDebitField = 'next_debit_qty';

        $oldCreditField = $creditField . '_' . $hourTypeId;
        $oldDebitField = $debitField . '_' . $hourTypeId;
        $oldNextDebitField = $nextDebitField . '_' . $hourTypeId;

        $styleField = 'style_' . $hourTypeId;

        $cellStyle = '';

        $combinedField = strtolower($hourTypeLabel) . '_hours';

        if (!$data[$oldNextDebitField]) {
            $data['_cellVariants'][$combinedField] = '';
        }

        // $data[$reqHoursField] = $type['default_qty'];

        // if (isset($modLookup[$user_id][$hourTypeId])) {
        //     $data[$reqHoursField] = $data[$reqHoursField] + $modLookup[$user_id][$hourTypeId];
        // }

        // subtract total debits from earned hours
        $hoursDiff = $data[$oldCreditField] - $data[$oldDebitField];

        if ($hoursDiff != 0) {
            $data[$creditField][$hourTypeId] = $data[$oldNextDebitField] + $hoursDiff;
        }
        else {
            // unset($data[$creditHoursField]);
        }

        // strip empty decimal places
        $data[$nextDebitField][$hourTypeId] = floatval($data[$oldNextDebitField]);

        if (isset($data[$creditField][$hourTypeId]) && $hoursDiff >= 0) {
            $cellStyle = 'positive';
        }
        else if ($hoursDiff < -$data[$oldNextDebitField]) {
            $cellStyle = 'negative';
        }

        $data['_cellVariants'][$combinedField] = $cellStyle;

        unset($data[$oldNextDebitField]);
        unset($data[$oldDebitField]);
        unset($data[$oldCreditField]);
    }

    unset($data['slack_user_id']);

    if ($data['real_name'] == $slack->userInfo['real_name']) {
        $reportData['userRecord'] = $data;
    }

    $groupedMemberData[$house][] = $data;
}

$reportData['members']['items'] = $groupedMemberData;

$fields = array(
    array(
        'key' => 'real_name',
        'label' => 'Name'
    ),
    array(
        'key' => 'room',
        'label' => 'Room'
    )
);

$reportData['members']['fields'] = array_merge($fields, $combinedFields);
$reportData['members']['mobileFields'] = array_merge($fields, array(array('key' => 'hours')));

$reportData['currentPage'] = 1;
$reportData['perPage'] = 3;

// get time records
$submissionData = $slack->sqlSelect("
    select
        tc.timestamp as 'Timestamp',
        u.real_name as 'Member',
        tc.hours_credited as 'Hours Completed',
        lht.name as 'Type of Hours',
        tc.contribution_date as 'Date of Contribution',
        tc.description as 'Description of Work Completed'
    from wc_time_credits as tc
    left join sl_users as u on u.slack_user_id = tc.slack_user_id
    left join wc_lookup_hour_types as lht on lht.id = tc.hour_type_id
    order by tc.id desc
", null, true);

$reportData['submissions'] = array(
    'fields' => array_keys($submissionData[0]), // todo throws error when no submissions
    'items' => $submissionData,
    'currentPage' => 1,
    'perPage' => 50
);

?>