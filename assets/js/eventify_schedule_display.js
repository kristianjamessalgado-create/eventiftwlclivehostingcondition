/**
 * Structured schedule display for event detail modals (organizer, student, admin).
 */
(function (global) {
    'use strict';

    const DEFAULT_TIME_OPTS = { hour: 'numeric', minute: '2-digit', hour12: true };

    function formatTimeLabel(time, tOpts) {
        if (!time) {
            return '';
        }
        const t = new Date('1970-01-01T' + String(time).slice(0, 8));
        return isNaN(t.getTime()) ? '' : t.toLocaleTimeString(undefined, tOpts || DEFAULT_TIME_OPTS);
    }

    function formatDayLabel(ymd, withWeekday) {
        const d = new Date(ymd + 'T12:00:00');
        if (isNaN(d.getTime())) {
            return ymd;
        }
        const opts = withWeekday
            ? { weekday: 'short', month: 'short', day: 'numeric' }
            : { month: 'short', day: 'numeric' };
        return d.toLocaleDateString(undefined, opts);
    }

    function formatDayTimeRange(day, tOpts) {
        const start = day.start_time ? formatTimeLabel(day.start_time, tOpts) : '';
        if (day.end_time_na) {
            return start ? (start + ' – open') : 'Open all day';
        }
        const end = day.end_time ? formatTimeLabel(day.end_time, tOpts) : '';
        if (start && end) {
            return start + ' – ' + end;
        }
        if (start) {
            return 'From ' + start;
        }
        if (end) {
            return 'Until ' + end;
        }
        return 'All day';
    }

    function datesAreConsecutive(sortedDates) {
        if (sortedDates.length < 2) {
            return false;
        }
        for (let i = 1; i < sortedDates.length; i++) {
            const prev = new Date(sortedDates[i - 1] + 'T12:00:00');
            const nextYmd = sortedDates[i];
            prev.setDate(prev.getDate() + 1);
            const expected = prev.getFullYear() + '-'
                + String(prev.getMonth() + 1).padStart(2, '0') + '-'
                + String(prev.getDate()).padStart(2, '0');
            if (expected !== nextYmd) {
                return false;
            }
        }
        return true;
    }

    function buildSummaryLabel(sortedDates) {
        if (!sortedDates.length) {
            return '';
        }
        if (sortedDates.length === 1) {
            const d = new Date(sortedDates[0] + 'T12:00:00');
            return isNaN(d.getTime())
                ? sortedDates[0]
                : d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
        }

        const y = sortedDates[0].slice(0, 4);
        const sameYear = sortedDates.every(function (d) { return d.slice(0, 4) === y; });

        if (datesAreConsecutive(sortedDates)) {
            const first = formatDayLabel(sortedDates[0], false);
            const lastD = new Date(sortedDates[sortedDates.length - 1] + 'T12:00:00');
            const last = lastD.toLocaleDateString(undefined, sameYear
                ? { month: 'short', day: 'numeric', year: 'numeric' }
                : { year: 'numeric', month: 'short', day: 'numeric' });
            return first + ' – ' + last;
        }

        let out = sortedDates.map(function (ymd) { return formatDayLabel(ymd, false); }).join(', ');
        if (sameYear) {
            out += ', ' + y;
        }
        return out;
    }

    function fillDateRange(startYmd, endYmd) {
        const out = [];
        const cur = new Date(startYmd + 'T12:00:00');
        const end = new Date(endYmd + 'T12:00:00');
        if (isNaN(cur.getTime()) || isNaN(end.getTime())) {
            return [startYmd];
        }
        while (cur <= end) {
            out.push(
                cur.getFullYear() + '-'
                + String(cur.getMonth() + 1).padStart(2, '0') + '-'
                + String(cur.getDate()).padStart(2, '0')
            );
            cur.setDate(cur.getDate() + 1);
        }
        return out.length ? out : [startYmd];
    }

    function normalizeScheduleDays(props) {
        const raw = Array.isArray(props.schedule_days) ? props.schedule_days : [];
        const mapped = raw
            .map(function (d) {
                return {
                    schedule_date: String(d.schedule_date || '').slice(0, 10),
                    start_time: d.start_time || null,
                    end_time: d.end_time || null,
                    end_time_na: !!d.end_time_na
                };
            })
            .filter(function (d) { return d.schedule_date; })
            .sort(function (a, b) { return a.schedule_date.localeCompare(b.schedule_date); });

        if (mapped.length) {
            return mapped;
        }

        let dates = Array.isArray(props.schedule_dates) ? props.schedule_dates.filter(Boolean) : [];
        const startYmd = String(props.event_date_ymd || '').trim();
        const endYmd = String(props.event_end_ymd || props.event_date_ymd || '').trim();

        if (!dates.length && startYmd) {
            dates = endYmd > startYmd ? fillDateRange(startYmd, endYmd) : [startYmd];
        }

        return dates.map(function (ymd) {
            return {
                schedule_date: ymd,
                start_time: props.start_time || null,
                end_time: props.end_time || null,
                end_time_na: !!props.end_time_na
            };
        });
    }

    function timeRangeKey(day) {
        return String(day.start_time || '').slice(0, 8) + '|' + (day.end_time_na ? 'na' : String(day.end_time || '').slice(0, 8));
    }

    function allDaysSameTimeRange(days) {
        if (days.length < 2) {
            return true;
        }
        const key = timeRangeKey(days[0]);
        return days.every(function (d) { return timeRangeKey(d) === key; });
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function buildEventScheduleDisplayHtml(props, fallback) {
        fallback = fallback || {};
        const tOpts = DEFAULT_TIME_OPTS;
        const days = normalizeScheduleDays(props);

        if (!days.length) {
            if (fallback.start instanceof Date && !isNaN(fallback.start.getTime())) {
                const dOpts = { year: 'numeric', month: 'short', day: 'numeric' };
                let text = fallback.start.toLocaleDateString(undefined, dOpts);
                if (!fallback.allDay) {
                    const startTime = fallback.start.toLocaleTimeString(undefined, tOpts);
                    if (fallback.end instanceof Date && !isNaN(fallback.end.getTime())) {
                        text += ' · ' + startTime + ' – ' + fallback.end.toLocaleTimeString(undefined, tOpts);
                    } else {
                        text += ' · ' + startTime;
                    }
                }
                return '<div class="efy-event-schedule"><div class="efy-event-schedule__summary">' + escapeHtml(text) + '</div></div>';
            }
            const plain = String(fallback.startStr || '').trim();
            return '<div class="efy-event-schedule"><div class="efy-event-schedule__summary">' + escapeHtml(plain || 'TBA') + '</div></div>';
        }

        const sortedDates = days.map(function (d) { return d.schedule_date; });
        const summary = buildSummaryLabel(sortedDates);
        const uniform = allDaysSameTimeRange(days);
        const uniformRange = uniform ? formatDayTimeRange(days[0], tOpts) : '';

        let html = '<div class="efy-event-schedule">';
        html += '<div class="efy-event-schedule__summary">' + escapeHtml(summary) + '</div>';

        if (uniform && days.length >= 2) {
            html += '<div class="efy-event-schedule__uniform"><i class="fas fa-clock" aria-hidden="true"></i><span>Each day · ' + escapeHtml(uniformRange) + '</span></div>';
        } else if (uniform && days.length === 1) {
            html += '<div class="efy-event-schedule__uniform"><i class="fas fa-clock" aria-hidden="true"></i><span>' + escapeHtml(uniformRange) + '</span></div>';
        } else {
            html += '<ul class="efy-event-schedule__days">';
            days.forEach(function (day) {
                html += '<li><span class="efy-event-schedule__day">' + escapeHtml(formatDayLabel(day.schedule_date, true)) + '</span>';
                html += '<span class="efy-event-schedule__time">' + escapeHtml(formatDayTimeRange(day, tOpts)) + '</span></li>';
            });
            html += '</ul>';
        }

        html += '</div>';
        return html;
    }

    function renderEventScheduleInto(el, props, fallback) {
        if (!el) {
            return;
        }
        el.innerHTML = buildEventScheduleDisplayHtml(props, fallback);
    }

    global.eventifyBuildEventScheduleDisplayHtml = buildEventScheduleDisplayHtml;
    global.eventifyRenderEventScheduleInto = renderEventScheduleInto;
}(typeof window !== 'undefined' ? window : this));
