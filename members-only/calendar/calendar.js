var calendar;

document.addEventListener('DOMContentLoaded', function() {
    var Calendar = FullCalendar.Calendar;
    var Draggable = FullCalendar.Draggable;

    var containerEl = document.getElementById('external-events');
    var calendarEl = document.getElementById('calendar');

    // initialize the external events
    // -----------------------------------------------------------------

    new Draggable(containerEl, {
        itemSelector: '.fc-event',
        eventData: function(eventEl) {
            return {
                title: eventEl.innerText
            };
        }
    });

    var initialSources = [];

    $.each(houseLookup, function (index, houseInfo) {
        var $checkbox = $(`
            <input
                id="checkbox_${houseInfo.name}"
                class="house-checkbox"
                type="checkbox"
                value="${index}"
            >
            <label for="checkbox_${houseInfo.name}">${houseInfo.name}</label>
            <br />
        `);

        if (houseInfo.slack_group_id === userInfo.house_id) {
            $checkbox.prop('checked', true);
        }

        $('#selectHouse').append($checkbox);
    });

    // initialize the calendar
    // -----------------------------------------------------------------

    // todo filter by house id
    // $('.house-checkbox').click(function () {
    //     var check_id = $(this).val();
    //
    //     if ($(this).is(':checked')) {
    //         calendar.addEventSource(eventSources[check_id]);
    //     }
    //     else {
    //         // eventSources[check_id] = calendar.getEventSourceById(check_id);
    //         calendar.getEventSourceById(check_id).remove()
    //     }
    // });

    calendar = new Calendar(calendarEl, {
        headerToolbar: {
            left: '',
            center: 'title',
            right: 'prev,next today'
        },
        editable: true,
        droppable: true,
        eventOverlap: false,
        events: events,
        eventChange: function(event) {
            console.log('changed');
            saveEvent(event);
        },
        drop: function(event) {
            // saveEvent(event);
        },
        eventDrop: function(event) {
            // saveEvent(event);
        },
        eventAdd: function(addInfo) {
            // console.log(addInfo);
            saveEvent(addInfo);
        },
        eventsSet: function(events) {
            // console.log(events);
        },
        dateClick: function (dateInfo) {
            var houseInfo = houseLookup[userInfo.house_id];

            dateInfo = $.extend(dateInfo, {
                house: houseInfo.name,
                color: houseInfo.css_color,
                textColor: 'black',
                title: 'Dinner - ' + userInfo.display_name
            });

            this.addEvent(dateInfo);
        },
        eventDidMount: function(arg) {
            var $element = $(arg.el);
            var event = arg.event;

            $element.find('.fc-event-main-frame').append( "<button type=\"button\" class=\"close\" aria-label=\"Close\">\n" +
                "  <span aria-hidden=\"true\">&times;</span>\n" +
                "</button>" );
            $element.find(".close").click(function() {
                event.remove();
            });
        }
    });

    calendar.render();

    function saveEvent(event) {
        var data = {
            type: 'set'
        };

        var newEvent = false;

        console.log(event.event.extendedProps);

        // new event
        if (!event.oldEvent) {
            data.date = event.dateStr;
            data.display = 'block';

            newEvent = true;
        }
        // update existing
        else {
            data.id = event.event.extendedProps.db_id;
            data.date = event.event.startStr;
        }

        $.ajax({
            type: "POST",
            url: "/members-only/calendar/update.php",
            cache: false,
            data: data,
            success: function(response) { //todo prob should have better error handling here
                // console.log(response);
                // calendar.refetchEvents();
                //
                // if (event.event) {
                //     event.event.remove();
                // }
                // else {
                //     event.remove();
                // }

                if (newEvent) {
                    event.event.setExtendedProp('db_id', response);
                }
            },
            error: function(){
                alert("Something went wrong!");
            }
        });
    }
});