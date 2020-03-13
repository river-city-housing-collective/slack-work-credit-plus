<?php
// page is only accessible if authorized via slack
// $slack is available for additional API calls
require_once('../auth.php');
include('../includes.php');
?>

<ul>
    <li><a href="work-credit">Work Credit</a></li>
    <? if ($slack->admin) {
        echo '<li><a href="admin.php">Admin Tools</a></li>';
    } ?>
</ul>