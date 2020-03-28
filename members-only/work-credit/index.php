<?php
require_once('getData.php');
?>

<script>
    let data = <?= json_encode($reportData) ?>;

    data['submissions']['fields'] = $.map(data['submissions']['fields'], function (field) {
        return {'key': field, 'sortable': true};
    });

    data['userInfo'] = userInfo;
</script>

<html>
<head>
    <title>Work Credit Report</title>

    <!-- BootstrapVue CSS -->
    <link type="text/css" rel="stylesheet" href="//unpkg.com/bootstrap-vue@latest/dist/bootstrap-vue.min.css" />

    <!-- Load polyfills to support older browsers -->
    <script src="//polyfill.io/v3/polyfill.min.js?features=es2015%2CIntersectionObserver" crossorigin="anonymous"></script>

    <!-- Load Vue followed by BootstrapVue -->
    <script src="/resources/vue.min.js"></script>
    <script src="//unpkg.com/bootstrap-vue@latest/dist/bootstrap-vue.min.js"></script>

    <!-- Load the following for BootstrapVueIcons support -->
    <script src="//unpkg.com/bootstrap-vue@latest/dist/bootstrap-vue-icons.min.js"></script>

    <!-- loading bar for dashboard view -->
    <link rel="stylesheet" type="text/css" href="/resources/loading-bar/loading-bar.css"/>
    <script type="text/javascript" src="/resources/loading-bar/loading-bar.js"></script>

    <!-- for emoji -->
    <link href="https://emoji-css.afeld.me/emoji.css" rel="stylesheet">

    <!-- jquery validator -->
    <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.1/dist/jquery.validate.min.js"></script>

    <link href="/style.css" rel="stylesheet">
    <script src="/members-only/work-credit/work-credit.js"></script>


    <link rel="apple-touch-icon" sizes="180x180" href="icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="icons/favicon-16x16.png">
    <link rel="manifest" href="icons/site.webmanifest">

    <meta name="apple-mobile-web-app-title" content="Work Credit">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <button class="btn btn-info portal-back"><i class="fas fa-arrow-left"></i> Back to Portal</button>
    <div id="app" v-cloak>
        <div id="successAlert" class="alert alert-success alert-dismissible fade show" style="display: none" role="alert">
            Your hours have been submitted. Thank you for your hard work!
        </div>
        <div id="header">
            <div id="title">
                <h3>Work Credit Report</h3>
            </div>
            <ul class="nav nav-pills justify-content-center" id="pills-tab" role="tablist">
                <li>
                    <a class="nav-item nav-link active" data-toggle="pill" href="#report" id="report-tab" role="tab" aria-controls="report" aria-selected="true">Current Hours</a>
                </li>
                <!-- <li>
                    <a class="nav-item nav-link" data-toggle="pill" href="#dashboard" id="dashboard-tab" role="tab" aria-controls="dashboard" aria-selected="false">Personal Dashboard</a>
                </li> -->
                <li>
                    <a class="nav-item nav-link" data-toggle="pill" href="#submissions" id="submissions-tab" role="tab" aria-controls="submissions" aria-selected="false">Submission History</a>
                </li>
                <li>
                    <a class="nav-item nav-link btn-success" href="" id="submit-tab" role="tab" data-target="#submitTimeModal" data-toggle="modal" data-backdrop="static" data-keyboard="false">Submit Time</a>
                </li>
            </ul>
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
                            <div class="card-body desktop-only">    
                                <template>
                                    <div>
                                        <b-table
                                            striped
                                            head-variant="primary"
                                            fixed
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
                                            </template>
                                            <template v-slot:cell(collective_hours)="data">
                                                <div v-if="data.item.next_debit_qty[2]">
                                                    <span :class="styleUser(data.item.real_name)">{{ data.item.hours_credited[2] }} / {{ data.item.next_debit_qty[2] }}</span>
                                                <div> 
                                            </template>
                                            <template v-slot:cell(maintenance_hours)="data">
                                                <div v-if="data.item.next_debit_qty[3]">
                                                    <span :class="styleUser(data.item.real_name)">{{ data.item.hours_credited[3] }} / {{ data.item.next_debit_qty[3] }}</span>
                                                </div>
                                            </template>
                                        </b-table>
                                    </div>
                                </template>
                            </div>
                            <div class="card-body mobile-only mobile-full-width">    
                                <template>
                                    <div>
                                        <b-table
                                            striped
                                            head-variant="primary"
                                            responsive
                                            stacked
                                            :items="members['items'][key]"
                                            :fields="members['mobileFields']"
                                            :tbody-tr-class="rowClass"
                                        >
                                            <template v-slot:cell(real_name)="data">
                                                <span :class="styleUser(data.item.real_name)">{{ data.item.real_name }}</span>
                                            </template>
                                            <template v-slot:cell(room)="data">
                                                <span :class="styleUser(data.item.real_name)">{{ data.item.room }}</span>
                                            </template>
                                            <template v-slot:cell(hours)="data">
                                                <div style="display:inline-block" :class="styleUser(data.item.real_name)">
                                                    <div v-if="data.item.next_debit_qty[3]" class="mobile-hours-cell" :class="'table-' + data.item._cellVariants.maintenance_hours">
                                                        <p>&#128736;</p>
                                                        {{ data.item.hours_credited[2] }} / {{ data.item.next_debit_qty[3] }}
                                                    </div>
                                                    <div v-if="data.item.next_debit_qty[2]" class="mobile-hours-cell" :class="'table-' + data.item._cellVariants.collective_hours">
                                                        <p>&#10024;</p>
                                                        {{ data.item.hours_credited[2] }} / {{ data.item.next_debit_qty[2] }}
                                                    </div>
                                                    <div v-if="data.item.next_debit_qty[1]" class="mobile-hours-cell" :class="'table-' + data.item._cellVariants.house_hours">
                                                        <p>&#127968;</p>
                                                        {{ data.item.hours_credited[1] }} / {{ data.item.next_debit_qty[1] }}
                                                    </div>
                                                </div>
                                            </template>
                                        </b-table>
                                    </div>
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
                <template>
                    <div class="overflow-auto">
                        <b-pagination
                            v-model="currentPage"
                            :total-rows="submissionRows"
                            :per-page="submissions['perPage']"
                            aria-controls="submissionsTable"
                        ></b-pagination>

                        <p class="mt-3">Current Page: {{ currentPage }}</p>

                        <b-table
                            id="submissionsTable"
                            :items="submissions['items']"
                            :fields="submissions['fields']"
                            :per-page="submissions['perPage']"
                            :current-page="submissions['currentPage']"
                            small
                            striped
                            head-variant="primary"
                        ></b-table>
                    </div>
                </template>
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
                    <form id="submissionForm">
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
                                    <input class="form-check-input" type="radio" name="hour_type_id" id="hour_type_id_2" value="2" :disabled="userInfo['is_boarder'] == 1">
                                    <label class="form-check-label" for="hour_type_id_2">
                                        &#10024; Collective
                                    </label>
                                    <p><small class="text-muted">e.g. committee meetings, community outreach</small></p>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="hour_type_id" id="hour_type_id_3" value="3" :disabled="userInfo['is_boarder'] == 1">
                                    <label class="form-check-label" for="hour_type_id_3">
                                        &#128736; Maintenance
                                    </label>
                                    <p><small class="text-muted">e.g. repairs, snow removal</small></p>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="hours_credited" class="submission-label">Hours Completed:</label>
                                <input type="number" step="0.25" min="0.25" max="99" class="form-control" id="hours_credited" name="hours_credited" required>
                            </div>
                            <div class="form-group">
                                <label for="contribution_date" class="submission-label">Date of Contribution:</label>
                                <input type="date" class="form-control" id="contribution_date" name="contribution_date" required>
                            </div>
                            <div class="form-group">
                                <label for="description" class="submission-label">Description of Work Completed:</label>
                                <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                            </div>
                            <div class="form-group" v-if="userInfo['is_boarder'] != 1">
                                <label for="other_req_id" class="submission-label">Does this fulfill an additional monthly requirement?</label>
                                <select class="form-control" id="other_req_id" name="other_req_id">
                                    <option value="" selected>Select</option>
                                    <option value="1">Cooked Dinner</option>
                                    <option value="2">Attended House Meeting</option>
                                    <option value="3">Attended BOD Meeting</option>
                                </select>
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