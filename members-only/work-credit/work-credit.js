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

    // todo anchors for linking directly to report/dashboard/history

    $('.collapse').each(function(index, collapse) {
        var id = $(collapse).get(0).id;

        if (localStorage.getItem(id) === 'false') {
            $('#' + id).removeClass('show');
        }
    });

    $('')

    $('#reportAccordion').on('shown.bs.collapse', function (e) {
        localStorage.setItem(e.target.id, true);
    });

    $('#reportAccordion').on('hidden.bs.collapse', function (e) {
        localStorage.setItem(e.target.id, false);
    });

    var pagerOptions = {

        // target the pager markup - see the HTML block below
        container: $(".pager"),

        // use this url format "http:/mydatabase.com?page={page}&size={size}&{sortList:col}"
        ajaxUrl: null,

        // modify the url after all processing has been applied
        customAjaxUrl: function(table, url) { return url; },

        // ajax error callback from $.tablesorter.showError function
        // ajaxError: function( config, xhr, settings, exception ) { return exception; };
        // returning false will abort the error message
        ajaxError: null,

        // add more ajax settings here
        // see http://api.jquery.com/jQuery.ajax/#jQuery-ajax-settings
        ajaxObject: { dataType: 'json' },

        // process ajax so that the data object is returned along with the total number of rows
        ajaxProcessing: null,

        // Set this option to false if your table data is preloaded into the table, but you are still using ajax
        processAjaxOnInit: true,

        // output string - default is '{page}/{totalPages}'
        // possible variables: {size}, {page}, {totalPages}, {filteredPages}, {startRow}, {endRow}, {filteredRows} and {totalRows}
        // also {page:input} & {startRow:input} will add a modifiable input in place of the value
        // In v2.27.7, this can be set as a function
        // output: function(table, pager) { return 'page ' + pager.startRow + ' - ' + pager.endRow; }
        output: '{startRow:input} – {endRow} / {totalRows} rows',

        // apply disabled classname (cssDisabled option) to the pager arrows when the rows
        // are at either extreme is visible; default is true
        updateArrows: true,

        // starting page of the pager (zero based index)
        page: 0,

        // Number of visible rows - default is 10
        size: 10,

        // Save pager page & size if the storage script is loaded (requires $.tablesorter.storage in jquery.tablesorter.widgets.js)
        savePages : true,

        // Saves tablesorter paging to custom key if defined.
        // Key parameter name used by the $.tablesorter.storage function.
        // Useful if you have multiple tables defined
        storageKey:'tablesorter-pager',

        // Reset pager to this page after filtering; set to desired page number (zero-based index),
        // or false to not change page at filter start
        pageReset: 0,

        // if true, the table will remain the same height no matter how many records are displayed. The space is made up by an empty
        // table row set to a height to compensate; default is false
        fixedHeight: true,

        // remove rows from the table to speed up the sort of large tables.
        // setting this to false, only hides the non-visible rows; needed if you plan to add/remove rows with the pager enabled.
        removeRows: false,

        // If true, child rows will be counted towards the pager set size
        countChildRows: false,

        // css class names of pager arrows
        cssNext: '.next', // next page arrow
        cssPrev: '.prev', // previous page arrow
        cssFirst: '.first', // go to first page arrow
        cssLast: '.last', // go to last page arrow
        cssGoto: '.gotoPage', // select dropdown to allow choosing a page

        cssPageDisplay: '.pagedisplay', // location of where the "output" is displayed
        cssPageSize: '.pagesize', // page size selector - select dropdown that sets the "size" option

        // class added to arrows when at the extremes (i.e. prev/first arrows are "disabled" when on the first page)
        cssDisabled: 'disabled', // Note there is no period "." in front of this class name
        cssErrorRow: 'tablesorter-errorRow' // ajax error information row

    };

    $("#submissionsTable").tablesorter({
        theme: 'bootstrap',
        widgets: [ "filter", "columns", "zebra", "editable" ],
        columns: [ "primary", "secondary", "tertiary" ],
        widgetOptions: {
            editable_columns       : [0,1,2],       // or "0-2" (v2.14.2); point to the columns to make editable (zero-based index)
            editable_enterToAccept : true,          // press enter to accept content, or click outside if false
            editable_autoAccept    : true,          // accepts any changes made to the table cell automatically (v2.17.6)
            editable_autoResort    : false,         // auto resort after the content has changed.
            editable_validate      : null,          // return a valid string: function(text, original, columnIndex) { return text; }
            editable_focused       : function(txt, columnIndex, $element) {
                // $element is the div, not the td
                // to get the td, use $element.closest('td')
                $element.addClass('focused');
            },
            editable_blur          : function(txt, columnIndex, $element) {
                // $element is the div, not the td
                // to get the td, use $element.closest('td')
                $element.removeClass('focused');
            },
            editable_selectAll     : function(txt, columnIndex, $element) {
                // note $element is the div inside of the table cell, so use $element.closest('td') to get the cell
                // only select everthing within the element when the content starts with the letter "B"
                return /^b/i.test(txt) && columnIndex === 0;
            },
            editable_wrapContent   : '<div>',       // wrap all editable cell content... makes this widget work in IE, and with autocomplete
            editable_trimContent   : true,          // trim content ( removes outer tabs & carriage returns )
            editable_noEdit        : 'no-edit',     // class name of cell that is not editable
            editable_editComplete  : 'editComplete' // event fired after the table content has been edited
        },
        filter_functions: {

            // Add select menu to this column
            // set the column value to true, and/or add "filter-select" class name to header
            // '.first-name' : true,

            // Exact match only
            1 : function(e, n, f, i, $r, c, data) {
                return e === f;
            }
        }
    })
        // bind to pager events
        // *********************
        .bind('pagerChange pagerComplete pagerInitialized pageMoved', function(e, c) {
            var msg = '"</span> event triggered, ' + (e.type === 'pagerChange' ? 'going to' : 'now on') +
                ' page <span class="typ">' + (c.page + 1) + '/' + c.totalPages + '</span>';
            $('#display')
                .append('<li><span class="str">"' + e.type + msg + '</li>')
                .find('li:first').remove();
        })

        // initialize the pager plugin
        // ****************************
        .tablesorterPager(pagerOptions);
});