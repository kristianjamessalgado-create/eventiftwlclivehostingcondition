// Global calendar instance
let calendar = null;
let currentDate = new Date();
let renderMiniCalendar = null; // Will be set by initMiniCalendar

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    initStudentOpenModalFromUrl();
    initStudentSettings();
    initStudentCourseDepartmentDisplay();
    initMiniCalendar();
    initFullCalendar();
    initViewButtons();
    initTopCalendarShortcut();
    initCalendarNavigation();
    initMobileSidebar();
    initStudentUpcomingEventClicks();
    initUrgentFeedbackPrompt();
    initCancelRsvpConfirmModal();
    initRegisterRsvpConfirmModal();
    initStudentNotificationHooks();
    initStudentEventDetailsModalCleanup();
    initStudentEvaluationChoiceDelegation();
    initStudentMobileNav();
    initStudentDashboardPanels();
    initStudentDashboardPanelFromUrl();
    var studentUrlState = studentGetPanelStateFromUrl();
    if (studentUrlState.panel && studentUrlState.panel !== 'home') {
        initStudentDashPanelEnter();
    }
    initStudentDashboardHashScroll();
    initStudentPhotoGalleryViewer();
});

function jumpStudentCalendarToToday(options) {
    if (!calendar) return;
    var opts = options || {};
    calendar.today();
    const focus = calendar.getDate ? calendar.getDate() : new Date();
    currentDate = new Date(focus);
    selectedDate = new Date(focus);
    if (renderMiniCalendar) renderMiniCalendar();

    if (opts.syncActiveButton) {
        const viewButtons = document.querySelectorAll('.view-btn');
        viewButtons.forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-view') === 'today');
        });
    }
}

function initTopCalendarShortcut() {
    const topCalendarBtn = document.getElementById('topCalendarShortcutBtn');
    const controlsEl = document.querySelector('.calendar-controls');
    if (!topCalendarBtn) return;

    topCalendarBtn.addEventListener('click', function () {
        jumpStudentCalendarToToday({ syncActiveButton: true });
        if (controlsEl && typeof controlsEl.scrollIntoView === 'function') {
            controlsEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
}

function initStudentCourseDepartmentDisplay() {
    var courseEl = document.getElementById('studentCourseModal');
    var deptEl = document.getElementById('studentDepartmentModal');
    if (!courseEl || !deptEl) return;
    var map = window.__studentCourseDepartmentMap || {};
    var syncDept = function () {
        var course = String(courseEl.value || '');
        var dept = String(map[course] || '').trim();
        deptEl.value = dept || 'Department will be set from selected course';
    };
    courseEl.addEventListener('change', syncDept);
    syncDept();
}

function initStudentOpenModalFromUrl() {
    var openModal = String(window.__studentOpenModal || '').toLowerCase();
    if (!openModal || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        return;
    }
    var targetId = null;
    if (openModal === 'change_password') {
        targetId = 'studentChangePasswordModal';
    } else if (openModal === 'settings') {
        targetId = 'settingsModal';
    } else if (openModal === 'scan') {
        targetId = 'scanQRModal';
    }
    if (!targetId) {
        return;
    }
    var modalEl = document.getElementById(targetId);
    if (!modalEl) {
        return;
    }
    setTimeout(function () {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }, 180);
}

function initStudentSettings() {
    var form = document.getElementById('studentSettingsForm');
    var updateBtn = document.getElementById('studentSettingsUpdateBtn');
    var confirmModalEl = document.getElementById('confirmStudentSettingsModal');
    var confirmYesBtn = document.getElementById('confirmStudentSettingsYesBtn');
    var settings = window.__studentSettings || {};
    var legend = document.getElementById('studentCalendarLegend');

    if (legend && Number(settings.show_calendar_legend || 0) !== 1) {
        legend.style.display = 'none';
    }

    if (!form || !updateBtn || !confirmModalEl || !confirmYesBtn || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        return;
    }

    var confirmModal = bootstrap.Modal.getOrCreateInstance(confirmModalEl);
    updateBtn.addEventListener('click', function () {
        confirmModal.show();
    });
    confirmYesBtn.addEventListener('click', function () {
        confirmModal.hide();
        form.submit();
    });

    document.querySelectorAll('.password-toggle-btn[data-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-target') || '';
            var input = targetId ? document.getElementById(targetId) : null;
            if (!input) return;
            var icon = btn.querySelector('i');
            var isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            if (icon) {
                icon.classList.toggle('fa-eye', !isHidden);
                icon.classList.toggle('fa-eye-slash', isHidden);
            }
        });
    });

}

function initRegisterRsvpConfirmModal() {
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (!form.classList.contains('js-register-rsvp-form')) return;
        e.preventDefault();
        var eventId = form.querySelector('input[name="event_id"]');
        eventId = eventId ? eventId.value : '';
        if (!eventId) return;

        function submitAjax() {
            var submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;
            postMainEventRsvpAjax(
                getStudentDashboardBase() + '/backend/auth/register_event_rsvp.php',
                eventId
            ).then(function (data) {
                if (submitBtn) submitBtn.disabled = false;
                handleMainEventRsvpResponse(data, eventId);
            }).catch(function () {
                if (submitBtn) submitBtn.disabled = false;
                showStudentRsvpNotice('Could not register. Please try again.', 'fa-exclamation-circle');
            });
        }

        if (typeof showEdsConfirmModal === 'function') {
            showEdsConfirmModal({
                title: 'Confirm registration',
                message: 'Are you sure you want to register for this event?',
                confirmLabel: 'Yes, register',
                confirmClass: 'btn-primary',
                icon: 'fa-user-plus'
            }).then(function (ok) {
                if (ok) submitAjax();
            });
            return;
        }
        form.submit();
    });
}

function initCancelRsvpConfirmModal() {
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
    var modalEl = document.getElementById('cancelRsvpConfirmModal');
    var yesBtn = document.getElementById('cancelRsvpConfirmYesBtn');
    if (!modalEl || !yesBtn) return;

    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var pendingForm = null;

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (!form.classList.contains('js-cancel-rsvp-form')) return;
        e.preventDefault();
        pendingForm = form;
        modal.show();
    });

    yesBtn.addEventListener('click', function () {
        if (!pendingForm) return;
        var form = pendingForm;
        pendingForm = null;
        modal.hide();
        var eventInput = form.querySelector('input[name="event_id"]');
        var eventId = eventInput ? eventInput.value : '';
        if (!eventId) return;
        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;
        postMainEventRsvpAjax(
            getStudentDashboardBase() + '/backend/auth/cancel_event_rsvp.php',
            eventId
        ).then(function (data) {
            if (submitBtn) submitBtn.disabled = false;
            handleMainEventRsvpResponse(data, eventId);
        }).catch(function () {
            if (submitBtn) submitBtn.disabled = false;
            showStudentRsvpNotice('Could not cancel RSVP. Please try again.', 'fa-exclamation-circle');
        });
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        pendingForm = null;
    });
}

function getStudentDashboardBase() {
    return (window.BASE_URL || '').replace(/\/$/, '');
}

function postMainEventRsvpAjax(url, eventId) {
    var body = new FormData();
    body.append('ajax', '1');
    body.append('csrf_token', window.csrfToken || '');
    body.append('event_id', String(eventId));
    return fetch(url, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) {
        return r.json();
    });
}

function patchStudentEventRegistration(eventId, patch) {
    var eid = String(eventId);
    (window.studentEvents || []).forEach(function (ev) {
        if (!ev) return;
        var props = ev.extendedProps || {};
        var id = String(props.event_id || String(ev.id || '').split('-')[0]);
        if (id === eid) {
            if (!ev.extendedProps) ev.extendedProps = {};
            Object.assign(ev.extendedProps, patch);
        }
    });
}

function refreshOpenStudentEventDetails(eventId) {
    var current = window.__studentEventDetailsCurrent;
    if (!current) return;
    var props = current.extendedProps || {};
    var id = String(props.event_id || String(current.id || '').split('-')[0]);
    if (id !== String(eventId)) return;
    var events = window.studentEvents || [];
    var updated = events.find(function (e) {
        var p = e.extendedProps || {};
        return String(p.event_id || String(e.id || '').split('-')[0]) === id;
    });
    if (updated) {
        showStudentEventDetails(updated, { contentOnly: true, preserveSessions: true });
    }
}

function showStudentRsvpNotice(message, icon) {
    if (typeof showEdsMessageModal === 'function') {
        showEdsMessageModal(message, { title: 'Event RSVP', icon: icon || 'fa-info-circle' });
    }
}

function handleMainEventRsvpResponse(data, eventId) {
    if (!data) return;
    if (typeof data.is_registered === 'boolean' || data.registration_count != null) {
        patchStudentEventRegistration(eventId, {
            is_registered: !!data.is_registered,
            registration_count: parseInt(data.registration_count, 10) || 0
        });
    }
    if (data.ok) {
        refreshOpenStudentEventDetails(eventId);
        return;
    }
    if (data.is_registered) {
        refreshOpenStudentEventDetails(eventId);
    }
    showStudentRsvpNotice(data.message || 'Request failed.', 'fa-exclamation-circle');
}

function fetchStudentEventRsvpStatus(eventId) {
    var base = getStudentDashboardBase();
    return fetch(base + '/backend/auth/student_event_rsvp_status.php?event_id=' + encodeURIComponent(String(eventId)), {
        credentials: 'same-origin'
    }).then(function (r) {
        return r.json();
    });
}

function buildStudentEventFooterRsvpHtml(opts) {
    opts = opts || {};
    var eventId = opts.eventId;
    var csrf = opts.csrf || '';
    var base = opts.base || '';
    var isRegistered = !!opts.isRegistered;
    var isFull = !!opts.isFull;
    var allowsMainRsvp = opts.allowsMainRsvp !== false;

    if (!allowsMainRsvp) {
        if (isRegistered) {
            return '<span class="btn btn-success btn-sm disabled pe-none" style="opacity:1" aria-disabled="true">' +
                '<i class="fas fa-check-circle me-1"></i>RSVP confirmed</span>';
        }
        return '';
    }
    if (isRegistered) {
        var html = '<span class="btn btn-success btn-sm disabled pe-none me-1" style="opacity:1" aria-disabled="true">' +
            '<i class="fas fa-check-circle me-1"></i>RSVP confirmed</span>';
        if (eventId && csrf) {
            html += '<form method="post" action="' + escapeHtmlStudent(base + '/backend/auth/cancel_event_rsvp.php') + '" class="d-inline js-cancel-rsvp-form">' +
                '<input type="hidden" name="csrf_token" value="' + escapeHtmlStudent(csrf) + '">' +
                '<input type="hidden" name="event_id" value="' + escapeHtmlStudent(String(eventId)) + '">' +
                '<button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-user-minus me-1"></i>Cancel RSVP</button>' +
                '</form>';
        }
        return html;
    }
    if (isFull) {
        return '<span class="text-muted small">No spots available</span>';
    }
    if (eventId && csrf) {
        return '<form method="post" action="' + escapeHtmlStudent(base + '/backend/auth/register_event_rsvp.php') + '" class="d-inline js-register-rsvp-form">' +
            '<input type="hidden" name="csrf_token" value="' + escapeHtmlStudent(csrf) + '">' +
            '<input type="hidden" name="event_id" value="' + escapeHtmlStudent(String(eventId)) + '">' +
            '<button type="submit" class="btn btn-success btn-sm"><i class="fas fa-user-plus me-1"></i>RSVP for this event</button>' +
            '</form>';
    }
    return '';
}

function initStudentEventDetailsModalCleanup() {
    var modalEl = document.getElementById('eventDetailsModal');
    if (!modalEl) return;
    modalEl.addEventListener('hidden.bs.modal', function () {
        if (typeof edsForceModalCleanupIfIdle === 'function') {
            edsForceModalCleanupIfIdle();
        }
    });
}

function studentEventAllowsMainRsvp(props) {
    var regMode = String((props && props.registration_mode) || 'rsvp').toLowerCase();
    if (regMode === 'paid_ticket' || regMode === 'open') {
        return false;
    }
    if (props && props.event_allows_rsvp === false) {
        return false;
    }
    if (props && props.event_allows_rsvp === true) {
        return String((props.status) || '').toLowerCase() === 'active';
    }
    var st = String((props && props.status) || '').toLowerCase();
    if (st !== 'active') {
        return false;
    }
    var endYmd = String((props && props.event_end_ymd) || (props && props.event_date_ymd) || '').trim();
    var today = todayYmdLocal();
    if (endYmd !== '' && endYmd < today) {
        return false;
    }
    return true;
}

function normalizeEvaluationSections(sections) {
    if (!sections) {
        return [];
    }
    if (Array.isArray(sections)) {
        return sections;
    }
    if (typeof sections === 'object') {
        return Object.keys(sections).map(function (key) {
            return sections[key];
        });
    }
    return [];
}

function buildStudentEvaluationFormHtml(eventId, csrf, base) {
    var sections = normalizeEvaluationSections(window.__eventEvaluationSections);
    if (!eventId || !csrf || !sections.length) {
        return '';
    }
    var html = '<hr class="my-3">' +
        '<h6 class="small text-uppercase text-muted mb-2">Post-event evaluation</h6>' +
        '<p class="small text-muted mb-2">You checked in to this event. Rate each statement from <strong>1 (lowest)</strong> to <strong>5 (highest)</strong>. Your name and ID stay private — organizers and admin only see <strong>Anonymous</strong> and your <strong>department</strong>.</p>' +
        '<form method="post" action="' + escapeHtmlStudent(base + '/backend/auth/submit_event_feedback.php') + '">' +
        '<input type="hidden" name="csrf_token" value="' + escapeHtmlStudent(csrf) + '">' +
        '<input type="hidden" name="event_id" value="' + escapeHtmlStudent(String(eventId)) + '">';
    sections.forEach(function (section, sIdx) {
        html += '<div class="mb-3">' +
            '<div class="fw-semibold small text-success mb-2">' + escapeHtmlStudent(String(section.label || 'Section')) + '</div>';
        (section.questions || []).forEach(function (q, qIdx) {
            var key = String(q.key || '');
            if (!key) return;
            var fieldId = 'eval_' + String(eventId).replace(/\W/g, '') + '_' + sIdx + '_' + qIdx;
            html += '<div class="mb-2 ps-1 border-start border-2 border-success-subtle">' +
                '<label class="form-label small mb-1 d-block">' + escapeHtmlStudent(String(q.text || key)) + '</label>' +
                '<input type="number" class="efy-eval-choice__value" name="eval[' + escapeHtmlStudent(key) + ']" id="' + fieldId + '" value="" min="1" max="5" required tabindex="-1" aria-hidden="true">' +
                '<div class="efy-eval-choice" role="group" aria-label="' + escapeHtmlStudent(String(q.text || key)) + '">' +
                '<button type="button" class="efy-eval-choice__btn" data-target="' + fieldId + '" data-value="1" title="1 - Strongly disagree / Poor">1</button>' +
                '<button type="button" class="efy-eval-choice__btn" data-target="' + fieldId + '" data-value="2" title="2">2</button>' +
                '<button type="button" class="efy-eval-choice__btn" data-target="' + fieldId + '" data-value="3" title="3 - Neutral">3</button>' +
                '<button type="button" class="efy-eval-choice__btn" data-target="' + fieldId + '" data-value="4" title="4">4</button>' +
                '<button type="button" class="efy-eval-choice__btn" data-target="' + fieldId + '" data-value="5" title="5 - Strongly agree / Excellent">5</button>' +
                '</div>' +
                '<div class="small text-muted mt-1">1 = lowest, 5 = highest</div>' +
                '</div>';
        });
        html += '</div>';
    });
    html += '<div class="mb-2">' +
        '<label class="form-label small">Additional comments (optional)</label>' +
        '<textarea name="comment" class="form-control form-control-sm" rows="3" maxlength="2000" placeholder="Anything else we should know?"></textarea>' +
        '</div>' +
        '<button type="submit" class="btn btn-outline-primary btn-sm">Submit evaluation</button>' +
        '</form>';
    return html;
}

function applyStudentEvaluationChoice(btn) {
    if (!btn) return;
    var targetId = btn.getAttribute('data-target');
    var value = btn.getAttribute('data-value');
    if (!targetId || !value) return;
    var hidden = document.getElementById(targetId);
    if (!hidden) return;
    hidden.value = value;
    hidden.setCustomValidity('');
    var group = btn.closest('.efy-eval-choice');
    if (!group) return;
    group.querySelectorAll('.efy-eval-choice__btn').forEach(function (b) {
        b.classList.toggle('is-active', b === btn);
    });
}

function captureStudentEvaluationDraft(bodyEl) {
    if (!bodyEl) return null;
    var form = bodyEl.querySelector('form[action*="submit_event_feedback"]');
    if (!form) return null;
    var draft = { comment: '', choices: {} };
    var commentEl = form.querySelector('textarea[name="comment"]');
    draft.comment = commentEl ? commentEl.value : '';
    form.querySelectorAll('.efy-eval-choice__value').forEach(function (input) {
        if (input.name) {
            draft.choices[input.name] = input.value || '';
        }
    });
    return draft;
}

function restoreStudentEvaluationDraft(bodyEl, draft) {
    if (!bodyEl || !draft) return;
    var form = bodyEl.querySelector('form[action*="submit_event_feedback"]');
    if (!form) return;
    var commentEl = form.querySelector('textarea[name="comment"]');
    if (commentEl && draft.comment) {
        commentEl.value = draft.comment;
    }
    form.querySelectorAll('.efy-eval-choice__value').forEach(function (input) {
        var val = draft.choices[input.name];
        if (!val) return;
        input.value = val;
        input.setCustomValidity('');
        var activeBtn = form.querySelector(
            '.efy-eval-choice__btn[data-target="' + input.id + '"][data-value="' + val + '"]'
        );
        if (activeBtn) {
            applyStudentEvaluationChoice(activeBtn);
        }
    });
}

function initStudentEvaluationChoiceDelegation() {
    var bodyEl = document.getElementById('eventDetailsModalBody');
    if (!bodyEl || bodyEl.dataset.evalChoiceDelegated === '1') return;
    bodyEl.dataset.evalChoiceDelegated = '1';
    bodyEl.addEventListener('click', function (ev) {
        var btn = ev.target && ev.target.closest ? ev.target.closest('.efy-eval-choice__btn') : null;
        if (!btn || !bodyEl.contains(btn)) return;
        ev.preventDefault();
        applyStudentEvaluationChoice(btn);
    });
}

function closeStudentNotifDropdown() {
    var toggle = document.getElementById('studentNotifDropdownToggle');
    if (!toggle || typeof bootstrap === 'undefined' || !bootstrap.Dropdown) {
        return;
    }
    var dd = bootstrap.Dropdown.getInstance(toggle);
    if (dd) {
        dd.hide();
    }
}

function updateStudentNavbarNotifBadge(count) {
    var btn = document.getElementById('studentNotifDropdownToggle');
    if (!btn) return;
    var badge = btn.querySelector('.badge');
    var n = parseInt(count, 10) || 0;
    if (n < 1) {
        if (badge) badge.remove();
        return;
    }
    var label = n > 99 ? '99+' : String(n);
    if (!badge) {
        badge = document.createElement('span');
        badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
        badge.style.fontSize = '0.55rem';
        btn.appendChild(badge);
    }
    badge.textContent = label;
}

function initStudentNotificationHooks() {
    document.addEventListener('eventify:notif-read', function (e) {
        var detail = (e && e.detail) || {};
        if (typeof detail.unreadCount === 'number') {
            updateStudentNavbarNotifBadge(detail.unreadCount);
        }
        closeStudentNotifDropdown();
    });
    document.addEventListener('eventify:notif-view-event', function (e) {
        var detail = (e && e.detail) || {};
        if (!detail.eventId) {
            return;
        }
        setTimeout(function () {
            navigateStudentToEvent(String(detail.eventId));
        }, 200);
    });
}

function initStudentMobileNav() {
    document.querySelectorAll('.student-app-tabbar__btn[data-scroll-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var sel = btn.getAttribute('data-scroll-target');
            if (!sel) return;
            var home = document.getElementById('studentDashboardHome');
            if (home && home.classList.contains('d-none')) {
                showStudentDashboardPanel('home');
                window.setTimeout(function () {
                    var el = document.querySelector(sel);
                    if (el) {
                        el.scrollIntoView({ behavior: studentPrefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
                    }
                }, 80);
                return;
            }
            var el = document.querySelector(sel);
            if (el) {
                el.scrollIntoView({ behavior: studentPrefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
            }
        });
    });
}

function initStudentDashboardHashScroll() {
    var hash = window.location.hash;
    if (!hash || hash.length < 2) {
        return;
    }
    var home = document.getElementById('studentDashboardHome');
    if (!home || home.classList.contains('d-none')) {
        return;
    }
    var el = document.querySelector(hash);
    if (el) {
        window.setTimeout(function () {
            el.scrollIntoView({ behavior: studentPrefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
        }, 80);
    }
}

function studentDashboardBaseUrl() {
    var base = (window.BASE_URL || '').replace(/\/$/, '');
    return base + '/backend/auth/dashboard_student.php';
}

function studentDashboardPanelUrl(panel, query) {
    var url = studentDashboardBaseUrl() + '?panel=' + encodeURIComponent(panel);
    if (query && typeof query === 'object') {
        Object.keys(query).forEach(function (key) {
            var val = query[key];
            if (val !== null && val !== undefined && String(val) !== '') {
                url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(String(val));
            }
        });
    }
    return url;
}

function studentDashboardTicketsUrl(query) {
    return studentDashboardPanelUrl('tickets', query);
}

var STUDENT_PANEL_IDS = {
    tickets: 'studentMyTicketsPanel',
    attendance: 'studentAttendancePanel',
    upcoming: 'studentUpcomingPanel',
    photos: 'studentPhotoGalleryPanel'
};

var STUDENT_SIDEBAR_PANELS = ['tickets', 'photos', 'upcoming', 'attendance'];

function studentGetPanelStateFromUrl() {
    try {
        var params = new URLSearchParams(window.location.search);
        return {
            panel: params.get('panel') || 'home',
            event_id: params.get('event_id') || '',
            order_id: params.get('order_id') || ''
        };
    } catch (e) {
        return { panel: 'home', event_id: '', order_id: '' };
    }
}

function studentUpdatePanelUrl(panel, query, replace) {
    try {
        var url = new URL(window.location.href);
        url.searchParams.delete('panel');
        url.searchParams.delete('event_id');
        url.searchParams.delete('order_id');
        if (panel && panel !== 'home') {
            url.searchParams.set('panel', panel);
            if (query && query.event_id) {
                url.searchParams.set('event_id', String(query.event_id));
            }
            if (query && query.order_id) {
                url.searchParams.set('order_id', String(query.order_id));
            }
        }
        var next = url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '') + url.hash;
        var state = {
            studentPanel: panel || 'home',
            studentEventId: (query && query.event_id) ? String(query.event_id) : '',
            studentOrderId: (query && query.order_id) ? String(query.order_id) : ''
        };
        if (replace) {
            history.replaceState(state, '', next);
        } else {
            history.pushState(state, '', next);
        }
    } catch (e) { /* ignore */ }
}

function studentPanelNeedsFetch(panel, query) {
    if (panel !== 'tickets' && panel !== 'photos') {
        return false;
    }
    var el = document.getElementById(STUDENT_PANEL_IDS[panel]);
    if (!el) {
        return true;
    }
    var nextEvent = query && query.event_id ? String(query.event_id) : '';
    var nextOrder = query && query.order_id ? String(query.order_id) : '';
    var curEvent = el.getAttribute('data-rendered-event-id') || '';
    var curOrder = el.getAttribute('data-rendered-order-id') || '';
    if (panel === 'photos') {
        return curEvent !== nextEvent;
    }
    return curEvent !== nextEvent || curOrder !== nextOrder;
}

function studentExtractPhotoViewerUrls(doc) {
    var scripts = doc.querySelectorAll('script');
    for (var i = 0; i < scripts.length; i++) {
        var text = scripts[i].textContent || '';
        var match = text.match(/window\.__studentPhotoViewerUrls\s*=\s*(\[[\s\S]*?\]);/);
        if (match) {
            try {
                return JSON.parse(match[1]);
            } catch (e) { /* ignore */ }
        }
    }
    return null;
}

function replaceStudentPanelFromDoc(panel, doc) {
    var newPanel = doc.getElementById(STUDENT_PANEL_IDS[panel]);
    var oldPanel = document.getElementById(STUDENT_PANEL_IDS[panel]);
    if (!newPanel || !oldPanel) {
        return;
    }
    oldPanel.replaceWith(newPanel);

    if (panel === 'photos') {
        var newViewer = doc.getElementById('studentPhotoViewer');
        var oldViewer = document.getElementById('studentPhotoViewer');
        if (newViewer) {
            if (oldViewer) {
                oldViewer.replaceWith(newViewer);
            } else {
                newPanel.insertAdjacentElement('afterend', newViewer);
            }
        } else if (oldViewer) {
            oldViewer.remove();
        }
        var urls = studentExtractPhotoViewerUrls(doc);
        if (urls) {
            window.__studentPhotoViewerUrls = urls;
        } else {
            delete window.__studentPhotoViewerUrls;
        }
    }
}

function fetchStudentPanelFragment(panel, query, callback) {
    var url = studentDashboardPanelUrl(panel, query);
    fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (res) { return res.text(); })
        .then(function (html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            replaceStudentPanelFromDoc(panel, doc);
            if (typeof callback === 'function') {
                callback(null);
            }
        })
        .catch(function (err) {
            if (typeof callback === 'function') {
                callback(err);
            }
        });
}

function updateStudentSidebarActive(panel) {
    document.querySelectorAll('[data-student-panel]').forEach(function (el) {
        var p = el.getAttribute('data-student-panel');
        if (STUDENT_SIDEBAR_PANELS.indexOf(p) === -1) {
            return;
        }
        if (!el.classList.contains('action-btn') && !el.classList.contains('student-app-tabbar__btn')) {
            return;
        }
        el.classList.toggle('is-active', p === panel);
    });
}

function showStudentDashboardPanel(panel, options) {
    options = options || {};
    var panelName = !panel || panel === 'home' ? 'home' : panel;
    var query = options.query || {};
    var home = document.getElementById('studentDashboardHome');
    var mainContent = document.querySelector('body.student-dashboard .main-content');

    function applyView() {
        if (panelName === 'home') {
            if (home) {
                home.classList.remove('d-none');
            }
            Object.keys(STUDENT_PANEL_IDS).forEach(function (key) {
                var el = document.getElementById(STUDENT_PANEL_IDS[key]);
                if (el) {
                    el.classList.add('d-none');
                    el.setAttribute('hidden', '');
                }
            });
            updateStudentSidebarActive('');
            if (calendar && typeof calendar.updateSize === 'function') {
                [50, 180, 320].forEach(function (ms) {
                    window.setTimeout(function () {
                        calendar.updateSize();
                    }, ms);
                });
            }
        } else {
            if (home) {
                home.classList.add('d-none');
            }
            Object.keys(STUDENT_PANEL_IDS).forEach(function (key) {
                var el = document.getElementById(STUDENT_PANEL_IDS[key]);
                if (!el) {
                    return;
                }
                var show = key === panelName;
                el.classList.toggle('d-none', !show);
                if (show) {
                    el.removeAttribute('hidden');
                } else {
                    el.setAttribute('hidden', '');
                }
            });
            updateStudentSidebarActive(panelName);
        }

        if (!options.skipUrl) {
            studentUpdatePanelUrl(panelName === 'home' ? '' : panelName, query, !!options.replaceUrl);
        }

        if (!options.skipAnimation && panelName !== 'home') {
            initStudentDashPanelEnter();
        }

        if (mainContent) {
            mainContent.scrollTop = 0;
        }

        if (panelName === 'photos') {
            initStudentPhotoGalleryViewer();
        }
    }

    if (panelName !== 'home' && studentPanelNeedsFetch(panelName, query)) {
        fetchStudentPanelFragment(panelName, query, function () {
            applyView();
        });
        return;
    }

    applyView();
}

function studentPrefersReducedMotion() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
}

function initStudentDashPanelEnter() {
    if (studentPrefersReducedMotion()) {
        document.querySelectorAll('.student-dash-panel--enter').forEach(function (panel) {
            panel.classList.remove('student-dash-panel--enter');
        });
        return;
    }
    var panel = document.querySelector('.student-dash-panel:not(.d-none):not([hidden])');
    if (!panel) {
        return;
    }
    if (!panel.classList.contains('student-dash-panel--enter')) {
        panel.classList.add('student-dash-panel--enter');
    }
    window.setTimeout(function () {
        panel.classList.remove('student-dash-panel--enter');
    }, 1100);
}

function initStudentDashboardPanels() {
    if (!document.getElementById('studentDashboardHome')) {
        return;
    }

    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-student-panel]');
        if (!el) {
            return;
        }
        var panel = el.getAttribute('data-student-panel');
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

        var query = {};
        if (panel === 'tickets') {
            var ticketEventId = parseInt(el.getAttribute('data-student-tickets-event') || '0', 10) || 0;
            if (ticketEventId > 0) {
                query.event_id = ticketEventId;
            }
        } else if (panel === 'photos') {
            var photosEventId = parseInt(el.getAttribute('data-student-photos-event') || '0', 10) || 0;
            if (photosEventId > 0) {
                query.event_id = photosEventId;
            }
        }

        showStudentDashboardPanel(panel, { query: query });
    });

    window.addEventListener('popstate', function (ev) {
        var panel = (ev.state && ev.state.studentPanel) || studentGetPanelStateFromUrl().panel;
        var query = {
            event_id: (ev.state && ev.state.studentEventId) || studentGetPanelStateFromUrl().event_id,
            order_id: (ev.state && ev.state.studentOrderId) || studentGetPanelStateFromUrl().order_id
        };
        showStudentDashboardPanel(panel, { query: query, skipUrl: true });
    });
}

function initStudentDashboardPanelFromUrl() {
    var state = studentGetPanelStateFromUrl();
    var panel = state.panel;
    if (panel === 'tickets' || panel === 'attendance' || panel === 'upcoming' || panel === 'photos') {
        showStudentDashboardPanel(panel, {
            query: {
                event_id: state.event_id || undefined,
                order_id: state.order_id || undefined
            },
            replaceUrl: true,
            skipAnimation: !!document.querySelector('.student-dash-panel.student-dash-panel--enter')
        });
    } else {
        try {
            history.replaceState({ studentPanel: 'home', studentEventId: '', studentOrderId: '' }, '', window.location.href);
        } catch (e) { /* ignore */ }
    }
}

var studentPhotoViewerControlsBound = false;
var studentPhotoViewerCurrent = 0;

function studentPhotoViewerHide() {
    var overlay = document.getElementById('studentPhotoViewer');
    var imgEl = document.getElementById('studentPhotoViewerImg');
    if (!overlay || !imgEl) {
        return;
    }
    overlay.hidden = true;
    imgEl.src = '';
    document.body.classList.remove('student-photo-viewer-open');
}

function studentPhotoViewerShow(index) {
    var urls = window.__studentPhotoViewerUrls;
    if (!Array.isArray(urls) || !urls.length) {
        return;
    }
    var overlay = document.getElementById('studentPhotoViewer');
    var imgEl = document.getElementById('studentPhotoViewerImg');
    var counterEl = document.getElementById('studentPhotoViewerCounter');
    if (!overlay || !imgEl) {
        return;
    }
    studentPhotoViewerCurrent = index;
    if (studentPhotoViewerCurrent < 0) {
        studentPhotoViewerCurrent = urls.length - 1;
    }
    if (studentPhotoViewerCurrent >= urls.length) {
        studentPhotoViewerCurrent = 0;
    }
    imgEl.src = urls[studentPhotoViewerCurrent];
    if (counterEl) {
        counterEl.textContent = (studentPhotoViewerCurrent + 1) + ' / ' + urls.length;
    }
    overlay.hidden = false;
    document.body.classList.add('student-photo-viewer-open');
}

function initStudentPhotoGalleryViewer() {
    var urls = window.__studentPhotoViewerUrls;
    if (!Array.isArray(urls) || !urls.length) {
        return;
    }
    var overlay = document.getElementById('studentPhotoViewer');
    var thumbs = document.querySelectorAll('.student-photo-thumb[data-photo-index]');
    if (!overlay || !thumbs.length) {
        return;
    }

    thumbs.forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            var idx = parseInt(thumb.getAttribute('data-photo-index') || '0', 10) || 0;
            studentPhotoViewerShow(idx);
        });
    });

    if (!studentPhotoViewerControlsBound) {
        studentPhotoViewerControlsBound = true;

        document.addEventListener('click', function (e) {
            if (e.target.closest('#studentPhotoViewerClose')) {
                studentPhotoViewerHide();
                return;
            }
            if (e.target.closest('#studentPhotoViewerPrev')) {
                studentPhotoViewerShow(studentPhotoViewerCurrent - 1);
                return;
            }
            if (e.target.closest('#studentPhotoViewerNext')) {
                studentPhotoViewerShow(studentPhotoViewerCurrent + 1);
                return;
            }
            if (e.target.id === 'studentPhotoViewer') {
                studentPhotoViewerHide();
            }
        });

        document.addEventListener('keydown', function (e) {
            var activeOverlay = document.getElementById('studentPhotoViewer');
            if (!activeOverlay || activeOverlay.hidden) {
                return;
            }
            if (e.key === 'Escape') {
                studentPhotoViewerHide();
            } else if (e.key === 'ArrowLeft') {
                studentPhotoViewerShow(studentPhotoViewerCurrent - 1);
            } else if (e.key === 'ArrowRight') {
                studentPhotoViewerShow(studentPhotoViewerCurrent + 1);
            }
        });
    }
}

// ===============================
// MOBILE SIDEBAR DRAWER
// ===============================
function initMobileSidebar() {
    const toggle = document.getElementById('sidebarToggleMobile');
    const closeBtn = document.getElementById('sidebarCloseMobile');
    const backdrop = document.getElementById('sidebarBackdrop');
    const sidebar = document.getElementById('studentSidebar');
    const isMobileView = () => window.matchMedia('(max-width: 768px)').matches;
    const refreshCalendarLayout = () => {
        if (!calendar) return;
        if (typeof calendar.updateSize === 'function') {
            calendar.updateSize();
        }
    };
    const refreshCalendarLayoutSmooth = () => {
        if (!calendar) return;
        // Recompute during and after transition so grid stays fluid.
        [0, 90, 180, 280, 360].forEach(function (ms) {
            setTimeout(refreshCalendarLayout, ms);
        });
    };

    function openSidebar() {
        document.body.classList.add('student-sidebar-open');
    }

    function closeSidebar() {
        document.body.classList.remove('student-sidebar-open');
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            if (isMobileView()) {
                document.body.classList.toggle('student-sidebar-open');
                return;
            }
            // Desktop: use the same icon to collapse/expand sidebar.
            document.body.classList.toggle('student-sidebar-collapsed');
            refreshCalendarLayoutSmooth();
        });
    }
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (backdrop) backdrop.addEventListener('click', closeSidebar);

    // Close drawer when a quick action or modal trigger is clicked
    if (sidebar) {
        sidebar.addEventListener('transitionend', function (e) {
            if (e.propertyName === 'width' || e.propertyName === 'padding-left' || e.propertyName === 'padding-right') {
                refreshCalendarLayout();
            }
        });
        sidebar.addEventListener('click', function(e) {
            var target = e.target.closest('.action-btn, .logout-btn, [data-bs-toggle="modal"]');
            if (target && isMobileView()) {
                closeSidebar();
            }
        });
    }

    window.addEventListener('resize', function () {
        if (!isMobileView()) {
            // Ensure mobile drawer state does not leak to desktop.
            closeSidebar();
        }
        refreshCalendarLayoutSmooth();
    });
}

// ===============================
// MINI CALENDAR
// ===============================
function initMiniCalendar() {
    const miniCalEl = document.getElementById('miniCalendar');
    const monthEl = document.getElementById('miniCalMonth');
    const prevBtn = document.getElementById('miniCalPrev');
    const nextBtn = document.getElementById('miniCalNext');

    if (!miniCalEl || !monthEl) return;

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
            miniCalEl.appendChild(dayEl);
        }

        // Current month days
        const today = new Date();
        for (let day = 1; day <= daysInMonth; day++) {
            const dayEl = document.createElement('div');
            dayEl.className = 'mini-cal-day';
            dayEl.textContent = day;

            // Highlight today only
            if (year === today.getFullYear() && month === today.getMonth() && day === today.getDate()) {
                dayEl.classList.add('today');
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
            miniCalEl.appendChild(dayEl);
        }
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            currentDate.setMonth(currentDate.getMonth() - 1);
            if (calendar) {
                calendar.prev();
                // Sync with calendar focus date
                const focus = calendar.getDate ? calendar.getDate() : new Date();
                currentDate = new Date(focus);
                selectedDate = new Date(focus);
            }
            renderMiniCalendar();
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            currentDate.setMonth(currentDate.getMonth() + 1);
            if (calendar) {
                calendar.next();
                // Sync with calendar focus date
                const focus = calendar.getDate ? calendar.getDate() : new Date();
                currentDate = new Date(focus);
                selectedDate = new Date(focus);
            }
            renderMiniCalendar();
        });
    }

    // Initial render
    renderMiniCalendar();
}

// ===============================
// FULLCALENDAR INITIALIZATION
// ===============================
function getStudentCalendarHeight() {
    var container = document.querySelector('.main-content .calendar-container');
    if (!container) {
        return 520;
    }
    var h = container.clientHeight;
    if (h < 200) {
        h = container.getBoundingClientRect().height;
    }
    return Math.max(360, Math.min(h || 520, 720));
}

function initFullCalendar() {
    const calendarEl = document.getElementById('student-calendar');
    if (!calendarEl) return;

    var settings = window.__studentSettings || {};
    var allowedViews = ['dayGridMonth', 'timeGridWeek', 'timeGridDay'];
    var defaultView = allowedViews.indexOf(String(settings.default_calendar_view || '')) !== -1
        ? String(settings.default_calendar_view)
        : 'dayGridMonth';

    var rawStudentEvents = Array.isArray(window.studentEvents) ? window.studentEvents : [];
    var studentCalendarEvents = rawStudentEvents.filter(function (ev) {
        var props = ev.extendedProps || {};
        var st = String(props.status != null ? props.status : (ev.status || '')).toLowerCase().trim();
        return st !== 'rejected';
    });

    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: defaultView,
        initialDate: currentDate,
        selectable: false, // Students can't create events
        dayMaxEvents: false,
        eventOrder: 'start',
        headerToolbar: false, // We use custom controls
        events: studentCalendarEvents,
        eventDisplay: 'block',
        height: getStudentCalendarHeight(),
        expandRows: true,
        views: {
            dayGridMonth: {
                dayMaxEvents: false,
                dayMaxEventRows: false
            }
        },
        dayHeaderFormat: window.matchMedia('(max-width: 768px)').matches ? { weekday: 'short' } : { weekday: 'long' },
        firstDay: 0,
        weekends: true,
        nowIndicator: true,
        eventTimeFormat: {
            hour: 'numeric',
            minute: '2-digit',
            omitZeroMinute: false,
            meridiem: 'short'
        },

        // Click event -> show details in modal (read-only for students)
        eventClick: function(info) {
            showStudentEventDetails(info.event, {
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
            // Use the calendar focus date, not the visible-range start.
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
        }
    });

    calendar.render();

    var calContainer = calendarEl.closest('.calendar-container');
    function syncStudentCalendarHeight() {
        if (!calendar) return;
        var h = getStudentCalendarHeight();
        try {
            calendar.setOption('height', h);
            calendar.updateSize();
        } catch (e) { /* ignore */ }
    }
    requestAnimationFrame(syncStudentCalendarHeight);
    setTimeout(syncStudentCalendarHeight, 80);
    setTimeout(syncStudentCalendarHeight, 320);
    if (typeof eventifyBindCalendarScrollFix === 'function') {
        eventifyBindCalendarScrollFix(calendar, calContainer);
    }
    if (typeof eventifyBindCalendarSegmentRepaint === 'function') {
        eventifyBindCalendarSegmentRepaint(calendar, calendarEl);
    }
    window.addEventListener('resize', syncStudentCalendarHeight);

    // On resize (e.g. rotate phone), switch day headers between short (mobile) and long (desktop)
    window.addEventListener('resize', function() {
        if (!calendar) return;
        var isMobile = window.matchMedia('(max-width: 768px)').matches;
        calendar.setOption('dayHeaderFormat', isMobile ? { weekday: 'short' } : { weekday: 'long' });
    });

    // Force initial sync (removes the hardcoded placeholder month in sidebar)
    const focus = calendar.getDate ? calendar.getDate() : new Date();
    currentDate = new Date(focus);
    selectedDate = new Date(focus);
    if (renderMiniCalendar) renderMiniCalendar();
}

function studentFormatDeptLabel(stored) {
    const d = String(stored || 'ALL').trim();
    if (d === '' || d === 'ALL') return 'All Departments';
    if (d.charAt(0) === '[') {
        try {
            const arr = JSON.parse(d);
            if (Array.isArray(arr) && arr.length) return arr.join(' · ');
        } catch (e) { /* ignore */ }
    }
    return d;
}

// ===============================
// STUDENT EVENT DETAILS (SHARED)
// ===============================
function showStudentEventDetails(eventLike, options) {
    if (!eventLike) return;
    options = options || {};
    window.__studentEventDetailsCurrent = eventLike;
    const props = eventLike.extendedProps || {};
    const deptText = String(props.department_display || '').trim() || studentFormatDeptLabel(props.department);
    let startDate = null;
    let endDate = null;

    // eventLike may be a FullCalendar Event or a plain object from window.studentEvents
    if (eventLike.start instanceof Date) {
        startDate = eventLike.start;
        endDate = eventLike.end instanceof Date ? eventLike.end : null;
    } else if (eventLike.start) {
        const s = new Date(eventLike.start);
        if (!isNaN(s.getTime())) {
            startDate = s;
        }
        if (eventLike.end) {
            const e = new Date(eventLike.end);
            if (!isNaN(e.getTime())) {
                endDate = e;
            }
        }
    }

    let scheduleHtml = '';
    if (typeof eventifyBuildEventScheduleDisplayHtml === 'function') {
        scheduleHtml = eventifyBuildEventScheduleDisplayHtml(props, {
            start: startDate,
            end: endDate,
            allDay: !!eventLike.allDay,
            startStr: eventLike.startStr || ''
        });
    } else {
        scheduleHtml = '<div class="efy-event-schedule"><div class="efy-event-schedule__summary">' + escapeHtmlStudent('TBA') + '</div></div>';
    }

    const startYmd = String(props.event_date_ymd || '').trim();
    const endYmd = String(props.event_end_ymd || props.event_date_ymd || '').trim();
    const eventYmd = endYmd || startYmd || '';
    const todayY = todayYmdLocal();
    const isPast = eventYmd !== '' && eventYmd < todayY;
    const statusLower = String(props.status || '').toLowerCase();
    const endedByOrganizer = statusLower === 'closed' || statusLower === 'completed';
    const endedAfterSchedule = statusLower === 'active' && props.event_is_live === false;
    const isEndedForFeedback = isPast || endedByOrganizer || endedAfterSchedule;
    const allowsMainRsvp = studentEventAllowsMainRsvp(props);

    const maxCap = props.max_capacity != null && props.max_capacity !== '' ? parseInt(props.max_capacity, 10) : null;
    const regCount = parseInt(props.registration_count, 10) || 0;
    const isRegistered = !!props.is_registered;
    const hasFeedback = !!props.has_feedback;
    const attended = !!props.attended;
    const forceEvaluation = !!options.forceEvaluation;
    const showingEvaluation = attended && (isEndedForFeedback || forceEvaluation) && !hasFeedback;
    let eventId = props.event_id || eventLike.id;
    if (eventId && String(eventId).indexOf('-') > 0) {
        eventId = String(eventId).split('-')[0];
    }
    const csrf = window.csrfToken || '';
    const base = (window.BASE_URL || '').replace(/\/$/, '');

    let capacityHtml = '';
    if (maxCap != null && !isNaN(maxCap) && maxCap > 0) {
        capacityHtml = '<p class="mb-2"><strong>RSVPs:</strong> ' + regCount + ' / ' + maxCap + '</p>';
    } else {
        capacityHtml = '<p class="mb-2"><strong>RSVPs:</strong> ' + regCount + ' registered (no cap)</p>';
    }

    let actionHtml = '';
    let footerRsvpHtml = '';
    const isFull = maxCap != null && !isNaN(maxCap) && maxCap > 0 && regCount >= maxCap;
    const registrationMode = String(props.registration_mode || 'rsvp').toLowerCase();
    const isPaidTicketEvent = registrationMode === 'paid_ticket';
    const eventIsLive = props.event_is_live === true;

    if (isPaidTicketEvent && !isEndedForFeedback && eventIsLive) {
        capacityHtml = '<p class="mb-2"><strong>Entry:</strong> Ticket required (paid event)</p>';
        actionHtml = '<p class="mb-2 small text-muted">Purchase a ticket to receive your digital pass with QR for venue entry.</p>';
        if (eventId) {
            footerRsvpHtml = '<a class="btn btn-success btn-sm" href="' + escapeHtmlStudent(base + '/event_tickets.php?event_id=' + encodeURIComponent(String(eventId))) + '">' +
                '<i class="fas fa-ticket-alt me-1"></i>Buy tickets</a>' +
                ' <a class="btn btn-outline-primary btn-sm" href="' + escapeHtmlStudent(studentDashboardTicketsUrl()) + '">My tickets</a>';
        }
    } else if (isPaidTicketEvent && !eventIsLive) {
        capacityHtml = '<p class="mb-2"><strong>Entry:</strong> Ticket sales closed</p>';
        actionHtml = '<p class="mb-2 small text-muted">This event has ended. If you already bought a ticket, open <strong>My tickets</strong> for your digital pass.</p>';
        footerRsvpHtml = '<a class="btn btn-outline-primary btn-sm" href="' + escapeHtmlStudent(studentDashboardTicketsUrl()) + '"><i class="fas fa-ticket-alt me-1"></i>My tickets</a>';
    } else if (attended && (isEndedForFeedback || forceEvaluation)) {
        if (!hasFeedback && eventId && csrf) {
            actionHtml = buildStudentEvaluationFormHtml(eventId, csrf, base);
            if (!actionHtml) {
                actionHtml = '<p class="mb-0 small text-warning">Unable to load the evaluation form. Please refresh the page and try again.</p>';
            }
        } else if (hasFeedback) {
            actionHtml = '<p class="mb-0 small text-muted mt-2"><i class="fas fa-check me-1"></i>Thanks — you already submitted your evaluation for this event.</p>';
        }
    } else if (allowsMainRsvp) {
        if (isRegistered) {
            actionHtml = '<p class="mb-2 text-success small"><i class="fas fa-check-circle me-1"></i>Your RSVP for this event is confirmed.</p>';
        } else if (isFull) {
            actionHtml = '<p class="mb-2 text-warning small mb-0">This event is full.</p>';
        } else if (eventId && csrf) {
            actionHtml = '<p class="mb-2 small text-muted">Register for the main event below. Activity RSVPs are separate.</p>';
        } else {
            actionHtml = '<p class="mb-0 small text-muted">RSVP unavailable — refresh the page and try again.</p>';
        }
        footerRsvpHtml = buildStudentEventFooterRsvpHtml({
            eventId: eventId,
            csrf: csrf,
            base: base,
            isRegistered: isRegistered,
            isFull: isFull,
            allowsMainRsvp: true
        });
    } else if (!allowsMainRsvp && isRegistered) {
        actionHtml = '<p class="mb-2 text-success small"><i class="fas fa-check-circle me-1"></i>Your RSVP for this event is confirmed.</p>';
        footerRsvpHtml = buildStudentEventFooterRsvpHtml({
            eventId: eventId,
            csrf: csrf,
            base: base,
            isRegistered: true,
            allowsMainRsvp: false
        });
    } else if (attended && !isEndedForFeedback) {
        actionHtml = '<p class="mb-0 small text-muted"><i class="fas fa-check-circle me-1 text-success"></i>You checked in. Post-event evaluation will open after this event ends.</p>';
    } else if (!isEndedForFeedback && statusLower === 'active') {
        // Active upcoming/live event that does not use main-event RSVP (e.g. open entry).
        if (registrationMode === 'open') {
            capacityHtml = '<p class="mb-2"><strong>Entry:</strong> Open entry (no RSVP)</p>';
            actionHtml = '<p class="mb-0 small text-muted">No RSVP needed. Use <strong>Browse activities</strong> for the day schedule, or scan the event QR at the venue to check in.</p>';
        } else if (isPaidTicketEvent) {
            capacityHtml = '<p class="mb-2"><strong>Entry:</strong> Ticket required (paid event)</p>';
            actionHtml = '<p class="mb-0 small text-muted">Purchase a ticket if sales are open, then use your digital pass for entry.</p>';
        } else {
            actionHtml = '<p class="mb-0 small text-muted">This event is on your calendar. Open <strong>Browse activities</strong> for day details.</p>';
        }
    } else {
        actionHtml = '<p class="mb-0 small text-muted">This event is finished or was marked ended by the organizer. <strong>Post-event evaluation</strong> is only available if you attended using <strong>QR check-in</strong>.</p>';
    }

    const title = eventLike.title || 'Untitled';
    const bodyEl = document.getElementById('eventDetailsModalBody');
    const clickCtx = {
        clickEl: options.clickEl || null,
        jsEvent: options.jsEvent || null
    };
    const scheduleDateForSessions = (typeof eventifyResolveStudentDaySessionsDate === 'function')
        ? eventifyResolveStudentDaySessionsDate(eventLike, clickCtx)
        : '';
    const mainBodyHtml = '<p class="mb-2"><strong>Event:</strong> ' + escapeHtmlStudent(title) + '</p>' +
            '<div class="mb-2"><strong class="d-block mb-1">Schedule</strong>' + scheduleHtml + '</div>' +
            '<p class="mb-2"><strong>Location:</strong> ' + escapeHtmlStudent(props.location || 'N/A') + '</p>' +
            '<p class="mb-2"><strong>Department:</strong> ' + escapeHtmlStudent(deptText) + '</p>' +
            capacityHtml +
            '<p class="mb-2"><strong>Description:</strong> ' + escapeHtmlStudent(props.description || 'No description provided.') + '</p>' +
            (eventId ? '<p class="mb-2"><a class="btn btn-sm btn-outline-success" href="' + escapeHtmlStudent(base + '/event_activities.php?id=' + encodeURIComponent(String(eventId))) + '"><i class="fas fa-th-large me-1"></i>Browse activities</a></p>' : '') +
            actionHtml;
    if (bodyEl) {
        var evalDraft = captureStudentEvaluationDraft(bodyEl);
        var existingSessions = bodyEl.querySelector('#studentDaySessionsBlock');
        var preserveSessions = options.contentOnly && existingSessions && eventId && scheduleDateForSessions &&
            existingSessions.getAttribute('data-event-id') === String(eventId) &&
            existingSessions.getAttribute('data-schedule-date') === scheduleDateForSessions;
        if (preserveSessions) {
            var keptSessions = existingSessions;
            keptSessions.remove();
            bodyEl.innerHTML = mainBodyHtml;
            bodyEl.appendChild(keptSessions);
        } else {
            bodyEl.innerHTML = mainBodyHtml;
            if (typeof eventifyAppendStudentDaySessions === 'function' && eventId && scheduleDateForSessions) {
                eventifyAppendStudentDaySessions(bodyEl, eventLike, clickCtx);
            }
        }
        restoreStudentEvaluationDraft(bodyEl, evalDraft);
    }
    var footerRsvpEl = document.getElementById('studentEventDetailsRsvpActions');
    if (footerRsvpEl) {
        footerRsvpEl.innerHTML = footerRsvpHtml;
    }
    if (eventId && !isPaidTicketEvent && !showingEvaluation && (allowsMainRsvp || !isRegistered)) {
        fetchStudentEventRsvpStatus(eventId).then(function (data) {
            if (!data || !data.ok) return;
            var regChanged = !!data.is_registered !== isRegistered;
            var countChanged = (parseInt(data.registration_count, 10) || 0) !== regCount;
            if (!regChanged && !countChanged) return;
            patchStudentEventRegistration(eventId, {
                is_registered: !!data.is_registered,
                registration_count: parseInt(data.registration_count, 10) || 0
            });
            var current = window.__studentEventDetailsCurrent;
            if (!current) return;
            var curProps = current.extendedProps || {};
            var curId = String(curProps.event_id || String(current.id || '').split('-')[0]);
            if (curId !== String(eventId)) return;
            showStudentEventDetails(current, { contentOnly: true, preserveSessions: true });
        }).catch(function () { /* ignore */ });
    }
    const modalEl = document.getElementById('eventDetailsModal');
    if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        if (!options.contentOnly && !modalEl.classList.contains('show')) {
            if (typeof eventifyCloseFullCalendarPopovers === 'function') {
                eventifyCloseFullCalendarPopovers();
            }
            if (typeof edsForceHideHelperModals === 'function') {
                edsForceHideHelperModals();
            }
            var existingModal = bootstrap.Modal.getInstance(modalEl);
            if (existingModal) {
                existingModal.dispose();
            }
            var modal = new bootstrap.Modal(modalEl);
            modal.show();
        }
    }
}

function navigateStudentToEvent(id) {
    if (!id) {
        return;
    }
    if (openStudentEventById(String(id))) {
        return;
    }
    var base = (window.BASE_URL || '').replace(/\/$/, '');
    window.location.href = base + '/event_activities.php?id=' + encodeURIComponent(String(id));
}

function initStudentUpcomingEventClicks() {
    const links = document.querySelectorAll('.student-event-link[data-event-id], .js-student-open-event[data-event-id]');
    if (!links.length) return;

    links.forEach(function(el) {
        el.addEventListener('click', function() {
            const id = this.getAttribute('data-event-id');
            navigateStudentToEvent(id);
        });
        el.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            if (e.key === ' ') e.preventDefault();
            const id = this.getAttribute('data-event-id');
            navigateStudentToEvent(id);
        });
    });
}

function openStudentEventById(id) {
    if (!id || !window.studentEvents) {
        return false;
    }
    const events = Array.isArray(window.studentEvents) ? window.studentEvents : [];
    const match = events.find(function(e) {
        return String(e.id) === String(id) || String((e.extendedProps && e.extendedProps.event_id) || '') === String(id);
    });
    if (!match) {
        var byPrefix = events.find(function(e) {
            return String(e.id || '').indexOf(String(id) + '-') === 0;
        });
        if (byPrefix) {
            showStudentEventDetails(byPrefix);
            return true;
        }
        return false;
    }
    showStudentEventDetails(match);
    return true;
}

function studentEventFromUrgentPayload(ev) {
    if (!ev || ev.id == null) return null;
    var ymd = String(ev.date || '').trim();
    var endYmd = String(ev.end_date || ev.date || '').trim();
    var status = String(ev.status || '').toLowerCase();
    return {
        id: ev.id,
        title: ev.title || 'Event',
        start: ymd ? new Date(ymd + 'T12:00:00') : null,
        extendedProps: {
            event_id: ev.id,
            event_date_ymd: ymd,
            event_end_ymd: endYmd,
            location: '',
            description: 'Open your calendar for full details.',
            department: 'ALL',
            department_display: '',
            max_capacity: null,
            registration_count: 0,
            is_registered: false,
            has_feedback: false,
            attended: true,
            status: status
        }
    };
}

function initUrgentFeedbackPrompt() {
    var openModal = String(window.__studentOpenModal || '').toLowerCase();
    if (openModal === 'change_password' || openModal === 'settings' || openModal === 'scan') {
        return;
    }
    var list = window.__studentPendingUrgentFeedback;
    if (!Array.isArray(list) || !list.length) return;
    try {
        var until = parseInt(sessionStorage.getItem('eventify_urgent_feedback_snooze_until') || '0', 10);
        if (until && Date.now() < until) return;
    } catch (e) { /* ignore */ }

    var modalEl = document.getElementById('studentUrgentFeedbackModal');
    if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;

    var body = document.getElementById('studentUrgentFeedbackModalBody');
    if (body) {
        var html = '<p class="mb-3 fw-semibold">You attended the event(s) below. Please complete the post-event evaluation (1 = lowest, 5 = highest). Your name stays private — only your department may be shown to organizers and admin.</p>' +
            '<ul class="list-group list-group-flush">';
        list.forEach(function (ev) {
            var dateLine = '';
            if (ev.date) {
                try {
                    var d = new Date(ev.date + 'T12:00:00');
                    if (!isNaN(d.getTime())) {
                        dateLine = d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
                    }
                } catch (e2) { dateLine = String(ev.date); }
            }
            html += '<li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2 px-0">' +
                '<span><strong>' + escapeHtmlStudent(String(ev.title || 'Event')) + '</strong>' +
                (dateLine ? '<br><span class="small text-muted">' + escapeHtmlStudent(dateLine) + '</span>' : '') +
                '</span>' +
                '<button type="button" class="btn btn-primary btn-sm urgent-fb-open" data-event-id="' + escapeHtmlStudent(String(ev.id)) + '">' +
                '<i class="fas fa-comment-dots me-1"></i>Evaluate</button></li>';
        });
        html += '</ul>';
        body.innerHTML = html;
        body.querySelectorAll('.urgent-fb-open').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-event-id');
                var urgent = Array.isArray(window.__studentPendingUrgentFeedback) ? window.__studentPendingUrgentFeedback : [];
                var payload = urgent.find(function (x) { return String(x.id) === String(id); });
                var events = Array.isArray(window.studentEvents) ? window.studentEvents : [];
                var match = events.find(function (e) { return String(e.id) === String(id); });
                var toShow = match || studentEventFromUrgentPayload(payload);
                var inst = bootstrap.Modal.getInstance(modalEl);
                if (inst) inst.hide();
                if (toShow && typeof showStudentEventDetails === 'function') {
                    setTimeout(function () { showStudentEventDetails(toShow, { forceEvaluation: true }); }, 320);
                }
            });
        });
    }

    var snoozeBtn = document.getElementById('studentUrgentFeedbackSnoozeBtn');
    if (snoozeBtn) {
        snoozeBtn.onclick = function () {
            try {
                sessionStorage.setItem('eventify_urgent_feedback_snooze_until', String(Date.now() + 4 * 60 * 60 * 1000));
            } catch (e3) { /* ignore */ }
            var m = bootstrap.Modal.getInstance(modalEl);
            if (m) m.hide();
        };
    }

    setTimeout(function () {
        try {
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
            setTimeout(function () {
                var backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) backdrop.style.zIndex = '1199';
                modalEl.style.zIndex = '1200';
            }, 10);
        } catch (e4) { /* ignore */ }
    }, 650);
}

// ===============================
// CALENDAR TITLE UPDATE
// ===============================
function updateCalendarTitle(info) {
    const titleEl = document.getElementById('calendarTitle');
    if (!titleEl || !calendar) return;

    // Always use FullCalendar's own computed title (prevents wrong month)
    titleEl.textContent = calendar.view?.title || '';
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
                jumpStudentCalendarToToday({ syncActiveButton: false });
            } else {
                // Change view
                calendar.changeView(view);
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

// ===============================
// PROFILE MODAL FUNCTIONS
// ===============================
function openProfileModal() {
    const modal = document.getElementById('profileModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeProfileModal() {
    const modal = document.getElementById('profileModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('profileModal');
    if (event.target === modal) {
        closeProfileModal();
    }
});

// Get BASE_URL from window or set default
const BASE_URL = window.BASE_URL || '';

function escapeHtmlStudent(s) {
    if (s == null || s === undefined) {
        return '';
    }
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function todayYmdLocal() {
    var d = new Date();
    var m = String(d.getMonth() + 1).padStart(2, '0');
    var day = String(d.getDate()).padStart(2, '0');
    return d.getFullYear() + '-' + m + '-' + day;
}
