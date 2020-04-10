<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions.php');

// page is only accessible if authorized via slack
// $slack is available for additional API calls
$slack = signInWithSlack($conn, true);

?>
<script type="text/javascript" src="admin.js"></script>

<button class="btn btn-info portal-back"><i class="fas fa-arrow-left"></i> Back to Portal</button>

<div class="jumbotron">
    <h1 class="display-4">Admin Tools</h1>
    <p class="lead">The tools available on this page are for admins only! Please proceed with caution.</p>
    <hr class="my-4">
    <div id="buttons"></div>
</div>

<div class="modal fade" id="updateDoorCodeModal" tabindex="-1" role="dialog" aria-labelledby="updateDoorCodeLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Door Code</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form class="modal-form">
                <div class="modal-body">
                    <p>Active users can request these codes in Slack via the <code>/code</code> command. You may optionally send an email notification out upon saving as well.</p>
                    <hr class="my-4">
                    <div class="input-group mb-8 justify-content-center">
                        <div class="input-group-prepend">
                            <select class="custom-select" name="slack_group_id" id="selectHouse" style="width=100%">
                                <option value="" selected>Choose...</option>
                            </select>
                        </div>
                        <input type="text" class="form-control col-2" style="text-align: center;" id="doorCodeInput" name="door_code" aria-label="Door Code" disabled>
                        <div class="input-group-append">
                            <button class="btn btn-success randomize-code" type="button"><i class="fas fa-random"></i> Randomize</button>
                        </div>
                    </div>
                    <hr class="my-4">
                    <div class="alert alert-danger" role="alert">
                        <strong>Warning:</strong> Be sure to deactivate old user accounts prior to making changes or they may be able to easily gain access to the new door codes.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="cancelDoorCode" class="btn btn-secondary cancel-button" data-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary action-button" type="button" id="updateDoorCode" data-action="updateDoorCode" data-method="save" disabled>
                        <span class="submit-label">Save</span>
                        <div class="submitted-label" style="display: none">
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
<div class="modal fade" id="adjustRequirementsModal" tabindex="-1" role="dialog" aria-labelledby="adjustRequirementsLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adjust Work Credit Requirements</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form class="modal-form">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="userSelect" class="bold-label">
                            <span id="loadingUser" style="display: none">
                                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                <span class="sr-only">Loading user...</span>
                            </span>
                            <span> User: </span>
                        </label>
                        <select class="form-control" id="userSelect" name="user_id" data-action="adjustRequirements">
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
                    <button type="button" id="cancelModifiers" class="btn btn-secondary cancel-button" data-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary action-button" type="button" id="saveModifers" data-action="adjustRequirements" data-method="save" disabled>
                        <span class="submit-label">Save</span>
                        <div class="submitted-label" style="display: none">
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