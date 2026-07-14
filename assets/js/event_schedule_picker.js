/**
 * Event schedule: single | range | specific — all use the click calendar.
 */
(function (global) {
    'use strict';

    var pickerState = {};
    var escClickTimers = {};

    function pad2(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function toYmd(d) {
        return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
    }

    function todayYmd() {
        var t = new Date();
        return toYmd(new Date(t.getFullYear(), t.getMonth(), t.getDate()));
    }

    function parseYmd(ymd) {
        var p = String(ymd).split('-');
        if (p.length !== 3) {
            return null;
        }
        var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
        return isNaN(d.getTime()) ? null : d;
    }

    function datesBetweenInclusive(a, b) {
        if (a > b) {
            var t = a;
            a = b;
            b = t;
        }
        var start = parseYmd(a);
        var end = parseYmd(b);
        if (!start || !end) {
            return [];
        }
        var out = [];
        var cur = new Date(start.getTime());
        while (cur <= end) {
            out.push(toYmd(cur));
            cur.setDate(cur.getDate() + 1);
        }
        return out;
    }

    function getBlock(prefix) {
        return document.querySelector('.event-schedule-block[data-schedule-prefix="' + prefix + '"]');
    }

    function startDateInputId(prefix) {
        if (prefix === 'ce') {
            return 'ceDate';
        }
        if (prefix === 'standalone') {
            return 'date';
        }
        return prefix + 'Date';
    }

    function getMode(prefix) {
        var block = getBlock(prefix);
        if (!block) {
            return 'single';
        }
        var mode = block.querySelector('.js-schedule-mode:checked');
        return mode ? mode.value : 'single';
    }

    function usesPerDayEndTimes(mode, dayCount) {
        return (mode === 'specific' || mode === 'range') && dayCount >= 1;
    }

    function usesPerDayStartTimes(mode, dayCount) {
        if (mode === 'range') {
            return dayCount >= 2;
        }
        if (mode === 'specific') {
            return dayCount >= 2;
        }
        return false;
    }

    function globalStartTimeInputId(prefix) {
        if (prefix === 'ce') {
            return 'ceStartTime';
        }
        if (prefix === 'standalone') {
            return 'start_time';
        }
        return prefix + 'StartTime';
    }

    function globalStartTimeRowId(prefix) {
        if (prefix === 'ce') {
            return 'ceStartTimeRow';
        }
        if (prefix === 'standalone') {
            return 'standaloneStartTimeRow';
        }
        return prefix + 'StartTimeRow';
    }

    function getGlobalStartTimeValue(prefix) {
        var el = document.getElementById(globalStartTimeInputId(prefix));
        return el ? el.value : '';
    }

    function updateGlobalStartTimeRow(prefix) {
        var row = document.getElementById(globalStartTimeRowId(prefix));
        var state = pickerState[prefix];
        var mode = getMode(prefix);
        var count = state ? state.dates.length : 0;
        var perDay = usesPerDayStartTimes(mode, count);
        if (row) {
            row.style.display = perDay ? 'none' : '';
        }
        var input = document.getElementById(globalStartTimeInputId(prefix));
        if (input) {
            input.required = !perDay;
        }
    }

    function syncHiddenScheduleDates(prefix, dates) {
        var wrap = document.getElementById(prefix + 'ScheduleDatesHidden');
        if (!wrap) {
            return;
        }
        wrap.innerHTML = '';
        var mode = getMode(prefix);
        if (mode !== 'specific' && mode !== 'range') {
            return;
        }
        if (mode === 'range' && dates.length < 2) {
            return;
        }
        dates.slice().sort().forEach(function (ymd) {
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'schedule_dates[]';
            inp.value = ymd;
            wrap.appendChild(inp);
        });
    }

    function syncFormFields(prefix, dates) {
        var sorted = dates.slice().sort();
        var startEl = document.getElementById(startDateInputId(prefix));
        var endEl = document.getElementById(prefix + 'EndDate');
        var mode = getMode(prefix);

        if (startEl) {
            startEl.value = sorted.length ? sorted[0] : '';
        }

        if (endEl) {
            if (mode === 'range' && sorted.length > 1) {
                endEl.value = sorted[sorted.length - 1];
            } else {
                endEl.value = '';
            }
        }

        syncHiddenScheduleDates(prefix, sorted);
    }

    function formatLong(ymd) {
        var d = parseYmd(ymd);
        if (!d) {
            return ymd;
        }
        return d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
    }

    function updateSummary(prefix, dates) {
        var cal = document.getElementById(prefix + 'ScheduleClickCalendar');
        var hint = document.getElementById(prefix + 'ScheduleModeHint');
        var mode = getMode(prefix);
        var sorted = dates.slice().sort();

        var hintText = '';
        if (mode === 'single') {
            hintText = 'Click one day on the calendar for your event. Double-click a selected day to clear it.';
        } else if (mode === 'range') {
            hintText = 'Click the first day, then the last day. All days in between will be selected. Double-click any selected day to clear the range.';
        } else {
            hintText = 'Click each day your event runs. Click or double-click a selected day to remove it.';
        }
        if (hint) {
            hint.textContent = hintText;
        }

        if (!cal) {
            return;
        }
        var summary = cal.querySelector('.esc-summary');
        if (!summary) {
            summary = document.createElement('div');
            summary.className = 'esc-summary';
            cal.appendChild(summary);
        }

        if (sorted.length === 0) {
            summary.textContent = 'No days selected yet.';
            return;
        }

        if (mode === 'single') {
            summary.textContent = 'Event day: ' + formatLong(sorted[0]) + '.';
            return;
        }

        if (mode === 'range') {
            if (sorted.length === 1) {
                summary.textContent = 'Start: ' + formatLong(sorted[0]) + ' — now click the end day.';
                return;
            }
            summary.textContent = formatLong(sorted[0]) + ' – ' + formatLong(sorted[sorted.length - 1]) + ' (' + sorted.length + ' days).';
            return;
        }

        if (sorted.length === 1) {
            summary.textContent = '1 day selected: ' + formatLong(sorted[0]) + '.';
        } else {
            summary.textContent = sorted.length + ' days selected.';
        }
    }

    function dayEndKey(prefix, ymd) {
        return prefix + '_dayend_' + ymd.replace(/-/g, '');
    }

    function getDayEndState(state, ymd) {
        if (!state.dayEndTimes[ymd]) {
            state.dayEndTimes[ymd] = { mode: 'none', time: '' };
        }
        return state.dayEndTimes[ymd];
    }

    function getDayStartState(state, ymd) {
        if (!state.dayStartTimes[ymd]) {
            state.dayStartTimes[ymd] = '';
        }
        return state.dayStartTimes[ymd];
    }

    function renderCompactEndPickerHtml(opts) {
        var option = opts.option || 'none';
        var timeVal = opts.timeVal || '';
        var flat = !!opts.flat;
        var namePrefix = opts.namePrefix || 'end';
        var modeName = flat ? 'end_time_option' : (namePrefix + '[mode]');
        var timeName = flat ? 'end_time' : (namePrefix + '[time]');
        var idBase = flat ? 'evt_end' : namePrefix.replace(/[^a-z0-9]/gi, '_');
        var timeDisabled = option !== 'time' ? ' disabled' : '';

        return '<div class="esc-end-picker">' +
            '<div class="btn-group btn-group-sm esc-end-btn-group" role="group" aria-label="End time option">' +
            '<input class="btn-check js-end-mode" type="radio" name="' + modeName + '" id="' + idBase + '_none" value="none"' + (option === 'none' ? ' checked' : '') + '>' +
            '<label class="btn btn-outline-secondary" for="' + idBase + '_none">None</label>' +
            '<input class="btn-check js-end-mode" type="radio" name="' + modeName + '" id="' + idBase + '_time" value="time"' + (option === 'time' ? ' checked' : '') + '>' +
            '<label class="btn btn-outline-secondary" for="' + idBase + '_time">Set time</label>' +
            '<input class="btn-check js-end-mode" type="radio" name="' + modeName + '" id="' + idBase + '_na" value="na"' + (option === 'na' ? ' checked' : '') + '>' +
            '<label class="btn btn-outline-secondary" for="' + idBase + '_na">N/A</label>' +
            '</div>' +
            '<input type="time" class="form-control form-control-sm js-end-time-input esc-end-time-input" name="' + timeName + '" value="' + timeVal + '"' + timeDisabled + ' aria-label="End time">' +
            '</div>';
    }

    function renderDayScheduleCardHtml(ymd, dayStart, dayState, namePrefix) {
        var startName = 'schedule_day_start[' + ymd + ']';
        return '<article class="esc-day-card" data-day-schedule-block="' + ymd + '">' +
            '<header class="esc-day-card__date">' + formatLong(ymd) + '</header>' +
            '<div class="esc-day-card__body">' +
            '<div class="esc-day-card__field">' +
            '<span class="esc-field-label">Starts <span class="text-danger" aria-hidden="true">*</span></span>' +
            '<input type="time" class="form-control form-control-sm js-day-start-time" name="' + startName + '" value="' + (dayStart || '') + '" required aria-label="Start time for ' + formatLong(ymd) + '">' +
            '</div>' +
            '<div class="esc-day-card__field esc-day-card__field--end">' +
            '<span class="esc-field-label">Ends</span>' +
            renderCompactEndPickerHtml({
                namePrefix: namePrefix,
                option: dayState.mode,
                timeVal: dayState.time,
                flat: false
            }) +
            '</div>' +
            '</div>' +
            '</article>';
    }

    function renderEndTimeBlockHtml(opts) {
        var option = opts.option || 'none';
        var timeVal = opts.timeVal || '';
        var title = opts.title || 'End time';
        var hint = opts.hint || '';
        var flat = !!opts.flat;
        var namePrefix = opts.namePrefix || 'end';
        var modeName = flat ? 'end_time_option' : (namePrefix + '[mode]');
        var timeName = flat ? 'end_time' : (namePrefix + '[time]');
        var idBase = flat ? 'evt_end' : namePrefix.replace(/[^a-z0-9]/gi, '_');

        var wrapOpen = opts.noOuterWrap
            ? '<div class="event-end-time-block">'
            : '<div class="border rounded p-3 bg-light mb-2 event-end-time-block">';
        var html = wrapOpen;
        if (title) {
            html += '<div class="fw-semibold small mb-2">' + title + '</div>';
        }
        if (hint) {
            html += '<p class="text-muted small mb-2 mb-md-2">' + hint + '</p>';
        }
        html += '<div class="form-check">' +
            '<input class="form-check-input js-end-mode" type="radio" name="' + modeName + '" id="' + idBase + '_none" value="none"' + (option === 'none' ? ' checked' : '') + '>' +
            '<label class="form-check-label" for="' + idBase + '_none">No end time specified</label></div>' +
            '<div class="form-check d-flex flex-wrap align-items-center gap-2">' +
            '<input class="form-check-input js-end-mode" type="radio" name="' + modeName + '" id="' + idBase + '_time" value="time"' + (option === 'time' ? ' checked' : '') + '>' +
            '<label class="form-check-label" for="' + idBase + '_time">Set end time</label>' +
            '<input type="time" class="form-control form-control-sm js-end-time-input" style="max-width:140px;" name="' + timeName + '" value="' + timeVal + '"' + (option === 'time' ? '' : ' disabled') + '></div>' +
            '<div class="form-check">' +
            '<input class="form-check-input js-end-mode" type="radio" name="' + modeName + '" id="' + idBase + '_na" value="na"' + (option === 'na' ? ' checked' : '') + '>' +
            '<label class="form-check-label" for="' + idBase + '_na">Not applicable</label></div></div>';
        return html;
    }

    function bindEndTimeBlock(container) {
        var pickers = container.querySelectorAll('.esc-end-picker');
        if (!pickers.length) {
            pickers = [container];
        }
        pickers.forEach(function (picker) {
            var radios = picker.querySelectorAll('.js-end-mode');
            var timeInput = picker.querySelector('.js-end-time-input');
            function syncEndTimeInput() {
                if (!timeInput) {
                    return;
                }
                var useTime = false;
                radios.forEach(function (r) {
                    if (r.checked && r.value === 'time') {
                        useTime = true;
                    }
                });
                timeInput.disabled = !useTime;
                picker.classList.toggle('esc-end-picker--time-active', useTime);
                if (useTime) {
                    timeInput.focus();
                }
            }
            radios.forEach(function (radio) {
                radio.addEventListener('change', syncEndTimeInput);
            });
            syncEndTimeInput();
        });
    }

    function renderEndTimePanel(prefix) {
        var state = pickerState[prefix];
        var panel = document.getElementById(prefix + 'EndTimePanel');
        if (!state || !panel) {
            return;
        }

        var mode = getMode(prefix);
        var sorted = state.dates.slice().sort();
        panel.innerHTML = '';

        if (sorted.length === 0) {
            panel.innerHTML = '<p class="text-muted small mb-0">Select event day(s) on the calendar first, then set times for each day.</p>';
            updateGlobalStartTimeRow(prefix);
            return;
        }

        if (usesPerDayEndTimes(mode, sorted.length) && (mode !== 'range' || sorted.length >= 2)) {
            var rangeNote = mode === 'range'
                ? 'Set a start time for each day. End time is optional (use N/A if not relevant).'
                : 'Set a start time for each selected day. End time is optional (use N/A if not relevant).';
            panel.innerHTML =
                '<div class="esc-times-panel__head">' +
                '<h6 class="esc-times-panel__title mb-1">Times for each day</h6>' +
                '<p class="esc-times-panel__hint mb-0">' + rangeNote + '</p>' +
                '</div>';
            var list = document.createElement('div');
            list.className = 'esc-day-cards';
            sorted.forEach(function (ymd) {
                var dayState = getDayEndState(state, ymd);
                var dayStart = getDayStartState(state, ymd);
                var namePrefix = 'schedule_day_end[' + ymd + ']';
                var block = document.createElement('div');
                block.innerHTML = renderDayScheduleCardHtml(ymd, dayStart, dayState, namePrefix);
                var card = block.firstElementChild;
                if (card) {
                    list.appendChild(card);
                    bindEndTimeBlock(card);
                }
            });
            panel.appendChild(list);
            updateGlobalStartTimeRow(prefix);
            return;
        }

        var lastYmd = sorted[sorted.length - 1];
        var title = mode === 'single'
            ? 'End time — ' + formatLong(lastYmd)
            : 'End time on last day — ' + formatLong(lastYmd);
        var hint = mode === 'single'
            ? 'Optional. Use N/A if the event has no fixed end time.'
            : 'Optional for the last day of the range. Use N/A if not relevant.';
        panel.innerHTML =
            '<div class="esc-day-card esc-day-card--solo">' +
            '<header class="esc-day-card__date">' + title + '</header>' +
            '<div class="esc-day-card__body esc-day-card__body--solo">' +
            '<p class="esc-times-panel__hint mb-2">' + hint + '</p>' +
            renderCompactEndPickerHtml({
                option: state.lastDayEnd.mode,
                timeVal: state.lastDayEnd.time,
                flat: true
            }) +
            '</div></div>';
        bindEndTimeBlock(panel);
        updateGlobalStartTimeRow(prefix);
    }

    function readEndTimePanelIntoState(prefix) {
        var state = pickerState[prefix];
        var panel = document.getElementById(prefix + 'EndTimePanel');
        if (!state || !panel) {
            return;
        }
        var mode = getMode(prefix);
        var sorted = state.dates.slice().sort();

        if (usesPerDayEndTimes(mode, sorted.length) && (mode !== 'range' || sorted.length >= 2)) {
            sorted.forEach(function (ymd) {
                var namePrefix = 'schedule_day_end[' + ymd + ']';
                var selected = panel.querySelector('input[name="' + namePrefix + '[mode]"]:checked');
                var timeInput = panel.querySelector('input[name="' + namePrefix + '[time]"]');
                var m = selected ? selected.value : 'none';
                state.dayEndTimes[ymd] = {
                    mode: m,
                    time: timeInput && m === 'time' ? timeInput.value : ''
                };
                var startInput = panel.querySelector('input[name="schedule_day_start[' + ymd + ']"]');
                state.dayStartTimes[ymd] = startInput ? startInput.value : '';
            });
        } else {
            var selected = panel.querySelector('input[name="end_time_option"]:checked');
            var timeInput = panel.querySelector('input[name="end_time"]');
            var m = selected ? selected.value : 'none';
            state.lastDayEnd = {
                mode: m,
                time: timeInput && m === 'time' ? timeInput.value : ''
            };
        }
    }

    function applySelection(prefix, dates) {
        var state = pickerState[prefix];
        if (!state) {
            return;
        }
        state.dates = dates.slice().sort();
        var defaultStart = getGlobalStartTimeValue(prefix);
        dates.forEach(function (ymd) {
            if (!state.dayEndTimes[ymd]) {
                state.dayEndTimes[ymd] = { mode: 'none', time: '' };
            }
            if (!state.dayStartTimes[ymd] && defaultStart) {
                state.dayStartTimes[ymd] = defaultStart;
            }
        });
        Object.keys(state.dayEndTimes).forEach(function (ymd) {
            if (dates.indexOf(ymd) === -1) {
                delete state.dayEndTimes[ymd];
            }
        });
        Object.keys(state.dayStartTimes).forEach(function (ymd) {
            if (dates.indexOf(ymd) === -1) {
                delete state.dayStartTimes[ymd];
            }
        });
        syncFormFields(prefix, state.dates);
        updateSummary(prefix, state.dates);
        renderClickCalendar(prefix);
        renderEndTimePanel(prefix);
        updateGlobalStartTimeRow(prefix);
    }

    function handleDayClick(prefix, ymd) {
        var state = pickerState[prefix];
        if (!state || ymd < state.minDate) {
            return;
        }

        var mode = getMode(prefix);
        var dates = state.dates.slice();

        if (mode === 'single') {
            dates = [ymd];
            state.rangeAnchor = null;
        } else if (mode === 'range') {
            if (!state.rangeAnchor || dates.length === 0) {
                state.rangeAnchor = ymd;
                dates = [ymd];
            } else if (state.rangeAnchor === ymd) {
                dates = [ymd];
                state.rangeAnchor = null;
            } else {
                dates = datesBetweenInclusive(state.rangeAnchor, ymd);
                state.rangeAnchor = null;
            }
        } else {
            var idx = dates.indexOf(ymd);
            if (idx === -1) {
                dates.push(ymd);
            } else {
                dates.splice(idx, 1);
            }
            state.rangeAnchor = null;
        }

        applySelection(prefix, dates);
    }

    function handleDayDoubleClick(prefix, ymd) {
        var state = pickerState[prefix];
        if (!state || ymd < state.minDate) {
            return;
        }

        var dates = state.dates.slice();
        if (dates.indexOf(ymd) === -1) {
            return;
        }

        var mode = getMode(prefix);
        state.rangeAnchor = null;

        if (mode === 'specific') {
            dates.splice(dates.indexOf(ymd), 1);
            applySelection(prefix, dates);
            return;
        }

        applySelection(prefix, []);
    }

    function renderClickCalendar(prefix) {
        var state = pickerState[prefix];
        var container = document.getElementById(prefix + 'ScheduleClickCalendar');
        if (!state || !container) {
            return;
        }

        var view = state.viewMonth;
        var year = view.getFullYear();
        var month = view.getMonth();
        var first = new Date(year, month, 1);
        var startPad = first.getDay();
        var daysInMonth = new Date(year, month + 1, 0).getDate();
        var today = todayYmd();
        var mode = getMode(prefix);

        var monthLabel = first.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });

        var html = '<div class="esc-header">' +
            '<button type="button" class="esc-nav" data-esc-nav="prev" aria-label="Previous month">&lsaquo;</button>' +
            '<span class="esc-title">' + monthLabel + '</span>' +
            '<button type="button" class="esc-nav" data-esc-nav="next" aria-label="Next month">&rsaquo;</button>' +
            '</div>' +
            '<div class="esc-weekdays">' +
            ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(function (w) {
                return '<span>' + w + '</span>';
            }).join('') +
            '</div><div class="esc-grid">';

        var i;
        for (i = 0; i < startPad; i++) {
            html += '<span class="esc-day esc-day--outside" aria-hidden="true"></span>';
        }
        for (var day = 1; day <= daysInMonth; day++) {
            var ymd = year + '-' + pad2(month + 1) + '-' + pad2(day);
            var classes = ['esc-day'];
            var isPast = ymd < state.minDate;
            var isSelected = state.dates.indexOf(ymd) !== -1;
            var isAnchor = mode === 'range' && state.rangeAnchor === ymd;
            if (isSelected) {
                classes.push('esc-day--selected');
            }
            if (isAnchor && !isSelected) {
                classes.push('esc-day--anchor');
            }
            if (ymd === today) {
                classes.push('esc-day--today');
            }
            if (isPast) {
                classes.push('esc-day--past');
            }
            var label = new Date(year, month, day).toLocaleDateString(undefined, {
                weekday: 'long',
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            });
            if (isPast) {
                html += '<span class="' + classes.join(' ') + '" aria-label="' + label + ' (past)">' + day + '</span>';
            } else {
                html += '<button type="button" class="' + classes.join(' ') + '" data-esc-ymd="' + ymd + '" aria-label="' + label + (isSelected ? ', selected' : '') + '" aria-pressed="' + (isSelected ? 'true' : 'false') + '">' + day + '</button>';
            }
        }

        html += '</div>';
        container.innerHTML = html;

        var summary = container.querySelector('.esc-summary');
        if (!summary) {
            summary = document.createElement('div');
            summary.className = 'esc-summary';
            container.appendChild(summary);
        }
        updateSummary(prefix, state.dates);

        container.querySelectorAll('[data-esc-nav]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var dir = btn.getAttribute('data-esc-nav');
                if (dir === 'prev') {
                    state.viewMonth = new Date(year, month - 1, 1);
                } else {
                    state.viewMonth = new Date(year, month + 1, 1);
                }
                renderClickCalendar(prefix);
            });
        });

        container.querySelectorAll('[data-esc-ymd]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var ymd = btn.getAttribute('data-esc-ymd');
                var timerKey = prefix + ':' + ymd;
                clearTimeout(escClickTimers[timerKey]);
                escClickTimers[timerKey] = setTimeout(function () {
                    delete escClickTimers[timerKey];
                    handleDayClick(prefix, ymd);
                }, 220);
            });
            btn.addEventListener('dblclick', function (e) {
                e.preventDefault();
                var ymd = btn.getAttribute('data-esc-ymd');
                var timerKey = prefix + ':' + ymd;
                clearTimeout(escClickTimers[timerKey]);
                delete escClickTimers[timerKey];
                handleDayDoubleClick(prefix, ymd);
            });
        });
    }

    function normalizeDatesForMode(prefix, dates) {
        var mode = getMode(prefix);
        var sorted = dates.slice().sort();
        if (sorted.length === 0) {
            return [];
        }
        if (mode === 'single') {
            return [sorted[0]];
        }
        if (mode === 'range' && sorted.length >= 2) {
            return datesBetweenInclusive(sorted[0], sorted[sorted.length - 1]);
        }
        return sorted;
    }

    function onModeChange(prefix) {
        var state = pickerState[prefix];
        if (!state) {
            return;
        }
        state.rangeAnchor = null;
        readEndTimePanelIntoState(prefix);
        var next = normalizeDatesForMode(prefix, state.dates);
        applySelection(prefix, next);
    }

    function buildInitialDates(opts) {
        opts = opts || {};
        var dates = Array.isArray(opts.dates) ? opts.dates.slice() : [];
        var start = opts.startDate ? String(opts.startDate).slice(0, 10) : '';
        var end = opts.endDate ? String(opts.endDate).slice(0, 10) : '';

        if (dates.length > 0) {
            return dates;
        }
        if (start && end && end > start) {
            return datesBetweenInclusive(start, end);
        }
        if (start) {
            return [start];
        }
        return [];
    }

    function eventifyInitSchedulePicker(prefix, initialOpts) {
        var block = getBlock(prefix);
        if (!block) {
            return;
        }

        initialOpts = initialOpts || {};
        var dates = buildInitialDates(initialOpts);
        var viewMonth = new Date();
        if (dates.length > 0) {
            var first = parseYmd(dates[0]);
            if (first) {
                viewMonth = new Date(first.getFullYear(), first.getMonth(), 1);
            }
        }

        var dayEndTimes = {};
        var dayStartTimes = {};
        if (initialOpts.dayEndTimes && typeof initialOpts.dayEndTimes === 'object') {
            Object.keys(initialOpts.dayEndTimes).forEach(function (ymd) {
                var row = initialOpts.dayEndTimes[ymd] || {};
                dayEndTimes[ymd] = {
                    mode: row.mode || 'none',
                    time: row.time || ''
                };
            });
        }
        if (initialOpts.dayStartTimes && typeof initialOpts.dayStartTimes === 'object') {
            Object.keys(initialOpts.dayStartTimes).forEach(function (ymd) {
                dayStartTimes[ymd] = initialOpts.dayStartTimes[ymd] || '';
            });
        }

        pickerState[prefix] = {
            dates: dates,
            minDate: todayYmd(),
            viewMonth: viewMonth,
            rangeAnchor: null,
            lastDayEnd: {
                mode: initialOpts.endTimeOption || 'none',
                time: initialOpts.endTime || ''
            },
            dayEndTimes: dayEndTimes,
            dayStartTimes: dayStartTimes
        };

        dates = normalizeDatesForMode(prefix, dates);
        applySelection(prefix, dates);

        block.querySelectorAll('.js-schedule-mode').forEach(function (radio) {
            radio.addEventListener('change', function () {
                onModeChange(prefix);
            });
        });

        updateGlobalStartTimeRow(prefix);
    }

    function syncGlobalStartTimeFromDays(prefix) {
        var state = pickerState[prefix];
        if (!state) {
            return;
        }
        var mode = getMode(prefix);
        var sorted = state.dates.slice().sort();
        if (!usesPerDayStartTimes(mode, sorted.length)) {
            return;
        }
        var first = sorted[0];
        var st = state.dayStartTimes[first] || '';
        if (!st) {
            var panel = document.getElementById(prefix + 'EndTimePanel');
            if (panel && first) {
                var inp = panel.querySelector('input[name="schedule_day_start[' + first + ']"]');
                if (inp) {
                    st = inp.value;
                }
            }
        }
        var globalEl = document.getElementById(globalStartTimeInputId(prefix));
        if (globalEl && st) {
            globalEl.value = st;
        }
    }

    function eventifySyncScheduleBeforeSubmit(prefix) {
        var state = pickerState[prefix];
        if (!state) {
            return;
        }
        readEndTimePanelIntoState(prefix);
        var dates = normalizeDatesForMode(prefix, state.dates);
        state.dates = dates;
        syncFormFields(prefix, dates);
        syncHiddenScheduleDates(prefix, dates);
        syncGlobalStartTimeFromDays(prefix);
    }

    function eventifyValidateScheduleOnSubmit(prefix) {
        var block = getBlock(prefix);
        if (!block) {
            return true;
        }

        eventifySyncScheduleBeforeSubmit(prefix);

        var state = pickerState[prefix];
        var mode = getMode(prefix);
        var count = state ? state.dates.length : 0;
        var startEl = document.getElementById(startDateInputId(prefix));

        if (count < 1) {
            alert('Click at least one day on the calendar for your event.');
            return false;
        }

        if (mode === 'single' && count !== 1) {
            alert('Single-day events must have exactly one day selected. Click one day only.');
            return false;
        }

        readEndTimePanelIntoState(prefix);
        state = pickerState[prefix];
        var sorted = state.dates.slice().sort();

        if (mode === 'range') {
            if (count < 2) {
                alert('For a date range, click a start day and then an end day on the calendar.');
                return false;
            }
            var sorted = state.dates.slice().sort();
            var filled = datesBetweenInclusive(sorted[0], sorted[sorted.length - 1]);
            if (filled.length !== count) {
                alert('Date range must be consecutive days. Use “Specific days” to skip days in between.');
                return false;
            }
        }

        if (!startEl || !startEl.value) {
            alert('Please select an event day on the calendar.');
            return false;
        }

        if (startEl.value < todayYmd()) {
            alert('Event days cannot be in the past.');
            return false;
        }

        var startTimeEl = document.getElementById(globalStartTimeInputId(prefix));
        var startTime = startTimeEl ? startTimeEl.value : '';

        if (usesPerDayStartTimes(mode, sorted.length)) {
            var missingStart = false;
            var badRange = false;
            sorted.forEach(function (ymd) {
                var st = state.dayStartTimes[ymd] || '';
                var panel = document.getElementById(prefix + 'EndTimePanel');
                if (!st && panel) {
                    var inp = panel.querySelector('input[name="schedule_day_start[' + ymd + ']"]');
                    st = inp ? inp.value : '';
                }
                if (!st) {
                    missingStart = true;
                    return;
                }
                var dayEnd = state.dayEndTimes[ymd] || { mode: 'none', time: '' };
                if (dayEnd.mode === 'time' && dayEnd.time && dayEnd.time <= st) {
                    badRange = true;
                }
            });
            if (missingStart) {
                alert('Please set a start time for each event day.');
                return false;
            }
            if (badRange) {
                alert('End time must be after start time on each event day.');
                return false;
            }
            syncGlobalStartTimeFromDays(prefix);
            return true;
        }

        if (!startTime) {
            alert('Please select an event start time.');
            return false;
        }

        var lastEnd = state.lastDayEnd || { mode: 'none', time: '' };
        if (lastEnd.mode === 'time') {
            if (!lastEnd.time) {
                alert('Please enter an end time or choose Not applicable.');
                return false;
            }
            if (mode === 'single' && startTime && lastEnd.time <= startTime) {
                alert('End time must be after start time on the same day.');
                return false;
            }
        }

        return true;
    }

    global.eventifyInitSchedulePicker = eventifyInitSchedulePicker;
    global.eventifyValidateScheduleOnSubmit = eventifyValidateScheduleOnSubmit;
    global.eventifySyncScheduleBeforeSubmit = eventifySyncScheduleBeforeSubmit;
})(typeof window !== 'undefined' ? window : this);
