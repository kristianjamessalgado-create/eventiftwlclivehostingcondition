function adminDashboardBaseUrl() {
    var base = (window.BASE_URL || '').replace(/\/$/, '');
    return base + '/backend/admin/dashboard.php';
}

var ADMIN_DASH_PANEL_MAP = {
    events: 'adminAllEventsPanel',
    users: 'adminAllUsersPanel',
    messages: 'adminMessagesPanel',
    pending: 'adminPendingEventsPanel',
    feedback: 'adminStudentFeedbackPanel',
    audit: 'adminAuditLogPanel',
    revenue: 'adminRevenuePanel',
    analytics: 'adminAnalyticsPanel',
    upcoming: 'adminUpcomingEventsPanel',
    announcements: 'adminAnnouncementsPanel'
};

var ADMIN_DASH_PANEL_NAMES = Object.keys(ADMIN_DASH_PANEL_MAP);
var adminDashLastPanel = 'home';

function adminPanelIdForName(panelName) {
    return ADMIN_DASH_PANEL_MAP[panelName] || '';
}

function adminOpenModalToPanel(openModal) {
    var map = {
        events: 'events',
        accounts: 'users',
        pending: 'pending',
        charts: 'analytics',
        analytics: 'analytics'
    };
    return map[openModal] || '';
}

function adminDashboardPanelUrl(panel) {
    return adminDashboardBaseUrl() + '?panel=' + encodeURIComponent(panel);
}

function adminGetPanelFromUrl() {
    try {
        return new URLSearchParams(window.location.search).get('panel') || 'home';
    } catch (e) {
        return 'home';
    }
}

function adminGetFocusEventFromUrl() {
    try {
        var fromUrl = new URLSearchParams(window.location.search).get('focus_event') || '';
        if (fromUrl) {
            return fromUrl;
        }
    } catch (e) { /* ignore */ }
    var stored = window.__adminFocusPendingEventId;
    return stored ? String(stored) : '';
}

function adminUpdatePanelUrl(panel, replace, focusEventId) {
    try {
        var url = new URL(window.location.href);
        url.searchParams.delete('focus_event');
        if (!panel || panel === 'home') {
            url.searchParams.delete('panel');
            url.searchParams.delete('with');
        } else {
            url.searchParams.set('panel', panel);
            if (panel !== 'messages') {
                url.searchParams.delete('with');
            }
            if (panel === 'pending' && focusEventId) {
                url.searchParams.set('focus_event', String(focusEventId));
            }
        }
        var next = url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '') + url.hash;
        var state = { adminPanel: panel || 'home' };
        if (replace) {
            history.replaceState(state, '', next);
        } else {
            history.pushState(state, '', next);
        }
    } catch (e) { /* ignore */ }
}

function showAdminDashboardPanel(panel, options) {
    options = options || {};
    var panelName = panel === 'home' || !panel ? 'home' : panel;
    var home = document.getElementById('adminDashboardHome');
    var mainContent = document.querySelector('body.admin-dashboard .main-content');
    var panelOpen = panelName !== 'home' && !!ADMIN_DASH_PANEL_MAP[panelName];

    if (adminDashLastPanel === 'analytics' && panelName !== 'analytics' && typeof window.destroyAdminCharts === 'function') {
        window.destroyAdminCharts();
    }

    if (home) {
        home.classList.toggle('d-none', panelOpen);
    }

    ADMIN_DASH_PANEL_NAMES.forEach(function (name) {
        var el = document.getElementById(ADMIN_DASH_PANEL_MAP[name]);
        if (!el) {
            return;
        }
        var active = panelName === name;
        if (active) {
            el.classList.remove('d-none');
            el.removeAttribute('hidden');
        } else {
            el.classList.add('d-none');
            el.setAttribute('hidden', '');
        }
    });

    document.querySelectorAll('[data-admin-panel]').forEach(function (btn) {
        var target = btn.getAttribute('data-admin-panel');
        if (!target || target === 'home') {
            return;
        }
        btn.classList.toggle('is-active', target === panelName);
    });

    if (!panelOpen && window.eventifyCalendar && typeof window.eventifyCalendar.updateSize === 'function') {
        [50, 180, 320].forEach(function (ms) {
            window.setTimeout(function () {
                window.eventifyCalendar.updateSize();
            }, ms);
        });
    }

    if (!options.skipUrl) {
        var focusForUrl = panelName === 'pending' ? (options.focusPendingEventId || '') : '';
        adminUpdatePanelUrl(panelName === 'home' ? '' : panelName, !!options.replaceUrl, focusForUrl);
    }

    if (!options.skipAnimation && panelOpen) {
        initAdminDashPanelEnter();
    }

    if (panelName === 'analytics' && typeof window.initAdminAnalyticsCharts === 'function') {
        window.setTimeout(function () {
            window.initAdminAnalyticsCharts();
        }, 100);
    }

    if (mainContent) {
        if (!options.focusPendingEventId) {
            mainContent.scrollTop = 0;
        }
    }

    adminDashLastPanel = panelName;

    if (panelName === 'pending' && options.focusPendingEventId) {
        window.setTimeout(function () {
            adminTryFocusPendingEventWithFallback(options.focusPendingEventId);
        }, options.skipAnimation ? 80 : 320);
    }
}

function adminFocusPendingEventCard(eventId) {
    var id = parseInt(eventId, 10);
    if (!id) {
        return false;
    }
    var card = document.querySelector('#admPendingList .adm-pending-card[data-event-id="' + id + '"]');
    if (!card) {
        return false;
    }

    document.querySelectorAll('.adm-pending-card.is-focused').forEach(function (el) {
        el.classList.remove('is-focused');
    });
    card.classList.add('is-focused');

    try {
        card.scrollIntoView({ behavior: adminPrefersReducedMotion() ? 'auto' : 'smooth', block: 'center' });
    } catch (e) {
        card.scrollIntoView(true);
    }

    window.setTimeout(function () {
        var editBtn = card.querySelector('.js-edit-pending-event');
        if (editBtn && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            editBtn.focus({ preventScroll: true });
        } else {
            card.setAttribute('tabindex', '-1');
            card.focus({ preventScroll: true });
        }
    }, adminPrefersReducedMotion() ? 0 : 350);

    window.setTimeout(function () {
        card.classList.remove('is-focused');
    }, 4500);

    return true;
}

function adminDismissBootstrapModals() {
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        document.querySelectorAll('.modal.show').forEach(function (el) {
            var inst = bootstrap.Modal.getInstance(el);
            if (inst) {
                try {
                    inst.hide();
                } catch (e) { /* ignore */ }
            }
        });
    }
    window.setTimeout(function () {
        document.querySelectorAll('.modal-backdrop').forEach(function (el) {
            el.remove();
        });
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
        document.body.style.removeProperty('overflow');
    }, 0);
}

function adminTryFocusPendingEventWithFallback(eventId) {
    var eid = String(eventId || '').trim();
    if (!eid) {
        return;
    }

    function tryFocus(attempt) {
        if (adminFocusPendingEventCard(eid)) {
            return;
        }
        if (attempt < 4) {
            window.setTimeout(function () {
                tryFocus(attempt + 1);
            }, attempt === 0 ? 320 : 250);
            return;
        }
        showAdminDashboardPanel('home', { replaceUrl: true });
        if (typeof window.eventifyOpenEventDetailsById === 'function') {
            window.setTimeout(function () {
                window.eventifyOpenEventDetailsById(eid);
            }, 150);
        }
        if (typeof window.eventifyToast === 'object' && window.eventifyToast && typeof window.eventifyToast.info === 'function') {
            window.eventifyToast.info('This event is no longer pending. Showing event details instead.');
        }
    }

    tryFocus(0);
}

function adminOpenPendingFromNotification(eventId) {
    var eid = eventId ? String(eventId).trim() : '';
    var onAdminDash = false;
    try {
        onAdminDash = /\/backend\/admin\/dashboard\.php$/i.test(window.location.pathname)
            || /\/admin\/dashboard\.php$/i.test(window.location.pathname);
    } catch (err) {
        onAdminDash = false;
    }

    if (!onAdminDash || typeof showAdminDashboardPanel !== 'function') {
        var url = adminDashboardBaseUrl() + '?panel=pending';
        if (eid) {
            url += '&focus_event=' + encodeURIComponent(eid);
        }
        window.location.href = url;
        return;
    }

    adminDismissBootstrapModals();
    window.setTimeout(function () {
        showAdminDashboardPanel('pending', {
            focusPendingEventId: eid
        });
    }, 280);
}

window.adminFocusPendingEventCard = adminFocusPendingEventCard;
window.showAdminDashboardPanel = showAdminDashboardPanel;
window.adminOpenPendingFromNotification = adminOpenPendingFromNotification;

document.addEventListener('eventify:notif-open-pending', function (e) {
    var detail = (e && e.detail) || {};
    adminOpenPendingFromNotification(detail.eventId || '');
});

function initAdminDashboardPanels() {
    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-admin-panel]');
        if (!el) {
            return;
        }
        var panel = el.getAttribute('data-admin-panel');
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
        showAdminDashboardPanel(panel);
    });

    window.addEventListener('popstate', function (e) {
        var panel = (e.state && e.state.adminPanel) || adminGetPanelFromUrl();
        showAdminDashboardPanel(panel, { skipUrl: true });
    });
}

function initAdminDashboardPanelFromUrl() {
    var panel = adminGetPanelFromUrl();
    var focusEventId = adminGetFocusEventFromUrl();
    var openModal = String(window.__adminOpenModal || '').toLowerCase();
    var mappedPanel = adminOpenModalToPanel(openModal);
    if (mappedPanel) {
        panel = mappedPanel;
    }
    if (ADMIN_DASH_PANEL_NAMES.indexOf(panel) >= 0) {
        var panelId = adminPanelIdForName(panel);
        showAdminDashboardPanel(panel, {
            replaceUrl: true,
            skipAnimation: !!(panelId && document.querySelector('#' + panelId + '.adm-dash-panel--enter')),
            focusPendingEventId: panel === 'pending' ? focusEventId : ''
        });
    } else {
        try {
            history.replaceState({ adminPanel: 'home' }, '', window.location.href);
        } catch (e) { /* ignore */ }
    }
}

function adminPrefersReducedMotion() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
}

function initAdminDashPanelEnter() {
    if (adminPrefersReducedMotion()) {
        document.querySelectorAll('.adm-dash-panel--enter').forEach(function (panel) {
            panel.classList.remove('adm-dash-panel--enter');
        });
        return;
    }
    var panel = document.querySelector('.adm-dash-panel:not(.d-none):not([hidden])');
    if (!panel) {
        return;
    }
    if (!panel.classList.contains('adm-dash-panel--enter')) {
        panel.classList.add('adm-dash-panel--enter');
    }
    window.setTimeout(function () {
        panel.classList.remove('adm-dash-panel--enter');
    }, 1100);
}

function previewAdminProfilePicture(input) {
    var preview = document.getElementById('adminProfilePicturePreview');
    if (!preview) return;
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function (e) {
            if (preview.tagName === 'IMG') {
                preview.src = e.target.result;
            } else {
                var img = document.createElement('img');
                img.id = 'adminProfilePicturePreview';
                img.className = 'organizer-profile-picture-preview';
                img.alt = 'Preview';
                img.src = e.target.result;
                preview.parentNode.replaceChild(img, preview);
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function adminEscapeHtml(text) {
    return String(text || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function buildAdminProfileChangeLines(form) {
    var lines = [];
    var trim = function (v) { return String(v || '').trim(); };
    var initialName = trim(form.dataset.initialName);
    var name = trim((form.querySelector('input[name="name"]') || {}).value);
    var fileInput = form.querySelector('input[name="profile_picture"]');
    var hasFile = fileInput && fileInput.files && fileInput.files.length > 0;

    if (name !== initialName) {
        lines.push('Display name to <strong>' + adminEscapeHtml(name) + '</strong>');
    }
    if (hasFile) {
        lines.push('Upload a new profile picture');
    }
    return lines;
}

function confirmAdminProfileChanges(form) {
    var lines = buildAdminProfileChangeLines(form);
    var messageEl = document.getElementById('confirmAdminProfileMessage');
    if (messageEl) {
        if (lines.length === 0) {
            messageEl.textContent = 'No changes detected. Save anyway?';
        } else if (lines.length === 1) {
            messageEl.innerHTML = '<p class="mb-0">You are about to update your ' + lines[0] + '.</p>';
        } else {
            messageEl.innerHTML =
                '<p class="mb-2">You are about to save these changes:</p>' +
                '<ul class="mb-0 ps-3">' + lines.map(function (line) {
                    return '<li>' + line + '</li>';
                }).join('') + '</ul>';
        }
    }

    var modalEl = document.getElementById('confirmAdminProfileModal');
    if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        form.submit();
        return;
    }

    var confirmModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var confirmBtn = document.getElementById('confirmAdminProfileBtn');
    if (!confirmBtn) {
        form.submit();
        return;
    }

    var onConfirm = function () {
        confirmBtn.removeEventListener('click', onConfirm);
        confirmModal.hide();
        form.submit();
    };

    confirmBtn.addEventListener('click', onConfirm);
    confirmModal.show();
}

document.addEventListener('DOMContentLoaded', function () {
    var sidebarToggle = document.getElementById('adminSidebarToggle');
    var sidebarClose = document.getElementById('adminSidebarClose');
    var sidebarBackdrop = document.getElementById('adminSidebarBackdrop');
    var adminSidebar = document.getElementById('adminSidebar');
    var mainContent = document.querySelector('body.admin-dashboard .main-content');
    var savedMainScroll = 0;
    var isMobileView = function () { return window.matchMedia('(max-width: 768px)').matches; };
    var openMobileSidebar = function () {
        if (mainContent) {
            savedMainScroll = mainContent.scrollTop;
        }
        document.body.classList.add('admin-sidebar-open');
    };
    var closeMobileSidebar = function () {
        document.body.classList.remove('admin-sidebar-open');
        if (mainContent) {
            mainContent.scrollTop = savedMainScroll;
        }
    };
    var getCalendarInstance = function () {
        if (window.eventifyCalendar && typeof window.eventifyCalendar.updateSize === 'function') {
            return window.eventifyCalendar;
        }
        return null;
    };
    var refreshCalendarLayout = function () {
        var cal = getCalendarInstance();
        if (!cal) return;
        cal.updateSize();
    };
    var refreshCalendarLayoutSmooth = function () {
        [0, 90, 180, 280, 360].forEach(function (ms) {
            setTimeout(refreshCalendarLayout, ms);
        });
    };
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            if (isMobileView()) {
                if (document.body.classList.contains('admin-sidebar-open')) {
                    closeMobileSidebar();
                } else {
                    openMobileSidebar();
                }
                return;
            }
            var collapsed = document.body.classList.toggle('admin-sidebar-collapsed');
            if (adminSidebar) {
                adminSidebar.classList.toggle('is-collapsed', collapsed);
            }
            refreshCalendarLayoutSmooth();
        });
    }
    if (sidebarClose) sidebarClose.addEventListener('click', closeMobileSidebar);
    if (sidebarBackdrop) sidebarBackdrop.addEventListener('click', closeMobileSidebar);
    if (adminSidebar) {
        adminSidebar.addEventListener('transitionend', function (e) {
            if (e.propertyName === 'width' || e.propertyName === 'padding-left' || e.propertyName === 'padding-right') {
                refreshCalendarLayout();
            }
        });
        adminSidebar.addEventListener('click', function (e) {
            var target = e.target.closest('.action-btn, [data-bs-toggle="modal"]');
            if (target && isMobileView()) {
                closeMobileSidebar();
            }
        });
    }
    window.addEventListener('resize', function () {
        if (!isMobileView()) {
            closeMobileSidebar();
        }
        refreshCalendarLayoutSmooth();
    });

    var settingsForm = document.getElementById('adminSettingsForm');
    var settingsUpdateBtn = document.getElementById('adminSettingsUpdateBtn');
    var confirmSettingsModalEl = document.getElementById('confirmAdminSettingsUpdateModal');
    var confirmSettingsYesBtn = document.getElementById('confirmAdminSettingsUpdateYes');
    var confirmSettingsModal = confirmSettingsModalEl ? bootstrap.Modal.getOrCreateInstance(confirmSettingsModalEl) : null;

    if (settingsUpdateBtn && settingsForm && confirmSettingsModal) {
        settingsUpdateBtn.addEventListener('click', function () {
            confirmSettingsModal.show();
        });
    }
    if (confirmSettingsYesBtn && settingsForm) {
        confirmSettingsYesBtn.addEventListener('click', function () {
            if (confirmSettingsModal) {
                confirmSettingsModal.hide();
            }
            settingsForm.submit();
        });
    }

    var otpReqModalEl = document.getElementById('otpRequestConfirmModal');
    var otpReqMsgEl = document.getElementById('otpRequestConfirmText');
    var otpReqConfirmBtn = document.getElementById('otpRequestConfirmBtn');
    var otpReqModal = otpReqModalEl ? bootstrap.Modal.getOrCreateInstance(otpReqModalEl) : null;
    var otpPendingPayload = null;

    function eventifyGetCsrfToken() {
        var fromBulk = document.querySelector('#bulkEventStatusForm input[name="csrf_token"]');
        if (fromBulk && fromBulk.value) {
            return fromBulk.value;
        }
        var any = document.querySelector('input[name="csrf_token"]');
        return any && any.value ? any.value : '';
    }

    function eventifySubmitOtpRequest(payload) {
        if (!payload || !payload.action || !payload.eventId) {
            return;
        }
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = payload.action;
        form.style.display = 'none';
        var fields = {
            csrf_token: eventifyGetCsrfToken(),
            event_id: String(payload.eventId),
            action: 'send_otp',
            return_to: payload.returnTo || 'dashboard',
            return_panel: payload.returnPanel || 'pending'
        };
        Object.keys(fields).forEach(function (name) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = fields[name];
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    }

    document.querySelectorAll('.js-confirm-otp-request').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.disabled) {
                return;
            }
            otpPendingPayload = {
                action: btn.getAttribute('data-otp-action') || '',
                eventId: btn.getAttribute('data-event-id') || '',
                returnTo: btn.getAttribute('data-return-to') || 'dashboard',
                returnPanel: btn.getAttribute('data-return-panel') || btn.getAttribute('data-open-modal') || 'pending'
            };
            if (otpReqMsgEl) {
                otpReqMsgEl.textContent = btn.getAttribute('data-confirm-message') || 'Are you sure you want to request OTP?';
            }
            if (otpReqModal) {
                otpReqModal.show();
            } else {
                eventifySubmitOtpRequest(otpPendingPayload);
                otpPendingPayload = null;
            }
        });
    });
    if (otpReqConfirmBtn) {
        otpReqConfirmBtn.addEventListener('click', function () {
            if (!otpPendingPayload) return;
            if (otpReqModal) otpReqModal.hide();
            eventifySubmitOtpRequest(otpPendingPayload);
            otpPendingPayload = null;
        });
    }
    if (otpReqModalEl) {
        otpReqModalEl.addEventListener('hidden.bs.modal', function () {
            otpPendingPayload = null;
        });
    }

    document.querySelectorAll('.js-assign-organizer-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var sel = form.querySelector('select[name="organizer_id"]');
            if (!sel) return;
            var eventTitle = form.getAttribute('data-event-title') || 'this event';
            var orgName = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text.trim() : 'organizer';
            var msg = 'Assign "' + eventTitle + '" to ' + orgName + '?\n\nAny pending OTP for this event will be cleared. Send a new OTP after assigning.';
            if (!window.confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    var adminChartInstances = [];

    function restoreAdminChartCanvas(wrapId, canvasId, ariaLabel) {
        var wrap = document.getElementById(wrapId);
        if (!wrap) return null;
        var existing = document.getElementById(canvasId);
        if (existing) return existing;
        wrap.innerHTML = '<canvas id="' + canvasId + '" aria-label="' + ariaLabel + '"></canvas>';
        return document.getElementById(canvasId);
    }

    function showEmptyChartMessage(wrapId, msg) {
        var wrap = document.getElementById(wrapId);
        if (!wrap) return;
        wrap.innerHTML = '<div class="adm-chart-empty">' + msg + '</div>';
    }

    function destroyAdminCharts() {
        adminChartInstances.forEach(function (chart) {
            try { chart.destroy(); } catch (e) { /* ignore */ }
        });
        adminChartInstances = [];
    }

    function initAdminAnalyticsCharts() {
        destroyAdminCharts();

        var deptData = window.__adminChartDept || { labels: [], counts: [] };
        var stData = window.__adminChartStatus || { labels: [], counts: [] };
        var fData = window.__adminChartFeedback || { labels: ['1★', '2★', '3★', '4★', '5★'], counts: [0, 0, 0, 0, 0] };
        var deptLabels = deptData.labels && deptData.labels.length ? deptData.labels : ['No events'];
        var deptCounts = deptData.counts && deptData.counts.length ? deptData.counts : [0];

        if (typeof Chart === 'undefined') {
            showEmptyChartMessage('adminChartDeptWrap', 'Charts unavailable (Chart.js failed to load).');
            showEmptyChartMessage('adminChartStatusWrap', 'Charts unavailable (Chart.js failed to load).');
            showEmptyChartMessage('adminChartFeedbackWrap', 'Charts unavailable (Chart.js failed to load).');
            return;
        }

        var cdept = restoreAdminChartCanvas('adminChartDeptWrap', 'adminChartDept', 'Bar chart of events by department');
        if (cdept && deptData.counts && deptData.counts.length) {
            adminChartInstances.push(new Chart(cdept, {
                type: 'bar',
                data: {
                    labels: deptLabels,
                    datasets: [{
                        label: 'Events',
                        data: deptCounts,
                        backgroundColor: 'rgba(14, 165, 233, 0.55)',
                        borderColor: 'rgba(14, 165, 233, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } },
                        x: { ticks: { maxRotation: 45, minRotation: 0 } }
                    }
                }
            }));
        } else if (cdept) {
            showEmptyChartMessage('adminChartDeptWrap', 'No events yet for department chart.');
        }

        var statusColorMap = {
            Pending: 'rgba(234, 179, 8, 0.85)',
            Active: 'rgba(16, 185, 129, 0.85)',
            Rejected: 'rgba(239, 68, 68, 0.85)',
            Closed: 'rgba(100, 116, 139, 0.85)',
            Completed: 'rgba(100, 116, 139, 0.85)',
            Unknown: 'rgba(148, 163, 184, 0.85)'
        };
        var cst = restoreAdminChartCanvas('adminChartStatusWrap', 'adminChartStatus', 'Doughnut chart of events by status');
        if (cst && stData.counts && stData.counts.length) {
            var statusLabels = stData.labels || [];
            var statusColors = statusLabels.map(function (label) {
                return statusColorMap[label] || statusColorMap.Unknown;
            });
            adminChartInstances.push(new Chart(cst, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: stData.counts || [],
                        backgroundColor: statusColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            }));
        } else if (cst) {
            showEmptyChartMessage('adminChartStatusWrap', 'No events yet for status chart.');
        }

        var cfb = restoreAdminChartCanvas('adminChartFeedbackWrap', 'adminChartFeedback', 'Bar chart of feedback ratings');
        if (cfb && fData.counts && fData.counts.length) {
            adminChartInstances.push(new Chart(cfb, {
                type: 'bar',
                data: {
                    labels: fData.labels || [],
                    datasets: [{
                        label: 'Feedback',
                        data: fData.counts || [],
                        backgroundColor: 'rgba(99, 102, 241, 0.6)',
                        borderColor: 'rgba(99, 102, 241, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            }));
        }
    }

    window.initAdminAnalyticsCharts = initAdminAnalyticsCharts;
    window.destroyAdminCharts = destroyAdminCharts;

    var openPendingBtn = document.getElementById('admOpenPendingBtn');
    var eventModal = document.getElementById('eventDetailsModal');
    if (openPendingBtn && eventModal) {
        openPendingBtn.addEventListener('click', function () {
            var eventModalInstance = bootstrap.Modal.getInstance(eventModal);
            if (eventModalInstance) eventModalInstance.hide();
            setTimeout(function () {
                if (typeof showAdminDashboardPanel === 'function') {
                    showAdminDashboardPanel('pending');
                }
            }, 300);
        });
    }

    var auditSearch = document.getElementById('auditLogSearch');
    if (auditSearch) {
        auditSearch.addEventListener('input', function () {
            var q = (auditSearch.value || '').toLowerCase().trim();
            document.querySelectorAll('#auditLogTableBody tr.audit-log-row').forEach(function (tr) {
                tr.style.display = !q || tr.innerText.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }

    // Reject modal: set event_id and return_to from trigger button
    var rejectModal = document.getElementById('rejectEventModal');
    if (rejectModal) {
        rejectModal.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            if (btn && btn.getAttribute('data-event-id')) {
                document.getElementById('rejectEventId').value = btn.getAttribute('data-event-id');
                document.getElementById('rejectReturnTo').value = btn.getAttribute('data-return-to') || '';
                var returnPanel = btn.getAttribute('data-return-panel') || btn.getAttribute('data-open-modal') || 'pending';
                var rejectPanelInput = document.getElementById('rejectReturnPanel');
                if (rejectPanelInput) {
                    rejectPanelInput.value = returnPanel;
                }
                var rejectOpenModal = document.getElementById('rejectOpenModal');
                if (rejectOpenModal) {
                    rejectOpenModal.value = returnPanel;
                }
                var title = btn.getAttribute('data-event-title') || 'this event';
                document.getElementById('rejectEventTitleText').textContent = 'Reject "' + title + '"? Optionally give a reason so the organizer knows what to fix.';
                document.getElementById('rejectReasonInput').value = '';
            }
        });
    }

    var headCheck = document.getElementById('pendingHeadCheck');
    var selectAllBtn = document.getElementById('bulkSelectAllPending');
    var bulkRejectBtn = document.getElementById('bulkRejectBtn');
    var bulkForm = document.getElementById('bulkEventStatusForm');
    function getPendingChecks() {
        return Array.prototype.slice.call(document.querySelectorAll('.pending-event-checkbox'));
    }
    function syncPendingCardSelection() {
        document.querySelectorAll('[data-pending-card]').forEach(function (card) {
            var cb = card.querySelector('.pending-event-checkbox');
            card.classList.toggle('is-selected', !!(cb && cb.checked));
        });
    }
    function setAllPendingChecks(v) {
        getPendingChecks().forEach(function (c) { c.checked = !!v; });
        if (headCheck) headCheck.checked = !!v;
        syncPendingCardSelection();
    }
    getPendingChecks().forEach(function (cb) {
        cb.addEventListener('change', syncPendingCardSelection);
    });
    if (headCheck) {
        headCheck.addEventListener('change', function () { setAllPendingChecks(headCheck.checked); });
    }
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function () {
            var checks = getPendingChecks();
            var allChecked = checks.length > 0 && checks.every(function (c) { return c.checked; });
            setAllPendingChecks(!allChecked);
        });
    }
    if (bulkRejectBtn && bulkForm) {
        bulkRejectBtn.addEventListener('click', function () {
            var selected = getPendingChecks().some(function (c) { return c.checked; });
            if (!selected) {
                alert('Select at least one event first.');
                return;
            }
            var reason = prompt('Optional rejection reason for selected events:', '');
            var input = document.getElementById('bulkRejectReasonInput');
            if (input) input.value = reason || '';
            var hiddenAction = document.createElement('input');
            hiddenAction.type = 'hidden';
            hiddenAction.name = 'action';
            hiddenAction.value = 'reject';
            bulkForm.appendChild(hiddenAction);
            bulkForm.submit();
        });
    }

    var openModal = String(window.__adminOpenModal || '').toLowerCase();
    var mappedOpenPanel = adminOpenModalToPanel(openModal);
    if (!mappedOpenPanel) {
        if (openModal === 'settings') {
            var sm = document.getElementById('adminSettingsModal');
            if (sm && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(sm).show();
            }
        } else if (openModal === 'profile') {
            var apm = document.getElementById('adminProfileModal');
            if (apm && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(apm).show();
            }
        } else if (openModal === 'notifications') {
            var nm = document.getElementById('adminNotificationsModal');
            if (nm && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(nm).show();
            }
        }
    }

    if (mappedOpenPanel || openModal === 'events' || openModal === 'accounts') {
        try {
            var panelUrl = new URL(window.location.href);
            panelUrl.searchParams.delete('open_modal');
            if (!panelUrl.searchParams.has('panel')) {
                var nextPanel = mappedOpenPanel || (openModal === 'accounts' ? 'users' : 'events');
                panelUrl.searchParams.set('panel', nextPanel);
            }
            var panelNext = panelUrl.pathname + (panelUrl.searchParams.toString() ? '?' + panelUrl.searchParams.toString() : '') + panelUrl.hash;
            history.replaceState({ adminPanel: panelUrl.searchParams.get('panel') || 'home' }, '', panelNext);
        } catch (e) { /* ignore */ }
    }

    initAdminDashboardPanels();
    initAdminDashboardPanelFromUrl();
    var activePanel = adminGetPanelFromUrl();
    var openModalLower = String(window.__adminOpenModal || '').toLowerCase();
    if (ADMIN_DASH_PANEL_NAMES.indexOf(activePanel) >= 0 || adminOpenModalToPanel(openModalLower)) {
        initAdminDashPanelEnter();
    }

    (function initAdminAllEventsFilters() {
        var search = document.getElementById('adminAllEventsSearch');
        var statusFilter = document.getElementById('adminAllEventsStatusFilter');
        var items = document.querySelectorAll('.admin-all-events-item');
        var emptyMsg = document.getElementById('adminAllEventsEmpty');
        if (!items.length) return;

        function applyAllEventsFilters() {
            var q = search ? (search.value || '').toLowerCase().trim() : '';
            var status = statusFilter ? statusFilter.value : '';
            var visibleCount = 0;
            items.forEach(function (row) {
                var matchesSearch = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
                var rowStatus = row.getAttribute('data-status') || '';
                var matchesStatus = !status || rowStatus === status;
                var visible = matchesSearch && matchesStatus;
                row.classList.toggle('d-none', !visible);
                if (visible) visibleCount++;
            });
            if (emptyMsg) {
                emptyMsg.classList.toggle('d-none', visibleCount > 0);
            }
        }

        if (search) search.addEventListener('input', applyAllEventsFilters);
        if (statusFilter) statusFilter.addEventListener('change', applyAllEventsFilters);
    })();

    document.querySelectorAll('.admin-upcoming-event-link').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = this.getAttribute('data-event-id');
            var dateStr = this.getAttribute('data-event-date') || '';
            if (typeof showAdminDashboardPanel === 'function') {
                showAdminDashboardPanel('home');
            }
            setTimeout(function () {
                var opened = typeof window.eventifyOpenEventDetailsById === 'function' && id && window.eventifyOpenEventDetailsById(id);
                if (!opened && window.eventifyCalendar && dateStr) {
                    try {
                        window.eventifyCalendar.gotoDate(dateStr);
                    } catch (e) { /* ignore */ }
                }
            }, 320);
        });
    });

    // Apply saved admin display preferences client-side.
    var settings = window.__adminSettings || {};
    var defaultView = String(settings.default_dashboard_view || '').toLowerCase();
    if (defaultView === 'pending') {
        setTimeout(function () {
            if (typeof showAdminDashboardPanel === 'function') {
                showAdminDashboardPanel('pending', { replaceUrl: true });
            }
        }, 250);
    } else if (defaultView === 'charts') {
        setTimeout(function () {
            if (typeof showAdminDashboardPanel === 'function') {
                showAdminDashboardPanel('analytics', { replaceUrl: true });
            }
        }, 250);
    }

    var legend = document.getElementById('adminCalendarLegend');
    if (legend && Number(settings.calendar_legend_visible || 0) !== 1) {
        legend.style.display = 'none';
    }

    function adminParseStoredDepartments(stored) {
        var raw = String(stored || 'ALL').trim();
        if (raw === '' || raw === 'ALL') {
            return ['ALL'];
        }
        if (raw.charAt(0) === '[') {
            try {
                var arr = JSON.parse(raw);
                if (Array.isArray(arr) && arr.length) {
                    return arr.map(function (x) { return String(x).trim(); }).filter(Boolean);
                }
            } catch (e) { /* ignore */ }
        }
        return [raw];
    }

    function adminSetEditPendingDepartments(stored) {
        var selected = adminParseStoredDepartments(stored);
        var useAll = selected.indexOf('ALL') !== -1;
        document.querySelectorAll('.admin-edit-dept-cb').forEach(function (cb) {
            if (cb.classList.contains('admin-edit-dept-cb--all')) {
                cb.checked = useAll;
            } else {
                cb.checked = !useAll && selected.indexOf(cb.value) !== -1;
            }
        });
    }

    var editPendingModal = document.getElementById('adminEditPendingEventModal');
    if (editPendingModal) {
        editPendingModal.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            if (!btn || !btn.classList.contains('js-edit-pending-event')) {
                return;
            }
            var idInput = document.getElementById('adminEditPendingEventId');
            var titleInput = document.getElementById('adminEditPendingTitle');
            var dateInput = document.getElementById('adminEditPendingDate');
            var locInput = document.getElementById('adminEditPendingLocation');
            var descInput = document.getElementById('adminEditPendingDescription');
            if (idInput) idInput.value = btn.getAttribute('data-event-id') || '';
            if (titleInput) titleInput.value = btn.getAttribute('data-event-title') || '';
            if (dateInput) dateInput.value = btn.getAttribute('data-event-date') || '';
            if (locInput) locInput.value = btn.getAttribute('data-event-location') || '';
            if (descInput) descInput.value = btn.getAttribute('data-event-description') || '';
            adminSetEditPendingDepartments(btn.getAttribute('data-event-department') || 'ALL');
        });

        var allDeptCb = editPendingModal.querySelector('.admin-edit-dept-cb--all');
        if (allDeptCb) {
            allDeptCb.addEventListener('change', function () {
                if (!allDeptCb.checked) {
                    return;
                }
                editPendingModal.querySelectorAll('.admin-edit-dept-cb:not(.admin-edit-dept-cb--all)').forEach(function (cb) {
                    cb.checked = false;
                });
            });
        }
        editPendingModal.querySelectorAll('.admin-edit-dept-cb:not(.admin-edit-dept-cb--all)').forEach(function (cb) {
            cb.addEventListener('change', function () {
                if (!cb.checked) {
                    return;
                }
                if (allDeptCb) {
                    allDeptCb.checked = false;
                }
            });
        });
    }

    document.addEventListener('eventify:notif-open-accounts', function () {
        var listModal = document.getElementById('adminNotificationsModal');
        if (listModal && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var listInst = bootstrap.Modal.getInstance(listModal);
            if (listInst) {
                listInst.hide();
            }
        }
        if (typeof showAdminDashboardPanel === 'function') {
            setTimeout(function () {
                showAdminDashboardPanel('users');
            }, 200);
        }
    });

    // All Users panel: confirm destructive/approval actions then submit.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-confirm-submit');
        if (!btn) return;
        var msg = btn.getAttribute('data-confirm-message') || 'Are you sure?';
        if (window.confirm(msg)) {
            var form = btn.closest('form');
            if (form) form.submit();
        }
    });

    // All Users panel: open the "edit student course" dialog prefilled.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-edit-student-course');
        if (!btn) return;
        var idEl = document.getElementById('admEditCourseUserId');
        var nameEl = document.getElementById('admEditCourseUserName');
        var sel = document.getElementById('admEditCourseSelect');
        if (idEl) idEl.value = btn.getAttribute('data-user-id') || '';
        if (nameEl) nameEl.textContent = btn.getAttribute('data-user-name') || 'this student';
        if (sel) sel.value = btn.getAttribute('data-current-course') || '';
        if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
        var editModalEl = document.getElementById('adminEditStudentCourseModal');
        if (editModalEl) {
            bootstrap.Modal.getOrCreateInstance(editModalEl).show();
        }
    });

    // All Users panel: open the "edit student section" dialog prefilled.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-edit-student-section');
        if (!btn) return;
        var idEl = document.getElementById('admEditSectionUserId');
        var nameEl = document.getElementById('admEditSectionUserName');
        var sel = document.getElementById('admEditSectionSelect');
        var neu = document.getElementById('admEditSectionNew');
        var current = btn.getAttribute('data-current-section') || '';
        if (idEl) idEl.value = btn.getAttribute('data-user-id') || '';
        if (nameEl) nameEl.textContent = btn.getAttribute('data-user-name') || 'this student';
        if (neu) neu.value = '';

        // Rebuild dropdown from PHP list + visible chips (fixes empty select after add).
        if (sel) {
            var labels = [];
            var seen = {};
            function pushLabel(lab) {
                lab = String(lab || '').trim();
                if (!lab) return;
                var key = lab.toLowerCase();
                if (seen[key]) return;
                seen[key] = true;
                labels.push(lab);
            }
            if (Array.isArray(window.__adminClassSections)) {
                window.__adminClassSections.forEach(function (s) {
                    pushLabel(s && s.label);
                });
            }
            var chipList = document.getElementById('admClassSectionsList');
            if (chipList) {
                Array.prototype.forEach.call(chipList.querySelectorAll('[data-section-label]'), function (el) {
                    pushLabel(el.getAttribute('data-section-label'));
                });
            }
            // Keep any existing option labels already in the select.
            Array.prototype.forEach.call(sel.options, function (opt) {
                if (opt.value) pushLabel(opt.value);
            });
            labels.sort(function (a, b) {
                return a.localeCompare(b, undefined, { sensitivity: 'base' });
            });
            sel.innerHTML = '';
            var none = document.createElement('option');
            none.value = '';
            none.textContent = '— None / clear —';
            sel.appendChild(none);
            labels.forEach(function (lab) {
                var opt = document.createElement('option');
                opt.value = lab;
                opt.textContent = lab;
                if (lab.toLowerCase() === current.toLowerCase()) {
                    opt.selected = true;
                }
                sel.appendChild(opt);
            });
            if (!sel.value && current) {
                var orphan = document.createElement('option');
                orphan.value = current;
                orphan.textContent = current + ' (current)';
                orphan.selected = true;
                sel.appendChild(orphan);
            }
        }

        if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
        var editModalEl = document.getElementById('adminEditStudentSectionModal');
        if (editModalEl) {
            bootstrap.Modal.getOrCreateInstance(editModalEl).show();
        }
    });

    // All Users panel: live search + role/status filtering.
    (function () {
        var search = document.getElementById('admUserSearch');
        var roleFilter = document.getElementById('admUserRoleFilter');
        var statusFilter = document.getElementById('admUserStatusFilter');
        var table = document.getElementById('admUsersTable');
        var noResults = document.getElementById('admUsersNoResults');
        if (!table) return;
        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody .adm-user-row'));

        function applyFilters() {
            var q = (search && search.value ? search.value : '').trim().toLowerCase();
            var role = roleFilter ? roleFilter.value : '';
            var status = statusFilter ? statusFilter.value : '';
            var shown = 0;
            rows.forEach(function (row) {
                var matchesSearch = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
                var matchesRole = !role || row.getAttribute('data-role') === role;
                var matchesStatus = !status || row.getAttribute('data-status') === status;
                var visible = matchesSearch && matchesRole && matchesStatus;
                row.style.display = visible ? '' : 'none';
                if (visible) shown++;
            });
            if (noResults) noResults.style.display = shown === 0 ? '' : 'none';
        }

        if (search) search.addEventListener('input', applyFilters);
        if (roleFilter) roleFilter.addEventListener('change', applyFilters);
        if (statusFilter) statusFilter.addEventListener('change', applyFilters);
    })();

    document.addEventListener('eventify:notif-view-event', function (e) {
        var detail = (e && e.detail) || {};
        if (detail.eventId && typeof window.eventifyOpenEventDetailsById === 'function') {
            setTimeout(function () {
                window.eventifyOpenEventDetailsById(String(detail.eventId));
            }, 200);
        }
    });

    (function initAdminAnnouncementForm() {
        var form = document.getElementById('adminAnnouncementForm');
        if (!form) return;
        var filterError = document.getElementById('announceFilterError');
        var deptAll = form.querySelector('[data-announce-dept-all]');
        var deptSpecific = form.querySelectorAll('[data-announce-dept-specific]');

        function syncDeptCheckboxes(source) {
            if (!deptAll) return;
            if (source === deptAll && deptAll.checked) {
                deptSpecific.forEach(function (cb) { cb.checked = false; });
            } else if (source !== deptAll) {
                var anySpecific = false;
                deptSpecific.forEach(function (cb) {
                    if (cb.checked) anySpecific = true;
                });
                if (anySpecific) deptAll.checked = false;
            }
        }

        if (deptAll) {
            deptAll.addEventListener('change', function () { syncDeptCheckboxes(deptAll); });
        }
        deptSpecific.forEach(function (cb) {
            cb.addEventListener('change', function () { syncDeptCheckboxes(cb); });
        });

        function hasAudienceFilter() {
            var checked = form.querySelectorAll(
                'input[name="department[]"]:checked, input[name="section[]"]:checked, input[name="course[]"]:checked'
            );
            if (checked.length > 0) return true;
            var newSection = form.querySelector('input[name="new_section"]');
            return !!(newSection && newSection.value && newSection.value.trim());
        }

        form.addEventListener('submit', function (e) {
            if (!hasAudienceFilter()) {
                e.preventDefault();
                if (filterError) filterError.classList.remove('d-none');
                var audience = document.getElementById('announceAudienceFilters');
                if (audience && typeof audience.scrollIntoView === 'function') {
                    audience.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
                return false;
            }
            if (filterError) filterError.classList.add('d-none');
            return true;
        });
    })();

    (function initAdminAnnouncementModals() {
        function openModal(id) {
            if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
            var el = document.getElementById(id);
            if (!el) return;
            bootstrap.Modal.getOrCreateInstance(el).show();
        }

        document.addEventListener('click', function (e) {
            var editBtn = e.target.closest('.js-edit-announcement');
            if (editBtn) {
                e.preventDefault();
                var idInput = document.getElementById('editAnnounceId');
                var titleInput = document.getElementById('editAnnounceTitle');
                var bodyInput = document.getElementById('editAnnounceBody');
                if (idInput) idInput.value = editBtn.getAttribute('data-announce-id') || '';
                if (titleInput) {
                    titleInput.value = editBtn.getAttribute('data-announce-title') || '';
                    titleInput.removeAttribute('readonly');
                    titleInput.removeAttribute('disabled');
                }
                if (bodyInput) {
                    bodyInput.value = editBtn.getAttribute('data-announce-body') || '';
                    bodyInput.removeAttribute('readonly');
                    bodyInput.removeAttribute('disabled');
                }
                openModal('editAnnouncementModal');
                setTimeout(function () {
                    if (titleInput) titleInput.focus();
                }, 250);
                return;
            }

            var deleteBtn = e.target.closest('.js-delete-announcement');
            if (deleteBtn) {
                e.preventDefault();
                var delId = document.getElementById('deleteAnnounceId');
                var delMsg = document.getElementById('deleteAnnounceMessage');
                var title = deleteBtn.getAttribute('data-announce-title') || 'this announcement';
                if (delId) delId.value = deleteBtn.getAttribute('data-announce-id') || '';
                if (delMsg) {
                    delMsg.textContent = 'Delete “' + title + '”? Students will also lose this bell notification.';
                }
                openModal('deleteAnnouncementModal');
            }
        });

        var editModal = document.getElementById('editAnnouncementModal');
        if (editModal) {
            editModal.addEventListener('shown.bs.modal', function () {
                var titleInput = document.getElementById('editAnnounceTitle');
                if (titleInput) {
                    titleInput.removeAttribute('readonly');
                    titleInput.removeAttribute('disabled');
                    titleInput.focus();
                }
                var bodyInput = document.getElementById('editAnnounceBody');
                if (bodyInput) {
                    bodyInput.removeAttribute('readonly');
                    bodyInput.removeAttribute('disabled');
                }
            });
        }
    })();

});
