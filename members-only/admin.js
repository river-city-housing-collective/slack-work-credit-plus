let lookup = {};
let initialLoad = false;
let userReqMods = {};

$( document ).ready(function() {
    $(".action-button").click(function(e) {
        let $this = $(this);
        let postJson = {'action': $this.data('action')};

        $this.prop("disabled", true);
        $this.find('.button-label').hide();
        $this.find('.button-loading').show();

        if ($this.data('action') === 'getUserRequirements') {
            let $saveButton = $('#saveModifers');

            $saveButton.prop('disabled', true);

            if (!initialLoad) {
                $.ajax({
                    type: "POST",
                    url: "/ajax.php",
                    cache: false,
                    data: {'action': $this.data('action')},
                    success: function (response) {
                        let data = JSON.parse(response);

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
                                            <td class="qty-input"><input step="0.5" min="-${type['default_qty']}" data-type="${category}" data-id="${id}" type="number" value="0" class="form-control" disabled></td>
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

                        $('#adjustRequirementsModal').modal('show');

                        $this.find('.button-label').show();
                        $this.find('.button-loading').hide();
                        $this.prop("disabled", false);

                        initialLoad = true;
                    },
                    error: function () {
                        alert("Something went wrong!");
                    }
                });

                $('#userSelect').change(function () {
                    let $this = $(this);
                    let $saveButton = $('#saveModifers');
                    let $loadingSpinner = $('#loadingUser');

                    $('.qty-input > input').prop('disabled', true);
                    $saveButton.prop('disabled', true);

                    if ($this.val() !== '') {
                        $loadingSpinner.show();

                        $.ajax({
                            type: "POST",
                            url: "/ajax.php",
                            cache: false,
                            data: {
                                'action': $this.data('action'),
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
                })
            }
            else {
                $('.qty-input > input').prop('disabled', true);

                $('#adjustRequirementsModal').modal('show');
            }
        }
        else {
            if ($this.data('action') === 'saveUserRequirements') {
                postJson['data'] = userReqMods; // todo define this
            }

            $.ajax({
                type: "POST",
                url: "/ajax.php",
                cache: false,
                data: postJson,
                success: function (response) {
                    $this.find('.button-label').show();
                    $this.find('.button-loading').hide();
                    $this.prop("disabled", false);

                    alert(response);
                },
                error: function () {
                    alert("Something went wrong!");
                }
            });

        }

    });
});