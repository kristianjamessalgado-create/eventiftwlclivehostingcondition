/**
 * Calendar event block colors by approval + schedule state.
 * Keep in sync with views/partials/calendar_event_state_legend.php and calendar_legend.css
 */
(function (global) {
    'use strict';

    var COLORS = {
        pending: '#d97706',
        upcoming: '#f59e0b',
        active: '#16a34a',
        closed: '#6b7280',
        rejected: '#dc2626'
    };

    function eventifyStartOfDay(date) {
        var d = new Date(date.getTime());
        d.setHours(0, 0, 0, 0);
        return d;
    }

    function eventifyFormatYmd(date) {
        var y = date.getFullYear();
        var m = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }

    function eventifyParseYmdDate(ymd) {
        if (!ymd) {
            return null;
        }
        var parts = String(ymd).trim().split('-');
        if (parts.length !== 3) {
            return null;
        }
        var y = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10) - 1;
        var day = parseInt(parts[2], 10);
        if (isNaN(y) || isNaN(m) || isNaN(day)) {
            return null;
        }
        return new Date(y, m, day);
    }

    /**
     * Y-m-d for the calendar column this event block is rendered in.
     * @param {HTMLElement|null} el
     * @returns {string|null}
     */
    function eventifyGetSegmentYmdFromEl(el) {
        if (!el) {
            return null;
        }
        var dayCell = el.closest('.fc-daygrid-day[data-date], .fc-timegrid-col[data-date], td[data-date]');
        if (dayCell) {
            var fromCell = dayCell.getAttribute('data-date');
            if (fromCell) {
                return String(fromCell).slice(0, 10);
            }
        }
        var node = el;
        while (node && node !== document.body) {
            var ymd = (node.dataset && node.dataset.date)
                || (node.getAttribute && node.getAttribute('data-date'));
            if (ymd) {
                return String(ymd).slice(0, 10);
            }
            node = node.parentElement;
        }
        return null;
    }

    /**
     * @param {{ el?: HTMLElement }} info
     * @returns {Date|null}
     */
    function eventifyGetCalendarMountDayDate(info) {
        if (!info || !info.el) {
            return null;
        }
        var ymd = eventifyGetSegmentYmdFromEl(info.el);
        return ymd ? eventifyParseYmdDate(ymd) : null;
    }

    /**
     * Lifecycle color for one calendar column (today = green, future = gold, past = gray).
     * @param {string|null} segmentYmd
     * @param {{ allDay?: boolean, start?: Date|null }} [eventInfo]
     * @returns {'upcoming'|'active'|'closed'}
     */
    function eventifyStateFromSegmentYmd(segmentYmd, eventInfo) {
        var day = segmentYmd ? eventifyParseYmdDate(segmentYmd) : null;
        if (!day) {
            return 'active';
        }
        var today = eventifyStartOfDay(new Date());
        var d = eventifyStartOfDay(day);
        if (d.getTime() > today.getTime()) {
            return 'upcoming';
        }
        if (d.getTime() < today.getTime()) {
            return 'closed';
        }
        return 'active';
    }

    function eventifyIsApprovedActiveStatus(status) {
        var s = String(status || 'active').toLowerCase().trim();
        return s === 'active' || s === '';
    }

    /**
     * @param {string} status DB approval status
     * @param {string|null} segmentYmd Y-m-d for this calendar column (today → green when active)
     * @param {{ allDay?: boolean, start?: Date|null }} [eventInfo]
     * @param {HTMLElement|null} [el]
     * @returns {'pending'|'upcoming'|'active'|'closed'|'rejected'}
     */
    function eventifyResolveCalendarEventState(status, segmentYmd, eventInfo, el) {
        var s = String(status || '').toLowerCase().trim();
        if (s === 'rejected') {
            return 'rejected';
        }
        if (s === 'pending') {
            return 'pending';
        }
        if (s === 'closed' || s === 'completed') {
            return 'closed';
        }

        if (eventifyIsApprovedActiveStatus(s)) {
            var ymd = segmentYmd || (el ? eventifyGetSegmentYmdFromEl(el) : null);
            if (ymd) {
                return eventifyStateFromSegmentYmd(ymd, eventInfo);
            }
            if (el) {
                if (el.classList.contains('fc-event-today')) {
                    return 'active';
                }
                if (el.classList.contains('fc-event-future')) {
                    return 'upcoming';
                }
                if (el.classList.contains('fc-event-past')) {
                    return 'closed';
                }
            }
        }

        return 'active';
    }

    /**
     * Connected multi-day bar: gradient across days (gray / green / gold per column).
     * @param {HTMLElement} el
     * @param {string} approvalStatus
     * @param {string[]} segmentDates
     * @param {{ allDay?: boolean, start?: Date|null }} [eventInfo]
     * @returns {boolean}
     */
    function eventifyPaintRangeEventGradient(el, approvalStatus, segmentDates, eventInfo) {
        if (!el || !segmentDates || segmentDates.length < 2) {
            return false;
        }
        var states = segmentDates.map(function (ymd) {
            return eventifyResolveCalendarEventState(approvalStatus, ymd, eventInfo, null);
        });
        var allSame = states.every(function (s) { return s === states[0]; });
        el.setAttribute('data-eventify-range-multiday', '1');
        el.setAttribute('data-eventify-segment-dates', segmentDates.join(','));
        el.setAttribute('data-eventify-approval-status', String(approvalStatus || 'active').toLowerCase());
        el.removeAttribute('data-eventify-color-locked');
        el.removeAttribute('data-dept');
        if (allSame) {
            el.removeAttribute('data-eventify-range-gradient');
            var solid = COLORS[states[0]] || COLORS.active;
            el.setAttribute('data-event-state', states[0]);
            el.style.setProperty('background', solid, 'important');
            el.style.setProperty('background-color', solid, 'important');
            el.style.setProperty('border-color', solid, 'important');
            el.style.setProperty('color', '#ffffff', 'important');
            return true;
        }
        el.setAttribute('data-eventify-range-gradient', '1');
        el.setAttribute('data-event-state', 'range');
        var n = states.length;
        var parts = [];
        for (var i = 0; i < n; i++) {
            var color = COLORS[states[i]] || COLORS.active;
            var pctStart = ((i / n) * 100).toFixed(4);
            var pctEnd = (((i + 1) / n) * 100).toFixed(4);
            parts.push(color + ' ' + pctStart + '% ' + pctEnd + '%');
        }
        var gradient = 'linear-gradient(to right, ' + parts.join(', ') + ')';
        el.style.setProperty('background', gradient, 'important');
        el.style.setProperty('background-color', 'transparent', 'important');
        el.style.setProperty('border-color', 'transparent', 'important');
        el.style.setProperty('color', '#ffffff', 'important');
        return true;
    }

    function eventifyRepaintRangeEventGradient(el) {
        if (!el || el.getAttribute('data-eventify-range-multiday') !== '1') {
            return;
        }
        var datesStr = el.getAttribute('data-eventify-segment-dates');
        if (!datesStr) {
            return;
        }
        var segmentDates = datesStr.split(',').map(function (d) { return d.trim(); }).filter(Boolean);
        var approval = el.getAttribute('data-eventify-approval-status') || 'active';
        var startMs = parseInt(el.getAttribute('data-eventify-start-ms') || '', 10);
        var start = !isNaN(startMs) ? new Date(startMs) : null;
        var allDay = el.getAttribute('data-eventify-all-day') === '1'
            || el.classList.contains('fc-daygrid-block-event');
        eventifyPaintRangeEventGradient(el, approval, segmentDates, { allDay: allDay, start: start });
    }

    function eventifyPaintCalendarEventEl(el, approvalStatus, eventInfo, segmentYmd, options) {
        if (!el) {
            return;
        }
        options = options || {};
        var ymd = segmentYmd || eventifyGetSegmentYmdFromEl(el);
        var state = options.lockedState || eventifyResolveCalendarEventState(
            approvalStatus,
            ymd,
            eventInfo,
            el
        );
        el.setAttribute('data-eventify-approval-status', String(approvalStatus || 'active').toLowerCase());
        if (ymd) {
            el.setAttribute('data-eventify-segment-ymd', ymd);
        }
        el.setAttribute('data-event-state', state);
        el.removeAttribute('data-dept');
        if (options.colorLocked) {
            el.setAttribute('data-eventify-color-locked', '1');
            if (options.lockedState) {
                el.setAttribute('data-eventify-segment-state', options.lockedState);
            }
            return;
        }
        el.removeAttribute('data-eventify-color-locked');
        el.removeAttribute('data-eventify-segment-state');
        var bg = COLORS[state] || COLORS.active;
        el.style.setProperty('background-color', bg, 'important');
        el.style.setProperty('border-color', bg, 'important');
        el.style.setProperty('color', '#ffffff', 'important');
    }

    /**
     * Repaint every month-view block using its day column (fixes multi-day bar segments).
     * @param {HTMLElement|string|null} root
     */
    function eventifyRepaintCalendarEventSegments(root) {
        var container = root;
        if (typeof root === 'string') {
            container = document.querySelector(root);
        }
        if (!container) {
            container = document.getElementById('calendar') || document.getElementById('student-calendar');
        }
        if (!container) {
            return;
        }
        container.querySelectorAll('[data-eventify-range-multiday="1"]').forEach(function (el) {
            eventifyRepaintRangeEventGradient(el);
        });
        container.querySelectorAll('.fc-daygrid-day[data-date]').forEach(function (dayCell) {
            var ymd = dayCell.getAttribute('data-date');
            if (!ymd) {
                return;
            }
            dayCell.querySelectorAll('.fc-daygrid-event').forEach(function (el) {
                if (el.getAttribute('data-eventify-range-multiday') === '1') {
                    return;
                }
                if (el.getAttribute('data-eventify-color-locked') === '1') {
                    var lockedState = el.getAttribute('data-eventify-segment-state');
                    if (lockedState) {
                        el.setAttribute('data-event-state', lockedState);
                    }
                    return;
                }
                var approval = el.getAttribute('data-eventify-approval-status')
                    || el.getAttribute('data-eventify-status')
                    || 'active';
                var startMs = parseInt(el.getAttribute('data-eventify-start-ms') || '', 10);
                var start = !isNaN(startMs) ? new Date(startMs) : null;
                var allDay = el.getAttribute('data-eventify-all-day') === '1'
                    || el.classList.contains('fc-daygrid-block-event');
                eventifyPaintCalendarEventEl(el, approval, { allDay: allDay, start: start }, ymd.slice(0, 10));
            });
        });
    }

    function eventifyApplyCalendarEventMount(info) {
        if (!info || !info.el || !info.event) {
            return;
        }
        try {
            var props = info.event.extendedProps || {};
            var approvalStatus = String(props.status || 'active').toLowerCase();
            var eventStart = info.event.start instanceof Date ? info.event.start : null;
            info.el.setAttribute('data-eventify-approval-status', approvalStatus);
            info.el.setAttribute('data-eventify-all-day', info.event.allDay ? '1' : '0');
            if (eventStart && !isNaN(eventStart.getTime())) {
                info.el.setAttribute('data-eventify-start-ms', String(eventStart.getTime()));
            }
            if (props.event_id) {
                info.el.setAttribute('data-eventify-range-id', String(props.event_id));
            }

            if (props.calendar_range_multiday && Array.isArray(props.segment_dates) && props.segment_dates.length >= 2) {
                info.el.classList.add('eventify-fc-range-event');
                eventifyPaintRangeEventGradient(info.el, approvalStatus, props.segment_dates, {
                    allDay: info.event.allDay,
                    start: eventStart
                });
                return;
            }

            var colorLocked = props.calendar_color_locked === true;
            var lockedState = props.calendar_segment_state ? String(props.calendar_segment_state) : null;
            var segmentYmd = props.schedule_date_ymd
                ? String(props.schedule_date_ymd).slice(0, 10)
                : eventifyGetSegmentYmdFromEl(info.el);
            eventifyPaintCalendarEventEl(info.el, approvalStatus, {
                allDay: info.event.allDay,
                start: eventStart
            }, segmentYmd, {
                colorLocked: colorLocked,
                lockedState: lockedState
            });
        } catch (e) {
            /* keep calendar rendering if one block fails */
        }
    }

    /**
     * @param {import('fullcalendar').Calendar} calendar
     * @param {HTMLElement|string|null} calendarRoot
     */
    function eventifyBindCalendarSegmentRepaint(calendar, calendarRoot) {
        if (!calendar) {
            return;
        }
        var root = calendarRoot;
        if (typeof root === 'string') {
            root = document.querySelector(root);
        }
        var repaint = function () {
            eventifyRepaintCalendarEventSegments(root);
        };
        calendar.on('eventsSet', function () {
            requestAnimationFrame(repaint);
            setTimeout(repaint, 40);
        });
        calendar.on('datesSet', function () {
            requestAnimationFrame(repaint);
            setTimeout(repaint, 40);
        });
        requestAnimationFrame(repaint);
        setTimeout(repaint, 120);
    }

    /**
     * Keep FullCalendar sized to its container so events stay in day cells while scrolling.
     * @param {import('fullcalendar').Calendar} calendar
     * @param {HTMLElement|null} containerEl usually .calendar-container
     */
    function eventifyBindCalendarScrollFix(calendar, containerEl) {
        if (!calendar || !containerEl) {
            return;
        }
        var refresh = function () {
            try {
                calendar.updateSize();
            } catch (e) { /* ignore */ }
        };
        requestAnimationFrame(refresh);
        setTimeout(refresh, 60);
        setTimeout(refresh, 280);
        if (!containerEl.dataset.eventifyResizeBound) {
            containerEl.dataset.eventifyResizeBound = '1';
            window.addEventListener('resize', refresh);
            if (typeof ResizeObserver !== 'undefined') {
                var ro = new ResizeObserver(refresh);
                ro.observe(containerEl);
            }
        }
    }

    /** Close FullCalendar "+more" day popovers so they do not sit above Bootstrap modals. */
    function eventifyCloseFullCalendarPopovers() {
        document.querySelectorAll('.fc-popover').forEach(function (el) {
            el.remove();
        });
    }

    function eventifyEscapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /**
     * Compact week/day blocks — one line, no cramped side-by-side overlap text.
     * @returns {true|{html: string}} true = default FullCalendar render
     */
    function eventifyCalendarEventContent(arg) {
        var viewType = arg.view && arg.view.type ? arg.view.type : '';
        if (viewType.indexOf('timeGrid') !== 0) {
            return true;
        }
        var title = eventifyEscapeHtml(arg.event.title || 'Event');
        var timeText = arg.timeText ? eventifyEscapeHtml(arg.timeText) : '';
        var line = timeText ? timeText + ' · ' + title : title;
        return {
            html: '<div class="eventify-fc-compact" title="' + line + '">' + line + '</div>'
        };
    }

    /**
     * Double-click a highlighted (selected) day cell to clear selection back to default white.
     * @param {import('fullcalendar').Calendar} calendar
     * @param {HTMLElement|null} calendarEl
     * @param {{ onClear?: function(): void, clearPendingClicks?: function(): void }} [options]
     */
    function eventifyBindCalendarDoubleClickUnselect(calendar, calendarEl, options) {
        if (!calendar || !calendarEl) {
            return;
        }
        options = options || {};
        calendarEl.addEventListener('dblclick', function (e) {
            if (e.target.closest('.fc-event, .fc-more-link')) {
                return;
            }
            var dayCell = e.target.closest('.fc-daygrid-day, .fc-timegrid-col');
            if (!dayCell) {
                return;
            }
            var highlighted = dayCell.querySelector('.fc-highlight');
            if (!highlighted && !dayCell.classList.contains('fc-day-selected')) {
                return;
            }
            e.preventDefault();
            if (typeof options.clearPendingClicks === 'function') {
                options.clearPendingClicks();
            }
            try {
                calendar.unselect();
            } catch (err) { /* ignore */ }
            if (typeof options.onClear === 'function') {
                options.onClear();
            }
        });
    }

    global.eventifyResolveCalendarEventState = eventifyResolveCalendarEventState;
    global.eventifyGetCalendarMountDayDate = eventifyGetCalendarMountDayDate;
    global.eventifyGetSegmentYmdFromEl = eventifyGetSegmentYmdFromEl;
    global.eventifyApplyCalendarEventMount = eventifyApplyCalendarEventMount;
    global.eventifyRepaintCalendarEventSegments = eventifyRepaintCalendarEventSegments;
    global.eventifyBindCalendarSegmentRepaint = eventifyBindCalendarSegmentRepaint;
    global.eventifyBindCalendarScrollFix = eventifyBindCalendarScrollFix;
    global.eventifyBindCalendarDoubleClickUnselect = eventifyBindCalendarDoubleClickUnselect;
    global.eventifyCloseFullCalendarPopovers = eventifyCloseFullCalendarPopovers;
    global.eventifyCalendarEventContent = eventifyCalendarEventContent;
    global.eventifyCalendarEventColors = COLORS;
})(typeof window !== 'undefined' ? window : this);
