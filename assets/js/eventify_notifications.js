/**
 * EVENTIFY in-app notifications — mark read, detail modal, clear all.
 */
(function (global) {
    'use strict';

    var detailModalBound = false;

    function baseUrl() {
        return (global.BASE_URL || '').replace(/\/$/, '');
    }

    function csrfToken() {
        return global.csrfToken || global.__adminCsrfToken || '';
    }

    function currentRole() {
        return String(global.currentRole || '').toLowerCase();
    }

    function postNotification(action, extra, csrfOverride) {
        var token = csrfOverride || csrfToken();
        if (!token) {
            return Promise.resolve({ ok: false, message: 'Missing security token. Refresh the page and try again.' });
        }
        var fd = new FormData();
        fd.append('csrf_token', token);
        fd.append('action', action);
        fd.append('ajax', '1');
        if (extra) {
            Object.keys(extra).forEach(function (k) {
                if (extra[k] != null && extra[k] !== '') {
                    fd.append(k, String(extra[k]));
                }
            });
        }
        return fetch(baseUrl() + '/backend/auth/mark_notification_read.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json();
        });
    }

    function updateBadgeCount(count) {
        var n = parseInt(count, 10) || 0;
        document.querySelectorAll('[data-eventify-notif-badge]').forEach(function (btn) {
            var badge = btn.querySelector('.badge');
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
        });
        document.querySelectorAll('.eventify-notif-dropdown__header .badge').forEach(function (b) {
            if (n < 1) b.remove();
        });
    }

    function showEmptyList(listEl) {
        if (!listEl) return;
        var scroll = listEl.closest('.eventify-notif-scroll') || listEl.parentElement;
        if (!scroll) return;
        scroll.innerHTML =
            '<div class="eventify-notif-empty">' +
            '<div class="eventify-notif-empty__icon" aria-hidden="true"><i class="fas fa-bell-slash"></i></div>' +
            '<div class="eventify-notif-empty__title">All caught up</div>' +
            '<p class="eventify-notif-empty__text">No new notifications right now.</p>' +
            '</div>';
    }

    function markCardRead(card, data) {
        card.classList.remove('eventify-notif-card--unread');
        card.removeAttribute('data-unread');
        var badge = card.querySelector('.eventify-notif-card__badge');
        if (badge) badge.remove();
        if (data && typeof data.unread_count === 'number') {
            updateBadgeCount(data.unread_count);
        }
    }

    function hideOpenNotifDropdowns() {
        document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function (toggle) {
            var menu = toggle.nextElementSibling;
            if (!menu || !menu.classList.contains('eventify-notif-dropdown')) {
                return;
            }
            if (global.bootstrap) {
                var inst = bootstrap.Dropdown.getInstance(toggle);
                if (inst) {
                    inst.hide();
                }
            }
        });
    }

    function hideNotifListModals() {
        var adminModal = document.getElementById('adminNotificationsModal');
        if (adminModal && global.bootstrap) {
            var inst = bootstrap.Modal.getInstance(adminModal);
            if (inst) {
                inst.hide();
            }
        }
    }

    function cardText(card, selector, attr) {
        if (attr && card.getAttribute(attr)) {
            return String(card.getAttribute(attr) || '').trim();
        }
        var el = card.querySelector(selector);
        return el ? String(el.textContent || '').replace(/\s+/g, ' ').trim() : '';
    }

    function resolveNotifAction(notifType, eventId) {
        var role = currentRole();
        var eid = parseInt(eventId, 10);
        if (notifType === 'ticket_payment_pending' && eid > 0) {
            return {
                label: 'Review payments',
                mode: 'href',
                href: baseUrl() + '/manage_event_tickets.php?event_id=' + eid + '#pendingPayments'
            };
        }
        if ((notifType === 'event_pending_review' || notifType === 'event_update_pending_review') && (role === 'admin' || role === 'super_admin')) {
            return { label: 'Review pending', mode: 'pending' };
        }
        if (notifType === 'account_pending_approval' && role === 'admin') {
            return { label: 'View pending accounts', mode: 'accounts' };
        }
        if (notifType === 'staff_message') {
            return {
                label: 'Open messages',
                mode: 'href',
                href: global.__eventifyMessengerHref || (baseUrl() + '/backend/messaging/staff_messenger.php')
            };
        }
        if (notifType === 'photo_pending_approval') {
            return {
                label: 'Review photos',
                mode: 'href',
                href: baseUrl() + '/backend/auth/dashboard_multimedia.php?panel=photo_approvals'
            };
        }
        if (eid > 0) {
            if (role === 'student') {
                return {
                    label: 'View event',
                    mode: 'href',
                    href: baseUrl() + '/event_activities.php?id=' + eid
                };
            }
            if (role === 'multimedia') {
                return {
                    label: 'View event',
                    mode: 'href',
                    href: baseUrl() + '/backend/auth/dashboard_multimedia.php?panel=events&event_id=' + eid
                };
            }
            return { label: 'View event', mode: 'event' };
        }
        return null;
    }

    function afterNotifModalsClosed(callback) {
        var detailModal = document.getElementById('eventifyNotificationDetailModal');
        var listModal = document.getElementById('adminNotificationsModal');
        var waiting = 0;
        var done = false;

        function finish() {
            if (done) {
                return;
            }
            done = true;
            callback();
        }

        [detailModal, listModal].forEach(function (el) {
            if (!el || !global.bootstrap) {
                return;
            }
            if (!el.classList.contains('show')) {
                return;
            }
            waiting++;
            el.addEventListener('hidden.bs.modal', function () {
                waiting--;
                if (waiting <= 0) {
                    finish();
                }
            }, { once: true });
            var inst = bootstrap.Modal.getInstance(el);
            if (inst) {
                inst.hide();
            }
        });

        if (waiting === 0) {
            window.setTimeout(finish, 50);
        }
    }

    function bindDetailModalActions() {
        if (detailModalBound) return;
        var modalEl = document.getElementById('eventifyNotificationDetailModal');
        var actionBtn = document.getElementById('eventifyNotifDetailAction');
        if (!modalEl || !actionBtn) return;

        actionBtn.addEventListener('click', function () {
            var mode = actionBtn.getAttribute('data-action-mode') || '';
            var eventId = modalEl.getAttribute('data-event-id') || '';
            var notifType = modalEl.getAttribute('data-notif-type') || '';

            function runAction() {
                if (mode === 'href') {
                    var href = actionBtn.getAttribute('data-action-href') || '';
                    if (href) {
                        window.location.href = href;
                    }
                    return;
                }
                if (mode === 'pending') {
                    if (typeof global.adminOpenPendingFromNotification === 'function') {
                        global.adminOpenPendingFromNotification(eventId);
                        return;
                    }
                    global.dispatchEvent(new CustomEvent('eventify:notif-open-pending', {
                        detail: { eventId: eventId, notifType: notifType }
                    }));
                    return;
                }
                if (mode === 'accounts') {
                    global.dispatchEvent(new CustomEvent('eventify:notif-open-accounts', { detail: {} }));
                    return;
                }
                if (mode === 'event' && eventId) {
                    global.dispatchEvent(new CustomEvent('eventify:notif-view-event', {
                        detail: { eventId: eventId, notifType: notifType }
                    }));
                }
            }

            if (mode === 'pending' || mode === 'accounts' || mode === 'event') {
                afterNotifModalsClosed(runAction);
                return;
            }

            if (global.bootstrap) {
                var inst = bootstrap.Modal.getInstance(modalEl);
                if (inst) {
                    inst.hide();
                }
            }
            runAction();
        });
        detailModalBound = true;
    }

    function openNotificationDetailModal(card) {
        var modalEl = document.getElementById('eventifyNotificationDetailModal');
        if (!modalEl || !global.bootstrap) return;

        bindDetailModalActions();
        hideOpenNotifDropdowns();
        hideNotifListModals();

        var title = cardText(card, '.eventify-notif-card__title', 'data-notif-title');
        title = title.replace(/\s*New\s*$/i, '').trim();
        var message = card.getAttribute('data-notif-message');
        if (message === null || message === '') {
            message = cardText(card, '.eventify-notif-card__message', null);
        }
        var timeLabel = cardText(card, '.eventify-notif-card__time', 'data-notif-time');
        var typeLabel = card.getAttribute('data-notif-label') || cardText(card, '.eventify-notif-card__type-pill', null) || 'Notice';
        var iconClass = card.getAttribute('data-notif-icon') || 'fa-bell';
        var accent = card.getAttribute('data-notif-accent') || 'neutral';
        var notifType = card.getAttribute('data-notif-type') || '';
        var eventId = card.getAttribute('data-event-id') || '';

        var titleEl = document.getElementById('eventifyNotifDetailTitle');
        var msgEl = document.getElementById('eventifyNotifDetailMessage');
        var timeEl = document.getElementById('eventifyNotifDetailTime');
        var typeEl = document.getElementById('eventifyNotifDetailType');
        var iconWrap = document.getElementById('eventifyNotifDetailIcon');
        var actionBtn = document.getElementById('eventifyNotifDetailAction');

        if (titleEl) titleEl.textContent = title || 'Notification';
        if (msgEl) {
            msgEl.textContent = message || 'No additional details.';
            msgEl.classList.toggle('text-muted', !message);
        }
        if (timeEl) timeEl.textContent = timeLabel || '';
        if (typeEl) typeEl.textContent = typeLabel;
        if (iconWrap) {
            iconWrap.className = 'eventify-notif-detail__icon eventify-notif-detail__icon--' + accent;
            iconWrap.innerHTML = '<i class="fas ' + iconClass + '"></i>';
        }

        modalEl.setAttribute('data-event-id', eventId);
        modalEl.setAttribute('data-notif-type', notifType);

        if (actionBtn) {
            var action = resolveNotifAction(notifType, eventId);
            if (action) {
                actionBtn.style.display = '';
                actionBtn.textContent = action.label;
                actionBtn.setAttribute('data-action-mode', action.mode);
                if (action.href) {
                    actionBtn.setAttribute('data-action-href', action.href);
                } else {
                    actionBtn.removeAttribute('data-action-href');
                }
            } else {
                actionBtn.style.display = 'none';
                actionBtn.removeAttribute('data-action-mode');
                actionBtn.removeAttribute('data-action-href');
            }
        }

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    function initCards() {
        document.querySelectorAll('#eventifyNotifList').forEach(function (list) {
            if (list.dataset.eventifyNotifBound === '1') return;
            list.dataset.eventifyNotifBound = '1';

            function activate(card) {
                var notifId = card.getAttribute('data-notif-id');
                if (!notifId) return;

                var showDetail = function () {
                    openNotificationDetailModal(card);
                };

                if (card.getAttribute('data-unread') === '1') {
                    postNotification('mark_one', { id: notifId }).then(function (data) {
                        if (data && data.ok) {
                            markCardRead(card, data);
                        }
                        showDetail();
                        global.dispatchEvent(new CustomEvent('eventify:notif-read', {
                            detail: {
                                notifId: notifId,
                                eventId: data && data.event_id ? data.event_id : (card.getAttribute('data-event-id') || null),
                                notifType: card.getAttribute('data-notif-type') || '',
                                unreadCount: data ? data.unread_count : undefined,
                                fromDetail: true
                            }
                        }));
                    }).catch(function () {
                        showDetail();
                    });
                } else {
                    showDetail();
                }
            }

            list.addEventListener('click', function (e) {
                var card = e.target.closest('.js-eventify-notif-card');
                if (!card || !list.contains(card)) return;
                e.preventDefault();
                activate(card);
            });

            list.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                var card = e.target.closest('.js-eventify-notif-card');
                if (!card || !list.contains(card)) return;
                e.preventDefault();
                activate(card);
            });
        });
    }

    function notifyActionFailed(message) {
        window.alert(message || 'Could not update notifications. Refresh the page and try again.');
    }

    function initMarkAll() {
        document.querySelectorAll('.js-eventify-mark-all-notifs').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                postNotification('mark_all').then(function (data) {
                    if (!data || !data.ok) {
                        notifyActionFailed(data && data.message ? data.message : '');
                        return;
                    }
                    var list = document.getElementById('eventifyNotifList');
                    showEmptyList(list);
                    updateBadgeCount(data.unread_count);
                    hideOpenNotifDropdowns();
                    document.querySelectorAll('.eventify-notif-dropdown__footer').forEach(function (f) {
                        if ((data.unread_count || 0) < 1) {
                            f.style.display = 'none';
                        }
                    });
                }).catch(function () {
                    notifyActionFailed('');
                });
            });
        });
    }

    function findOpenNotifListModal() {
        return document.querySelector('.eventify-notif-modal.show:not(.eventify-notif-clear-modal):not(.eventify-notif-detail-modal)');
    }

    function boostClearModalStack(modalEl) {
        if (!modalEl) return;
        modalEl.style.zIndex = '2000';
        var backs = document.querySelectorAll('.modal-backdrop');
        backs.forEach(function (b, i) {
            if (i === backs.length - 1) {
                b.style.zIndex = '1990';
            }
        });
        var dialog = modalEl.querySelector('.modal-dialog');
        if (dialog) {
            dialog.style.position = 'relative';
            dialog.style.zIndex = '1';
        }
    }

    function resetClearModalStack(modalEl) {
        if (!modalEl) return;
        modalEl.style.zIndex = '';
        var dialog = modalEl.querySelector('.modal-dialog');
        if (dialog) {
            dialog.style.position = '';
            dialog.style.zIndex = '';
        }
        document.querySelectorAll('.modal-backdrop').forEach(function (b) {
            b.style.zIndex = '';
        });
    }

    var pendingParentNotifModal = null;

    function initClearModalStacking() {
        document.querySelectorAll('.js-eventify-open-clear-notifs[data-bs-target]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                hideOpenNotifDropdowns();
                var targetSel = btn.getAttribute('data-bs-target');
                if (!targetSel || !global.bootstrap) {
                    return;
                }
                var modalEl = document.querySelector(targetSel);
                if (!modalEl) {
                    return;
                }

                var parent = findOpenNotifListModal();
                var showClear = function () {
                    modalEl.removeAttribute('data-eventify-clear-done');
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                };

                if (parent) {
                    pendingParentNotifModal = parent;
                    var parentInst = bootstrap.Modal.getInstance(parent) || bootstrap.Modal.getOrCreateInstance(parent);
                    parent.addEventListener('hidden.bs.modal', function onParentHidden() {
                        parent.removeEventListener('hidden.bs.modal', onParentHidden);
                        showClear();
                    }, { once: true });
                    parentInst.hide();
                } else {
                    pendingParentNotifModal = null;
                    showClear();
                }
            });
        });

        document.querySelectorAll('.eventify-notif-clear-modal').forEach(function (modalEl) {
            modalEl.addEventListener('show.bs.modal', function () {
                hideOpenNotifDropdowns();
            });
            modalEl.addEventListener('shown.bs.modal', function () {
                boostClearModalStack(modalEl);
            });
            modalEl.addEventListener('hidden.bs.modal', function () {
                resetClearModalStack(modalEl);
                if (modalEl.getAttribute('data-eventify-clear-done') === '1') {
                    modalEl.removeAttribute('data-eventify-clear-done');
                    pendingParentNotifModal = null;
                    return;
                }
                if (pendingParentNotifModal && global.bootstrap) {
                    var resume = pendingParentNotifModal;
                    pendingParentNotifModal = null;
                    if (document.body.contains(resume)) {
                        bootstrap.Modal.getOrCreateInstance(resume).show();
                    }
                }
            });
        });
    }

    function initClearForms() {
        document.querySelectorAll('.js-eventify-clear-notifs-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var csrfInput = form.querySelector('[name="csrf_token"]');
                var csrfVal = csrfInput ? csrfInput.value : '';
                postNotification('clear_all', null, csrfVal).then(function (data) {
                    if (!data || !data.ok) {
                        notifyActionFailed(data && data.message ? data.message : '');
                        return;
                    }
                    var list = document.getElementById('eventifyNotifList');
                    showEmptyList(list);
                    updateBadgeCount(0);
                    var modal = form.closest('.modal');
                    if (modal) {
                        modal.setAttribute('data-eventify-clear-done', '1');
                    }
                    if (modal && global.bootstrap) {
                        var inst = bootstrap.Modal.getInstance(modal);
                        if (inst) inst.hide();
                    }
                    document.querySelectorAll('.eventify-notif-dropdown__footer').forEach(function (f) {
                        f.style.display = 'none';
                    });
                }).catch(function () {
                    notifyActionFailed('');
                });
            });
        });
    }

    function boot() {
        initCards();
        initMarkAll();
        initClearModalStacking();
        initClearForms();
        bindDetailModalActions();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    global.eventifyNotifications = {
        postNotification: postNotification,
        updateBadgeCount: updateBadgeCount,
        openNotificationDetailModal: openNotificationDetailModal
    };
})(typeof window !== 'undefined' ? window : this);
