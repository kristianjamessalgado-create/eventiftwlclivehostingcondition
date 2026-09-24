/**
 * Import day activities from a printed program photo (organizer hub).
 */
(function () {
    var tesseractPromise = null;
    var parsedRows = [];

    function $(id) {
        return document.getElementById(id);
    }

    function getActivityDay() {
        var el = $('eahAddActivityDay');
        var day = el ? String(el.value || '').slice(0, 10) : '';
        if (day) {
            return day;
        }
        var dates = window.__eahEventScheduleDates;
        if (Array.isArray(dates) && dates.length) {
            return String(dates[0]).slice(0, 10);
        }
        return '';
    }

    function venue() {
        return window.__eahImportVenue || { location: '', latitude: '', longitude: '' };
    }

    function pad2(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function toHHmm(hour, minute, ampm) {
        var h = parseInt(hour, 10);
        var m = parseInt(minute, 10);
        if (isNaN(h) || isNaN(m) || m < 0 || m > 59) {
            return '';
        }
        var ap = String(ampm || '').toUpperCase();
        if (ap === 'PM' && h < 12) {
            h += 12;
        }
        if (ap === 'AM' && h === 12) {
            h = 0;
        }
        if (h > 23) {
            return '';
        }
        return pad2(h) + ':' + pad2(m);
    }

    function cleanTitle(raw) {
        return String(raw || '')
            .replace(/[|•·]+/g, ' ')
            .replace(/\s+/g, ' ')
            .replace(/^[\-–—:\s]+|[\-–—:\s]+$/g, '')
            .trim();
    }

    function isJunkTitle(title) {
        var t = title.toLowerCase();
        if (t.length < 2) {
            return true;
        }
        return /^(date|time|venue|location|schedule|program|activity|activities|event|sample|sports|category)$/.test(t);
    }

    function parseScheduleText(text) {
        var lines = String(text || '').split(/\r?\n/);
        var items = [];
        var seen = {};
        var timeRe = /(\d{1,2})[:.](\d{2})\s*(a\.?m\.?|p\.?m\.?)?/gi;
        lines.forEach(function (line) {
            var raw = String(line || '').replace(/\s+/g, ' ').trim();
            if (raw.length < 3) {
                return;
            }
            var matches = [];
            var m;
            timeRe.lastIndex = 0;
            while ((m = timeRe.exec(raw)) !== null) {
                matches.push({
                    hour: m[1],
                    minute: m[2],
                    ampm: m[3] ? m[3].replace(/\./g, '').toUpperCase() : '',
                    index: m.index,
                    length: m[0].length
                });
            }
            if (!matches.length) {
                return;
            }
            var start = toHHmm(matches[0].hour, matches[0].minute, matches[0].ampm);
            var end = '';
            if (matches.length >= 2) {
                end = toHHmm(matches[1].hour, matches[1].minute, matches[1].ampm || matches[0].ampm);
            }
            var title = raw;
            matches.slice().reverse().forEach(function (tm) {
                title = title.slice(0, tm.index) + ' ' + title.slice(tm.index + tm.length);
            });
            title = cleanTitle(title.replace(/\b(to|until|-|–|—)\b/gi, ' '));
            if (!start || isJunkTitle(title)) {
                return;
            }
            var key = start + '|' + title.toLowerCase();
            if (seen[key]) {
                return;
            }
            seen[key] = true;
            items.push({ title: title, start: start, end: end });
        });
        return items;
    }

    function loadTesseract() {
        if (window.Tesseract) {
            return Promise.resolve(window.Tesseract);
        }
        if (tesseractPromise) {
            return tesseractPromise;
        }
        tesseractPromise = new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
            s.onload = function () {
                if (window.Tesseract) {
                    resolve(window.Tesseract);
                } else {
                    reject(new Error('Could not start photo reader.'));
                }
            };
            s.onerror = function () {
                reject(new Error('Could not load photo reader. Check your internet connection.'));
            };
            document.head.appendChild(s);
        });
        return tesseractPromise;
    }

    function setStatus(msg, isError) {
        var el = $('eahImportPhotoStatus');
        if (!el) {
            return;
        }
        el.hidden = !msg;
        el.textContent = msg || '';
        el.classList.toggle('is-error', !!isError);
    }

    function renderRows(rows) {
        var wrap = $('eahImportRows');
        var saveBtn = $('eahImportPhotoSave');
        parsedRows = rows.slice();
        if (!wrap) {
            return;
        }
        if (!rows.length) {
            wrap.hidden = true;
            wrap.innerHTML = '';
            if (saveBtn) {
                saveBtn.disabled = true;
            }
            return;
        }
        wrap.hidden = false;
        wrap.innerHTML = rows.map(function (row, i) {
            return '<div class="eah-import-row" data-index="' + i + '">' +
                '<label><span>Activity</span><input type="text" class="form-control js-import-title" maxlength="150" value="' + escapeHtml(row.title) + '"></label>' +
                '<label><span>Start</span><input type="time" class="form-control js-import-start" value="' + escapeHtml(row.start) + '"></label>' +
                '<label><span>End</span><input type="time" class="form-control js-import-end" value="' + escapeHtml(row.end || '') + '"></label>' +
                '<button type="button" class="eah-import-row__remove js-import-remove" aria-label="Remove">&times;</button>' +
                '</div>';
        }).join('');
        if (saveBtn) {
            saveBtn.disabled = false;
        }
    }

    function escapeHtml(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function collectRows() {
        var wrap = $('eahImportRows');
        if (!wrap) {
            return [];
        }
        var out = [];
        wrap.querySelectorAll('.eah-import-row').forEach(function (row) {
            var title = row.querySelector('.js-import-title');
            var start = row.querySelector('.js-import-start');
            var end = row.querySelector('.js-import-end');
            var t = title ? String(title.value || '').trim() : '';
            var st = start ? String(start.value || '').slice(0, 5) : '';
            if (!t || !st) {
                return;
            }
            out.push({
                title: t,
                start: st,
                end: end ? String(end.value || '').slice(0, 5) : ''
            });
        });
        return out;
    }

    function openModal() {
        var el = $('eahImportPhotoModal');
        if (!el || typeof bootstrap === 'undefined') {
            return;
        }
        var venueEl = $('eahImportVenue');
        if (venueEl && !venueEl.value) {
            venueEl.value = venue().location || '';
        }
        bootstrap.Modal.getOrCreateInstance(el).show();
    }

    function saveActivities() {
        var rows = collectRows();
        var saveBtn = $('eahImportPhotoSave');
        var day = getActivityDay();
        var locEl = $('eahImportVenue');
        var location = locEl ? String(locEl.value || '').trim() : '';
        var v = venue();
        if (!rows.length) {
            setStatus('Add at least one activity with a name and start time.', true);
            return;
        }
        if (!day) {
            setStatus('Choose an event day first.', true);
            return;
        }
        if (!location) {
            setStatus('Enter a venue for the imported activities.', true);
            return;
        }
        if (window.EVENTIFY_SESSIONS_HAVE_GEO && (!v.latitude || !v.longitude)) {
            setStatus('Set a map pin on the main event (or one existing activity) first, then import again.', true);
            return;
        }
        if (saveBtn) {
            saveBtn.disabled = true;
        }
        setStatus('Saving ' + rows.length + ' activit' + (rows.length === 1 ? 'y' : 'ies') + '…');

        var chain = Promise.resolve();
        var saved = 0;
        rows.forEach(function (row) {
            chain = chain.then(function () {
                var body = new FormData();
                body.append('action', 'save');
                body.append('event_id', String(window.__eahEventId || 0));
                body.append('schedule_date', day);
                body.append('title', row.title);
                body.append('location', location);
                body.append('start_time', row.start);
                if (row.end) {
                    body.append('end_time', row.end);
                }
                body.append('status', 'scheduled');
                body.append('access_mode', 'open');
                body.append('audience_inherit', '1');
                if (v.latitude && v.longitude) {
                    body.append('latitude', String(v.latitude));
                    body.append('longitude', String(v.longitude));
                }
                if (window.csrfToken) {
                    body.append('csrf_token', window.csrfToken);
                }
                return fetch((window.BASE_URL || '').replace(/\/$/, '') + '/backend/auth/event_day_sessions_api.php', {
                    method: 'POST',
                    body: body,
                    credentials: 'same-origin'
                }).then(function (res) { return res.json(); }).then(function (data) {
                    if (!data || !data.ok) {
                        throw new Error((data && data.error) ? data.error : 'Could not save ' + row.title + '.');
                    }
                    saved += 1;
                });
            });
        });

        chain.then(function () {
            window.location.href = (window.BASE_URL || '').replace(/\/$/, '') +
                '/event_activities.php?id=' + encodeURIComponent(window.__eahEventId || 0) +
                '&msg=' + encodeURIComponent(saved + ' activit' + (saved === 1 ? 'y' : 'ies') + ' imported from photo.');
        }).catch(function (err) {
            if (saveBtn) {
                saveBtn.disabled = false;
            }
            setStatus(err && err.message ? err.message : 'Could not import activities.', true);
        });
    }

    function readPhoto(file) {
        if (!file) {
            return;
        }
        var preview = $('eahImportPhotoPreview');
        if (preview) {
            preview.hidden = false;
            preview.src = URL.createObjectURL(file);
        }
        setStatus('Reading the photo… this can take a few seconds.');
        var saveBtn = $('eahImportPhotoSave');
        if (saveBtn) {
            saveBtn.disabled = true;
        }
        loadTesseract().then(function (Tesseract) {
            return Tesseract.recognize(file, 'eng');
        }).then(function (result) {
            var text = result && result.data ? result.data.text : '';
            var rows = parseScheduleText(text);
            renderRows(rows);
            if (!rows.length) {
                setStatus('No times were found. Use a clearer photo, or add activities with Add activity.', true);
                return;
            }
            setStatus('Found ' + rows.length + '. Check the names and times, then save.');
        }).catch(function (err) {
            setStatus(err && err.message ? err.message : 'Could not read that photo.', true);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        ['eahHeroImportPhoto', 'eahTopbarImportPhoto', 'eahDrawerImportPhoto'].forEach(function (id) {
            var btn = $(id);
            if (btn) {
                btn.addEventListener('click', openModal);
            }
        });
        var input = $('eahImportPhotoInput');
        var pickBtn = $('eahImportPhotoPick');
        if (pickBtn && input) {
            pickBtn.addEventListener('click', function () {
                input.click();
            });
        }
        if (input) {
            input.addEventListener('change', function () {
                readPhoto(input.files && input.files[0]);
            });
        }
        var rows = $('eahImportRows');
        if (rows) {
            rows.addEventListener('click', function (e) {
                var btn = e.target.closest('.js-import-remove');
                if (!btn) {
                    return;
                }
                var row = btn.closest('.eah-import-row');
                if (row) {
                    row.remove();
                }
                if (!rows.querySelector('.eah-import-row')) {
                    renderRows([]);
                    setStatus('All rows removed. Choose another photo, or cancel.');
                }
            });
        }
        var saveBtn = $('eahImportPhotoSave');
        if (saveBtn) {
            saveBtn.addEventListener('click', saveActivities);
        }
    });
})();
