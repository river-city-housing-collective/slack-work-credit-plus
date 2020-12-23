<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/functions.php');

// check status of Slack API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://status.slack.com/api/v2.0.0/current");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$slackStatus = curl_exec($ch);
curl_close($ch);
$slackStatus = json_decode($slackStatus, true);

// page is only accessible if authorized via slack
// $slack is available for additional API calls
$slack = signInWithSlack($conn);

?>

<div class="jumbotron">
    <h1 class="display-4">Welcome, <span class="user-display-name"></span>!</h1>
    <p class="lead">This is the RCHC Member Portal.</p>
    <hr class="my-4">
    <ul class="nav nav-pills nav-justified">
        <li class="nav-item" style="padding: 10px">
            <a class="btn-primary nav-link" href="/members-only/work-credit" role="button">Work Credit Report</a>
        </li>
        <li class="nav-item" style="padding: 10px">
            <a class="btn-primary nav-link" href="/members-only/directory.php" role="button">Member Directory</a>
        </li>
<!--        <li class="nav-item" style="padding: 10px">-->
<!--            <a class="btn-primary nav-link" href="/members-only/calendar" role="button">Calendar</a>-->
<!--        </li>-->
        <?php if ($slack->admin): ?>
            <li class="nav-item" style="padding: 10px">
                <a class="btn-warning nav-link" href="/members-only/admin.php" role="button">Admin Tools</a>
            </li>
        <?php endif; ?>
    </ul>
</div>

<?php if ($slackStatus['status'] == 'active'): ?>
    <p>Slack appears to be having issues currently, which may affect your ability to use this site.</p>
<?php endif; ?>