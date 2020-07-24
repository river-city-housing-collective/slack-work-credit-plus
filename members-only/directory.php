<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions.php');

// page is only accessible if authorized via slack
// $slack is available for additional API calls
$slack = signInWithSlack($conn);

$directoryData = $slack->getMemberList();

?>

<script>
    let data = <?= json_encode($directoryData) ?>;
    let teamId = <?= json_encode($slack->config['TEAM_ID']) ?>;
</script>

<!doctype html>
<html>
<head>
    <title>Member Directory</title>

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
            <h3>Member Directory</h3>
        </div>
    </div>
    <br />
    <br />
    <div id="directory" class="tab-pane fade show active" role="tabpanel" aria-labelledby="report-tab">
        <div class="accordion" id="reportAccordion">
            <div class="card" v-for="(value, key, index) in data['members']">
                <div class="card-header" v-bind:id="'header' + index" data-toggle="collapse" v-bind:data-target="'#collapse' + index">
                    <h5 class="mb-0">
                        <span aria-expanded="true" v-bind:aria-controls="'#collapse' + index" class="font-large">
                            {{ key }}
                        </span>
                    </h5>
                </div>
                <div v-bind:id="'collapse' + index" class="collapse show" v-bind:aria-labelledby="'header' + index">
                    <div class="card-body">
                        <template>
                            <b-table-lite
                                    striped
                                    head-variant="primary"
                                    fixed
                                    stacked="md"
                                    responsive
                                    :items="data['members'][key]"
                                    :fields="data['fields']"
                            >
                                <template v-slot:cell(slack_user_id)="data">
                                    <span><a class="btn btn-primary" :href="'slack://user?team=' + teamId + '&id=' + data.item.slack_user_id"><i class="fab fa-slack"></i></a></span>
                                </template>
                                <template v-slot:cell(email)="data">
                                    <span><a :href="'mailto:' + data.item.email">{{ data.item.email }}</a></span>
                                </template>
                                <template v-slot:cell(phone)="data">
                                    <span><a :href="'tel:' + data.item.phone">{{ data.item.phone }}</a></span>
                                </template>
                            </b-table-lite>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>