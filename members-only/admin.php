<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions.php');

// page is only accessible if authorized via slack
// $slack is available for additional API calls
$slack = signInWithSlack($conn, true);

// todo make button
// $slack->importSlackUsersToDb();

// $hourTypes = $slack->sqlSelect("select id from wc_lookup_hour_types");

// foreach ($hourTypes as $id) {
//     $slack->scheduleHoursDebit($slack->userId, $id);
// }
?>
<script type="text/javascript" src="admin.js"></script>

<button class="btn btn-info portal-back"><i class="fas fa-arrow-left"></i> Back to Portal</button>

<div class="jumbotron">
    <h1 class="display-4">Admin Tools</h1>
    <p class="lead">The tools available on this page are for admins only! Please proceed with caution.</p>
    <hr class="my-4">
    <p class="lead">
        <button class="btn btn-primary btn-lg action-button" data-action="scheduleHoursDebit" role="button">
            <span class="button-label">Schedule Hour Debits</span>
            <div class="button-loading" style="display: none">
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                <span class="sr-only">Please wait...</span>
                <span> Please wait...</span>
            </div>
        </button>
    </p>
    <p>This button should be pressed on the first of each month to update the hours due for each member.</p>
    <p class="lead">
        <button class="btn btn-primary btn-lg action-button" data-action="syncSlackUsers" role="button">
            <span class="button-label">Force Sync User Data From Slack</span>
            <div class="button-loading" style="display: none">
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                <span class="sr-only">Please wait...</span>
                <span> Please wait...</span>
            </div>
        </button>
    </p>
    <p>Re-sync all user information from Slack to the Work Credit database (use this if someone's name isn't appearing in the Work Credit Report).</p>
    <p class="lead">
        <button class="btn btn-primary btn-lg action-button" data-action="getUserRequirements" role="button">
            <span class="button-label">Adjust Work Credit Requirements</span>
            <div class="button-loading" style="display: none">
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                <span class="sr-only">Please wait...</span>
                <span> Please wait...</span>
            </div>
        </button>
    </p>
    <p>Make per-member adjustments to hour requirements</p>
</div>

<div class="modal fade" id="adjustRequirementsModal" tabindex="-1" role="dialog" aria-labelledby="adjustRequirementsLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adjust Work Credit Requirements</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="submissionForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="userSelect" class="bold-label">
                            <span id="loadingUser" style="display: none">
                                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                <span class="sr-only">Loading user...</span>
                            </span>
                            <span> User: </span>
                        </label>
                        <select class="form-control" id="userSelect" name="userSelect" data-action="getRequirementMods">
                            <option value="" selected>Select</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="hourModDiv" class="bold-label">Hour Requirements: </label>
                        <table class="table" id="hourModDiv">
                            <thead>
                            <tr>
                                <td class="w-25">Type</td>
                                <td class="w-25">Base</td>
                                <td class="w-25">Modifier</td>
                                <td class="w-25">Total</td>
                            </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="form-group">
                        <label for="otherModDiv" class="bold-label">Other Requirements: </label>
                        <table class="table" id="otherModDiv">
                            <thead>
                            <tr>
                                <td class="w-25">Type</td>
                                <td class="w-25">Base</td>
                                <td class="w-25">Modifier</td>
                                <td class="w-25">Total</td>
                            </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="cancelModifiers" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" type="button" id="saveModifers" data-action="saveUserRequirements" disabled>
                        <span id="saveText">Save</span>
                        <div id="savingText" style="display: none">
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                            <span class="sr-only">Saving...</span>
                            <span> Saving...</span>
                        </div>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>