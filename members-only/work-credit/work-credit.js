var app;

$( document ).ready(function() {
    const date = new Date;
    const month = date.toLocaleString('en-us', { month: 'long' });

    $('.month').html(month);

    $('[data-toggle="tooltip"]').tooltip();

    if(localStorage.getItem('success')) {
        $('#successAlert').show();
        localStorage.removeItem('success');
    };

    app = new Vue({
        el: '#app',
        data: data,
        methods: {
            rowClass(item, type) {
                let positive = true;
                
                $.each(item._cellVariants, function(index, value) {
                    if (value !== 'positive') {
                        positive = false;
                    };
                });

                if (!item || type !== 'row') return
                if (positive) return 'table-positive'
            },
            styleUser(real_name) {
                if (real_name == data['userInfo']['real_name']) {
                    return 'user-highlight';
                }
            },
            loadDashboard() {
                let memberData = this.userRecord;
            
                $('.hoursProgressDisplay').each(function(index, item) {
                    var typeId = $(item).data('hours-id');
                    var progressDisplay = new ldBar(item, {
                        'preset': 'circle'
                    });
                    var label = $(item).find('.ldBar-label');
            
                    var currentHours = Math.round(memberData.hours_credited[typeId] * 100) / 100;
                    var requiredHours = memberData.next_debit_qty[typeId];
            
                    if (requiredHours === 0) {
                        $(item).parent().hide();
                    }
            
                    var progressPercentage = (currentHours / requiredHours) * 100;
            
                    progressDisplay.set(progressPercentage);
            
                    $(item).find('.progress-label').remove();
                    $('<text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle">' +
                        (progressPercentage > 100 ? requiredHours : currentHours) + '/' + requiredHours +
                        '</text>').appendTo($(item).find('svg'));
                })
            
                $('.otherProgressDisplay').each(function(index, item) {
                    var otherType = $(item).data('othertype');
                    var progressDisplay = new ldBar(item, {
                        'preset': 'circle',
            
                    });
                    var label = $(item).find('.ldBar-label');
            
                    var currentOtherProgress = memberData['Completed ' + otherType + ' (Current Month)'] || 0;
                    var otherRequirement = memberData['Required ' + otherType + ' (Monthly)'];
            
                    if (otherRequirement === 0) {
                        $(item).parent().hide();
                    }
            
                    var progressPercentage = (currentOtherProgress / otherRequirement) * 100;
            
                    progressDisplay.set(progressPercentage)
                    $(item).find('.progress-other-label').remove();
                    $('<div class="progress-other-label">' + currentOtherProgress + '/' + otherRequirement + '</div>').appendTo($(item));
                })
            }
        },
        computed: {
            submissionRows() {
                return this.submissions['items'].length
            }
        }
    });

    $('#submissionForm').validate();

    // todo does not validate
    $("#submitHours").click(function(e){
        if (!$('#submissionForm').valid()) {
            return;
        }

        let data = $('#submissionForm').serialize();

        $("#submissionForm :input").prop("disabled", true);
        $("#submitHours").prop("disabled", true);
        $("#cancelSubmit").prop("disabled", true);

        $("#submitText").hide();
        $("#submitted").show();

        $.ajax({
            type: "POST",
            url: "/members-only/work-credit/submit.php",
            cache: false,
            data: data,
            success: function(response) {
                if (response == 1) {
                    localStorage.setItem('success', true);
                    location.reload();
                }
                else {
                    alert("Something went wrong!");
                }
            },
            error: function(){
                alert("Something went wrong!");
            }
        });
    });

    // todo dashboard view
    // app.loadDashboard();

    $('.collapse').each(function(index, collapse) {
        var id = $(collapse).get(0).id;

        if (localStorage.getItem(id) === 'false') {
            $('#' + id).removeClass('show');
        }
    });

    $('#reportAccordion').on('shown.bs.collapse', function (e) {
        localStorage.setItem(e.target.id, true);
    });

    $('#reportAccordion').on('hidden.bs.collapse', function (e) {
        localStorage.setItem(e.target.id, false);
    });
});