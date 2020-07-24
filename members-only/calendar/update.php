<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/functions.php');

// page is only accessible if authorized via slack
// $slack is available for additional API calls
$slack = signInWithSlack($conn, false, true);

$_POST['slack_user_id'] = $slack->userId;

if ($_POST['type'] == 'set') {
    unset($_POST['type']);

    $user_id = $slack->userId;

    $_POST['house_id'] = $slack->sqlSelect("select house_id from sl_users where slack_user_id = '$user_id'");

    echo $slack->sqlInsert('cal_events', $_POST, !isset($_POST['id']));
}
else {
    $start = $_GET['start'];
    $end = $_GET['end'];

    $house_id = $_GET['house'];

    // todo only dinners rn
    $sql = "
        select
            ce.id as 'db_id',
            ce.slack_user_id,
            concat('Dinner - ', su.display_name) as 'title',
            ce.date
        from cal_events ce
        left join sl_users su on ce.slack_user_id = su.slack_user_id
        where su.house_id = '$house_id')
    ";

    echo $slack->sqlSelect($sql, true,true);
}