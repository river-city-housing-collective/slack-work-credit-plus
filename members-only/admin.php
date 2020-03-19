<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions.php');

// page is only accessible if authorized via slack
// $slack is available for additional API calls
$slack = signInWithSlack($conn, true);

// todo make button
// $slack->importSlackUsersToDb();

// $slack->scheduleHoursDebit('UQLDMKLU8', 1);

?>

Admins Only