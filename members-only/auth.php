<?php
require_once('../secrets.php');
require_once('../functions.php');

$slack = signInWithSlack($SLACK_CLIENT_ID, $SLACK_CLIENT_SECRET);

if ($slack->authed) {
    echo 'hi ' . $slack->userInfo['name'];
}
else {
    // show "sign in with slack" button
    echo '<a href="https://slack.com/oauth/authorize?scope=identity.basic,identity.email,identity.team,identity.avatar&client_id=787965675794.822220955957"><img alt=""Sign in with Slack"" height="40" width="172" src="https://platform.slack-edge.com/img/sign_in_with_slack.png" srcset="https://platform.slack-edge.com/img/sign_in_with_slack.png 1x, https://platform.slack-edge.com/img/sign_in_with_slack@2x.png 2x" /></a>';

    exit();
}