<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions.php');

// page is only accessible if authorized via slack
// $slack is available for additional API calls
$slack = signInWithSlack($conn);

$reportData = $slack->getWorkCreditData();

?>

<script>
    let data = <?= json_encode($reportData) ?>;

    $.each(data['submissions']['fields'], function () {
        this['sortable'] = true;

        let classes = [];

        // hide filter and sort for delete row
        if (this['key'] === 'delete') {
            classes.push('filter-false');
            classes.push('sorter-false');
        }

        // enable select filter for categorical columns
        if (['real_name', 'name', 'other_req'].includes(this['key'])) {
            classes.push('filter-select');
        }

        // make name and description wider
        if (['real_name', 'name', 'description', 'other_req'].includes(this['key'])) {
            classes.push('credit-' + this['key'] + '-column');
        }

        this['class'] = classes.join(' ');
    });

    data['userInfo'] = userInfo;
</script>

<!doctype html>
<html>
<head>
    <title>Work Credit Report</title>

    <? include $_SERVER['DOCUMENT_ROOT'] . '/resources/includes.html'; ?>

    <script src="/members-only/work-credit/work-credit.js"></script>


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
    <div id="app" v-cloak>
        <div id="successAlert" style="display: none">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Your hours have been submitted. Thank you for your hard work!
            </div>
        </div>
        <div id="header">
            <div id="title">
                <h3>Work Credit Report</h3>
            </div>
            <nav class="nav nav-pills justify-content-center" id="pills-tab" role="tablist">
                <a class="nav-item nav-link active" data-toggle="pill" href="#report" id="report-tab" role="tab" aria-controls="report" aria-selected="true">Current Hours</a>
                <a class="nav-item nav-link" data-toggle="pill" href="#submissions" id="submissions-tab" role="tab" aria-controls="submissions" aria-selected="false">Submission History</a>
                <a class="nav-item nav-link btn-success" href="" id="submit-tab" role="tab" data-target="#submitTimeModal" data-toggle="modal" data-backdrop="static" data-keyboard="false">Submit Time</a>
            </nav>
        </div> 
        <div class="tab-content" id="pills-tabContent">
            <div id="report" class="tab-pane fade show active" role="tabpanel" aria-labelledby="report-tab">
                <div class="accordion" id="reportAccordion">
                    <div class="card" v-for="(value, key, index) in data['members']['items']">
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
                                        :items="members['items'][key]"
                                        :fields="members['fields']"
                                        :tbody-tr-class="rowClass"
                                    >
                                        <template v-slot:cell(real_name)="data">
                                            <span :class="styleUser(data.item.real_name)">{{ data.item.real_name }}</span>
                                        </template>
                                        <template v-slot:cell(room)="data">
                                            <span :class="styleUser(data.item.real_name)">{{ data.item.room }}</span>
                                        </template>
                                        <template v-slot:cell(house_hours)="data">
                                            <div v-if="data.item.next_debit_qty[1]">
                                                <span :class="styleUser(data.item.real_name)">{{ data.item.hours_credited[1] }} / {{ data.item.next_debit_qty[1] }}</span>
                                            </div>
                                            <div v-else>
                                                <span>N/A</span>
                                            </div>
                                        </template>
                                        <template v-slot:cell(collective_hours)="data">
                                            <div v-if="data.item.next_debit_qty[2]">
                                                <span :class="styleUser(data.item.real_name)">{{ data.item.hours_credited[2] }} / {{ data.item.next_debit_qty[2] }}</span>
                                            </div>
                                            <div v-else>
                                                <span>N/A</span>
                                            </div>
                                        </template>
                                        <template v-slot:cell(maintenance_hours)="data">
                                            <div v-if="data.item.next_debit_qty[3]">
                                                <span :class="styleUser(data.item.real_name)">{{ data.item.hours_credited[3] }} / {{ data.item.next_debit_qty[3] }}</span>
                                            </div>
                                            <div v-else>
                                                <span>N/A</span>
                                            </div>
                                        </template>
                                    </b-table-lite>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="dashboard" class="tab-pane fade" role="tabpanel" aria-labelledby="dashboard-tab">
                <div style="text-align: center">
                    <table id="progressDiv">
                        <tr>
                            <td>
                                <label>House<br />Hours</label>
                                <div data-hours-id="1" class="hoursProgressDisplay" style="margin:auto;"></div>
                            </td>
                            <td>
                                <label>House<br />Meetings</label>
                                <div data-othertype="House Meetings" class="otherProgressDisplay" style="margin:auto;"></div>
                            </td>
                        </tr>
                        <tr style="height:25px"></tr>
                        <tr>
                            <td>
                                <label>Collective<br />Hours</label>
                                <div data-hours-id="2" class="hoursProgressDisplay" style="margin:auto;"></div>
                            </td>
                            <td>
                                <label>BOD<br />Meetings</label>
                                <div data-othertype="BOD Meetings" class="otherProgressDisplay" style="margin:auto;"></div>
                            </td>
                        </tr>
                        <tr style="height:25px"></tr>
                        <tr>
                            <td>
                                <label>Maintenance<br />Hours</label>
                                <div data-hours-id="3" class="hoursProgressDisplay" style="margin:auto;"></div>
                            </td>
                            <td>
                                <label>Dinners<br />Prepared</label>
                                <div data-othertype="Dinners" class="otherProgressDisplay label-center" style="margin:auto;"></div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            <div id="submissions" style="padding-bottom:10%" class="tab-pane fade" role="tabpanel" aria-labelledby="submissions-tab">
                <!-- pager -->
                <div id="pager" class="pager">
                    <form>
                        <img src="/resources/tablesorter/first.png" class="first"/>
                        <img src="/resources/tablesorter/prev.png" class="prev"/>
                        <!-- the "pagedisplay" can be any element, including an input -->
                        <span class="pagedisplay" data-pager-output-filtered="{startRow:input} &ndash; {endRow} / {filteredRows} of {totalRows} total rows"></span>
                        <img src="/resources/tablesorter/next.png" class="next"/>
                        <img src="/resources/tablesorter/last.png" class="last"/>
                        <select class="pagesize">
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="30">30</option>
                            <option value="40">40</option>
                            <option value="all">All Rows</option>
                        </select>
                    </form>
                </div>
<!--                <div class="form-check" style="padding-bottom: 10px">-->
<!--                    <input class="form-check-input" type="checkbox" id="showAll">-->
<!--                    <label class="form-check-label" for="showAll">-->
<!--                        Show all records (including past member submissions)-->
<!--                    </label>-->
<!--                </div>-->
                <template v-if="submissions">
                    <div class="overflow-auto">
                        <b-table-lite
                            striped
                            stacked="md"
                            id="submissionsTable"
                            :items="submissions['items']"
                            :fields="submissions['fields']"
                        >
                            <template v-slot:cell(delete)="data">
                               <button class="btn btn-danger delete-record" :data-id="data.item.id"><b-icon-trash-fill></b-icon-trash-fill></button>
                            </template>
                        </b-table-lite>
                    </div>
                    </table>
                </template>
                <h4 v-else style="text-align: center">No submissions found.</h4>
            </div>
        </div>
        <div class="modal fade" id="submitTimeModal" tabindex="-1" role="dialog" aria-labelledby="submitTimeModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="submitTime">Log Work Credit Hours</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <!--todo abstract this here and in slack-->
                    <form class="modal-form">
                        <div class="modal-body">
                            <label for="hour_type_id" class="submission-label">Type of Hours:</label>
                            <div class="form-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="hour_type_id" id="hour_type_id_1" value="1" checked>
                                    <label class="form-check-label" for="hour_type_id_1">
                                        &#127968; House
                                    </label>
                                    <p><small class="text-muted">e.g. cooking, cleaning</small></p>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="hour_type_id" id="hour_type_id_2" value="2">
                                    <label class="form-check-label" for="hour_type_id_2">
                                        &#10024; Collective
                                    </label>
                                    <p><small class="text-muted">e.g. committee meetings, community outreach</small></p>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="hour_type_id" id="hour_type_id_3" value="3">
                                    <label class="form-check-label" for="hour_type_id_3">
                                        &#128736; Maintenance
                                    </label>
                                    <p><small class="text-muted">e.g. repairs, snow removal</small></p>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="hours_credited" class="submission-label">Hours Completed:</label>
                                <input type="number" step="0.25" min="0.25" max="24" class="form-control" id="hours_credited" name="hours_credited" required>
                            </div>
                            <div class="form-group">
                                <label for="contribution_date" class="submission-label">Date of Contribution:</label>
                                <input type="date" class="form-control" id="contribution_date" name="contribution_date" required>
                            </div>
                            <div class="form-group" v-if="userInfo['is_boarder'] != 1">
                                <label for="other_req_id" class="submission-label">Does this fulfill a specific monthly requirement?</label>
                                <select class="form-control" id="other_req_id" name="other_req_id">
                                    <option value="" selected>N/A</option>
                                    <option value="1">Cooked Dinner</option>
                                    <option value="2">Attended House Meeting</option>
                                    <option value="3">Attended BOD Meeting</option>
                                </select>
                            </div>
                            <div id="descriptionDiv" class="form-group">
                                <label for="description" class="submission-label">Description of Work Completed:</label>
                                <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                            </div>
                            <input name="submit_source" hidden value="2">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal" id="cancelSubmit">Cancel</button>
                            <button class="btn btn-primary" type="button" id="submitHours">
                                <span id="submitText">Submit</span>
                                <div id="submitted" style="display: none">
                                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                    <span class="sr-only">Submitting...</span>
                                    <span> Submitting...</span>
                                </div>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>