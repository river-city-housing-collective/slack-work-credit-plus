<?php
require_once('dbconnect.php');
require_once('functions.php');

if (isset($_GET['logout'])) {
    echo 'logged out?';
    setcookie('token', false, time() - 3600, '/');

    var_dump($_COOKIE);
    exit();
}

// create new slack object (if authenticated)
$slack = signInWithSlack($conn);

if (!$slack->authed) {
    // show "sign in with slack" button
    echo '<a href="https://slack.com/oauth/authorize?scope=identity.basic,identity.email,identity.team,identity.avatar&client_id=787965675794.822220955957"><img alt=""Sign in with Slack"" height="40" width="172" src="https://platform.slack-edge.com/img/sign_in_with_slack.png" srcset="https://platform.slack-edge.com/img/sign_in_with_slack.png 1x, https://platform.slack-edge.com/img/sign_in_with_slack@2x.png 2x" /></a>';

    die();
}