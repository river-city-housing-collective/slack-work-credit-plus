<?php
// page is only accessible if authorized via slack
// $slack is available for additional API calls
require_once('../auth.php');
include('../includes.php');

echo json_encode(importSlackUsersToDb());