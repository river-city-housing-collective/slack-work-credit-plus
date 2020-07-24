<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions.php');

// page is only accessible if authorized via slack
// $slack is available for additional API calls
$slack = signInWithSlack($conn, true);

$logData = $slack->sqlSelect("select * from event_logs", true);

echo $logData;

?>

<script>
    let data = <?= json_encode($logData) ?>;
</script>

<!doctype html>
<html>
<head>
    <title>Database Log</title>

    <? include $_SERVER['DOCUMENT_ROOT'] . '/resources/includes.html'; ?>

    <script src="/members-only/work-credit/work-credit.js"></script>

    <link rel="apple-touch-icon" sizes="180x180" href="icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="icons/favicon-16x16.png">
    <link rel="manifest" href="icons/site.webmanifest">

    <meta name="apple-mobile-web-app-title" content="Member Directory">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
</head>
<body>
<button class="btn btn-info portal-back"><i class="fas fa-arrow-left"></i> Back to Portal</button>
<div id="app" v-cloak>
    <div id="header">
        <div id="title">
            <h3>Database Log</h3>
        </div>
    </div>
    <br />
    <br />
    <div id="log" class="tab-pane fade show active" role="tabpanel" aria-labelledby="report-tab">
        <template>
            <b-table-lite
                    striped
                    head-variant="primary"
                    fixed
                    stacked="md"
                    responsive
            >
            </b-table-lite>
        </template>
    </div>
</div>
</body>
</html>