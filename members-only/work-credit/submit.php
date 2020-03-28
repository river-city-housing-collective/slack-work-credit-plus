<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions.php');

// page is only accessible if authorized via slack
// $slack is available for additional API calls
$slack = signInWithSlack($conn, false, true);

$_POST['contribution_date'] = date('Y-m-d', strtotime(str_replace('-', '/', $_POST['contribution_date'])));
$_POST['slack_user_id'] = $slack->userId;

echo $slack->sqlInsert('wc_time_credits', $_POST);