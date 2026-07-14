/**
 * Day activities (sub-events) within a parent event — organizer manage + display.
 */
(function (global) {
    'use strict';

    function baseUrl() {
        return global.BASE_URL || '';
    }

    function csrfToken() {
        return global.csrfToken || '';
    }

    function resolveEventScheduleDates(event) {
        var props = (event && event.extendedProps) ? event.extendedProps : {};
        var dates = [];
        if (Array.isArray(props.schedule_dates)) {
            dates = props.schedule_dates.slice();
        } else if (Array.isArray(props.segment_dates)) {
            dates = props.segment_dates.slice();
        }
        dates = dates.map(function (d) {
            return String(d).slice(0, 10);
        }).filter(Boolean);
        dates.sort();
        if (dates.length) {
            return dates;
        }
        var fallback = props.schedule_date_ymd || props.event_date_ymd || '';
        fallback = String(fallback).slice(0, 10);
        return fallback ? [fallback] : [];
    }

    function isYmdInEventSchedule(ymd, props, validDates) {
        if (!ymd) {
            return false;
        }
        if (validDates && validDates.indexOf(ymd) >= 0) {
            return true;
        }
        if (Array.isArray(props.segment_dates) && props.segment_dates.some(function (d) {
            return String(d).slice(0, 10) === ymd;
        })) {
            return true;
        }
        if (Array.isArray(props.schedule_dates) && props.schedule_dates.some(function (d) {
            return String(d).slice(0, 10) === ymd;
        })) {
            return true;
        }
        return false;
    }

    /** Which day column the user clicked on a connected multi-day calendar bar. */
    function resolveClickYmdFromPointer(event, jsEvent) {
        if (!jsEvent || !event) {
            return null;
        }
        var props = event.extendedProps || {};
        var validDates = [];
        if (Array.isArray(props.segment_dates) && props.segment_dates.length >= 2) {
            validDates = props.segment_dates.map(function (d) {
                return String(d).slice(0, 10);
            });
        } else if (Array.isArray(props.schedule_dates) && props.schedule_dates.length >= 2) {
            validDates = props.schedule_dates.map(function (d) {
                return String(d).slice(0, 10);
            });
        }
        if (validDates.length < 2) {
            return null;
        }
        var clickX = jsEvent.clientX;
        var days = document.querySelectorAll('.fc-daygrid-day[data-date]');
        for (var i = 0; i < days.length; i++) {
            var cell = days[i];
            var ymd = String(cell.getAttribute('data-date') || '').slice(0, 10);
            if (validDates.indexOf(ymd) < 0) {
                continue;
            }
            var rect = cell.getBoundingClientRect();
            if (clickX >= rect.left && clickX < rect.right) {
                return ymd;
            }
        }
        return null;
    }

    function parseClickedScheduleDate(event, clickContext) {
        var props = event.extendedProps || {};
        var validDates = resolveEventScheduleDates(event);
        var clickedYmd = null;

        if (clickContext) {
            if (clickContext.jsEvent && props.calendar_range_multiday) {
                clickedYmd = resolveClickYmdFromPointer(event, clickContext.jsEvent);
            }
            if (!clickedYmd && typeof global.eventifyGetSegmentYmdFromEl === 'function') {
                if (clickContext.clickEl) {
                    clickedYmd = global.eventifyGetSegmentYmdFromEl(clickContext.clickEl);
                }
                if (!clickedYmd && clickContext.jsEvent && clickContext.jsEvent.target) {
                    clickedYmd = global.eventifyGetSegmentYmdFromEl(clickContext.jsEvent.target);
                }
            }
        }
        if (clickedYmd && isYmdInEventSchedule(clickedYmd, props, validDates)) {
            return clickedYmd;
        }
        if (props.schedule_date_ymd) {
            return String(props.schedule_date_ymd).slice(0, 10);
        }
        var id = String(event.id || '');
        var m = id.match(/-(\d{4}-\d{2}-\d{2})$/);
        return m ? m[1] : String(props.event_date_ymd || '').slice(0, 10);
    }

    function parseEventId(event) {
        var props = event.extendedProps || {};
        if (props.event_id) {
            return parseInt(props.event_id, 10);
        }
        var id = String(event.id || '');
        var m = id.match(/^(\d+)-\d{4}-\d{2}-\d{2}$/);
        if (m) {
            return parseInt(m[1], 10);
        }
        return parseInt(id, 10) || 0;
    }

    function formatYmdLong(ymd) {
        var d = new Date(ymd + 'T12:00:00');
        return isNaN(d.getTime())
            ? ymd
            : d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
    }

    function formatTimeRange(start, end) {
        function fmt(t) {
            if (!t) {
                return '';
            }
            var x = new Date('1970-01-01T' + String(t).slice(0, 8));
            return isNaN(x.getTime()) ? t : x.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
        }
        var a = fmt(start);
        var b = fmt(end);
        if (a && b) {
            return a + ' – ' + b;
        }
        return a || '';
    }

    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    var edsMessageModalInstance = null;

    function ensureEdsMessageModal() {
        if (document.getElementById('edsMessageModal')) {
            return;
        }
        document.body.insertAdjacentHTML(
            'beforeend',
            '<div class="modal fade" id="edsMessageModal" tabindex="-1" aria-labelledby="edsMessageModalLabel" aria-hidden="true">' +
            '<div class="modal-dialog modal-dialog-centered">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title" id="edsMessageModalLabel"><i class="fas fa-info-circle me-2" id="edsMessageModalIcon"></i><span id="edsMessageModalTitle">Notice</span></h5>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
            '</div>' +
            '<div class="modal-body"><p class="mb-0" id="edsMessageModalBody"></p></div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>' +
            '</div></div></div></div>'
        );
    }

    function showEdsMessageModal(message, options) {
        options = options || {};
        var msg = String(message || '').trim() || 'Something went wrong.';
        ensureEdsMessageModal();
        var titleEl = document.getElementById('edsMessageModalTitle');
        var bodyEl = document.getElementById('edsMessageModalBody');
        var iconEl = document.getElementById('edsMessageModalIcon');
        var modalEl = document.getElementById('edsMessageModal');
        if (!modalEl || !bodyEl) {
            window.alert(msg);
            return;
        }
        if (titleEl) {
            titleEl.textContent = options.title || 'Notice';
        }
        if (iconEl) {
            iconEl.className = 'fas ' + (options.icon || 'fa-info-circle') + ' me-2';
        }
        bodyEl.textContent = msg;
        if (typeof global.bootstrap !== 'undefined' && global.bootstrap.Modal) {
            edsPrepareStackedModal(modalEl);
            edsMessageModalInstance = edsCreateHelperModalInstance(modalEl);
            modalEl.addEventListener('hidden.bs.modal', function () {
                edsResetHelperModal(modalEl);
            }, { once: true });
            if (edsMessageModalInstance) {
                edsMessageModalInstance.show();
            }
        } else {
            window.alert(msg);
        }
    }

    function showEdsRsvpError(error) {
        var msg = error || 'Could not RSVP.';
        if (msg.indexOf('main event') !== -1) {
            showEdsMessageModal(msg, {
                title: 'Register for the event first',
                icon: 'fa-calendar-check'
            });
            return;
        }
        showEdsMessageModal(msg, { title: 'Activity RSVP', icon: 'fa-exclamation-circle' });
    }

    var edsConfirmModalInstance = null;

    function edsCountOpenModals() {
        return document.querySelectorAll('.modal.show').length;
    }

    function edsForceModalCleanupIfIdle() {
        if (edsCountOpenModals() > 0) {
            return;
        }
        document.querySelectorAll('.modal-backdrop').forEach(function (el) {
            el.remove();
        });
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }

    function edsCreateHelperModalInstance(modalEl) {
        if (typeof global.bootstrap === 'undefined' || !global.bootstrap.Modal) {
            return null;
        }
        var existing = global.bootstrap.Modal.getInstance(modalEl);
        if (existing) {
            existing.dispose();
        }
        var stacked = edsCountOpenModals() > 0;
        return new global.bootstrap.Modal(modalEl, {
            backdrop: !stacked,
            keyboard: true,
            focus: true
        });
    }

    function edsPrepareStackedModal(modalEl) {
        var openCount = edsCountOpenModals();
        if (openCount > 0) {
            modalEl.style.zIndex = String(1055 + (openCount * 10));
        } else {
            modalEl.style.removeProperty('z-index');
        }
    }

    function edsResetHelperModal(modalEl) {
        modalEl.style.removeProperty('z-index');
        edsForceModalCleanupIfIdle();
    }

    function edsForceHideHelperModals() {
        ['edsConfirmModal', 'edsMessageModal'].forEach(function (id) {
            var helper = document.getElementById(id);
            if (!helper) {
                return;
            }
            if (typeof global.bootstrap !== 'undefined' && global.bootstrap.Modal) {
                var inst = global.bootstrap.Modal.getInstance(helper);
                if (inst) {
                    inst.hide();
                    return;
                }
            }
            helper.classList.remove('show');
            helper.setAttribute('aria-hidden', 'true');
            helper.style.removeProperty('display');
            helper.style.removeProperty('z-index');
        });
        edsForceModalCleanupIfIdle();
    }

    function ensureEdsConfirmModal() {
        if (document.getElementById('edsConfirmModal')) {
            return;
        }
        document.body.insertAdjacentHTML(
            'beforeend',
            '<div class="modal fade" id="edsConfirmModal" tabindex="-1" aria-labelledby="edsConfirmModalLabel" aria-hidden="true">' +
            '<div class="modal-dialog modal-dialog-centered">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title" id="edsConfirmModalLabel"><i class="fas fa-question-circle me-2" id="edsConfirmModalIcon"></i><span id="edsConfirmModalTitle">Confirm</span></h5>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
            '</div>' +
            '<div class="modal-body"><p class="mb-0" id="edsConfirmModalBody"></p></div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-secondary" id="edsConfirmCancelBtn" data-bs-dismiss="modal">Cancel</button>' +
            '<button type="button" class="btn btn-primary" id="edsConfirmYesBtn">Confirm</button>' +
            '</div></div></div></div>'
        );
    }

    function showEdsConfirmModal(options) {
        options = options || {};
        ensureEdsConfirmModal();
        return new Promise(function (resolve) {
            var modalEl = document.getElementById('edsConfirmModal');
            var titleEl = document.getElementById('edsConfirmModalTitle');
            var bodyEl = document.getElementById('edsConfirmModalBody');
            var iconEl = document.getElementById('edsConfirmModalIcon');
            var yesBtn = document.getElementById('edsConfirmYesBtn');
            var cancelBtn = document.getElementById('edsConfirmCancelBtn');
            if (!modalEl || !yesBtn || !bodyEl) {
                resolve(false);
                return;
            }
            if (titleEl) {
                titleEl.textContent = options.title || 'Confirm';
            }
            if (iconEl) {
                iconEl.className = 'fas ' + (options.icon || 'fa-question-circle') + ' me-2';
            }
            bodyEl.textContent = options.message || 'Are you sure?';
            yesBtn.textContent = options.confirmLabel || 'Confirm';
            yesBtn.className = 'btn ' + (options.confirmClass || 'btn-primary');
            if (cancelBtn) {
                cancelBtn.textContent = options.cancelLabel || 'Cancel';
            }

            var settled = false;
            var confirmed = false;

            function done(result) {
                if (settled) {
                    return;
                }
                settled = true;
                resolve(result);
            }

            function onHidden() {
                edsResetHelperModal(modalEl);
                done(confirmed);
            }

            function onYes() {
                confirmed = true;
                if (typeof global.bootstrap !== 'undefined' && global.bootstrap.Modal) {
                    var inst = global.bootstrap.Modal.getInstance(modalEl);
                    if (inst) {
                        inst.hide();
                    }
                } else {
                    onHidden();
                }
            }

            yesBtn.addEventListener('click', onYes, { once: true });
            modalEl.addEventListener('hidden.bs.modal', onHidden, { once: true });

            if (typeof global.bootstrap !== 'undefined' && global.bootstrap.Modal) {
                edsPrepareStackedModal(modalEl);
                edsConfirmModalInstance = edsCreateHelperModalInstance(modalEl);
                if (edsConfirmModalInstance) {
                    edsConfirmModalInstance.show();
                }
            } else {
                confirmed = window.confirm(bodyEl.textContent);
                onHidden();
            }
        });
    }

    function postActivitySessionRsvp(action, sessionId) {
        var body = new FormData();
        body.append('action', action);
        body.append('session_id', String(sessionId));
        body.append('csrf_token', csrfToken());
        return fetch(baseUrl() + '/backend/auth/event_day_sessions_api.php', {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json();
        });
    }

    function refreshStudentRsvpLists(data) {
        if (!data || !data.sessions) {
            return;
        }
        state.sessions = data.sessions;
        refreshDetailsPanel();
        var prev = document.getElementById('studentDaySessionsPreview');
        var block = document.getElementById('studentDaySessionsBlock');
        if (prev && block) {
            var eventId = block.getAttribute('data-event-id');
            var scheduleDate = block.getAttribute('data-schedule-date');
            if (eventId && scheduleDate) {
                getStudentSessionsCache()[studentSessionsCacheKey(eventId, scheduleDate)] = data.sessions;
            }
            renderStudentSessionsPreview(prev, data.sessions);
        }
    }

    function findSessionById(sessions, sessionId) {
        sessionId = parseInt(sessionId, 10);
        if (!sessions || !sessionId) {
            return null;
        }
        for (var i = 0; i < sessions.length; i++) {
            if (parseInt(sessions[i].id, 10) === sessionId) {
                return sessions[i];
            }
        }
        return null;
    }

    function renderOpenEntryStickyHtml(session) {
        if (!session || session.access_mode !== 'open') {
            return '';
        }
        if (session.user_checked_in) {
            return '';
        }
        var msg = session.allows_checkin === false
            ? 'Open entry — scan the activity check-in QR at the venue when check-in opens. No RSVP needed.'
            : 'Open entry — scan the activity check-in QR at the venue to check in. No RSVP needed.';
        return '<div class="eah-attendance-status eah-attendance-status--open" role="status">' +
            '<div class="eah-attendance-status__icon" aria-hidden="true"><i class="fas fa-door-open"></i></div>' +
            '<div class="eah-attendance-status__body">' +
            '<span class="eah-attendance-status__label">Open entry</span>' +
            '<span class="eah-attendance-status__meta">' + escapeHtml(msg) + '</span>' +
            '</div></div>';
    }

    function renderEahDetailStickyRsvpHtml(session) {
        if (!session || String(session.status || 'scheduled').toLowerCase() === 'cancelled') {
            return '';
        }
        var openHtml = renderOpenEntryStickyHtml(session);
        if (openHtml) {
            return openHtml;
        }
        var sid = parseInt(session.id, 10);
        if (session.user_checked_in) {
            var timeMeta = session.checked_in_at
                ? '<span class="eah-attendance-status__meta"><i class="fas fa-clock" aria-hidden="true"></i> ' +
                    escapeHtml(String(session.checked_in_at)) + '</span>'
                : '<span class="eah-attendance-status__meta">Attendance recorded for this activity</span>';
            return '<div class="eah-attendance-status eah-attendance-status--checked-in" role="status">' +
                '<div class="eah-attendance-status__icon" aria-hidden="true"><i class="fas fa-check"></i></div>' +
                '<div class="eah-attendance-status__body">' +
                '<span class="eah-attendance-status__label">You\'re checked in</span>' +
                timeMeta +
                '</div></div>';
        }
        if (session.user_rsvped) {
            var sticky = '<div class="eah-attendance-status eah-attendance-status--rsvp" role="status">' +
                '<div class="eah-attendance-status__icon" aria-hidden="true"><i class="fas fa-bookmark"></i></div>' +
                '<div class="eah-attendance-status__body">' +
                '<span class="eah-attendance-status__label">' +
                (session.allows_cancel_rsvp !== false ? 'RSVP confirmed' : 'RSVP saved') +
                '</span>' +
                '<span class="eah-attendance-status__meta">' +
                (session.allows_cancel_rsvp !== false
                    ? 'Show your QR at the venue to check in'
                    : 'This activity has ended') +
                '</span></div></div>';
            if (session.allows_cancel_rsvp !== false) {
                sticky += '<button type="button" class="eah-btn eah-btn-outline eah-btn-block js-eah-cancel-rsvp" data-session-id="' + sid + '">' +
                    '<i class="fas fa-times"></i> Cancel RSVP</button>';
            }
            return sticky;
        }
        if (session.allows_rsvp === false) {
            return '<div class="eah-detail-sticky__muted"><i class="fas fa-clock me-1"></i> RSVP closed — activity ended or not open yet</div>';
        }
        return '<button type="button" class="eah-btn eah-btn-primary eah-btn-block js-eah-rsvp" data-session-id="' + sid + '">' +
            '<i class="fas fa-user-plus"></i> RSVP for this activity</button>';
    }

    function syncDetailStickyMode(session) {
        var sticky = document.querySelector('.eah-detail-sticky');
        if (!sticky) {
            return;
        }
        var statusOnly = !!(session && (
            session.user_checked_in ||
            (session.user_rsvped && session.allows_cancel_rsvp === false)
        ));
        sticky.classList.toggle('eah-detail-sticky--status-only', statusOnly);
    }

    function renderEahRsvpButtonHtml(session) {
        if (!session || String(session.status || 'scheduled').toLowerCase() === 'cancelled') {
            return '';
        }
        var sid = parseInt(session.id, 10);
        if (session.user_checked_in) {
            return '<span class="eah-badge eah-badge-attended"><i class="fas fa-clipboard-check"></i> Checked in</span>';
        }
        if (session.access_mode === 'open') {
            return '<span class="eah-badge eah-badge-open"><i class="fas fa-door-open"></i> Open entry</span>';
        }
        if (session.user_rsvped) {
            return '<span class="eah-badge eah-badge-rsvp"><i class="fas fa-check-circle"></i> Confirmed RSVP</span>';
        }
        if (session.allows_rsvp === false) {
            return '<span class="eah-btn eah-btn-outline text-muted" style="pointer-events:none;opacity:.85">' +
                '<i class="fas fa-clock"></i> RSVP closed</span>';
        }
        return '<button type="button" class="eah-btn eah-btn-primary js-eah-rsvp" data-session-id="' + sid + '">' +
            '<i class="fas fa-user-plus"></i> RSVP for this activity</button>';
    }

    function applyActivityHubRsvpUi(sessionId, sessions) {
        var session = findSessionById(sessions, sessionId);
        if (!session) {
            return;
        }
        var detailActions = document.getElementById('eahDetailRsvpActions');
        if (detailActions) {
            detailActions.innerHTML = renderEahDetailStickyRsvpHtml(session);
            syncDetailStickyMode(session);
        }
        var actions = document.querySelector('.eah-actions');
        if (actions) {
            var rsvpBtn = actions.querySelector('.js-eah-rsvp, .js-eah-cancel-rsvp');
            var btnHtml = renderEahRsvpButtonHtml(session);
            if (rsvpBtn) {
                if (btnHtml) {
                    rsvpBtn.outerHTML = btnHtml;
                } else {
                    rsvpBtn.remove();
                }
            } else if (btnHtml) {
                var backBtn = actions.querySelector('a.eah-btn-outline[href*="event_activities"]');
                if (backBtn) {
                    backBtn.insertAdjacentHTML('beforebegin', btnHtml);
                } else {
                    actions.insertAdjacentHTML('afterbegin', btnHtml);
                }
            }
        }
        document.querySelectorAll('.eah-info-item').forEach(function (item) {
            var label = item.querySelector('.eah-info-item-label');
            if (label && label.textContent.trim() === 'RSVP') {
                var val = item.querySelector('.eah-info-item-value');
                if (val) {
                    val.textContent = String(session.rsvp_count != null ? session.rsvp_count : 0) + ' registered';
                }
            }
        });
    }

    function handleActivityHubRsvpSuccess(sessionId, data, btn) {
        if (btn) {
            btn.disabled = false;
        }
        if (!data || !data.ok) {
            return;
        }
        if (data.sessions) {
            applyActivityHubRsvpUi(sessionId, data.sessions);
        }
    }

    function initActivitiesHubRsvp() {
        if (!document.body || !document.body.classList.contains('event-activities-hub')) {
            return;
        }
        if (document.documentElement.getAttribute('data-eah-rsvp-bound') === '1') {
            return;
        }
        document.documentElement.setAttribute('data-eah-rsvp-bound', '1');

        function runPost(action, sessionId, btn) {
            btn.disabled = true;
            postActivitySessionRsvp(action, sessionId).then(function (data) {
                if (!data.ok) {
                    btn.disabled = false;
                    showEdsRsvpError(data.error);
                    return;
                }
                handleActivityHubRsvpSuccess(sessionId, data, btn);
            }).catch(function () {
                btn.disabled = false;
                showEdsMessageModal('Request failed. Please try again.', {
                    title: 'Activity RSVP',
                    icon: 'fa-exclamation-circle'
                });
            });
        }

        document.addEventListener('click', function (e) {
            var rsvpBtn = e.target.closest('.js-eah-rsvp');
            if (rsvpBtn) {
                e.preventDefault();
                var sid = rsvpBtn.getAttribute('data-session-id');
                if (!sid || typeof showEdsConfirmModal !== 'function') {
                    return;
                }
                showEdsConfirmModal({
                    title: 'Confirm RSVP',
                    message: 'Are you sure you want to register for this activity?',
                    confirmLabel: 'Yes, register',
                    confirmClass: 'btn-primary',
                    icon: 'fa-calendar-check'
                }).then(function (ok) {
                    if (ok) {
                        runPost('rsvp', sid, rsvpBtn);
                    }
                });
                return;
            }
            var cancelBtn = e.target.closest('.js-eah-cancel-rsvp');
            if (cancelBtn) {
                e.preventDefault();
                var sid2 = cancelBtn.getAttribute('data-session-id');
                if (!sid2 || typeof showEdsConfirmModal !== 'function') {
                    return;
                }
                showEdsConfirmModal({
                    title: 'Cancel RSVP',
                    message: 'Are you sure you want to cancel your RSVP for this activity?',
                    confirmLabel: 'Yes, cancel RSVP',
                    confirmClass: 'btn-danger',
                    icon: 'fa-user-minus'
                }).then(function (ok) {
                    if (ok) {
                        runPost('cancel_rsvp', sid2, cancelBtn);
                    }
                });
            }
        });
    }

    function fetchSessions(eventId, scheduleDate) {
        var url = baseUrl() + '/backend/auth/event_day_sessions_api.php?event_id=' +
            encodeURIComponent(eventId) +
            (scheduleDate ? '&schedule_date=' + encodeURIComponent(scheduleDate) : '');
        return fetch(url, { credentials: 'same-origin' }).then(function (r) {
            return r.json();
        });
    }

    function statusBadge(status) {
        status = String(status || 'scheduled').toLowerCase();
        if (status === 'scheduled') {
            return '';
        }
        var cls = status === 'cancelled' ? 'eds-status-badge eds-status-badge--cancelled' : 'eds-status-badge eds-status-badge--delayed';
        var label = status.charAt(0).toUpperCase() + status.slice(1);
        return ' <span class="' + cls + '">' + escapeHtml(label) + '</span>';
    }

    function renderSessionsList(sessions, options) {
        options = options || {};
        var canEditSchedule = !!options.canEditSchedule;
        var showOrganizerTools = !!options.showOrganizerTools;
        var canRsvp = !!options.canRsvp;
        if (!sessions || !sessions.length) {
            return '<p class="eds-empty-day mb-0">No activities added for this day yet.' +
                (canEditSchedule ? ' Use <strong>Add activity</strong> below.' : '') + '</p>';
        }
        var html = '<ul class="eds-session-list">';
        sessions.forEach(function (s) {
            var timeStr = formatTimeRange(s.start_time, s.end_time);
            var status = String(s.status || 'scheduled').toLowerCase();
            html += '<li class="eds-session-card' + (status === 'cancelled' ? ' is-cancelled' : '') + '">' +
                '<div class="eds-session-card__main">' +
                '<div class="eds-session-card__title-row">' +
                '<span class="eds-session-card__title">' + escapeHtml(s.title) + '</span>' +
                statusBadge(status) +
                ((s.requires_ticket || s.access_mode === 'ticket_required') ? ' <span class="eds-session-card__ticket">Ticket</span>' : '') +
                ((s.access_mode === 'open') ? ' <span class="eds-session-card__open">Open</span>' : '') +
                '</div>';
            if (s.category) {
                html += '<div class="eds-session-card__meta">' + escapeHtml(s.category) + '</div>';
            }
            html += (timeStr ? '<div class="eds-session-card__meta"><i class="fas fa-clock" aria-hidden="true"></i> ' + escapeHtml(timeStr) + '</div>' : '') +
                '<div class="eds-session-card__meta"><i class="fas fa-map-marker-alt" aria-hidden="true"></i> ' + escapeHtml(s.location) + '</div>';
            if (s.notes) {
                html += '<div class="eds-session-card__notes">' + escapeHtml(s.notes) + '</div>';
            }
            if (s.contact_name || s.contact_phone) {
                html += '<div class="eds-session-card__meta"><i class="fas fa-address-card" aria-hidden="true"></i> ' +
                    escapeHtml([s.contact_name, s.contact_phone].filter(Boolean).join(' · ')) + '</div>';
            }
            if (s.max_capacity) {
                var spots = (s.rsvp_count != null ? s.rsvp_count : 0) + ' / ' + s.max_capacity + ' RSVP\'d';
                html += '<div class="eds-session-card__meta">' + escapeHtml(spots) + '</div>';
            } else if (s.rsvp_count != null && s.rsvp_count > 0) {
                html += '<div class="eds-session-card__meta">' + escapeHtml(String(s.rsvp_count)) + ' RSVP\'d</div>';
            }
            if (s.latitude != null && s.longitude != null) {
                html += '<div class="eds-session-card__map-link"><a href="https://www.openstreetmap.org/?mlat=' +
                    encodeURIComponent(s.latitude) + '&mlon=' + encodeURIComponent(s.longitude) +
                    '#map=17/' + encodeURIComponent(s.latitude) + '/' + encodeURIComponent(s.longitude) +
                    '" target="_blank" rel="noopener">View on map</a></div>';
            }
            html += '</div>';
            if (canEditSchedule || showOrganizerTools) {
                html += '<div class="eds-session-card__actions">';
                if (canEditSchedule) {
                    html += '<button type="button" class="eds-icon-btn js-eds-edit" data-session-id="' + s.id + '" title="Edit"><i class="fas fa-pen"></i></button>' +
                        '<button type="button" class="eds-icon-btn eds-icon-btn--danger js-eds-delete" data-session-id="' + s.id + '" title="Delete"><i class="fas fa-trash"></i></button>';
                }
                if (showOrganizerTools) {
                    html += '<a class="eds-icon-btn" href="' + baseUrl() + '/activity_attendance.php?id=' + encodeURIComponent(s.id) + '" target="_blank" rel="noopener" title="View attendance"><i class="fas fa-clipboard-check"></i></a>' +
                        '<a class="eds-icon-btn eds-icon-btn--qr" href="' + baseUrl() + '/activity_qr.php?id=' + encodeURIComponent(s.id) + '" target="_blank" rel="noopener" title="Activity QR"><i class="fas fa-qrcode"></i></a>';
                }
                html += '</div>';
            } else if (canRsvp && status !== 'cancelled') {
                if (s.user_checked_in) {
                    html += '<span class="eds-session-card__ended"><i class="fas fa-clipboard-check"></i> Checked in</span>';
                } else if (s.user_rsvped) {
                    if (s.allows_cancel_rsvp !== false) {
                        html += '<button type="button" class="btn btn-sm eds-btn-outline flex-shrink-0 js-eds-cancel-rsvp" data-session-id="' + s.id + '">Cancel RSVP</button>';
                    } else {
                        html += '<span class="eds-session-card__ended">RSVP\'d</span>';
                    }
                } else if (s.access_mode === 'open') {
                    html += '<span class="eds-session-card__open-entry"><i class="fas fa-door-open"></i> Open entry</span>';
                } else if (s.allows_rsvp === false) {
                    html += '<span class="eds-session-card__ended">Ended</span>';
                } else {
                    html += '<button type="button" class="btn btn-sm eds-btn-primary flex-shrink-0 js-eds-rsvp" data-session-id="' + s.id + '">RSVP</button>';
                }
            }
            html += '</li>';
        });
        html += '</ul>';
        return html;
    }

    var state = {
        eventId: 0,
        scheduleDate: '',
        scheduleDates: [],
        sessions: [],
        ticketTypes: [],
        editingId: null,
        scheduleEditable: true,
        scheduleLockMessage: ''
    };

    function applyScheduleEditableFromResponse(data) {
        if (!data || typeof data.schedule_editable === 'undefined') {
            return;
        }
        state.scheduleEditable = !!data.schedule_editable;
        state.scheduleLockMessage = data.schedule_lock_message || '';
        if (typeof data.schedule_editable === 'boolean' && global.currentRole === 'organizer') {
            global.__eahScheduleEditable = data.schedule_editable;
        }
        syncScheduleLockUi();
    }

    function syncScheduleLockUi() {
        var notice = document.getElementById('edsScheduleLockNotice');
        var formPanel = document.querySelector('#eventDaySessionsModal .eds-form-panel');
        var editable = state.scheduleEditable !== false;
        if (notice) {
            if (!editable && state.scheduleLockMessage) {
                notice.textContent = state.scheduleLockMessage;
                notice.style.display = '';
            } else {
                notice.textContent = '';
                notice.style.display = 'none';
            }
        }
        if (formPanel) {
            formPanel.style.display = editable ? '' : 'none';
        }
    }

    function syncScheduleDatePicker() {
        var wrap = document.getElementById('edsScheduleDateWrap');
        var sel = document.getElementById('edsScheduleDate');
        if (!wrap || !sel) {
            return;
        }
        var dates = state.scheduleDates || [];
        if (dates.length <= 1) {
            wrap.style.display = 'none';
            sel.innerHTML = '';
            return;
        }
        wrap.style.display = '';
        var current = state.scheduleDate || dates[0];
        sel.innerHTML = dates.map(function (ymd) {
            return '<option value="' + escapeHtml(ymd) + '">' + escapeHtml(formatYmdLong(ymd)) + '</option>';
        }).join('');
        sel.value = dates.indexOf(current) >= 0 ? current : dates[0];
        sel.disabled = !!state.editingId;
    }

    function setScheduleDate(ymd, reload) {
        ymd = String(ymd || '').slice(0, 10);
        if (!ymd) {
            return;
        }
        state.scheduleDate = ymd;
        var els = getManageEls();
        if (els.dateLabel) {
            els.dateLabel.textContent = formatYmdLong(ymd);
        }
        if (els.printBtn && state.eventId) {
            els.printBtn.href = baseUrl() + '/activity_schedule.php?event_id=' +
                encodeURIComponent(state.eventId) + '&date=' + encodeURIComponent(ymd);
            els.printBtn.style.display = 'inline-block';
        }
        var dayLbl = document.getElementById('eventDaySessionsDayLabel');
        if (dayLbl) {
            dayLbl.textContent = formatYmdLong(ymd);
        }
        syncScheduleDatePicker();
        if (reload && state.eventId) {
            fetchSessions(state.eventId, ymd).then(function (data) {
                if (data && data.ok) {
                    state.sessions = data.sessions || [];
                    applyTicketTypesFromResponse(data);
                    applyScheduleEditableFromResponse(data);
                }
                refreshManageList();
                refreshDetailsPanel();
                applyDefaultSortOrder();
            });
        }
    }

    function normalizeSelectedTicketIds(selected) {
        if (Array.isArray(selected)) {
            return selected.map(function (v) {
                return parseInt(v, 10);
            }).filter(function (v) {
                return v > 0;
            });
        }
        if (selected === null || selected === undefined || selected === '') {
            return [];
        }
        var single = parseInt(selected, 10);
        return single > 0 ? [single] : [];
    }

    function getCheckedTicketTypeIds() {
        var box = document.getElementById('edsTicketTypeChecks');
        if (!box) {
            return [];
        }
        var ids = [];
        box.querySelectorAll('input[type="checkbox"]:checked').forEach(function (cb) {
            var v = parseInt(cb.value, 10);
            if (v > 0) {
                ids.push(v);
            }
        });
        return ids;
    }

    function syncTicketTypeOptions(types, selected) {
        var box = document.getElementById('edsTicketTypeChecks');
        if (!box) {
            return;
        }
        var selectedIds = normalizeSelectedTicketIds(selected);
        var list = types || [];
        box.innerHTML = '';
        if (!list.length) {
            var empty = document.createElement('p');
            empty.className = 'eds-tt-checks__empty';
            empty.id = 'edsTicketTypeChecksEmpty';
            empty.textContent = 'No ticket types yet — create one below.';
            box.appendChild(empty);
            return;
        }
        list.forEach(function (t) {
            var id = parseInt(t.id, 10);
            if (!id) {
                return;
            }
            var price = t.price != null ? parseFloat(t.price) : 0;
            var label = document.createElement('label');
            label.className = 'eds-tt-check';
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.value = String(id);
            cb.checked = selectedIds.indexOf(id) !== -1;
            var span = document.createElement('span');
            span.className = 'eds-tt-check__text';
            span.textContent = (t.name || 'Ticket') + (price > 0 ? ' — ₱' + price.toFixed(2) : ' — Free');
            label.appendChild(cb);
            label.appendChild(span);
            box.appendChild(label);
        });
    }

    function updateAccessModeUi() {
        var modeEl = document.getElementById('edsAccessMode');
        var wrap = document.getElementById('edsTicketTypeWrap');
        if (!modeEl || !wrap) {
            return;
        }
        var showTicket = modeEl.value === 'ticket_required';
        wrap.style.display = showTicket ? '' : 'none';
        if (!showTicket) {
            toggleInlineTicketType(false);
        }
    }

    function applyTicketTypesFromResponse(data) {
        if (data && data.ticket_types) {
            state.ticketTypes = data.ticket_types;
            syncTicketTypeOptions(state.ticketTypes);
        }
    }

    function toggleInlineTicketType(show) {
        var panel = document.getElementById('edsTicketTypeInline');
        var toggle = document.getElementById('edsTicketTypeAddToggle');
        if (!panel) {
            return;
        }
        panel.style.display = show ? '' : 'none';
        if (toggle) {
            toggle.style.display = show ? 'none' : '';
        }
        if (!show) {
            ['edsNewTypeName', 'edsNewTypePrice', 'edsNewTypeQty'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) {
                    el.value = '';
                }
            });
            var err = document.getElementById('edsNewTypeError');
            if (err) {
                err.style.display = 'none';
                err.textContent = '';
            }
        } else {
            var nameEl = document.getElementById('edsNewTypeName');
            if (nameEl) {
                nameEl.focus();
            }
        }
    }

    function submitNewTicketType() {
        var nameEl = document.getElementById('edsNewTypeName');
        var priceEl = document.getElementById('edsNewTypePrice');
        var qtyEl = document.getElementById('edsNewTypeQty');
        var errEl = document.getElementById('edsNewTypeError');
        var saveBtn = document.getElementById('edsNewTypeSave');
        var name = nameEl ? String(nameEl.value || '').trim() : '';
        function showErr(msg) {
            if (errEl) {
                errEl.textContent = msg;
                errEl.style.display = '';
            }
        }
        if (!name) {
            showErr('Ticket name is required.');
            if (nameEl) {
                nameEl.focus();
            }
            return;
        }
        if (!state.eventId) {
            showErr('Missing event.');
            return;
        }
        if (errEl) {
            errEl.style.display = 'none';
            errEl.textContent = '';
        }
        if (saveBtn) {
            saveBtn.disabled = true;
        }
        var body = new FormData();
        body.append('action', 'create_ticket_type');
        body.append('event_id', String(state.eventId));
        body.append('type_name', name);
        body.append('type_price', priceEl ? (priceEl.value || '0') : '0');
        body.append('type_quantity', qtyEl ? (qtyEl.value || '') : '');
        body.append('csrf_token', csrfToken());
        fetch(baseUrl() + '/backend/auth/event_day_sessions_api.php', {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        })
            .then(function (r) {
                return r.text().then(function (text) {
                    try {
                        return JSON.parse(text);
                    } catch (err) {
                        throw new Error('Server error while adding ticket type.');
                    }
                });
            })
            .then(function (data) {
                if (saveBtn) {
                    saveBtn.disabled = false;
                }
                if (!data.ok) {
                    showErr(data.error || 'Could not add ticket type.');
                    return;
                }
                if (data.ticket_types) {
                    state.ticketTypes = data.ticket_types;
                }
                var keep = getCheckedTicketTypeIds();
                if (data.new_type_id) {
                    keep.push(parseInt(data.new_type_id, 10));
                }
                syncTicketTypeOptions(state.ticketTypes, keep);
                toggleInlineTicketType(false);
            })
            .catch(function (err) {
                if (saveBtn) {
                    saveBtn.disabled = false;
                }
                showErr(err && err.message ? err.message : 'Could not add ticket type.');
            });
    }

    function initInlineTicketTypeCreate() {
        var toggle = document.getElementById('edsTicketTypeAddToggle');
        var cancel = document.getElementById('edsNewTypeCancel');
        var save = document.getElementById('edsNewTypeSave');
        if (toggle) {
            toggle.addEventListener('click', function () {
                toggleInlineTicketType(true);
            });
        }
        if (cancel) {
            cancel.addEventListener('click', function () {
                toggleInlineTicketType(false);
            });
        }
        if (save) {
            save.addEventListener('click', submitNewTicketType);
        }
    }

    var locationPickerInstance = null;

    function geocodeBase() {
        return global.EVENTIFY_GEOCODE_URL || (baseUrl() + '/backend/auth/geocode_proxy.php');
    }

    function destroyLocationPicker() {
        if (locationPickerInstance && locationPickerInstance.map) {
            try {
                locationPickerInstance.map.remove();
            } catch (err) {
                /* ignore */
            }
        }
        locationPickerInstance = null;
    }

    function initLocationPicker() {
        destroyLocationPicker();
        if (!global.EVENTIFY_SESSIONS_HAVE_GEO) {
            return;
        }
        if (typeof global.initEventLocationPicker !== 'function' || !global.L) {
            return;
        }
        if (!document.getElementById('edsLocationMap')) {
            return;
        }
        locationPickerInstance = global.initEventLocationPicker({
            mapElId: 'edsLocationMap',
            latInputId: 'edsLatitude',
            lngInputId: 'edsLongitude',
            addressInputId: 'edsLocation',
            searchInputId: 'edsLocSearch',
            searchBtnId: 'edsLocSearchBtn',
            useLocationBtnId: 'edsLocUseGps',
            resultsElId: 'edsLocResults',
            geocodeBase: geocodeBase()
        });
    }

    function applySessionCoordsToMap(session) {
        if (!locationPickerInstance || !locationPickerInstance.setCoords) {
            return;
        }
        var lat = session.latitude != null ? parseFloat(session.latitude) : NaN;
        var lng = session.longitude != null ? parseFloat(session.longitude) : NaN;
        if (!isNaN(lat) && !isNaN(lng)) {
            locationPickerInstance.setCoords(lat, lng, true);
        }
    }

    function getManageEls() {
        return {
            modal: document.getElementById('eventDaySessionsModal'),
            list: document.getElementById('eventDaySessionsList'),
            dateLabel: document.getElementById('eventDaySessionsDateLabel'),
            form: document.getElementById('eventDaySessionForm'),
            formTitle: document.getElementById('eventDaySessionFormTitle'),
            sessionId: document.getElementById('edsSessionId'),
            title: document.getElementById('edsTitle'),
            location: document.getElementById('edsLocation'),
            latitude: document.getElementById('edsLatitude'),
            longitude: document.getElementById('edsLongitude'),
            startTime: document.getElementById('edsStartTime'),
            endTime: document.getElementById('edsEndTime'),
            category: document.getElementById('edsCategory'),
            status: document.getElementById('edsStatus'),
            maxCapacity: document.getElementById('edsMaxCapacity'),
            contactName: document.getElementById('edsContactName'),
            contactPhone: document.getElementById('edsContactPhone'),
            notes: document.getElementById('edsNotes'),
            sortOrder: document.getElementById('edsSortOrder'),
            accessMode: document.getElementById('edsAccessMode'),
            ticketChecks: document.getElementById('edsTicketTypeChecks'),
            printBtn: document.getElementById('edsPrintScheduleBtn'),
            hubBtn: document.getElementById('edsActivitiesHubBtn'),
            cancelEdit: document.getElementById('edsCancelEditBtn')
        };
    }

    function resetExtraFields(els) {
        if (els.category) {
            els.category.value = '';
        }
        if (els.status) {
            els.status.value = 'scheduled';
        }
        if (els.maxCapacity) {
            els.maxCapacity.value = '';
        }
        if (els.contactName) {
            els.contactName.value = '';
        }
        if (els.contactPhone) {
            els.contactPhone.value = '';
        }
        if (els.notes) {
            els.notes.value = '';
        }
        if (els.sortOrder) {
            els.sortOrder.value = '0';
        }
        if (els.accessMode) {
            els.accessMode.value = 'open';
        }
        syncTicketTypeOptions(state.ticketTypes, []);
        updateAccessModeUi();
    }

    function nextSortOrderForDay() {
        var max = 0;
        (state.sessions || []).forEach(function (s) {
            var v = parseInt(s.sort_order, 10);
            if (!isNaN(v) && v > max) {
                max = v;
            }
        });
        return max + 1;
    }

    function applyDefaultSortOrder() {
        if (state.editingId) {
            return;
        }
        var el = document.getElementById('edsSortOrder');
        if (el) {
            el.value = String(nextSortOrderForDay());
        }
    }

    function resetForm() {
        var els = getManageEls();
        state.editingId = null;
        if (els.sessionId) {
            els.sessionId.value = '';
        }
        if (els.formTitle) {
            els.formTitle.textContent = 'Add activity';
        }
        if (els.title) {
            els.title.value = '';
        }
        if (els.location) {
            els.location.value = '';
        }
        if (els.latitude) {
            els.latitude.value = '';
        }
        if (els.longitude) {
            els.longitude.value = '';
        }
        if (els.startTime) {
            els.startTime.value = '';
        }
        if (els.endTime) {
            els.endTime.value = '';
        }
        resetExtraFields(els);
        if (els.cancelEdit) {
            els.cancelEdit.style.display = 'none';
        }
        var search = document.getElementById('edsLocSearch');
        if (search) {
            search.value = '';
        }
        var results = document.getElementById('edsLocResults');
        if (results) {
            results.innerHTML = '';
            results.style.display = 'none';
        }
        if (locationPickerInstance && locationPickerInstance.setCoords) {
            locationPickerInstance.setCoords(11.244, 125.004, false);
        }
        applyDefaultSortOrder();
        syncScheduleDatePicker();
    }

    function fillForm(session) {
        var els = getManageEls();
        state.editingId = session.id;
        if (els.sessionId) {
            els.sessionId.value = String(session.id);
        }
        if (els.formTitle) {
            els.formTitle.textContent = 'Edit activity';
        }
        if (els.title) {
            els.title.value = session.title || '';
        }
        if (els.location) {
            els.location.value = session.location || '';
        }
        if (els.latitude) {
            els.latitude.value = session.latitude != null && session.latitude !== '' ? String(session.latitude) : '';
        }
        if (els.longitude) {
            els.longitude.value = session.longitude != null && session.longitude !== '' ? String(session.longitude) : '';
        }
        if (els.startTime) {
            els.startTime.value = session.start_time ? String(session.start_time).slice(0, 5) : '';
        }
        if (els.endTime) {
            els.endTime.value = session.end_time ? String(session.end_time).slice(0, 5) : '';
        }
        if (els.category) {
            els.category.value = session.category || '';
        }
        if (els.status) {
            els.status.value = session.status || 'scheduled';
        }
        if (els.maxCapacity) {
            els.maxCapacity.value = session.max_capacity ? String(session.max_capacity) : '';
        }
        if (els.contactName) {
            els.contactName.value = session.contact_name || '';
        }
        if (els.contactPhone) {
            els.contactPhone.value = session.contact_phone || '';
        }
        if (els.notes) {
            els.notes.value = session.notes || '';
        }
        if (els.sortOrder) {
            els.sortOrder.value = session.sort_order != null ? String(session.sort_order) : '0';
        }
        if (els.accessMode) {
            var mode = String(session.access_mode || 'open').toLowerCase();
            if (mode === 'ticket_required') {
                els.accessMode.value = 'ticket_required';
            } else if (mode === 'free') {
                els.accessMode.value = 'free';
            } else {
                els.accessMode.value = 'open';
            }
        }
        var selectedTicketIds = (session.ticket_type_ids && session.ticket_type_ids.length)
            ? session.ticket_type_ids
            : (session.ticket_type_id || []);
        syncTicketTypeOptions(state.ticketTypes, selectedTicketIds);
        updateAccessModeUi();
        if (els.cancelEdit) {
            els.cancelEdit.style.display = 'inline-block';
        }
        applySessionCoordsToMap(session);
        syncScheduleDatePicker();
    }

    function refreshManageList() {
        var els = getManageEls();
        if (els.list) {
            els.list.innerHTML = renderSessionsList(state.sessions, {
                canEditSchedule: state.scheduleEditable !== false,
                showOrganizerTools: true
            });
            bindManageListActions();
        }
        refreshDetailsPanel();
        syncScheduleLockUi();
    }

    function refreshDetailsPanel() {
        var panel = document.getElementById('eventDaySessionsPanel');
        var listEl = document.getElementById('eventDaySessionsPreview');
        var dayLbl = document.getElementById('eventDaySessionsDayLabel');
        var manageBtn = document.getElementById('eventDaySessionsManageBtn');
        if (!panel || !listEl) {
            return;
        }
        if (!state.scheduleDate) {
            panel.style.display = 'none';
            return;
        }
        panel.style.display = 'block';
        if (dayLbl) {
            dayLbl.textContent = formatYmdLong(state.scheduleDate);
        }
        listEl.innerHTML = renderSessionsList(state.sessions, { canEdit: false, canRsvp: global.currentRole === 'student' });
        bindRsvpActions(listEl);
        if (manageBtn) {
            manageBtn.style.display = global.currentRole === 'organizer' ? 'inline-block' : 'none';
        }
        var hubLink = document.getElementById('eventDaySessionsHubLink');
        if (hubLink && state.eventId) {
            hubLink.href = baseUrl() + '/event_activities.php?id=' + encodeURIComponent(state.eventId);
            hubLink.style.display = 'inline';
        }
    }

    function bindRsvpActions(container) {
        if (!container) {
            return;
        }
        container.querySelectorAll('.js-eds-rsvp').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var sid = parseInt(btn.getAttribute('data-session-id'), 10);
                if (!sid) {
                    return;
                }
                showEdsConfirmModal({
                    title: 'Confirm RSVP',
                    message: 'Are you sure you want to register for this activity?',
                    confirmLabel: 'Yes, register',
                    confirmClass: 'btn-primary',
                    icon: 'fa-calendar-check'
                }).then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    btn.disabled = true;
                    postActivitySessionRsvp('rsvp', sid).then(function (data) {
                        btn.disabled = false;
                        if (!data.ok) {
                            showEdsRsvpError(data.error);
                            return;
                        }
                        refreshStudentRsvpLists(data);
                    }).catch(function () {
                        btn.disabled = false;
                        showEdsMessageModal('Could not RSVP. Please try again.', {
                            title: 'Activity RSVP',
                            icon: 'fa-exclamation-circle'
                        });
                    });
                });
            });
        });
        container.querySelectorAll('.js-eds-cancel-rsvp').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var sid = parseInt(btn.getAttribute('data-session-id'), 10);
                if (!sid) {
                    return;
                }
                showEdsConfirmModal({
                    title: 'Cancel RSVP',
                    message: 'Are you sure you want to cancel your RSVP for this activity?',
                    confirmLabel: 'Yes, cancel RSVP',
                    confirmClass: 'btn-danger',
                    icon: 'fa-user-minus'
                }).then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    btn.disabled = true;
                    postActivitySessionRsvp('cancel_rsvp', sid).then(function (data) {
                        btn.disabled = false;
                        if (!data.ok) {
                            showEdsMessageModal(data.error || 'Could not cancel RSVP.', {
                                title: 'Cancel RSVP',
                                icon: 'fa-exclamation-circle'
                            });
                            return;
                        }
                        refreshStudentRsvpLists(data);
                    }).catch(function () {
                        btn.disabled = false;
                        showEdsMessageModal('Could not cancel RSVP. Please try again.', {
                            title: 'Cancel RSVP',
                            icon: 'fa-exclamation-circle'
                        });
                    });
                });
            });
        });
    }

    function bindManageListActions() {
        var els = getManageEls();
        if (!els.list) {
            return;
        }
        els.list.querySelectorAll('.js-eds-edit').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var sid = parseInt(btn.getAttribute('data-session-id'), 10);
                var session = state.sessions.find(function (s) {
                    return s.id === sid;
                });
                if (session) {
                    fillForm(session);
                    if (els.form) {
                        els.form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                }
            });
        });
        els.list.querySelectorAll('.js-eds-delete').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var sid = parseInt(btn.getAttribute('data-session-id'), 10);
                if (!sid) {
                    return;
                }
                showEdsConfirmModal({
                    title: 'Remove activity',
                    message: 'Are you sure you want to remove this activity?',
                    confirmLabel: 'Yes, remove',
                    confirmClass: 'btn-danger',
                    icon: 'fa-trash-alt'
                }).then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    var body = new FormData();
                    body.append('action', 'delete');
                    body.append('event_id', String(state.eventId));
                    body.append('session_id', String(sid));
                    body.append('csrf_token', csrfToken());
                    fetch(baseUrl() + '/backend/auth/event_day_sessions_api.php', {
                        method: 'POST',
                        body: body,
                        credentials: 'same-origin'
                    })
                        .then(function (r) {
                            return r.json();
                        })
                        .then(function (data) {
                            if (!data.ok) {
                                showEdsMessageModal(data.error || 'Could not delete.', {
                                    title: 'Remove activity',
                                    icon: 'fa-exclamation-circle'
                                });
                                return;
                            }
                            return fetchSessions(state.eventId, state.scheduleDate);
                        })
                        .then(function (data) {
                            if (data && data.ok) {
                                state.sessions = data.sessions || [];
                                refreshManageList();
                                if (state.editingId === sid) {
                                    resetForm();
                                }
                            }
                        });
                });
            });
        });
    }

    function openManageModal() {
        var els = getManageEls();
        if (!els.modal || !state.eventId || !state.scheduleDate) {
            return;
        }
        setScheduleDate(state.scheduleDate, false);
        syncScheduleDatePicker();
        if (els.hubBtn && state.eventId) {
            els.hubBtn.href = baseUrl() + '/event_activities.php?id=' + encodeURIComponent(state.eventId);
            els.hubBtn.style.display = 'inline-block';
        }
        resetForm();
        fetchSessions(state.eventId, state.scheduleDate).then(function (data) {
            if (data && data.ok) {
                state.sessions = data.sessions || [];
                applyTicketTypesFromResponse(data);
                applyScheduleEditableFromResponse(data);
            } else if (data && data.error) {
                state.scheduleEditable = false;
                state.scheduleLockMessage = data.error;
            }
            refreshManageList();
            applyDefaultSortOrder();
        });
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(els.modal).show();
        }
    }

    function submitActivityForm(els) {
        if (state.scheduleEditable === false) {
            showEdsMessageModal(state.scheduleLockMessage || 'This schedule is read-only.', {
                title: 'Schedule locked',
                icon: 'fa-lock'
            });
            return;
        }
        var body = new FormData();
        body.append('action', 'save');
        body.append('event_id', String(state.eventId));
        body.append('schedule_date', state.scheduleDate);
        body.append('title', els.title ? els.title.value : '');
        body.append('location', els.location ? els.location.value : '');
        if (els.latitude && els.longitude) {
            body.append('latitude', els.latitude.value);
            body.append('longitude', els.longitude.value);
        }
        body.append('start_time', els.startTime ? els.startTime.value : '');
        body.append('end_time', els.endTime ? els.endTime.value : '');
        if (els.category) {
            body.append('category', els.category.value);
        }
        if (els.status) {
            body.append('status', els.status.value);
        }
        if (els.maxCapacity) {
            body.append('max_capacity', els.maxCapacity.value);
        }
        if (els.contactName) {
            body.append('contact_name', els.contactName.value);
        }
        if (els.contactPhone) {
            body.append('contact_phone', els.contactPhone.value);
        }
        if (els.notes) {
            body.append('notes', els.notes.value);
        }
        if (els.sortOrder) {
            body.append('sort_order', els.sortOrder.value || '0');
        }
        if (els.accessMode) {
            body.append('access_mode', els.accessMode.value || 'open');
        }
        if (els.accessMode && els.accessMode.value === 'ticket_required') {
            getCheckedTicketTypeIds().forEach(function (id) {
                body.append('ticket_type_ids[]', String(id));
            });
        }
        if (state.editingId) {
            body.append('session_id', String(state.editingId));
        }
        body.append('csrf_token', csrfToken());
        fetch(baseUrl() + '/backend/auth/event_day_sessions_api.php', {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        })
            .then(function (r) {
                return r.text().then(function (text) {
                    try {
                        return JSON.parse(text);
                    } catch (err) {
                        throw new Error(text && text.indexOf('<') >= 0
                            ? 'Server error while saving. Check PHP error log.'
                            : (text || 'Invalid server response.'));
                    }
                });
            })
            .then(function (data) {
                if (!data.ok) {
                    showEdsMessageModal(data.error || 'Could not save.', {
                        title: 'Save activity',
                        icon: 'fa-exclamation-circle'
                    });
                    return;
                }
                state.sessions = data.sessions || [];
                applyTicketTypesFromResponse(data);
                resetForm();
                refreshManageList();
                if (global.EVENTIFY_RELOAD_HUB_ON_SESSION_SAVE) {
                    window.location.reload();
                    return;
                }
            })
            .catch(function (err) {
                showEdsMessageModal(err && err.message ? err.message : 'Could not save activity.', {
                    title: 'Save activity',
                    icon: 'fa-exclamation-circle'
                });
            });
    }

    function initForm() {
        var els = getManageEls();
        if (els.modal) {
            els.modal.addEventListener('shown.bs.modal', function () {
                initLocationPicker();
            });
            els.modal.addEventListener('hidden.bs.modal', function () {
                destroyLocationPicker();
            });
        }
        if (!els.form) {
            return;
        }
        els.form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (state.scheduleEditable === false) {
                showEdsMessageModal(state.scheduleLockMessage || 'This schedule is read-only.', {
                    title: 'Schedule locked',
                    icon: 'fa-lock'
                });
                return;
            }
            if (global.EVENTIFY_SESSIONS_HAVE_GEO && els.latitude && els.longitude) {
                var lat = parseFloat(els.latitude.value);
                var lng = parseFloat(els.longitude.value);
                if (isNaN(lat) || isNaN(lng)) {
                    showEdsMessageModal('Please set the venue on the map, search for a place, or use your location.', {
                        title: 'Venue required',
                        icon: 'fa-map-marker-alt'
                    });
                    return;
                }
            }
            var isEdit = !!state.editingId;
            var activityName = els.title ? String(els.title.value || '').trim() : '';
            var confirmMessage = isEdit
                ? 'Are you sure you want to save your changes to this activity?'
                : (activityName
                    ? 'Are you sure you want to submit "' + activityName + '"?'
                    : 'Are you sure you want to submit this activity?');
            showEdsConfirmModal({
                title: isEdit ? 'Save changes' : 'Submit activity',
                message: confirmMessage,
                confirmLabel: isEdit ? 'Yes, save' : 'Yes, submit',
                confirmClass: 'btn-primary',
                icon: 'fa-save'
            }).then(function (ok) {
                if (!ok) {
                    return;
                }
                submitActivityForm(els);
            });
        });
        if (els.accessMode) {
            els.accessMode.addEventListener('change', updateAccessModeUi);
        }
        initInlineTicketTypeCreate();
        if (els.cancelEdit) {
            els.cancelEdit.addEventListener('click', resetForm);
        }
        var scheduleDateSel = document.getElementById('edsScheduleDate');
        if (scheduleDateSel) {
            scheduleDateSel.addEventListener('change', function () {
                if (state.editingId) {
                    scheduleDateSel.value = state.scheduleDate;
                    return;
                }
                setScheduleDate(scheduleDateSel.value, true);
            });
        }
        var manageBtn = document.getElementById('eventDaySessionsManageBtn');
        if (manageBtn) {
            manageBtn.addEventListener('click', openManageModal);
        }
    }

    function openManageForHub(eventId, scheduleDate) {
        state.eventId = parseInt(eventId, 10) || 0;
        state.scheduleDate = String(scheduleDate || '').slice(0, 10);
        var hubDates = global.__eahEventScheduleDates;
        if (Array.isArray(hubDates) && hubDates.length) {
            state.scheduleDates = hubDates.map(function (d) {
                return String(d).slice(0, 10);
            }).filter(Boolean);
        } else if (state.scheduleDate) {
            state.scheduleDates = [state.scheduleDate];
        } else {
            state.scheduleDates = [];
        }
        if (!state.eventId || !state.scheduleDate) {
            showEdsMessageModal('Choose an event day before adding an activity.', {
                title: 'Day required',
                icon: 'fa-calendar-day'
            });
            return;
        }
        if (global.__eahScheduleEditable === false && !global.__eahHasEditableScheduleDay) {
            showEdsMessageModal(global.__eahScheduleLockMessage || 'This schedule is read-only.', {
                title: 'Schedule locked',
                icon: 'fa-lock'
            });
            return;
        }
        openManageModal();
    }

    function loadForCalendarEvent(event, canEdit, clickContext) {
        state.eventId = parseEventId(event);
        state.scheduleDates = resolveEventScheduleDates(event);
        state.scheduleDate = parseClickedScheduleDate(event, clickContext);
        state.sessions = [];
        if (!state.eventId || !state.scheduleDate) {
            refreshDetailsPanel();
            return Promise.resolve();
        }
        return fetchSessions(state.eventId, state.scheduleDate).then(function (data) {
            if (data.ok) {
                state.sessions = data.sessions || [];
                applyTicketTypesFromResponse(data);
                applyScheduleEditableFromResponse(data);
            }
            refreshDetailsPanel();
        });
    }

    function studentSessionsCacheKey(eventId, scheduleDate) {
        return String(eventId) + ':' + String(scheduleDate || '');
    }

    function getStudentSessionsCache() {
        if (!global.__eventifyStudentSessionsCache) {
            global.__eventifyStudentSessionsCache = {};
        }
        return global.__eventifyStudentSessionsCache;
    }

    function renderStudentSessionsPreview(previewEl, sessions) {
        if (!previewEl) {
            return;
        }
        previewEl.innerHTML = renderSessionsList(sessions || [], { canEdit: false, canRsvp: true });
        bindRsvpActions(previewEl);
    }

    function appendStudentSessionsBlock(bodyEl, eventLike, clickContext) {
        if (!bodyEl) {
            return Promise.resolve();
        }
        var eventId = parseEventId(eventLike);
        var scheduleDate = parseClickedScheduleDate(eventLike, clickContext);
        if (!eventId || !scheduleDate) {
            return Promise.resolve();
        }

        var cacheKey = studentSessionsCacheKey(eventId, scheduleDate);
        var cache = getStudentSessionsCache();
        var block = document.getElementById('studentDaySessionsBlock');
        if (!block) {
            bodyEl.insertAdjacentHTML(
                'beforeend',
                '<div class="event-day-sessions-panel mt-3" id="studentDaySessionsBlock"' +
                ' data-event-id="' + escapeHtml(String(eventId)) + '"' +
                ' data-schedule-date="' + escapeHtml(scheduleDate) + '">' +
                '<strong class="small text-uppercase text-muted d-block mb-1">Activities on this day</strong>' +
                '<div class="small fw-semibold mb-2" id="studentDaySessionsDateLabel">' + escapeHtml(formatYmdLong(scheduleDate)) + '</div>' +
                '<div id="studentDaySessionsPreview" class="student-day-sessions-preview" aria-live="polite">' +
                '<span class="text-muted small">Loading…</span></div></div>'
            );
            block = document.getElementById('studentDaySessionsBlock');
        } else {
            block.setAttribute('data-event-id', String(eventId));
            block.setAttribute('data-schedule-date', scheduleDate);
            var dateLbl = document.getElementById('studentDaySessionsDateLabel');
            if (dateLbl) {
                dateLbl.textContent = formatYmdLong(scheduleDate);
            }
        }

        var preview = document.getElementById('studentDaySessionsPreview');
        if (!preview) {
            return Promise.resolve();
        }

        if (cache[cacheKey]) {
            renderStudentSessionsPreview(preview, cache[cacheKey]);
            return Promise.resolve();
        }

        if (!global.__eventifyStudentSessionsInflight) {
            global.__eventifyStudentSessionsInflight = {};
        }
        if (global.__eventifyStudentSessionsInflight[cacheKey]) {
            return global.__eventifyStudentSessionsInflight[cacheKey];
        }

        var hadContent = preview.querySelector('.eds-empty-day, .eds-session-list');
        if (!hadContent) {
            preview.innerHTML = '<span class="text-muted small">Loading…</span>';
        }

        var request = fetchSessions(eventId, scheduleDate).then(function (data) {
            delete global.__eventifyStudentSessionsInflight[cacheKey];
            var prev = document.getElementById('studentDaySessionsPreview');
            var activeBlock = document.getElementById('studentDaySessionsBlock');
            if (!prev || !activeBlock) {
                return;
            }
            if (activeBlock.getAttribute('data-event-id') !== String(eventId) ||
                activeBlock.getAttribute('data-schedule-date') !== scheduleDate) {
                return;
            }
            if (data.ok) {
                cache[cacheKey] = data.sessions || [];
                renderStudentSessionsPreview(prev, cache[cacheKey]);
            } else {
                prev.innerHTML = '<p class="text-muted small mb-0">Could not load activities.</p>';
            }
        }).catch(function () {
            delete global.__eventifyStudentSessionsInflight[cacheKey];
            var prev = document.getElementById('studentDaySessionsPreview');
            if (prev) {
                prev.innerHTML = '<p class="text-muted small mb-0">Could not load activities.</p>';
            }
        });

        global.__eventifyStudentSessionsInflight[cacheKey] = request;
        return request;
    }

    document.addEventListener('DOMContentLoaded', initForm);
    document.addEventListener('DOMContentLoaded', initActivitiesHubRsvp);

    global.eventifyLoadDaySessionsForEvent = loadForCalendarEvent;
    global.eventifyOpenDaySessionsManager = openManageModal;
    global.eventifyOpenDaySessionsManage = openManageForHub;
    global.eventifyAppendStudentDaySessions = appendStudentSessionsBlock;
    global.eventifyResolveStudentDaySessionsDate = parseClickedScheduleDate;
    global.showEdsMessageModal = showEdsMessageModal;
    global.showEdsRsvpError = showEdsRsvpError;
    global.showEdsConfirmModal = showEdsConfirmModal;
    global.postActivitySessionRsvp = postActivitySessionRsvp;
    global.initActivitiesHubRsvp = initActivitiesHubRsvp;
    global.edsForceModalCleanupIfIdle = edsForceModalCleanupIfIdle;
    global.edsForceHideHelperModals = edsForceHideHelperModals;
})(typeof window !== 'undefined' ? window : this);
