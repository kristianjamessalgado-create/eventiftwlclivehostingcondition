function eventifyInitSaMobileSidebar() {
    var sidebarToggle = document.getElementById('saSidebarToggle');
    var sidebarClose = document.getElementById('saSidebarClose');
    var sidebarBackdrop = document.getElementById('saSidebarBackdrop');
    var sidebar = document.getElementById('saSidebar');
    var layout = document.querySelector('.sa-layout');
    var mainEl = document.querySelector('.sa-main');
    var navEl = document.querySelector('.sa-navbar');
    var isMobileView = function () { return window.matchMedia('(max-width: 768px)').matches; };

    var setToggleExpanded = function (expanded) {
        if (!sidebarToggle) return;
        sidebarToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        sidebarToggle.setAttribute('aria-label', expanded ? 'Close menu' : 'Open menu');
    };

    var pinOverlays = function () {
        if (!navEl) return;
        if (isMobileView()) {
            if (sidebarBackdrop && sidebarBackdrop.previousElementSibling !== navEl) {
                navEl.after(sidebarBackdrop);
            }
            if (sidebar && sidebarBackdrop && sidebar.previousElementSibling !== sidebarBackdrop) {
                sidebarBackdrop.after(sidebar);
            }
        } else if (layout && mainEl && sidebar && sidebar.parentElement !== layout) {
            layout.insertBefore(sidebar, mainEl);
        }
    };

    var openMobileSidebar = function () {
        pinOverlays();
        document.body.classList.add('sa-sidebar-open');
        setToggleExpanded(true);
    };

    var closeMobileSidebar = function () {
        document.body.classList.remove('sa-sidebar-open');
        setToggleExpanded(false);
    };

    pinOverlays();

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (document.body.classList.contains('sa-sidebar-open')) {
                closeMobileSidebar();
                return;
            }
            if (!isMobileView()) return;
            openMobileSidebar();
        });
    }
    if (sidebarClose) {
        sidebarClose.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            closeMobileSidebar();
        });
    }
    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            closeMobileSidebar();
        });
    }
    document.querySelectorAll('a.sa-logout').forEach(function (link) {
        link.addEventListener('click', function () {
            closeMobileSidebar();
        });
    });
    var logoutModalEl = document.getElementById('logoutModal');
    if (logoutModalEl && logoutModalEl.parentElement !== document.body) {
        document.body.appendChild(logoutModalEl);
    }
    if (sidebar) {
        sidebar.addEventListener('click', function (e) {
            var target = e.target.closest('[data-bs-toggle="modal"], .sa-nav-btn, a.sa-nav-btn, .sa-logout');
            if (target) {
                closeMobileSidebar();
            }
        });
    }
    window.addEventListener('resize', function () {
        pinOverlays();
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
        var lt = window.saLoginTrend || { labels: [], counts: [] };
        var lCtx = document.getElementById('saLoginsChart');
        if (lCtx) {
            new Chart(lCtx, {
                type: 'line',
                data: {
                    labels: lt.labels || [],
                    datasets: [{
                        data: lt.counts || [],
                        borderColor: 'rgba(16,185,129,1)',
                        backgroundColor: 'rgba(16,185,129,0.18)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }
    }

    // Users / All Events filters run on the server (they search every page, not only the
    // 20 rows shown). Dropdowns apply right away; search applies on Enter or the Search button.
    (function () {
        document.querySelectorAll('form.js-sa-filter-form').forEach(function (form) {
            form.querySelectorAll('select').forEach(function (sel) {
                sel.addEventListener('change', function () {
                    form.submit();
                });
            });
        });
        // After a search reloads the page, put the cursor back in the search box.
        var params = new URLSearchParams(window.location.search || '');
        var focusId = params.get('u_q') ? 'userSearch' : (params.get('e_q') ? 'eventSearch' : '');
        if (focusId) {
            var modalId = focusId === 'userSearch' ? 'usersModal' : 'allEventsModal';
            var modalEl = document.getElementById(modalId);
            if (modalEl) {
                modalEl.addEventListener('shown.bs.modal', function () {
                    var input = document.getElementById(focusId);
                    if (input) {
                        input.focus();
                        var len = input.value.length;
                        try { input.setSelectionRange(len, len); } catch (e) { /* search inputs may not support it */ }
                    }
                }, { once: true });
            }
        }
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
        } else if (openModal === 'security') {
            targetId = 'securityModal';
        }
        if (!targetId) return;
        var modalEl = document.getElementById(targetId);
        if (!modalEl) return;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
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

    var delUserModal = document.getElementById('saDeleteUserModal');
    var delUserId = document.getElementById('saDeleteUserId');
    var delUserMsg = document.getElementById('saDeleteUserMessage');
    var delUserBlock = document.getElementById('saDeleteUserBlock');
    var delUserWrap = document.getElementById('saDeleteUserConfirmWrap');
    var delUserInput = document.getElementById('saDeleteUserConfirmInput');
    var delUserSubmit = document.getElementById('saDeleteUserSubmit');

    function refreshSaDeleteUserGate() {
        if (!delUserSubmit) {
            return;
        }
        var blocked = delUserBlock && !delUserBlock.hidden;
        var typedOk = delUserInput && String(delUserInput.value || '').trim() === 'DELETE';
        delUserSubmit.disabled = blocked || !typedOk;
    }

    if (delUserModal) {
        delUserModal.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            if (!btn || !delUserId) {
                return;
            }
            var name = btn.getAttribute('data-user-name') || 'this user';
            var email = btn.getAttribute('data-user-email') || '';
            var role = btn.getAttribute('data-user-role') || 'account';
            var eventCount = parseInt(btn.getAttribute('data-event-count') || '0', 10) || 0;
            delUserId.value = btn.getAttribute('data-user-id') || '';
            if (delUserMsg) {
                delUserMsg.textContent = 'Permanently delete ' + name + (email ? ' (' + email + ')' : '') + '? This cannot be undone.';
            }
            if (delUserInput) {
                delUserInput.value = '';
            }
            if (eventCount > 0) {
                if (delUserBlock) {
                    delUserBlock.hidden = false;
                    delUserBlock.textContent = 'This organizer still has ' + eventCount + ' event(s). Close or delete those events first, or deactivate the account instead.';
                }
                if (delUserWrap) {
                    delUserWrap.hidden = true;
                }
                if (delUserSubmit) {
                    delUserSubmit.hidden = true;
                }
            } else {
                if (delUserBlock) {
                    delUserBlock.hidden = true;
                    delUserBlock.textContent = '';
                }
                if (delUserWrap) {
                    delUserWrap.hidden = false;
                }
                if (delUserSubmit) {
                    delUserSubmit.hidden = false;
                }
            }
            refreshSaDeleteUserGate();
        });
    }
    if (delUserInput) {
        delUserInput.addEventListener('input', refreshSaDeleteUserGate);
    }
    var delUserForm = document.getElementById('saDeleteUserForm');
    if (delUserForm) {
        delUserForm.addEventListener('submit', function (e) {
            if (!delUserSubmit || delUserSubmit.disabled || delUserSubmit.hidden) {
                e.preventDefault();
            }
        });
    }
}
