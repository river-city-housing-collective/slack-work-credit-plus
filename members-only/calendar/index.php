<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions.php');

// page is only accessible if authorized via slack
// $slack is available for additional API calls
$slack = signInWithSlack($conn);

$houseLookup = $slack->sqlSelect("select slack_group_id, name, css_color from sl_houses");
$formattedHouseLookup = array();

foreach ($houseLookup as $house) {
    $formattedHouseLookup[$house['slack_group_id']] = $house;
}

$events = $slack->sqlSelect("
        select
            ce.id as 'db_id',
            ce.slack_user_id,
            concat('Dinner - ', su.display_name) as 'title',
            ce.date,
            ce.house_id
        from cal_events ce
        left join sl_users su on ce.slack_user_id = su.slack_user_id
    ", true);

echo $events;
?>

<!doctype html>
<html>
<head>
    <title>Calendar</title>

    <? include $_SERVER['DOCUMENT_ROOT'] . '/resources/includes.html'; ?>

    <script>
        let houseLookup = <?= json_encode($formattedHouseLookup); ?>;
        let events = <?= $events ?>;
    </script>

    <link href='/resources/fullcalendar/main.css' rel='stylesheet' />
    <script src='/resources/fullcalendar/main.js'></script>

    <script src="/members-only/calendar/calendar.js"></script>
    <link href='/members-only/calendar/calendar.css' rel='stylesheet' />

    <link rel="apple-touch-icon" sizes="180x180" href="icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="icons/favicon-16x16.png">
    <link rel="manifest" href="icons/site.webmanifest">

    <meta name="apple-mobile-web-app-title" content="Work Credit">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
</head>
<body>
    <button class="btn btn-info portal-back"><i class="fas fa-arrow-left"></i> Back to Portal</button>
    <div style="padding-top: 100px">
        <div id='external-events' style="padding: 50px">
            <div id="selectHouse" style="padding-top: 10px">
                <strong>Show events for:</strong>
                <br/>
            </div>
        </div>

        <div id='calendar-container'>
            <div id='calendar'></div>
        </div>
    </div>
</body>
</html>