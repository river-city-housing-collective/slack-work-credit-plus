let lookup = {};
let initialized = {
    'adjustRequirementsModal': false,
    'updateDoorCodeModal': false
};
let userReqMods = {};

let toolButtons = {
    'updateDoorCode': {
        'label': 'Update Door Code',
        'description': 'Generate a new door code for a house and distribute it to current members.',
        'modal': 'updateDoorCodeModal'
    },
    'adjustRequirements': {
        'label': 'Adjust Work Credit Requirements',
        'description': 'Make per-member adjustments to hour requirements.',
        'modal': 'adjustRequirementsModal'
    },
    'syncSlackUsers': {
        'label': 'Force Sync User Data',
        'description': 'Re-sync all user information from Slack to the Work Credit database (use this if someone\'s name isn\'t appearing in the Work Credit Report).',
        'modal' : false
    }
};

$( document ).ready(function() {
    $.each(toolButtons, function (action, data) {
        $('#buttons').append(`
            <p class="lead">
                <button class="btn btn-primary btn-lg action-button" data-action="${action}" role="button">
                    <span class="submit-label">${data['label']}</span>
                    <div class="submitted-label" style="display: none">
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        <span class="sr-only">Please wait...</span>
                        <span> Please wait...</span>
                    </div>
                </button>
            </p>
            <p>${data['description']}</p>
        `)
    });

    $(".action-button").click(function(e) {
        let $this = $(this);
        let action = $this.data('action');
        let method = $this.data('method');
        let data = '';

        let modalId = toolButtons[action]['modal'];
        let $modal = $('#' + modalId);
        let $modalForm = $modal.find('.modal-form');

        toggleActionButton($this, true);

        if (method === 'save') {
            let data = updateFormState($modalForm, true, true);

            $.ajax({
                type: "POST",
                url: "/ajax.php?action=" + action + '&method=' + method,
                cache: false,
                data: data,
                success: function (response) {
                    alert(response); //todo alert popup

                    //todo only allow save when values have changed

                    updateFormState($modalForm, false);
                    $modal.modal('hide')
                },
                error: function () {
                    alert("Something went wrong!");
                }
            });
        }
        else {
            if (!initialized[modalId]) {
                $.ajax({
                    type: "POST",
                    url: "/ajax.php?action=" + action + '&method=' + method,
                    cache: false,
                    data: data,
                    success: function (response) {
                        data = JSON.parse(response);

                        if (action === 'updateDoorCode') {
                            let houseData = JSON.parse(response);
                            let codeLookup = {};

                            $.each(houseData, function(index, data) {
                                $('#selectHouse').append(`<option value="${data['slack_group_id']}">${data['name']}</option>`);

                                codeLookup[data['slack_group_id']] = data['door_code'];
                            });

                            $('#selectHouse').change(function () {
                                let house = $(this).val();
                                let $saveButton = $modalForm.find(".action-button[data-method='save']");
                                let $input = $('#doorCodeInput');

                                if (house !== '') {
                                    $input.prop('disabled', false);
                                    $input.val(codeLookup[house]);
                                    $saveButton.prop('disabled', false);
                                }
                                else {
                                    $input.prop('disabled', true);
                                    $input.val('');
                                    $saveButton.prop('disabled', true);
                                }

                            });

                            $('.randomize-code').click(function () {
                                let newCode = Math.floor(Math.random() * 9000) + 1000;

                                $('#doorCodeInput').val(newCode);
                            });
                        }
                        else if (action === 'adjustRequirements') {
                            $.each(data['users'], function (house, users) {
                                let $group = $("<optgroup label='" + house + "'></optgroup>");

                                $.each(users, function (index, user) {
                                    $group.append("<option value='" + user['slack_user_id'] + "'>" + user['real_name'] + "</option>");
                                });

                                $("#userSelect").append($group);
                            });

                            $.each(data['types'], function (category, types) {
                                let $tableDiv = $('#' + category + 'ModDiv');

                                lookup[category] = {};

                                $.each(types, function (index, type) {
                                    let id = type['id'];

                                    let $newInput = $(`
                                        <tr class="qty-tr">
                                            <td>${type['name']}</td>
                                            <td>${type['default_qty']}</td>
                                            <td style="display: none"><input name="${category}_type_id[]" value="${id}"></td>
                                            <td class="qty-input"><input step="0.5" min="-${type['default_qty']}" name="${category}_qty_modifier[]" data-type="${category}" data-id="${id}" type="number" value="0" class="form-control" disabled></td>
                                            <td class="qty-total"><input data-type="${category}" data-id="${id}" type="number" class="form-control" value="${type['default_qty']}" disabled></td>
                                        </tr>
                                    `);

                                    lookup[category][id] = parseInt(type['default_qty']);

                                    $tableDiv.append($newInput);
                                });
                            });

                            $('.qty-input > input').on('keyup change', function () {
                                let type = $(this).data('type');
                                let typeId = $(this).data('id');
                                let total = parseFloat($(this).val()) + lookup[type][typeId];

                                $(".qty-total > input[data-id='" + typeId + "'][data-type='" + type + "']").val(total);
                            });

                            $('#userSelect').change(function () {
                                let $this = $(this);
                                let $saveButton = $modalForm.find(".action-button[data-method='save']");
                                let $loadingSpinner = $('#loadingUser');

                                $('.qty-input > input').prop('disabled', true);
                                $saveButton.prop('disabled', true);

                                if ($this.val() !== '') {
                                    $loadingSpinner.show();

                                    $.ajax({
                                        type: "POST",
                                        url: "/ajax.php?action=" + $this.data('action') + "&method=load_user",
                                        cache: false,
                                        data: {
                                            'user_id': $this.val()
                                        },
                                        success: function (response) {
                                            $('.qty-input > input').val(0).change();

                                            let data = JSON.parse(response);

                                            if (data['ok']) {
                                                $.each(data['data'], function (type, mods) {
                                                    $.each(mods, function (id, value) {
                                                        $(".qty-input > input[data-id='" + id + "'][data-type='" + type + "']").val(value).change();
                                                    });
                                                });
                                            }

                                            $loadingSpinner.hide();
                                            $('.qty-input > input').prop('disabled', false);
                                            $saveButton.prop('disabled', false);
                                        },
                                        error: function () {
                                            alert("Something went wrong!");
                                        }
                                    });
                                }
                            });

                            $('#cancelModifiers').click(function () {
                                $('.qty-input > input').val(0).change();
                                $('.qty-input > input').prop('disabled', true);
                            });
                        }

                        initialized[modalId] = true;

                        toggleActionButton($this, false);
                        $modal.modal('show');
                    },
                    error: function () {
                        alert("Something went wrong!");
                    }
                });
            }
            else {
                toggleActionButton($this, false);
                $modal.modal('show');
            }
        }


    });

    function toggleActionButton($button, disabled) {
        $button.prop("disabled", disabled);

        if (disabled) {
            $button.find('.submit-label').hide();
            $button.find('.submitted-label').show();
        }
        else {
            $button.find('.submit-label').show();
            $button.find('.submitted-label').hide();
        }

    }
});