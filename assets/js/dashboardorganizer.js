// Global calendar instance
let calendar = null;
const EVENTIFY_ROLE = (window.currentRole || 'organizer').toLowerCase();

function eventifyCalendarDayHeaderFormat() {
    if (window.matchMedia('(max-width: 480px)').matches) {
        return { weekday: 'narrow' };
    }
    if (window.matchMedia('(max-width: 768px)').matches) {
        return { weekday: 'short' };
    }
    return { weekday: 'long' };
}

function eventifyApplyCalendarDayHeaderFormat() {
    if (!calendar || typeof calendar.setOption !== 'function') {
        return;
    }
    calendar.setOption('dayHeaderFormat', eventifyCalendarDayHeaderFormat());
    try {
        calendar.updateSize();
    } catch (e) { /* ignore */ }
}

/** Sidebar filter: event row matches selected department (supports JSON multi-audience). */
function eventifyEventDeptMatchesFilter(eventDept, filterDept) {
    const f = String(filterDept || 'ALL').trim();
    const ev = String(eventDept || 'ALL').trim();
    if (f === 'ALL' || f === '') {
        return true;
    }
    if (ev === '' || ev === 'ALL') {
        return true;
    }
    if (ev === f) {
        return true;
    }
    if (ev.charAt(0) === '[') {
        try {
            const arr = JSON.parse(ev);
            if (Array.isArray(arr)) {
                return arr.indexOf(f) !== -1;
            }
        } catch (e) {
            /* ignore */
        }
    }
    return false;
}

/** Section filter: All = everything; specific = events that target that section label. */
function eventifyEventSectionMatchesFilter(targetSections, filterSection) {
    const f = String(filterSection || 'ALL').trim();
    if (f === 'ALL' || f === '') {
        return true;
    }
    const fKey = f.toLowerCase();
    const raw = targetSections == null ? '' : String(targetSections).trim();
    if (raw === '') {
        // College/school-wide event (no section lock) — hide when filtering to one section
        return false;
    }
    let list = [];
    if (raw.charAt(0) === '[') {
        try {
            const arr = JSON.parse(raw);
            if (Array.isArray(arr)) {
                list = arr.map(function (x) { return String(x || '').trim(); }).filter(Boolean);
            }
        } catch (e) {
            list = [raw];
        }
    } else {
        list = [raw];
    }
    return list.some(function (lab) {
        return lab.toLowerCase() === fKey;
    });
}

function eventifyOrganizerCalendarUsesAutoHeight(viewType) {
    var vt = String(viewType || '').toLowerCase();
    return vt === 'daygridmonth' || vt.indexOf('daygrid') === 0;
}

function eventifyOrganizerSyncCalendarLayout(viewType) {
    if (EVENTIFY_ROLE !== 'organizer') {
        return;
    }
    var isMonth = eventifyOrganizerCalendarUsesAutoHeight(viewType);
    document.body.classList.toggle('organizer-cal--month', isMonth);
    document.body.classList.toggle('organizer-cal--time', !isMonth);
    if (!calendar) {
        return;
    }
    calendar.setOption('height', isMonth ? 'auto' : '100%');
    try {
        calendar.updateSize();
    } catch (e) { /* ignore */ }
}

function eventifyOrganizerTodayYmd() {
    const d = new Date();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return d.getFullYear() + '-' + m + '-' + day;
}

function eventifyFormatStoredDeptForModal(stored) {
    const d = String(stored || 'ALL').trim();
    if (d === '' || d === 'ALL') {
        return 'All Departments';
    }
    if (d.charAt(0) === '[') {
        try {
            const arr = JSON.parse(d);
            if (Array.isArray(arr) && arr.length) {
                return arr.join(' · ');
            }
        } catch (e) {
            /* ignore */
        }
    }
    return d;
}

/**
 * Fill event details modal from a FullCalendar EventApi (shared: calendar click, admin upcoming list).
 */
function eventifyFillAndShowEventDetails(event, options) {
    if (!event) {
        return;
    }
    options = options || {};
    const props = event.extendedProps || {};
    const realEventId = props.event_id || (function () {
        const id = String(event.id || '');
        const dash = id.indexOf('-');
        return dash > 0 ? id.slice(0, dash) : id;
    })();

    const titleEl = document.getElementById('eventTitle');
    if (titleEl) {
        titleEl.textContent = event.title || 'Untitled event';
    }

    let dateStr = '';
    const dateCell = document.getElementById('eventDate');
    if (dateCell) {
        if (typeof eventifyRenderEventScheduleInto === 'function') {
            eventifyRenderEventScheduleInto(dateCell, props, {
                start: event.start,
                end: event.end,
                allDay: event.allDay,
                startStr: event.startStr
            });
            dateStr = dateCell.textContent || '';
        } else {
            const startYmd = String(props.event_date_ymd || '').trim();
            const endYmd = String(props.event_end_ymd || props.event_date_ymd || '').trim();
            const dOpts = { year: 'numeric', month: 'short', day: 'numeric' };
            if (startYmd && endYmd && endYmd > startYmd) {
                const startD = new Date(startYmd + 'T12:00:00');
                const endD = new Date(endYmd + 'T12:00:00');
                dateStr = startD.toLocaleDateString(undefined, dOpts) + ' – ' + endD.toLocaleDateString(undefined, dOpts);
            } else if (event.start) {
                dateStr = event.start.toLocaleDateString(undefined, dOpts);
            }
            dateCell.textContent = dateStr || (event.startStr || '');
        }
    }

    const locEl = document.getElementById('eventLocation');
    if (locEl) {
        locEl.textContent = props.location || 'N/A';
    }
    const descEl = document.getElementById('eventDescription');
    if (descEl) {
        descEl.textContent = props.description || 'No description provided.';
    }

    const deptEl = document.getElementById('eventDepartment');
    if (deptEl) {
        const label = String(props.department_display || '').trim();
        const secLabel = String(props.sections_display || '').trim();
        let text = label || eventifyFormatStoredDeptForModal(props.department);
        if (secLabel) {
            if (!text || text === 'All Departments') {
                text = 'Section · ' + secLabel;
            } else {
                text = text + ' · Section ' + secLabel;
            }
        }
        deptEl.textContent = text;
    }

    const orgEl = document.getElementById('eventOrganizer');
    if (orgEl) {
        orgEl.textContent = props.organizer || 'N/A';
    }

    const attWrap = document.getElementById('eventAttendanceSummaryWrap');
    const rsvpEl = document.getElementById('eventRsvpCount');
    const checkinEl = document.getElementById('eventCheckinCount');
    if (attWrap) {
        const showAtt = EVENTIFY_ROLE === 'admin' || EVENTIFY_ROLE === 'super_admin';
        if (showAtt) {
            const rsvp = parseInt(props.rsvp_count, 10);
            const checkin = parseInt(props.checkin_count, 10);
            if (rsvpEl) {
                rsvpEl.textContent = String(Number.isFinite(rsvp) ? rsvp : 0);
            }
            if (checkinEl) {
                checkinEl.textContent = String(Number.isFinite(checkin) ? checkin : 0);
            }
            attWrap.style.display = 'block';
        } else {
            attWrap.style.display = 'none';
        }
    }

    const statusEl = document.getElementById('eventStatus');
    const status = (props.status || 'active').toLowerCase();
    const eventIsLive = props.event_is_live === true;
    if (statusEl) {
        if (status === 'active' && eventIsLive) {
            statusEl.textContent = 'Active';
            statusEl.className = 'badge bg-success';
        } else if (status === 'active' && !eventIsLive) {
            statusEl.textContent = 'Ended';
            statusEl.className = 'badge bg-warning text-dark';
        } else if (status === 'rejected') {
            statusEl.textContent = 'Rejected';
            statusEl.className = 'badge bg-danger';
        } else if (status === 'pending') {
            statusEl.textContent = 'Pending';
            statusEl.className = 'badge bg-warning text-dark';
        } else if (status === 'closed' || status === 'completed') {
            statusEl.textContent = 'Closed';
            statusEl.className = 'badge bg-secondary';
        } else {
            statusEl.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            statusEl.className = 'badge bg-secondary';
        }
    }

    const otpWrap = document.getElementById('eventOtpVerifyWrap');
    const otpEventIdInput = document.getElementById('eventOtpEventId');
    const otpCodeInput = document.getElementById('eventOtpCodeInput');
    const otpForm = document.getElementById('eventOtpVerifyForm');
    const otpWaitingHint = document.getElementById('eventOtpWaitingHint');
    const otpVerifyHint = document.getElementById('eventOtpVerifyHint');
    if (otpWrap && otpEventIdInput) {
        const showOtpVerify = status === 'pending' && !!realEventId;
        const hasActiveOtp = props.has_active_otp === true;
        otpWrap.style.display = showOtpVerify ? 'block' : 'none';
        otpEventIdInput.value = showOtpVerify ? String(realEventId) : '';
        if (otpWaitingHint) {
            otpWaitingHint.style.display = showOtpVerify && !hasActiveOtp ? 'block' : 'none';
        }
        if (otpVerifyHint) {
            otpVerifyHint.style.display = showOtpVerify && hasActiveOtp ? 'block' : 'none';
        }
        if (otpForm) {
            otpForm.style.display = showOtpVerify && hasActiveOtp ? 'flex' : 'none';
        }
        if (otpCodeInput) {
            otpCodeInput.value = '';
            otpCodeInput.disabled = !(showOtpVerify && hasActiveOtp);
        }
    }

    const rejectWrap = document.getElementById('eventRejectReasonWrap');
    const rejectReasonEl = document.getElementById('eventRejectReason');
    if (rejectWrap && rejectReasonEl) {
        const reason = (props.reject_reason || '').trim();
        if (status === 'rejected' && reason) {
            rejectReasonEl.textContent = reason;
            rejectWrap.style.display = 'block';
        } else {
            rejectWrap.style.display = 'none';
        }
    }

    const createdEl = document.getElementById('eventCreatedAt');
    if (createdEl) {
        createdEl.textContent = props.created_at || 'N/A';
    }

    const editLink = document.getElementById('eventEditLink');
    if (editLink) {
        if (props.editUrl) {
            editLink.href = props.editUrl;
            editLink.style.display = 'inline-block';
        } else {
            editLink.style.display = 'none';
        }
    }

    const isStaffAdmin = EVENTIFY_ROLE === 'admin' || EVENTIFY_ROLE === 'super_admin';
    const closeBtn = document.getElementById('adminCloseEventBtn');
    if (closeBtn) {
        const canClose = isStaffAdmin && (props.admin_can_close === true || String(status).toLowerCase() === 'active');
        closeBtn.style.display = canClose && realEventId ? 'inline-block' : 'none';
        closeBtn.disabled = !(canClose && realEventId);
        closeBtn.onclick = canClose && realEventId ? function () {
            const idInput = document.getElementById('adminCloseEventId');
            const msg = document.getElementById('adminCloseEventMessage');
            if (idInput) idInput.value = String(realEventId);
            if (msg) {
                msg.textContent = 'Close "' + (event.title || 'this event') + '"? It stays in history (RSVPs / attendance) but leaves the live student calendar.';
            }
            const detailsModal = document.getElementById('eventDetailsModal');
            if (detailsModal && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(detailsModal).hide();
            }
            const closeModal = document.getElementById('adminCloseEventModal');
            if (closeModal && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(closeModal).show();
            }
        } : null;
    }

    const deleteBtn = document.getElementById('adminDeleteEventBtn');
    if (deleteBtn) {
        const canDelete = isStaffAdmin && props.admin_can_delete !== false && !!realEventId;
        deleteBtn.style.display = canDelete ? 'inline-block' : 'none';
        deleteBtn.disabled = !canDelete;
        deleteBtn.onclick = canDelete ? function () {
            const idInput = document.getElementById('adminDeleteEventId');
            const msg = document.getElementById('adminDeleteEventMessage');
            const impactEl = document.getElementById('adminDeleteEventImpact');
            const confirmInput = document.getElementById('adminDeleteConfirmInput');
            const submitBtn = document.getElementById('adminDeleteEventSubmit');
            const forceWrap = document.getElementById('adminDeleteForceWrap');
            const forceCb = document.getElementById('adminDeleteForceRevenue');
            if (idInput) idInput.value = String(realEventId);
            if (msg) {
                msg.textContent = 'Permanently delete "' + (event.title || 'this event') + '"? Use this for duplicate bookings or mistakes.';
            }
            if (impactEl) {
                const rsvp = Number(props.rsvp_count || 0);
                const checkins = Number(props.checkin_count || 0);
                const bits = [];
                bits.push(rsvp > 0 ? (rsvp + ' RSVP record' + (rsvp === 1 ? '' : 's') + ' will be removed') : 'No RSVPs on file');
                bits.push(checkins > 0 ? (checkins + ' check-in record' + (checkins === 1 ? '' : 's') + ' will be removed') : 'No check-ins on file');
                bits.push('Prefer Close if you only want it off the live calendar');
                impactEl.innerHTML = bits.map(function (b) { return '<li>' + b + '</li>'; }).join('');
            }
            if (confirmInput) confirmInput.value = '';
            if (submitBtn) submitBtn.disabled = true;
            const isPaid = String(props.registration_mode || '').toLowerCase() === 'paid_ticket';
            if (forceWrap) forceWrap.style.display = isPaid ? 'block' : 'none';
            if (forceCb) forceCb.checked = false;
            const detailsModal = document.getElementById('eventDetailsModal');
            if (detailsModal && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(detailsModal).hide();
            }
            const delModal = document.getElementById('adminDeleteEventModal');
            if (delModal && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(delModal).show();
            }
        } : null;
    }

    const qrLink = document.getElementById('eventQrLink');
    if (qrLink) {
        if (realEventId && eventIsLive) {
            qrLink.href = BASE_URL + '/event_qr.php?id=' + realEventId;
            qrLink.style.display = 'inline-block';
            qrLink.classList.remove('disabled');
            qrLink.setAttribute('aria-disabled', 'false');
            qrLink.title = '';
        } else if (realEventId) {
            qrLink.style.display = 'inline-block';
            qrLink.href = '#';
            qrLink.classList.add('disabled');
            qrLink.setAttribute('aria-disabled', 'true');
            qrLink.title = 'Event ended — QR check-in is disabled';
        } else {
            qrLink.style.display = 'none';
        }
    }

    const attendanceLink = document.getElementById('eventAttendanceLink');
    if (attendanceLink) {
        if (realEventId) {
            attendanceLink.href = BASE_URL + '/event_attendance.php?id=' + realEventId;
            attendanceLink.style.display = 'inline-block';
        } else {
            attendanceLink.style.display = 'none';
        }
    }

    const rsvpLink = document.getElementById('eventRsvpLink');
    if (rsvpLink) {
        if (realEventId) {
            rsvpLink.href = BASE_URL + '/event_rsvp.php?id=' + realEventId;
            rsvpLink.style.display = 'inline-block';
        } else {
            rsvpLink.style.display = 'none';
        }
    }

    const ticketsLink = document.getElementById('eventTicketsLink');
    if (ticketsLink) {
        if (realEventId) {
            ticketsLink.href = BASE_URL + '/manage_event_tickets.php?event_id=' + realEventId;
            ticketsLink.style.display = 'inline-block';
            ticketsLink.classList.remove('btn-outline-success', 'btn-outline-secondary');
            if (eventIsLive) {
                ticketsLink.classList.add('btn-outline-success');
                ticketsLink.innerHTML = '<i class="fas fa-ticket-alt me-1"></i> Ticket sales';
                ticketsLink.title = 'Enable paid tickets, add ticket types, confirm payments';
            } else {
                ticketsLink.classList.add('btn-outline-secondary');
                ticketsLink.innerHTML = '<i class="fas fa-ticket-alt me-1"></i> Tickets (closed)';
                ticketsLink.title = 'View ticket history — sales closed for ended events';
            }
        } else {
            ticketsLink.style.display = 'none';
        }
    }

    const regBadgeEl = document.getElementById('eventRegistrationModeBadge');
    const regHintEl = document.getElementById('eventRegistrationHint');
    const regMode = String(props.registration_mode || 'rsvp').toLowerCase();
    if (regBadgeEl && typeof eventifyRegistrationModeBadgeHtml === 'function') {
        regBadgeEl.innerHTML = eventifyRegistrationModeBadgeHtml(regMode);
    }
    if (regHintEl) {
        if (regMode === 'paid_ticket') {
            regHintEl.textContent = '— manage types and payments via Ticket sales below';
        } else if (regMode === 'open') {
            regHintEl.textContent = '— walk-in; students scan the venue QR (no RSVP)';
        } else {
            regHintEl.textContent = '— students RSVP on their dashboard before check-in';
        }
    }

    const activitiesHubLink = document.getElementById('eventActivitiesHubLink');
    if (activitiesHubLink) {
        if (realEventId) {
            activitiesHubLink.href = BASE_URL + '/event_activities.php?id=' + realEventId;
            activitiesHubLink.style.display = 'inline-block';
        } else {
            activitiesHubLink.style.display = 'none';
        }
    }

    const markBtn = document.getElementById('organizerMarkEndedBtn');
    if (markBtn) {
        const canMarkEnded =
            EVENTIFY_ROLE === 'organizer' &&
            status === 'active';
        if (canMarkEnded && realEventId) {
            markBtn.style.display = 'inline-block';
            markBtn.setAttribute('data-eventify-event-id', String(realEventId));
        } else {
            markBtn.style.display = 'none';
            markBtn.setAttribute('data-eventify-event-id', '');
        }
    }

    if (typeof eventifyLoadDaySessionsForEvent === 'function') {
        eventifyLoadDaySessionsForEvent(event, EVENTIFY_ROLE === 'organizer', {
            clickEl: options.clickEl || null,
            jsEvent: options.jsEvent || null
        });
    } else {
        const panel = document.getElementById('eventDaySessionsPanel');
        if (panel) {
            panel.style.display = 'none';
        }
    }

    if (typeof eventifyCloseFullCalendarPopovers === 'function') {
        eventifyCloseFullCalendarPopovers();
    }
    if (typeof edsForceHideHelperModals === 'function') {
        edsForceHideHelperModals();
    }

    const modalEl = document.getElementById('eventDetailsModal');
    if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const eventModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        eventModal.show();
    }
}

/**
 * Resolve a DB event id to a FullCalendar event or plain event payload from eventsData.
 */
function eventifyFindEventByDbId(eventId) {
    if (eventId == null || eventId === '') {
        return null;
    }
    var idStr = String(eventId);

    if (calendar) {
        var direct = calendar.getEventById(idStr);
        if (direct) {
            return direct;
        }
        var loaded = calendar.getEvents();
        for (var i = 0; i < loaded.length; i++) {
            var ev = loaded[i];
            var props = ev.extendedProps || {};
            if (String(props.event_id || '') === idStr) {
                return ev;
            }
        }
        for (var j = 0; j < loaded.length; j++) {
            var ev2 = loaded[j];
            var fcId = String(ev2.id || '');
            if (fcId === idStr || fcId.indexOf(idStr + '-') === 0) {
                return ev2;
            }
        }
    }

    if (window.eventsData && Array.isArray(window.eventsData)) {
        var dataMatch = window.eventsData.find(function (entry) {
            var props = entry.extendedProps || {};
            return String(entry.id) === idStr
                || String(props.event_id || '') === idStr
                || String(entry.id || '').indexOf(idStr + '-') === 0;
        });
        if (dataMatch) {
            return {
                id: dataMatch.id,
                title: dataMatch.title,
                start: dataMatch.start ? new Date(dataMatch.start) : null,
                end: dataMatch.end ? new Date(dataMatch.end) : null,
                allDay: !!dataMatch.allDay,
                startStr: dataMatch.start,
                extendedProps: dataMatch.extendedProps || {}
            };
        }
    }

    return null;
}

/**
 * Open event details from calendar by id (e.g. admin upcoming modal, notification action).
 */
function eventifyOpenEventDetailsById(eventId) {
    var ev = eventifyFindEventByDbId(eventId);
    if (!ev) {
        return false;
    }
    if (calendar) {
        try {
            var goto = ev.start || ev.startStr;
            if (goto) {
                calendar.gotoDate(goto);
            }
        } catch (err) { /* ignore */ }
    }
    eventifyFillAndShowEventDetails(ev);
    return true;
}

window.eventifyFillAndShowEventDetails = eventifyFillAndShowEventDetails;
window.eventifyOpenEventDetailsById = eventifyOpenEventDetailsById;

document.addEventListener('eventify:notif-view-event', function (e) {
    var detail = (e && e.detail) || {};
    if (!detail.eventId) {
        return;
    }
    setTimeout(function () {
        var opened = eventifyOpenEventDetailsById(String(detail.eventId));
        if (!opened && String(window.currentRole || '').toLowerCase() === 'organizer') {
            var base = (window.BASE_URL || '').replace(/\/$/, '');
            window.location.href = base + '/event_activities.php?id=' + encodeURIComponent(String(detail.eventId));
        }
    }, 200);
});

let currentDate = new Date();
let selectedDate = new Date(); // highlighted day in mini calendar
let fcDateClickTimers = {};
let selectedDepartment = (function () {
    var os = (typeof window !== 'undefined' && window.__organizerSettings) ? window.__organizerSettings : {};
    var d = String(os.default_department_filter != null ? os.default_department_filter : 'ALL').trim();
    if (!d) {
        d = 'ALL';
    }
    return d;
})();
let selectedSection = 'ALL';
let renderMiniCalendar = null; // Will be set by initMiniCalendar

function isSameDay(a, b) {
    return (
        a &&
        b &&
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate()
    );
}

function clearFcDateClickTimers() {
    Object.keys(fcDateClickTimers).forEach(function (key) {
        clearTimeout(fcDateClickTimers[key]);
        delete fcDateClickTimers[key];
    });
}

function attachMiniCalDayInteraction(dayEl, dateObj) {
    dayEl.addEventListener('click', function () {
        currentDate = dateObj;
        selectedDate = dateObj;
        if (calendar) {
            calendar.gotoDate(dateObj);
        }
        renderMiniCalendar();
    });
    dayEl.addEventListener('dblclick', function (e) {
        e.preventDefault();
        if (!isSameDay(dateObj, selectedDate)) {
            return;
        }
        selectedDate = null;
        if (calendar) {
            try {
                calendar.unselect();
            } catch (err) { /* ignore */ }
        }
        renderMiniCalendar();
    });
}

function initOrganizerSidebarToggle() {
    const toggle = document.getElementById('organizerSidebarToggle');
    const closeBtn = document.getElementById('organizerSidebarClose');
    const backdrop = document.getElementById('organizerSidebarBackdrop');
    const sidebar = document.getElementById('organizerSidebar');
    const isMobileView = () => window.matchMedia('(max-width: 768px)').matches;

    const refreshCalendarLayout = () => {
        if (!calendar) return;
        if (typeof calendar.updateSize === 'function') {
            calendar.updateSize();
        }
    };

    const refreshCalendarLayoutSmooth = () => {
        [0, 90, 180, 280, 360, 520, 680].forEach(function (ms) {
            setTimeout(refreshCalendarLayout, ms);
        });
    };

    const closeMobileSidebar = () => document.body.classList.remove('organizer-sidebar-open');

    if (toggle) {
        toggle.addEventListener('click', function () {
            if (isMobileView()) {
                document.body.classList.toggle('organizer-sidebar-open');
                return;
            }
            var collapsed = document.body.classList.toggle('organizer-sidebar-collapsed');
            if (sidebar) {
                sidebar.classList.toggle('is-collapsed', collapsed);
            }
            refreshCalendarLayoutSmooth();
        });
    }
    if (closeBtn) closeBtn.addEventListener('click', closeMobileSidebar);
    if (backdrop) backdrop.addEventListener('click', closeMobileSidebar);

    if (sidebar) {
        sidebar.addEventListener('transitionend', function (e) {
            if (e.propertyName === 'width' || e.propertyName === 'padding-left' || e.propertyName === 'padding-right'
                || e.propertyName === 'flex-basis' || e.propertyName === 'max-width') {
                refreshCalendarLayout();
            }
        });
        sidebar.addEventListener('click', function (e) {
            const target = e.target.closest('.action-btn, [data-bs-toggle="modal"]');
            if (target && isMobileView()) closeMobileSidebar();
        });
    }

    window.addEventListener('resize', function () {
        if (!isMobileView()) closeMobileSidebar();
        refreshCalendarLayoutSmooth();
    });
}

// Initialize on DOM ready
function initCreateEventDeptAudience() {
    const form = document.getElementById('createEventModalForm');
    if (!form) {
        return;
    }
    // create_event_modal.js owns create-event audience validation (efy alert + section-only).
    if (typeof window.initCreateEventModalScripts === 'function' || document.querySelector('script[src*="create_event_modal.js"]')) {
        return;
    }
    const allCb = document.getElementById('ceDeptAll');
    const specifics = form.querySelectorAll('.ce-dept-specific');
    if (allCb) {
        allCb.addEventListener('change', function () {
            if (allCb.checked) {
                specifics.forEach(function (cb) {
                    cb.checked = false;
                });
            }
        });
    }
    specifics.forEach(function (cb) {
        cb.addEventListener('change', function () {
            if (cb.checked && allCb) {
                allCb.checked = false;
            }
        });
    });
    form.addEventListener('submit', function (e) {
        const anySpecific = Array.from(specifics).some(function (c) {
            return c.checked;
        });
        const allOn = allCb && allCb.checked;
        const hasSection = form.querySelectorAll('input[name="section[]"]:checked').length > 0
            || !!(form.querySelector('input[name="new_section"]') && String(form.querySelector('input[name="new_section"]').value || '').trim());
        if (hasSection && allCb && allCb.checked) {
            allCb.checked = false;
        }
        if (!allOn && !anySpecific && !hasSection) {
            e.preventDefault();
            if (typeof window.eventifyAlert === 'function') {
                window.eventifyAlert('Choose All departments, pick at least one college, or select a class section.', {
                    title: 'Who can attend?',
                    type: 'warning'
                });
            } else {
                alert('Choose All departments, pick at least one college, or select a class section.');
            }
        }
    });
}

function eventifyOrganizerStatusUpdateUrl() {
    const b = String(typeof window.BASE_URL !== 'undefined' ? window.BASE_URL : '').replace(/\/+$/, '');
    return b + '/backend/auth/update_organizer_event_status.php';
}

let eventifyOrganizerStatusPending = null;

function eventifyOpenOrganizerEventStatusModal(opts) {
    eventifyOrganizerStatusPending = {
        action: opts.action,
        eventId: String(opts.eventId)
    };
    const titleEl = document.getElementById('organizerEventStatusConfirmTitle');
    const bodyEl = document.getElementById('organizerEventStatusConfirmBody');
    if (titleEl) {
        titleEl.textContent = opts.title || 'Confirm';
    }
    if (bodyEl) {
        bodyEl.textContent = opts.body || '';
    }
    const modalEl = document.getElementById('organizerEventStatusConfirmModal');
    if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
}

function initOrganizerEventStatusModal() {
    const statusModal = document.getElementById('organizerEventStatusConfirmModal');
    if (statusModal && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        statusModal.addEventListener('shown.bs.modal', function () {
            statusModal.style.zIndex = '2000';
            const backs = document.querySelectorAll('.modal-backdrop');
            backs.forEach(function (b, i) {
                if (i === backs.length - 1) {
                    b.style.zIndex = '1990';
                }
            });
        });
        statusModal.addEventListener('hidden.bs.modal', function () {
            statusModal.style.zIndex = '';
            document.querySelectorAll('.modal-backdrop').forEach(function (b) {
                b.style.zIndex = '';
            });
        });
    }

    const yesBtn = document.getElementById('organizerEventStatusConfirmYes');
    const form = document.getElementById('organizerEventStatusHiddenForm');
    if (yesBtn && form) {
        yesBtn.addEventListener('click', function () {
            if (!eventifyOrganizerStatusPending) {
                return;
            }
            const evIdInput = document.getElementById('organizerEventStatusHiddenEventId');
            const actInput = document.getElementById('organizerEventStatusHiddenAction');
            if (evIdInput) {
                evIdInput.value = eventifyOrganizerStatusPending.eventId;
            }
            if (actInput) {
                actInput.value = eventifyOrganizerStatusPending.action;
            }
            form.action = eventifyOrganizerStatusUpdateUrl();
            const confirmModal = document.getElementById('organizerEventStatusConfirmModal');
            if (confirmModal && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const inst = bootstrap.Modal.getInstance(confirmModal);
                if (inst) {
                    inst.hide();
                }
            }
            form.submit();
        });
    }

    document.body.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-organizer-event-status-btn');
        if (!btn) {
            return;
        }
        const action = btn.getAttribute('data-eventify-action') || '';
        const eventId = btn.getAttribute('data-eventify-event-id') || '';
        if (!eventId || (action !== 'close' && action !== 'cancel' && action !== 'reopen')) {
            return;
        }
        if (action === 'cancel') {
            eventifyOpenOrganizerEventStatusModal({
                action: 'cancel',
                eventId: eventId,
                title: 'Withdraw submission?',
                body: 'This event will no longer be pending approval.'
            });
        } else if (action === 'reopen') {
            eventifyOpenOrganizerEventStatusModal({
                action: 'reopen',
                eventId: eventId,
                title: 'Reopen this event?',
                body: 'Students will be able to check in, RSVP, and buy tickets again for all activities under this event.'
            });
        } else {
            eventifyOpenOrganizerEventStatusModal({
                action: 'close',
                eventId: eventId,
                title: 'End entire event?',
                body: 'This ends the whole main event — not just one activity. Check-in, RSVP, and ticket sales will stop for all activities. You can reopen later if this was a mistake.'
            });
        }
    });

    const markEndedBtn = document.getElementById('organizerMarkEndedBtn');
    if (markEndedBtn) {
        markEndedBtn.addEventListener('click', function () {
            const eventId = markEndedBtn.getAttribute('data-eventify-event-id') || '';
            if (!eventId) {
                return;
            }
            eventifyOpenOrganizerEventStatusModal({
                action: 'close',
                eventId: eventId,
                title: 'End entire event?',
                body: 'This ends the whole main event — not just one activity. Check-in, RSVP, and ticket sales will stop for all activities. You can reopen later if this was a mistake.'
            });
        });
    }
}

function initOrganizerFlashToast() {
    var flash = window.__organizerFlash;
    if (!flash || !flash.message) {
        return;
    }
    if (window.eventifyToast) {
        var type = flash.type || 'info';
        var show = window.eventifyToast[type] || window.eventifyToast.info;
        show.call(window.eventifyToast, flash.message, type === 'error' ? 6500 : 5200);
    }
    try {
        var url = new URL(window.location.href);
        var changed = false;
        if (url.searchParams.has('msg')) {
            url.searchParams.delete('msg');
            changed = true;
        }
        if (url.searchParams.has('error')) {
            url.searchParams.delete('error');
            changed = true;
        }
        if (changed) {
            var next = url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '') + url.hash;
            history.replaceState({}, '', next);
        }
    } catch (e) { /* ignore */ }
}

function organizerDashboardBaseUrl() {
    var base = (window.BASE_URL || '').replace(/\/$/, '');
    return base + '/backend/auth/dashboardorganizer.php';
}

function organizerDashboardPanelUrl(panel) {
    return organizerDashboardBaseUrl() + '?panel=' + encodeURIComponent(panel);
}

function organizerGetPanelFromUrl() {
    try {
        return new URLSearchParams(window.location.search).get('panel') || 'home';
    } catch (e) {
        return 'home';
    }
}

function organizerUpdatePanelUrl(panel, replace) {
    try {
        var url = new URL(window.location.href);
        if (!panel || panel === 'home') {
            url.searchParams.delete('panel');
        } else {
            url.searchParams.set('panel', panel);
        }
        var next = url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '') + url.hash;
        var state = { organizerPanel: panel || 'home' };
        if (replace) {
            history.replaceState(state, '', next);
        } else {
            history.pushState(state, '', next);
        }
    } catch (e) { /* ignore */ }
}

function organizerPanelElementId(panel) {
    if (panel === 'events') {
        return 'organizerMyEventsPanel';
    }
    if (panel === 'feedback') {
        return 'organizerFeedbackPanel';
    }
    return '';
}

function updateOrganizerSidebarActive(panel) {
    document.querySelectorAll('[data-organizer-panel]').forEach(function (el) {
        var p = el.getAttribute('data-organizer-panel');
        if (p !== 'events' && p !== 'feedback') {
            return;
        }
        if (!el.classList.contains('action-btn') && !el.classList.contains('dropdown-item')) {
            return;
        }
        el.classList.toggle('is-active', p === panel);
    });
}

function showOrganizerDashboardPanel(panel, options) {
    options = options || {};
    var panelName = !panel || panel === 'home' ? 'home' : panel;
    var home = document.getElementById('organizerDashboardHome');
    var panelIds = ['organizerMyEventsPanel', 'organizerFeedbackPanel'];
    var mainContent = document.querySelector('body.organizer-dashboard .main-content');

    if (panelName === 'home') {
        if (home) {
            home.classList.remove('d-none');
        }
        panelIds.forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.classList.add('d-none');
                el.setAttribute('hidden', '');
            }
        });
        updateOrganizerSidebarActive('');
        if (window.eventifyCalendar && typeof window.eventifyCalendar.updateSize === 'function') {
            [50, 180, 320].forEach(function (ms) {
                window.setTimeout(function () {
                    window.eventifyCalendar.updateSize();
                }, ms);
            });
        }
    } else {
        if (home) {
            home.classList.add('d-none');
        }
        panelIds.forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) {
                return;
            }
            var targetId = organizerPanelElementId(panelName);
            var show = id === targetId;
            el.classList.toggle('d-none', !show);
            if (show) {
                el.removeAttribute('hidden');
            } else {
                el.setAttribute('hidden', '');
            }
        });
        updateOrganizerSidebarActive(panelName);
    }

    if (!options.skipUrl) {
        organizerUpdatePanelUrl(panelName === 'home' ? '' : panelName, !!options.replaceUrl);
    }

    if (!options.skipAnimation && panelName !== 'home') {
        initOrganizerDashPanelEnter();
    }

    if (mainContent) {
        mainContent.scrollTop = 0;
    }
}

function organizerPrefersReducedMotion() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
}

function initOrganizerDashPanelEnter() {
    if (organizerPrefersReducedMotion()) {
        document.querySelectorAll('.org-dash-panel--enter').forEach(function (panel) {
            panel.classList.remove('org-dash-panel--enter');
        });
        return;
    }
    var panel = document.querySelector('.org-dash-panel:not(.d-none):not([hidden])');
    if (!panel) {
        return;
    }
    if (!panel.classList.contains('org-dash-panel--enter')) {
        panel.classList.add('org-dash-panel--enter');
    }
    window.setTimeout(function () {
        panel.classList.remove('org-dash-panel--enter');
    }, 1100);
}

function initOrganizerDashboardPanels() {
    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-organizer-panel]');
        if (!el) {
            return;
        }
        var panel = el.getAttribute('data-organizer-panel');
        if (!panel) {
            return;
        }
        if (el.tagName === 'A') {
            var href = el.getAttribute('href') || '';
            if (href && href !== '#') {
                try {
                    if (new URL(href, window.location.origin).pathname !== window.location.pathname) {
                        return;
                    }
                } catch (err) {
                    return;
                }
            }
        }
        e.preventDefault();
        showOrganizerDashboardPanel(panel);
    });

    window.addEventListener('popstate', function (ev) {
        var panel = (ev.state && ev.state.organizerPanel) || organizerGetPanelFromUrl();
        showOrganizerDashboardPanel(panel, { skipUrl: true });
    });
}

function initOrganizerDashboardPanelFromUrl() {
    var panel = organizerGetPanelFromUrl();
    if (panel === 'events' || panel === 'feedback') {
        showOrganizerDashboardPanel(panel, {
            replaceUrl: true,
            skipAnimation: !!document.querySelector('.org-dash-panel.org-dash-panel--enter')
        });
    } else {
        try {
            history.replaceState({ organizerPanel: 'home' }, '', window.location.href);
        } catch (e) { /* ignore */ }
    }
}

function initOrganizerReopenModalFromUrl() {
    var openModal = '';
    var eventId = '';
    try {
        var params = new URLSearchParams(window.location.search);
        openModal = params.get('open_modal') || '';
        eventId = params.get('event_id') || '';
    } catch (e) {
        return;
    }
    // Clean navigation params so a refresh doesn't keep reopening panels/modals.
    try {
        var url = new URL(window.location.href);
        url.searchParams.delete('open_modal');
        url.searchParams.delete('event_id');
        var next = url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '') + url.hash;
        history.replaceState({}, '', next);
    } catch (e) { /* ignore */ }

    if (openModal === 'eventDetails' && eventId && typeof window.eventifyOpenEventDetailsById === 'function') {
        setTimeout(function () {
            window.eventifyOpenEventDetailsById(eventId);
        }, 400);
        return;
    }

    if (!openModal || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        return;
    }
}

function initAdminEventDeleteConfirmGate() {
    var input = document.getElementById('adminDeleteConfirmInput');
    var submitBtn = document.getElementById('adminDeleteEventSubmit');
    var forceCb = document.getElementById('adminDeleteForceRevenue');
    var forceWrap = document.getElementById('adminDeleteForceWrap');
    if (!input || !submitBtn) {
        return;
    }
    function refresh() {
        var typedOk = String(input.value || '').trim() === 'DELETE';
        var forceNeeded = forceWrap && forceWrap.style.display !== 'none';
        var forceOk = !forceNeeded || (forceCb && forceCb.checked);
        submitBtn.disabled = !(typedOk && forceOk);
    }
    input.addEventListener('input', refresh);
    if (forceCb) {
        forceCb.addEventListener('change', refresh);
    }
    var modal = document.getElementById('adminDeleteEventModal');
    if (modal) {
        modal.addEventListener('shown.bs.modal', function () {
            input.focus();
            refresh();
        });
    }
    refresh();
}

document.addEventListener('DOMContentLoaded', function() {
    initOrganizerFlashToast();

    initOrganizerSidebarToggle();
    initMiniCalendar();
    initFullCalendar();
    initDepartmentFilter();
    initSectionFilter();
    initViewButtons();
    initCalendarNavigation();
    initOrganizerEventStatusModal();
    initAdminEventDeleteConfirmGate();
    initOrganizerDashboardPanels();
    initOrganizerDashboardPanelFromUrl();
    if (organizerGetPanelFromUrl() === 'events' || organizerGetPanelFromUrl() === 'feedback') {
        initOrganizerDashPanelEnter();
    }
    initOrganizerReopenModalFromUrl();

    var orgSettingsForm = document.getElementById('organizerSettingsForm');
    var orgSettingsBtn = document.getElementById('organizerSettingsUpdateBtn');
    var orgSettingsConfirmEl = document.getElementById('confirmOrganizerSettingsModal');
    var orgSettingsConfirmYes = document.getElementById('confirmOrganizerSettingsYes');
    if (orgSettingsForm && orgSettingsBtn && orgSettingsConfirmEl && orgSettingsConfirmYes && typeof bootstrap !== 'undefined') {
        var orgSettingsConfirmModal = bootstrap.Modal.getOrCreateInstance(orgSettingsConfirmEl);
        orgSettingsBtn.addEventListener('click', function () {
            orgSettingsConfirmModal.show();
        });
        orgSettingsConfirmYes.addEventListener('click', function () {
            orgSettingsConfirmModal.hide();
            orgSettingsForm.submit();
        });
    }

    var clearNotifModal = document.getElementById('organizerClearNotifsModal');
    if (clearNotifModal && typeof bootstrap !== 'undefined') {
        clearNotifModal.addEventListener('show.bs.modal', function () {
            document.querySelectorAll('.top-navbar .dropdown-menu.show').forEach(function (menu) {
                var toggle = menu.previousElementSibling;
                if (toggle && toggle.getAttribute('data-bs-toggle') === 'dropdown') {
                    var inst = bootstrap.Dropdown.getInstance(toggle);
                    if (inst) {
                        inst.hide();
                    }
                }
            });
        });
    }
});

// ===============================
// MINI CALENDAR
// ===============================
function initMiniCalendar() {
    const miniCalEl = document.getElementById('miniCalendar');
    const monthEl = document.getElementById('miniCalMonth');
    const prevBtn = document.getElementById('miniCalPrev');
    const nextBtn = document.getElementById('miniCalNext');

    if (!miniCalEl || !monthEl || !prevBtn || !nextBtn) return;

    renderMiniCalendar = function() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        
        // Update month display
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'];
        monthEl.textContent = `${monthNames[month]} ${year}`;

        // Clear previous content
        miniCalEl.innerHTML = '';

        // Day headers
        const dayHeaders = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
        dayHeaders.forEach(day => {
            const header = document.createElement('div');
            header.className = 'mini-cal-day-header';
            header.textContent = day;
            miniCalEl.appendChild(header);
        });

        // Get first day of month and number of days
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        const startingDayOfWeek = firstDay.getDay();

        // Previous month days
        const prevMonthLastDay = new Date(year, month, 0).getDate();
        for (let i = startingDayOfWeek - 1; i >= 0; i--) {
            const day = prevMonthLastDay - i;
            const dayEl = document.createElement('div');
            dayEl.className = 'mini-cal-day other-month';
            dayEl.textContent = day;
            
            // Make previous month days clickable
            attachMiniCalDayInteraction(dayEl, new Date(year, month - 1, day));

            if (isSameDay(new Date(year, month - 1, day), selectedDate)) {
                dayEl.classList.add('selected');
            }
            
            miniCalEl.appendChild(dayEl);
        }

        // Current month days
        const today = new Date();
        for (let day = 1; day <= daysInMonth; day++) {
            const dayEl = document.createElement('div');
            dayEl.className = 'mini-cal-day';
            dayEl.textContent = day;

            // Check if today
            if (year === today.getFullYear() && month === today.getMonth() && day === today.getDate()) {
                dayEl.classList.add('today');
            }

            // Click handler - navigate main calendar to this date
            attachMiniCalDayInteraction(dayEl, new Date(year, month, day));

            if (isSameDay(new Date(year, month, day), selectedDate)) {
                dayEl.classList.add('selected');
            }

            miniCalEl.appendChild(dayEl);
        }

        // Next month days
        const totalCells = 42; // 6 rows × 7 days
        const remainingCells = totalCells - (startingDayOfWeek + daysInMonth);
        for (let day = 1; day <= remainingCells; day++) {
            const dayEl = document.createElement('div');
            dayEl.className = 'mini-cal-day other-month';
            dayEl.textContent = day;
            
            // Make next month days clickable
            attachMiniCalDayInteraction(dayEl, new Date(year, month + 1, day));

            if (isSameDay(new Date(year, month + 1, day), selectedDate)) {
                dayEl.classList.add('selected');
            }
            
            miniCalEl.appendChild(dayEl);
        }
    }

    prevBtn.addEventListener('click', function() {
        currentDate.setMonth(currentDate.getMonth() - 1);
        if (calendar) {
            calendar.prev();
            // Sync with calendar's focus date (not range start)
            const focus = calendar.getDate ? calendar.getDate() : new Date();
            currentDate = new Date(focus);
            selectedDate = new Date(focus);
        }
        renderMiniCalendar();
    });

    nextBtn.addEventListener('click', function() {
        currentDate.setMonth(currentDate.getMonth() + 1);
        if (calendar) {
            calendar.next();
            // Sync with calendar's focus date (not range start)
            const focus = calendar.getDate ? calendar.getDate() : new Date();
            currentDate = new Date(focus);
            selectedDate = new Date(focus);
        }
        renderMiniCalendar();
    });

    // Initial render
    renderMiniCalendar();
}

// ===============================
// FULLCALENDAR INITIALIZATION
// ===============================
function initFullCalendar() {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

    const os = (EVENTIFY_ROLE === 'admin' ? window.__adminSettings : window.__organizerSettings) || {};
    const allowedViews = ['dayGridMonth', 'timeGridWeek', 'timeGridDay'];
    let initView = String(os.default_calendar_view || '').trim();
    if (!allowedViews.includes(initView)) {
        initView = 'dayGridMonth';
    }
    const deptPref = String(os.default_department_filter || '').trim();
    if (deptPref) {
        const matchEl = Array.from(document.querySelectorAll('.calendar-item[data-dept]')).find(function (el) {
            return (el.getAttribute('data-dept') || '') === deptPref;
        });
        if (matchEl) {
            selectedDepartment = deptPref;
        }
    }
    const showWeekends = !(os.show_weekends === 0 || os.show_weekends === false || String(os.show_weekends) === '0');
    const weekStartsOn = parseInt(os.week_starts_on, 10) === 1 ? 1 : 0;

    // Filter events by selected department + optional class section
    function getFilteredEvents() {
        if (!window.eventsData) return [];
        return window.eventsData.filter(function (event) {
            const dept = event.extendedProps?.department || 'ALL';
            const sections = event.extendedProps?.target_sections ?? '';
            return eventifyEventDeptMatchesFilter(dept, selectedDepartment)
                && eventifyEventSectionMatchesFilter(sections, selectedSection);
        });
    }

    var initMonthLayout = eventifyOrganizerCalendarUsesAutoHeight(initView);
    if (EVENTIFY_ROLE === 'organizer') {
        document.body.classList.toggle('organizer-cal--month', initMonthLayout);
        document.body.classList.toggle('organizer-cal--time', !initMonthLayout);
    }

    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: initView,
        initialDate: currentDate,
        selectable: true,
        selectMirror: true,
        dayMaxEvents: false,
        moreLinkClick: 'popover',
        eventOrder: 'start',
        headerToolbar: false, // We use custom controls
        events: getFilteredEvents(),
        eventDisplay: 'block',
        height: initMonthLayout ? 'auto' : '100%',
        expandRows: true,
        fixedWeekCount: false,
        slotMinTime: '06:00:00',
        slotMaxTime: '22:00:00',
        scrollTime: '07:00:00',
        slotEventOverlap: false,
        eventMaxStack: 4,
        dayHeaderFormat: eventifyCalendarDayHeaderFormat(),
        firstDay: weekStartsOn,
        weekends: showWeekends,
        nowIndicator: true,
        views: {
            dayGridMonth: {
                dayMaxEvents: false,
                dayMaxEventRows: false,
                expandRows: true
            },
            timeGridWeek: {
                dayMaxEvents: 3,
                eventMaxStack: 4
            },
            timeGridDay: {
                eventMaxStack: 6
            }
        },
        eventTimeFormat: {
            hour: 'numeric',
            minute: '2-digit',
            omitZeroMinute: false,
            meridiem: 'short'
        },
        eventContent: function (arg) {
            if (typeof eventifyCalendarEventContent === 'function') {
                var custom = eventifyCalendarEventContent(arg);
                if (custom !== true) {
                    return custom;
                }
            }
            return true;
        },

        // Click empty date -> create event (organizer only)
        dateClick: function(info) {
            if (EVENTIFY_ROLE !== 'organizer') {
                return;
            }
            var dateStr = info.dateStr;
            clearTimeout(fcDateClickTimers[dateStr]);
            fcDateClickTimers[dateStr] = setTimeout(function () {
                delete fcDateClickTimers[dateStr];
                window.location.href = BASE_URL + "/backend/auth/createevent.php?date=" + dateStr;
            }, 220);
        },

        // Click existing event -> show details modal
        eventClick: function(info) {
            eventifyFillAndShowEventDetails(info.event, {
                clickEl: info.el,
                jsEvent: info.jsEvent
            });
            info.jsEvent.preventDefault();
        },

        eventDidMount: function (info) {
            if (typeof eventifyApplyCalendarEventMount === 'function') {
                eventifyApplyCalendarEventMount(info);
            }
        },

        // Update title when view changes and sync mini calendar
        datesSet: function(info) {
            updateCalendarTitle(info);
            // IMPORTANT: FullCalendar's info.start is the start of the visible range
            // (can be previous month). Use the calendar "focus" date instead.
            const focus = calendar.getDate ? calendar.getDate() : new Date();
            currentDate = new Date(focus);
            selectedDate = new Date(focus);
            // Update mini calendar to match main calendar focus date
            if (renderMiniCalendar) {
                renderMiniCalendar();
            }
            requestAnimationFrame(function () {
                try { calendar.updateSize(); } catch (e) { /* ignore */ }
            });
            eventifyOrganizerSyncCalendarLayout(calendar.view ? calendar.view.type : initView);
        }
    });

    calendar.render();
    window.eventifyCalendar = calendar;
    eventifyOrganizerSyncCalendarLayout(initView);

    if (EVENTIFY_ROLE === 'admin') {
        [0, 80, 240, 480].forEach(function (ms) {
            setTimeout(function () {
                try {
                    calendar.updateSize();
                } catch (e) { /* ignore */ }
            }, ms);
        });
    }

    window.addEventListener('resize', eventifyApplyCalendarDayHeaderFormat);

    if (typeof eventifyBindCalendarDoubleClickUnselect === 'function') {
        eventifyBindCalendarDoubleClickUnselect(calendar, calendarEl, {
            clearPendingClicks: clearFcDateClickTimers,
            onClear: function () {
                selectedDate = null;
                if (renderMiniCalendar) {
                    renderMiniCalendar();
                }
            }
        });
    }

    var orgCalContainer = calendarEl.closest('.calendar-container');
    if (typeof eventifyBindCalendarScrollFix === 'function') {
        eventifyBindCalendarScrollFix(calendar, orgCalContainer);
    }
    if (typeof eventifyBindCalendarSegmentRepaint === 'function') {
        eventifyBindCalendarSegmentRepaint(calendar, calendarEl);
    }

    document.querySelectorAll('.calendar-item[data-dept]').forEach(function (i) {
        i.classList.toggle('active', (i.getAttribute('data-dept') || '') === selectedDepartment);
    });
    document.querySelectorAll('.calendar-item[data-section]').forEach(function (i) {
        i.classList.toggle('active', (i.getAttribute('data-section') || '') === selectedSection);
    });
    var secSel = document.getElementById('orgSectionFilter') || document.getElementById('adminSectionFilter');
    if (secSel) {
        secSel.value = selectedSection;
    }
    document.querySelectorAll('.view-btn').forEach(function (b) {
        const v = b.getAttribute('data-view');
        b.classList.toggle('active', v === initView && v !== 'today');
    });

    // Force initial sync (removes the hardcoded placeholder "September 2026")
    const focus = calendar.getDate ? calendar.getDate() : new Date();
    currentDate = new Date(focus);
    selectedDate = new Date(focus);
    if (renderMiniCalendar) renderMiniCalendar();

    // Update events when department filter changes
    window.updateCalendarEvents = function() {
        calendar.removeAllEvents();
        calendar.addEventSource(getFilteredEvents());
    };
}

// (Modal calendar removed; main calendar stays in dashboard)

// ===============================
// CALENDAR TITLE UPDATE
// ===============================
function updateCalendarTitle(info) {
    const titleEl = document.getElementById('calendarTitle');
    if (!titleEl || !calendar) return;

    // Always use FullCalendar's own computed title (prevents off-by-one / range-start issues)
    titleEl.textContent = calendar.view?.title || '';
}

// ===============================
// DEPARTMENT FILTER
// ===============================
function initDepartmentFilter() {
    const calendarItems = document.querySelectorAll('.calendar-item[data-dept]');
    
    calendarItems.forEach(item => {
        item.addEventListener('click', function() {
            // Remove active class from all department items
            calendarItems.forEach(i => i.classList.remove('active'));
            
            // Add active to clicked
            this.classList.add('active');
            
            // Update selected department
            selectedDepartment = this.getAttribute('data-dept') || 'ALL';
            
            // Update calendar events
            if (window.updateCalendarEvents) {
                window.updateCalendarEvents();
            }
        });
    });
}

// ===============================
// SECTION FILTER
// ===============================
function initSectionFilter() {
    function applySection(value) {
        selectedSection = String(value || 'ALL').trim() || 'ALL';
        document.querySelectorAll('.calendar-item[data-section]').forEach(function (i) {
            i.classList.toggle('active', (i.getAttribute('data-section') || '') === selectedSection);
        });
        var sel = document.getElementById('orgSectionFilter') || document.getElementById('adminSectionFilter');
        if (sel && sel.value !== selectedSection) {
            sel.value = selectedSection;
        }
        if (window.updateCalendarEvents) {
            window.updateCalendarEvents();
        }
    }

    document.querySelectorAll('.calendar-item[data-section]').forEach(function (item) {
        item.addEventListener('click', function () {
            applySection(this.getAttribute('data-section') || 'ALL');
        });
    });

    var sel = document.getElementById('orgSectionFilter') || document.getElementById('adminSectionFilter');
    if (sel) {
        sel.addEventListener('change', function () {
            applySection(this.value);
        });
    }
}

// ===============================
// VIEW BUTTONS
// ===============================
function initViewButtons() {
    const viewButtons = document.querySelectorAll('.view-btn');
    
    viewButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const view = this.getAttribute('data-view');
            
            // Remove active from all
            viewButtons.forEach(b => b.classList.remove('active'));
            
            // Add active to clicked
            this.classList.add('active');
            
            // Handle "Today" button
            if (view === 'today') {
                calendar.today();
                const focus = calendar.getDate ? calendar.getDate() : new Date();
                currentDate = new Date(focus);
                selectedDate = new Date(focus);
                if (renderMiniCalendar) renderMiniCalendar();
            } else {
                // Change view
                calendar.changeView(view);
                eventifyOrganizerSyncCalendarLayout(view);
            }
        });
    });
}

// ===============================
// CALENDAR NAVIGATION
// ===============================
function initCalendarNavigation() {
    const prevBtn = document.getElementById('calPrev');
    const nextBtn = document.getElementById('calNext');
    
    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            calendar.prev();
            const focus = calendar.getDate ? calendar.getDate() : new Date();
            currentDate = new Date(focus);
            selectedDate = new Date(focus);
            // Update mini calendar
            if (renderMiniCalendar) {
                renderMiniCalendar();
            }
        });
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            calendar.next();
            const focus = calendar.getDate ? calendar.getDate() : new Date();
            currentDate = new Date(focus);
            selectedDate = new Date(focus);
            // Update mini calendar
            if (renderMiniCalendar) {
                renderMiniCalendar();
            }
        });
    }
}

// Get BASE_URL from window or set default
const BASE_URL = window.BASE_URL || '';

// ===============================
// ORGANIZER PROFILE
// ===============================
function previewOrganizerProfilePicture(input) {
    const preview = document.getElementById('organizerProfilePicturePreview');
    if (!preview) return;
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            if (preview.tagName === 'IMG') {
                preview.src = e.target.result;
            } else {
                const img = document.createElement('img');
                img.id = 'organizerProfilePicturePreview';
                img.className = 'organizer-profile-picture-preview';
                img.alt = 'Preview';
                img.src = e.target.result;
                preview.parentNode.replaceChild(img, preview);
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function eventifyEscapeHtml(text) {
    return String(text || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function buildOrganizerProfileChangeLines(form) {
    const lines = [];
    const trim = (v) => String(v || '').trim();
    const initialName = trim(form.dataset.initialName);
    const initialMethod = trim(form.dataset.initialContactMethod || 'email');
    const initialEmail = trim(form.dataset.initialContactEmail);
    const initialPhone = trim(form.dataset.initialPhone);

    const name = trim((form.querySelector('input[name="name"]') || {}).value);
    const method = trim((form.querySelector('#organizerContactMethod') || {}).value || 'email');
    const email = trim((form.querySelector('#organizerContactEmail') || {}).value);
    const phone = trim((form.querySelector('#organizerPhone') || {}).value);
    const fileInput = form.querySelector('input[name="profile_picture"]');
    const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;

    if (name !== initialName) {
        lines.push('Display name to <strong>' + eventifyEscapeHtml(name) + '</strong>');
    }
    if (method !== initialMethod) {
        lines.push('OTP verification method to <strong>' + eventifyEscapeHtml(method === 'phone' ? 'Phone number' : 'Email') + '</strong>');
    }
    if (email !== initialEmail) {
        lines.push('Verification email to <strong>' + eventifyEscapeHtml(email) + '</strong>');
    }
    if (phone !== initialPhone) {
        lines.push('Verification phone to <strong>' + eventifyEscapeHtml(phone) + '</strong>');
    }
    if (hasFile) {
        lines.push('Upload a new profile picture');
    }
    return lines;
}

function confirmOrganizerProfileChanges(form) {
    const lines = buildOrganizerProfileChangeLines(form);
    const messageEl = document.getElementById('confirmOrganizerProfileMessage');
    if (messageEl) {
        if (lines.length === 0) {
            messageEl.textContent = 'No changes detected. Save anyway?';
        } else if (lines.length === 1) {
            messageEl.innerHTML = '<p class="mb-0">You are about to update your ' + lines[0] + '.</p>';
        } else {
            messageEl.innerHTML =
                '<p class="mb-2">You are about to save these changes:</p>' +
                '<ul class="mb-0 ps-3">' +
                lines.map(function (line) { return '<li>' + line + '</li>'; }).join('') +
                '</ul>';
        }
    }
    const modalEl = document.getElementById('confirmOrganizerProfileModal');
    if (!modalEl) {
        form.submit();
        return;
    }
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
    const confirmBtn = document.getElementById('confirmOrganizerProfileBtn');
    if (confirmBtn) {
        confirmBtn.onclick = function () {
            modal.hide();
            form.submit();
        };
    }
}
