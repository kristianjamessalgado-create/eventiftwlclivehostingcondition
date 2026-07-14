/**
 * Main event RSVP on the activities hub (separate from per-activity RSVPs).
 */
(function () {
    'use strict';

    function baseUrl() {
        return (window.BASE_URL || '').replace(/\/$/, '');
    }

    function postMainRsvp(url, eventId) {
        var body = new FormData();
        body.append('ajax', '1');
        body.append('csrf_token', window.csrfToken || '');
        body.append('event_id', String(eventId));
        return fetch(url, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) {
            return r.json();
        });
    }

    function showMsg(message, icon) {
        if (typeof showEdsMessageModal === 'function') {
            showEdsMessageModal(message, { title: 'Event RSVP', icon: icon || 'fa-info-circle' });
        } else {
            window.alert(message);
        }
    }

    function confirmAction(opts) {
        if (typeof showEdsConfirmModal === 'function') {
            return showEdsConfirmModal(opts);
        }
        return Promise.resolve(window.confirm(opts.message || 'Continue?'));
    }

    function refreshBar() {
        window.location.reload();
    }

    function bindMainRsvp() {
        var registerBtn = document.querySelector('.js-eah-main-rsvp');
        var cancelBtn = document.querySelector('.js-eah-main-cancel-rsvp');

        if (registerBtn) {
            registerBtn.addEventListener('click', function () {
                var eventId = registerBtn.getAttribute('data-event-id') || window.__eahMainEventId;
                if (!eventId) return;
                confirmAction({
                    title: 'RSVP for this event',
                    message: 'Register for the main event? You can still RSVP for individual activities separately.',
                    confirmLabel: 'Yes, register',
                    confirmClass: 'btn-primary',
                    icon: 'fa-user-plus'
                }).then(function (ok) {
                    if (!ok) return;
                    registerBtn.disabled = true;
                    postMainRsvp(baseUrl() + '/backend/auth/register_event_rsvp.php', eventId)
                        .then(function (data) {
                            registerBtn.disabled = false;
                            if (data && data.ok) {
                                showMsg(data.message || 'Registered.', 'fa-check-circle');
                                refreshBar();
                                return;
                            }
                            showMsg((data && data.message) || 'Could not register.', 'fa-exclamation-circle');
                        })
                        .catch(function () {
                            registerBtn.disabled = false;
                            showMsg('Could not register. Please try again.', 'fa-exclamation-circle');
                        });
                });
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                var eventId = cancelBtn.getAttribute('data-event-id') || window.__eahMainEventId;
                if (!eventId) return;
                confirmAction({
                    title: 'Cancel event RSVP',
                    message: 'Cancel your registration for this event? Activity RSVPs will also be cancelled.',
                    confirmLabel: 'Yes, cancel',
                    confirmClass: 'btn-danger',
                    icon: 'fa-user-minus'
                }).then(function (ok) {
                    if (!ok) return;
                    cancelBtn.disabled = true;
                    postMainRsvp(baseUrl() + '/backend/auth/cancel_event_rsvp.php', eventId)
                        .then(function (data) {
                            cancelBtn.disabled = false;
                            if (data && data.ok) {
                                showMsg(data.message || 'RSVP cancelled.', 'fa-check-circle');
                                refreshBar();
                                return;
                            }
                            showMsg((data && data.message) || 'Could not cancel.', 'fa-exclamation-circle');
                        })
                        .catch(function () {
                            cancelBtn.disabled = false;
                            showMsg('Could not cancel. Please try again.', 'fa-exclamation-circle');
                        });
                });
            });
        }
    }

    document.addEventListener('DOMContentLoaded', bindMainRsvp);
})();
