/*
|--------------------------------------------------------------------------
| Podaci o terminima
|--------------------------------------------------------------------------
*/

const currentMonthIndexes = {};

function openActivityModal(activityId)
{
    const modal =
        document.getElementById(
            'activity-modal-' + activityId
        );

    if (!modal) {
        return;
    }

    const activity =
        activityData[activityId];

    if (
        !activity
        ||
        !activity.months
        ||
        activity.months.length === 0
    ) {
        return;
    }

    currentMonthIndexes[activityId] = 0;

    renderCalendar(activityId);

    modal.classList.add('active');

    document.body.classList.add('modal-open');

    resetSelectedDate(activityId);
}

function closeActivityModal(activityId)
{
    const modal =
        document.getElementById(
            'activity-modal-' + activityId
        );

    if (!modal) {
        return;
    }

    modal.classList.remove('active');

    document.body.classList.remove('modal-open');
}

document.querySelectorAll('.modal').forEach(
    function(modal) {

        modal.addEventListener(
            'click',
            function(event) {

                if (event.target === modal) {

                    const activityId =
                        modal.dataset.activityId;

                    closeActivityModal(activityId);
                }

            }
        );

    }
);

document.addEventListener(
    'keydown',
    function(event) {

        if (event.key !== 'Escape') {
            return;
        }

        document
            .querySelectorAll('.modal.active')
            .forEach(
                function(modal) {

                    closeActivityModal(
                        modal.dataset.activityId
                    );

                }
            );

    }
);

function changeMonth(
    activityId,
    direction
) {

    const activity =
        activityData[activityId];

    if (!activity) {
        return;
    }

    let currentIndex =
        currentMonthIndexes[activityId] ?? 0;

    currentIndex += direction;

    if (currentIndex < 0) {

        currentIndex = 0;
    }

    const visibleMonths =
        getVisibleMonths(activity);

    if (currentIndex >= visibleMonths.length) {
        currentIndex =
            Math.max(visibleMonths.length - 1, 0);
    }

    currentMonthIndexes[activityId] =
        currentIndex;

    renderCalendar(activityId);

    resetSelectedDate(activityId);
}

function isOccurrenceAllowed(occurrence)
{
    return (
        occurrence.gender === 'ALL'
        || occurrence.gender === studentGender
    );
}

function getVisibleOccurrences(activity)
{
    return activity.occurrences.filter(
        function(occurrence) {
            return isOccurrenceAllowed(occurrence);
        }
    );
}

function getVisibleMonths(activity)
{
    const visibleOccurrences =
        getVisibleOccurrences(activity);

    const monthKeys = {};

    visibleOccurrences.forEach(
        function(occurrence) {

            const date =
                new Date(occurrence.date + 'T00:00:00');

            const key =
                date.getFullYear()
                + '-'
                + String(date.getMonth() + 1).padStart(2, '0');

            monthKeys[key] = {
                year: date.getFullYear(),
                month: date.getMonth() + 1
            };
        }
    );

    return Object.keys(monthKeys)
        .sort()
        .map(
            function(key) {
                return monthKeys[key];
            }
        );
}

function renderCalendar(activityId)
{
    const activity =
        activityData[activityId];

    if (!activity) {
        return;
    }

    const visibleMonths =
        getVisibleMonths(activity);

    const monthIndex =
        currentMonthIndexes[activityId] ?? 0;

    const monthData =
        visibleMonths[monthIndex];

    const calendar =
        document.getElementById(
            'calendar-' + activityId
        );

    const monthTitle =
        document.getElementById(
            'month-title-' + activityId
        );

    const previousButton =
        document.getElementById(
            'prev-month-' + activityId
        );

    const nextButton =
        document.getElementById(
            'next-month-' + activityId
        );

    if (!monthData) {
        monthTitle.textContent =
            'Nema dostupnih termina';

        previousButton.disabled = true;
        nextButton.disabled = true;
        calendar.innerHTML = '';
        return;
    }

    const year = monthData.year;
    const month = monthData.month;

    monthTitle.textContent =
        getMonthName(month)
        + ' '
        + year;

    previousButton.disabled =
        monthIndex === 0;

    nextButton.disabled =
        monthIndex ===
        visibleMonths.length - 1;

    calendar.innerHTML = '';

    const weekdays = [
        'Pon',
        'Uto',
        'Sri',
        'Čet',
        'Pet',
        'Sub',
        'Ned'
    ];

    weekdays.forEach(
        function(day) {

            const weekday =
                document.createElement('div');

            weekday.className =
                'weekday';

            weekday.textContent =
                day;

            calendar.appendChild(
                weekday
            );

        }
    );

    const firstDay =
        new Date(
            year,
            month - 1,
            1
        );

    let firstWeekday =
        firstDay.getDay();

    firstWeekday =
        (firstWeekday + 6) % 7;

    for (
        let i = 0;
        i < firstWeekday;
        i++
    ) {

        const emptyDay =
            document.createElement('div');

        emptyDay.className =
            'calendar-day empty';

        calendar.appendChild(
            emptyDay
        );
    }

    const daysInMonth =
        new Date(
            year,
            month,
            0
        ).getDate();

    for (
        let day = 1;
        day <= daysInMonth;
        day++
    ) {

        const dateKey =
            year
            + '-'
            + String(month).padStart(
                2,
                '0'
            )
            + '-'
            + String(day).padStart(
                2,
                '0'
            );

        const dayElement =
            document.createElement('div');

        dayElement.className =
            'calendar-day';

        dayElement.textContent =
            day;

        const hasOccurrence =
            getVisibleOccurrences(activity).some(
                function(occurrence) {

                    return occurrence.date ===
                        dateKey;

                }
            );

        if (hasOccurrence) {

            dayElement.classList.add(
                'available'
            );

            dayElement.addEventListener(
                'click',
                function() {

                    selectDate(
                        activityId,
                        dateKey,
                        dayElement
                    );

                }
            );
        }

        calendar.appendChild(
            dayElement
        );
    }
}

function selectDate(
    activityId,
    dateKey,
    clickedElement
) {

    const calendar =
        document.getElementById(
            'calendar-' + activityId
        );

    calendar
        .querySelectorAll(
            '.calendar-day.selected'
        )
        .forEach(
            function(element) {

                element.classList.remove(
                    'selected'
                );

            }
        );

    clickedElement.classList.add(
        'selected'
    );

    const activity =
        activityData[activityId];

    const occurrences =
        getVisibleOccurrences(activity).filter(
            function(occurrence) {

                return occurrence.date ===
                    dateKey;

            }
        );

    const selectedTitle =
        document.getElementById(
            'selected-date-title-' + activityId
        );

    const sessionList =
        document.getElementById(
            'session-list-' + activityId
        );

    selectedTitle.textContent =
        formatDateCroatian(
            dateKey
        );

    sessionList.innerHTML = '';

    if (occurrences.length === 0) {

        sessionList.innerHTML =
            '<div class="selected-date-empty">'
            + 'Na ovaj datum nema dostupnih termina.'
            + '</div>';

        return;
    }

    occurrences.sort(
        function(a, b) {

            return a.start_time.localeCompare(
                b.start_time
            );

        }
    );

    occurrences.forEach(
        function(occurrence) {

            const sessionCard =
                document.createElement('div');

            sessionCard.className =
                'session-card';

            const sessionTop =
                document.createElement('div');

            sessionTop.className =
                'session-top';

            const sessionTime =
                document.createElement('div');

            sessionTime.className =
                'session-time';

            sessionTime.textContent =
                occurrence.start_time
                + ' - '
                + occurrence.end_time;

            sessionTop.appendChild(
                sessionTime
            );

            const sessionDetails =
                document.createElement('div');

            sessionDetails.className =
                'session-details';

            const locationDetail =
                document.createElement('div');

            locationDetail.innerHTML =
                '<strong>Lokacija:</strong> '
                +
                escapeHtml(
                    occurrence.location
                );

            sessionDetails.appendChild(
                locationDetail
            );

            const genderDetail =
                document.createElement('div');

            let genderText =
                'Svi';

            if (
                occurrence.gender ===
                'FEMALE'
            ) {

                genderText =
                    'Žene';

            } else if (
                occurrence.gender ===
                'MALE'
            ) {

                genderText =
                    'Muškarci';
            }

            genderDetail.innerHTML =
                '<strong>Skupina:</strong> '
                +
                escapeHtml(
                    genderText
                );

            sessionDetails.appendChild(
                genderDetail
            );

            const capacityDetail =
                document.createElement('div');

            if (
                occurrence.capacity ===
                null
            ) {

                capacityDetail.innerHTML =
                    '<strong>Slobodna mjesta:</strong> '
                    +
                    'Nije ograničeno';

            } else {

                const available =
                    Math.max(
                        0,
                        occurrence.capacity
                        -
                        occurrence.reserved_count
                    );

                capacityDetail.innerHTML =
                    '<strong>Slobodna mjesta:</strong> '
                    +
                    available
                    +
                    ' / '
                    +
                    occurrence.capacity;
            }

            sessionDetails.appendChild(
                capacityDetail
            );

            const action =
                document.createElement('div');

            action.className =
                'session-action';

            if (
                occurrence.user_reserved
            ) {

                const reservedButton =
                    document.createElement(
                        'button'
                    );

                reservedButton.type =
                    'button';

                reservedButton.className =
                    'reserve-button reserved';

                reservedButton.disabled =
                    true;

                reservedButton.textContent =
                    'Rezervirano';

                action.appendChild(
                    reservedButton
                );

            }

            else if (
                occurrence.capacity !== null
                &&
                occurrence.reserved_count
                    >= occurrence.capacity
            ) {

                const fullButton =
                    document.createElement(
                        'button'
                    );

                fullButton.type =
                    'button';

                fullButton.className =
                    'reserve-button disabled';

                fullButton.disabled =
                    true;

                fullButton.textContent =
                    'Popunjeno';

                action.appendChild(
                    fullButton
                );

            }

            else {

                const form =
                    document.createElement(
                        'form'
                    );

                form.method =
                    'POST';

                form.action =
                    'reserve.php';

                const sessionInput =
                    document.createElement(
                        'input'
                    );

                sessionInput.type =
                    'hidden';

                sessionInput.name =
                    'session_id';

                sessionInput.value =
                    occurrence.session_id;

                const dateInput =
                    document.createElement(
                        'input'
                    );

                dateInput.type =
                    'hidden';

                dateInput.name =
                    'reservation_date';

                dateInput.value =
                    occurrence.date;

                const occurrenceInput =
                    document.createElement(
                        'input'
                    );

                occurrenceInput.type =
                    'hidden';

                occurrenceInput.name =
                    'occurrence_id';

                occurrenceInput.value =
                    occurrence.occurrence_id;

                const reserveButton =
                    document.createElement(
                        'button'
                    );

                reserveButton.type =
                    'submit';

                reserveButton.className =
                    'reserve-button';

                reserveButton.textContent =
                    'Rezerviraj';

                form.appendChild(
                    sessionInput
                );

                form.appendChild(
                    dateInput
                );

                form.appendChild(
                    occurrenceInput
                );

                form.appendChild(
                    reserveButton
                );

                action.appendChild(
                    form
                );
            }

            sessionCard.appendChild(
                sessionTop
            );

            sessionCard.appendChild(
                sessionDetails
            );

            sessionCard.appendChild(
                action
            );

            sessionList.appendChild(
                sessionCard
            );

        }
    );
}

function resetSelectedDate(
    activityId
) {

    const selectedTitle =
        document.getElementById(
            'selected-date-title-' + activityId
        );

    const sessionList =
        document.getElementById(
            'session-list-' + activityId
        );

    selectedTitle.textContent =
        'Odaberite datum';

    const activity =
        activityData[activityId];

    const message =
        activity
        && getVisibleOccurrences(activity).length > 0
            ? 'Kliknite na jedan od označenih datuma u kalendaru.'
            : 'Za vas trenutno nema dostupnih termina ove aktivnosti.';

    sessionList.innerHTML =
        '<div class="selected-date-empty">'
        + escapeHtml(message)
        + '</div>';
}

function getMonthName(month)
{
    const months = [

        'Siječanj',
        'Veljača',
        'Ožujak',
        'Travanj',
        'Svibanj',
        'Lipanj',
        'Srpanj',
        'Kolovoz',
        'Rujan',
        'Listopad',
        'Studeni',
        'Prosinac'

    ];

    return months[month - 1] ?? '';
}

function formatDateCroatian(
    dateString
) {

    const parts =
        dateString.split('-');

    const year =
        parseInt(
            parts[0],
            10
        );

    const month =
        parseInt(
            parts[1],
            10
        );

    const day =
        parseInt(
            parts[2],
            10
        );

    const date =
        new Date(
            year,
            month - 1,
            day
        );

    const weekdayNames = [

        'Nedjelja',
        'Ponedjeljak',
        'Utorak',
        'Srijeda',
        'Četvrtak',
        'Petak',
        'Subota'

    ];

    return (

        weekdayNames[
            date.getDay()
        ]

        +

        ', '

        +

        day

        +

        '. '

        +

        getMonthName(
            month
        ).toLowerCase()

        +

        ' '

        +

        year

        +

        '.'

    );
}

function escapeHtml(value)
{
    const div =
        document.createElement(
            'div'
        );

    div.textContent =
        value ?? '';

    return div.innerHTML;
}
