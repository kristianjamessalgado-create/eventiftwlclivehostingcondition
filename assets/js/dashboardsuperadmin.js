function eventifyInitSaMobileSidebar() {
    var sidebarToggle = document.getElementById('saSidebarToggle');
    var sidebarClose = document.getElementById('saSidebarClose');
    var sidebarBackdrop = document.getElementById('saSidebarBackdrop');
    var sidebar = document.getElementById('saSidebar');
    var isMobileView = function () { return window.matchMedia('(max-width: 768px)').matches; };

    var setToggleExpanded = function (expanded) {
        if (!sidebarToggle) return;
        sidebarToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        sidebarToggle.setAttribute('aria-label', expanded ? 'Close menu' : 'Open menu');
    };

    var openMobileSidebar = function () {
        document.body.classList.add('sa-sidebar-open');
        setToggleExpanded(true);
    };

    var closeMobileSidebar = function () {
        document.body.classList.remove('sa-sidebar-open');
        setToggleExpanded(false);
    };

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            if (!isMobileView()) return;
            if (document.body.classList.contains('sa-sidebar-open')) {
                closeMobileSidebar();
            } else {
                openMobileSidebar();
            }
        });
    }
    if (sidebarClose) sidebarClose.addEventListener('click', closeMobileSidebar);
    if (sidebarBackdrop) sidebarBackdrop.addEventListener('click', closeMobileSidebar);
    if (sidebar) {
        sidebar.addEventListener('click', function (e) {
            var target = e.target.closest('[data-bs-toggle="modal"], .sa-nav-btn, a.sa-nav-btn');
            if (target && isMobileView()) {
                closeMobileSidebar();
            }
        });
    }
    window.addEventListener('resize', function () {
        if (!isMobileView()) {
            closeMobileSidebar();
        }
    });
}

function eventifyInitSuperAdminDashboard() {
    eventifyInitSaMobileSidebar();

    var pendingConfirmForm = null;
    var actionConfirmModalEl = document.getElementById('actionConfirmModal');
    var actionConfirmTextEl = document.getElementById('actionConfirmText');
    var actionConfirmProceedBtn = document.getElementById('actionConfirmProceedBtn');
    var actionConfirmModal = null;
    if (actionConfirmModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        actionConfirmModal = bootstrap.Modal.getOrCreateInstance(actionConfirmModalEl);
        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('.js-confirm-submit');
            if (!trigger) return;
            e.preventDefault();
            var form = trigger.closest('form');
            if (!form) return;
            pendingConfirmForm = form;
            var msg = trigger.getAttribute('data-confirm-message') || 'Are you sure you want to continue?';
            if (actionConfirmTextEl) actionConfirmTextEl.textContent = msg;
            actionConfirmModal.show();
        });
        if (actionConfirmProceedBtn) {
            actionConfirmProceedBtn.addEventListener('click', function () {
                if (!pendingConfirmForm) return;
                var formToSubmit = pendingConfirmForm;
                pendingConfirmForm = null;
                actionConfirmModal.hide();
                formToSubmit.submit();
            });
        }
        actionConfirmModalEl.addEventListener('hidden.bs.modal', function () {
            pendingConfirmForm = null;
        });
    }

    var reactivateModalEl = document.getElementById('reactivateConfirmModal');
    if (reactivateModalEl) {
        reactivateModalEl.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            var userId = btn && btn.getAttribute ? btn.getAttribute('data-user-id') : '';
            var openModal = btn && btn.getAttribute ? (btn.getAttribute('data-open-modal') || 'users') : 'users';
            var input = document.getElementById('reactivateUserId');
            if (input) input.value = userId || '';
            var openInput = document.getElementById('reactivateOpenModal');
            if (openInput) openInput.value = openModal;
        });
    }

    if (typeof Chart !== 'undefined') {
        var ur = window.saUserRoles || { labels: [], counts: [] };
        var es = window.saEventStatus || { labels: [], counts: [] };
        var uCtx = document.getElementById('saUsersChart');
        if (uCtx) {
            new Chart(uCtx, {
                type: 'bar',
                data: {
                    labels: ur.labels || [],
                    datasets: [{ data: ur.counts || [], backgroundColor: 'rgba(56,189,248,0.65)', borderColor: 'rgba(56,189,248,1)', borderWidth: 1 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }
        var eCtx = document.getElementById('saEventsChart');
        if (eCtx) {
            new Chart(eCtx, {
                type: 'doughnut',
                data: {
                    labels: es.labels || [],
                    datasets: [{ data: es.counts || [], backgroundColor: ['rgba(234,179,8,.85)', 'rgba(16,185,129,.85)', 'rgba(239,68,68,.85)', 'rgba(100,116,139,.85)'] }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
            });
        }
    }

    var rejectModalEl = document.getElementById('rejectEventModal');
    if (rejectModalEl) {
        rejectModalEl.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            if (btn && btn.getAttribute('data-event-id')) {
                document.getElementById('rejectEventId').value = btn.getAttribute('data-event-id') || '';
                document.getElementById('rejectReturnTo').value = btn.getAttribute('data-return-to') || 'dashboard';
                document.getElementById('rejectOpenModal').value = btn.getAttribute('data-open-modal') || 'events';
                var title = btn.getAttribute('data-event-title') || 'this event';
                document.getElementById('rejectEventTitleText').textContent = 'Reject "' + title + '"? Optionally give a reason so the organizer knows what to fix.';
                document.getElementById('rejectReasonInput').value = '';
            }
        });
    }

    // Simple client-side filtering for All Users table
    (function () {
        var searchInput = document.getElementById('userSearch');
        var roleFilter = document.getElementById('roleFilter');
        var statusFilter = document.getElementById('statusFilter');
        var table = document.getElementById('usersTable');
        if (!table) return;

        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));

        function applyFilters() {
            var query = (searchInput && searchInput.value ? searchInput.value.toLowerCase() : '').trim();
            var role = roleFilter ? roleFilter.value : '';
            var status = statusFilter ? statusFilter.value : '';

            rows.forEach(function (row) {
                var nameCell = row.cells[1] ? row.cells[1].innerText.toLowerCase() : '';
                var emailCell = row.cells[2] ? row.cells[2].innerText.toLowerCase() : '';
                var rowRole = row.getAttribute('data-role') || '';
                var rowStatus = row.getAttribute('data-status') || '';

                var matchesSearch = !query || nameCell.indexOf(query) !== -1 || emailCell.indexOf(query) !== -1;
                var matchesRole = !role || rowRole === role;
                var matchesStatus = !status || rowStatus === status;

                row.style.display = (matchesSearch && matchesRole && matchesStatus) ? '' : 'none';
            });
        }

        if (searchInput) searchInput.addEventListener('input', applyFilters);
        if (roleFilter) roleFilter.addEventListener('change', applyFilters);
        if (statusFilter) statusFilter.addEventListener('change', applyFilters);
    })();

    // Keep modal open after server-side pagination reload.
    (function () {
        if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
        var params = new URLSearchParams(window.location.search || '');
        var openModal = (params.get('open_modal') || '').toLowerCase();
        var targetId = '';
        if (openModal === 'users') {
            targetId = 'usersModal';
        } else if (openModal === 'events') {
            targetId = 'allEventsModal';
        } else if (openModal === 'pending') {
            targetId = 'pendingEventsModal';
        }
        if (!targetId) return;
        var modalEl = document.getElementById(targetId);
        if (!modalEl) return;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    })();

    // All Events table filters
    (function () {
        var searchInput = document.getElementById('eventSearch');
        var statusFilter = document.getElementById('allEventsStatusFilter');
        var deptFilter = document.getElementById('allEventsDeptFilter');
        var table = document.getElementById('allEventsTable');
        if (!table) return;
        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
        function applyEventFilters() {
            var q = (searchInput && searchInput.value ? searchInput.value.toLowerCase() : '').trim();
            var status = statusFilter ? statusFilter.value : '';
            var dept = deptFilter ? deptFilter.value : '';
            rows.forEach(function (row) {
                var titleCell = row.cells[1] ? row.cells[1].innerText.toLowerCase() : '';
                var locCell = row.cells[2] ? row.cells[2].innerText.toLowerCase() : '';
                var rowStatus = row.getAttribute('data-status') || '';
                var rowDept = row.getAttribute('data-dept') || '';
                var matchSearch = !q || titleCell.indexOf(q) !== -1 || locCell.indexOf(q) !== -1;
                var normalizedStatus = (rowStatus === 'completed') ? 'closed' : rowStatus;
                var normalizedFilter = (status === 'completed') ? 'closed' : status;
                var matchStatus = !normalizedFilter || normalizedStatus === normalizedFilter;
                var matchDept = !dept || rowDept === dept;
                row.style.display = (matchSearch && matchStatus && matchDept) ? '' : 'none';
            });
        }
        if (searchInput) searchInput.addEventListener('input', applyEventFilters);
        if (statusFilter) statusFilter.addEventListener('change', applyEventFilters);
        if (deptFilter) deptFilter.addEventListener('change', applyEventFilters);
    })();

    // Calendar modal: init FullCalendar when modal is fully shown (so dimensions are correct)
    var saCalendarInstance = null;
    var calendarModalEl = document.getElementById('calendarModal');
    if (calendarModalEl) {
        calendarModalEl.addEventListener('shown.bs.modal', function () {
            var el = document.getElementById('saCalendar');
            if (!el) return;
            if (saCalendarInstance) {
                try { saCalendarInstance.destroy(); } catch (err) {}
                saCalendarInstance = null;
            }
            if (typeof FullCalendar === 'undefined') {
                console.warn('FullCalendar not loaded');
                el.innerHTML = '<p class="text-muted p-3">Calendar could not load. Check console.</p>';
                return;
            }
            var events = window.saAllEventsJson;
            if (!Array.isArray(events)) events = [];
            saCalendarInstance = new FullCalendar.Calendar(el, {
                initialView: 'dayGridMonth',
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek' },
                events: events,
                eventDisplay: 'block',
                height: 400,
                eventContent: function (arg) {
                    if (typeof eventifyCalendarEventContent === 'function') {
                        var custom = eventifyCalendarEventContent(arg);
                        if (custom !== true) {
                            return custom;
                        }
                    }
                    return true;
                },
                eventDidMount: function (info) {
                    if (typeof eventifyApplyCalendarEventMount === 'function') {
                        eventifyApplyCalendarEventMount(info);
                    }
                }
            });
            saCalendarInstance.render();
            if (typeof eventifyBindCalendarDoubleClickUnselect === 'function') {
                eventifyBindCalendarDoubleClickUnselect(saCalendarInstance, el);
            }
        });
        calendarModalEl.addEventListener('hidden.bs.modal', function () {
            if (saCalendarInstance) {
                try { saCalendarInstance.destroy(); } catch (err) {}
                saCalendarInstance = null;
            }
        });
    }
}
