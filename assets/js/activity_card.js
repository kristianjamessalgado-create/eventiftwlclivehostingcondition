(function () {
    var printBtn = document.getElementById('acPrintBtn');
    if (printBtn) {
        printBtn.addEventListener('click', function () {
            window.print();
        });
    }

    var bar = document.getElementById('acFilterBar');
    var body = document.getElementById('acCardBody');
    if (!bar || !body) {
        return;
    }

    var kindChips = bar.querySelectorAll('[data-ac-kind]');
    var formatChips = bar.querySelectorAll('[data-ac-format]');
    var dateSelect = document.getElementById('acFilterDate');
    var eventSelect = document.getElementById('acFilterEvent');
    var activitySelect = document.getElementById('acFilterActivity');
    var summary = document.getElementById('acFilterSummary');
    var clearBtn = document.getElementById('acFilterClear');
    var emptyMsg = document.getElementById('acFilterEmpty');
    var formatLog = document.getElementById('acFormatLog');
    var formatDaily = document.getElementById('acFormatDaily');
    var dailySheets = document.getElementById('acDailySheets');
    var dataRows = Array.prototype.slice.call(body.querySelectorAll('tr.ac-row--data'));
    var blankRows = Array.prototype.slice.call(body.querySelectorAll('tr.ac-row--blank'));
    var total = parseInt(bar.getAttribute('data-total') || String(dataRows.length), 10) || dataRows.length;
    var totalDays = 0;
    (function () {
        var dates = {};
        dataRows.forEach(function (row) {
            var ymd = row.getAttribute('data-date') || '';
            if (ymd !== '') {
                dates[ymd] = true;
            }
        });
        totalDays = Object.keys(dates).length;
    })();
    var kind = 'all';
    var format = 'log';

    function currentFilters() {
        return {
            kind: kind,
            date: dateSelect ? String(dateSelect.value || '') : '',
            event: eventSelect ? String(eventSelect.value || '') : '',
            activity: activitySelect ? String(activitySelect.value || '') : ''
        };
    }

    function isActive(filters) {
        return filters.kind !== 'all' || filters.date !== '' || filters.event !== '' || filters.activity !== '';
    }

    function rowMatches(row, filters) {
        var rowKind = row.getAttribute('data-kind') || '';
        var rowDate = row.getAttribute('data-date') || '';
        var rowEvent = row.getAttribute('data-event') || '';
        var rowActivity = row.getAttribute('data-activity') || '';
        if (filters.activity !== '' && rowActivity !== filters.activity) {
            return false;
        }
        if (filters.event !== '' && rowEvent !== filters.event) {
            return false;
        }
        if (filters.date !== '' && rowDate !== filters.date) {
            return false;
        }
        if (filters.kind === 'checked_in' && (row.getAttribute('data-checked-in') || '') !== '1') {
            return false;
        }
        if (filters.activity === '') {
            if (filters.kind === 'events' && rowKind !== 'event') {
                return false;
            }
            if (filters.kind === 'activities' && rowKind !== 'activity') {
                return false;
            }
        }
        return true;
    }

    function pickTimes(rows, period) {
        var inMins = -1;
        var outMins = -1;
        var inText = '';
        var outText = '';
        rows.forEach(function (row) {
            if ((row.getAttribute('data-period') || '') !== period) {
                return;
            }
            var tin = parseInt(row.getAttribute('data-time-in-mins') || '-1', 10);
            var tout = parseInt(row.getAttribute('data-time-out-mins') || '-1', 10);
            var tinText = row.getAttribute('data-time-in-text') || '';
            var toutText = row.getAttribute('data-time-out-text') || '';
            if (tin >= 0 && (inMins < 0 || tin < inMins)) {
                inMins = tin;
                inText = tinText;
            }
            if (tout >= 0 && tout > outMins) {
                outMins = tout;
                outText = toutText;
            }
        });
        return { inText: inText, outText: outText };
    }

    function uniqueTitles(rows) {
        var seen = {};
        var titles = [];
        rows.forEach(function (row) {
            var title = (row.getAttribute('data-event-title') || '').trim();
            if (title === '' || seen[title]) {
                return;
            }
            seen[title] = true;
            titles.push(title);
        });
        return titles;
    }

    function addCell(tr, text) {
        var td = document.createElement('td');
        td.className = 'ac-td-center';
        td.textContent = text || '';
        tr.appendChild(td);
        return td;
    }

    function formatStampDate(value) {
        if (!value) {
            return '';
        }
        var d = new Date(String(value).replace(' ', 'T'));
        if (isNaN(d.getTime())) {
            return '';
        }
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }

    /** Stamp info for a period: every checked-in row in it must be stamped. */
    function periodStamp(rows, period) {
        var checked = rows.filter(function (row) {
            return (row.getAttribute('data-period') || '') === period
                && (row.getAttribute('data-checked-in') || '') === '1';
        });
        if (!checked.length) {
            return null;
        }
        var latest = null;
        for (var i = 0; i < checked.length; i++) {
            if ((checked[i].getAttribute('data-stamped') || '') !== '1') {
                return null;
            }
            var at = checked[i].getAttribute('data-stamp-at') || '';
            if (!latest || at > latest.at) {
                latest = { by: checked[i].getAttribute('data-stamp-by') || '', at: at };
            }
        }
        return latest;
    }

    function buildStamp(stamp) {
        var wrap = document.createElement('span');
        wrap.className = 'ac-stamp';
        var dateText = formatStampDate(stamp.at);
        wrap.title = 'Digitally stamped' + (stamp.by ? ' by ' + stamp.by : '') + (dateText ? ' on ' + dateText : '');
        var top = document.createElement('span');
        top.className = 'ac-stamp__top';
        top.innerHTML = '<i class="fas fa-circle-check" aria-hidden="true"></i> Verified';
        var name = document.createElement('span');
        name.className = 'ac-stamp__name';
        name.textContent = stamp.by || 'Teacher';
        wrap.appendChild(top);
        wrap.appendChild(name);
        if (dateText) {
            var date = document.createElement('span');
            date.className = 'ac-stamp__date';
            date.textContent = dateText;
            wrap.appendChild(date);
        }
        return wrap;
    }

    function addSignCell(tr, stamp) {
        var td = document.createElement('td');
        td.className = 'ac-td-sign';
        if (stamp) {
            td.appendChild(buildStamp(stamp));
        } else {
            var line = document.createElement('span');
            line.className = 'ac-sign-line';
            td.appendChild(line);
        }
        tr.appendChild(td);
    }

    function uniqueActivities(rows) {
        var seen = {};
        var titles = [];
        rows.forEach(function (row) {
            var title = (row.getAttribute('data-activity-title') || '').trim();
            if (title === '' || seen[title]) {
                return;
            }
            seen[title] = true;
            titles.push(title);
        });
        return titles;
    }

    function buildDaySheet(ymd, label, rows) {
        var morning = pickTimes(rows, 'morning');
        var afternoon = pickTimes(rows, 'afternoon');
        var titles = uniqueTitles(rows);

        var sheet = document.createElement('section');
        sheet.className = 'ac-day';

        var dateRow = document.createElement('div');
        dateRow.className = 'ac-field ac-day__date';
        var dateLabel = document.createElement('span');
        dateLabel.className = 'ac-field__label';
        dateLabel.textContent = 'Date:';
        var dateValue = document.createElement('span');
        dateValue.className = 'ac-field__value';
        dateValue.textContent = label || ymd;
        dateRow.appendChild(dateLabel);
        dateRow.appendChild(dateValue);
        sheet.appendChild(dateRow);

        if (titles.length) {
            var events = document.createElement('p');
            events.className = 'ac-day__events';
            events.innerHTML = '<strong>Event:</strong> ';
            events.appendChild(document.createTextNode(titles.join(' · ')));
            sheet.appendChild(events);
        }
        var activities = uniqueActivities(rows);
        if (activities.length) {
            var acts = document.createElement('p');
            acts.className = 'ac-day__events ac-day__activities';
            acts.innerHTML = '<strong>Activities:</strong> ';
            acts.appendChild(document.createTextNode(activities.join(' · ')));
            sheet.appendChild(acts);
        }

        var wrap = document.createElement('div');
        wrap.className = 'ac-table-wrap';
        var table = document.createElement('table');
        table.className = 'ac-table ac-table--daily';
        table.innerHTML = '<thead>'
            + '<tr><th colspan="3">Morning</th><th colspan="3">Afternoon</th></tr>'
            + '<tr><th>Time in</th><th>Time out</th><th>Signature</th>'
            + '<th>Time in</th><th>Time out</th><th>Signature</th></tr>'
            + '</thead>';
        var tbody = document.createElement('tbody');
        var tr = document.createElement('tr');
        addCell(tr, morning.inText);
        addCell(tr, morning.outText);
        addSignCell(tr, periodStamp(rows, 'morning'));
        addCell(tr, afternoon.inText);
        addCell(tr, afternoon.outText);
        addSignCell(tr, periodStamp(rows, 'afternoon'));
        tbody.appendChild(tr);
        table.appendChild(tbody);
        wrap.appendChild(table);
        sheet.appendChild(wrap);
        return sheet;
    }

    function buildBlankDaySheet() {
        return buildDaySheet('', '', []);
    }

    function rebuildDaily(visibleRows, filters) {
        if (!dailySheets) {
            return 0;
        }
        dailySheets.innerHTML = '';
        var groups = {};
        var order = [];
        visibleRows.forEach(function (row) {
            var ymd = row.getAttribute('data-date') || '';
            if (ymd === '') {
                return;
            }
            if (!groups[ymd]) {
                groups[ymd] = [];
                order.push(ymd);
            }
            groups[ymd].push(row);
        });
        order.sort();
        order.forEach(function (ymd) {
            var first = groups[ymd][0];
            var label = first.getAttribute('data-date-label') || ymd;
            dailySheets.appendChild(buildDaySheet(ymd, label, groups[ymd]));
        });
        if (order.length === 0 || !isActive(filters)) {
            dailySheets.appendChild(buildBlankDaySheet());
        }
        return order.length;
    }

    function setFormat(next) {
        format = next === 'daily' ? 'daily' : 'log';
        if (formatLog) {
            formatLog.hidden = format !== 'log';
        }
        if (formatDaily) {
            formatDaily.hidden = format !== 'daily';
        }
        formatChips.forEach(function (chip) {
            var active = (chip.getAttribute('data-ac-format') || '') === format;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function applyFilters() {
        var filters = currentFilters();
        var shownRows = [];
        dataRows.forEach(function (row) {
            var match = rowMatches(row, filters);
            row.classList.toggle('ac-row--hidden', !match);
            if (match) {
                shownRows.push(row);
            }
        });

        var blankKeep = isActive(filters) ? 1 : blankRows.length;
        blankRows.forEach(function (row, index) {
            row.classList.toggle('ac-row--hidden', index >= blankKeep);
        });

        var dayCount = rebuildDaily(shownRows, filters);
        var shown = format === 'daily' ? dayCount : shownRows.length;

        kindChips.forEach(function (chip) {
            var active = (chip.getAttribute('data-ac-kind') || '') === filters.kind;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        if (summary) {
            if (format === 'daily') {
                var dayWord = totalDays === 1 ? 'day' : 'days';
                summary.innerHTML = 'Showing ' + dayCount + ' of ' + totalDays + ' ' + dayWord
                    + (isActive(filters) ? ' <span>· filtered</span>' : '');
            } else {
                var label = total === 1 ? 'row' : 'rows';
                summary.innerHTML = 'Showing ' + shown + ' of ' + total + ' ' + label
                    + (isActive(filters) ? ' <span>· filtered</span>' : '');
            }
        }
        if (clearBtn) {
            clearBtn.hidden = !isActive(filters);
        }
        if (emptyMsg) {
            emptyMsg.hidden = !(shownRows.length === 0 && isActive(filters));
        }
        updateTeacherQr(filters);
    }

    /** Point the teacher QR at the current filter so teachers only stamp these rows. */
    var teacherQr = document.getElementById('acTeacherQr');
    var teacherQrTimer = null;
    function updateTeacherQr(filters) {
        if (!teacherQr) {
            return;
        }
        var base = teacherQr.getAttribute('data-view-url') || '';
        if (!base) {
            return;
        }
        var params = [];
        if (filters.date) {
            params.push('date=' + encodeURIComponent(filters.date));
        }
        if (filters.event) {
            params.push('event=' + encodeURIComponent(filters.event));
        }
        if (filters.activity) {
            params.push('activity=' + encodeURIComponent(filters.activity));
        }
        if (filters.kind && filters.kind !== 'all') {
            params.push('kind=' + encodeURIComponent(filters.kind));
        }
        var url = base + (params.length ? '&' + params.join('&') : '');
        var src = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=4&data=' + encodeURIComponent(url);
        teacherQr.setAttribute('data-current-url', url);
        clearTimeout(teacherQrTimer);
        teacherQrTimer = setTimeout(function () {
            if (teacherQr.getAttribute('src') !== src) {
                teacherQr.setAttribute('src', src);
            }
        }, 150);
    }

    kindChips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            kind = chip.getAttribute('data-ac-kind') || 'all';
            applyFilters();
        });
    });

    formatChips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            setFormat(chip.getAttribute('data-ac-format') || 'log');
            applyFilters();
        });
    });

    [dateSelect, eventSelect, activitySelect].forEach(function (select) {
        if (select) {
            select.addEventListener('change', applyFilters);
        }
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            kind = 'all';
            if (dateSelect) {
                dateSelect.value = '';
            }
            if (eventSelect) {
                eventSelect.value = '';
            }
            if (activitySelect) {
                activitySelect.value = '';
            }
            applyFilters();
        });
    }

    setFormat('log');
    applyFilters();
})();
