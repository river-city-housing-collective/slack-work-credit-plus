<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions.php');

// page is only accessible if authorized via slack
// $slack is available for additional API calls
$slack = signInWithSlack($conn);
?>

<ul>
    <li><a href="/members-only/work-credit-report">Work Credit</a></li>
    <? if ($slack->admin) {
        echo '<li><a href="/members-only/admin.php">Admin Tools</a></li>';
    } ?>
</ul>