/**
 * Confirm before navigating to logout.php (unless Bootstrap modal already handles it).
 * Also clears this device's push subscription before logout (students) so the phone
 * stops receiving alerts for the logged-out account. Login can silently re-link later.
 */
(function () {
    'use strict';

    function logoutUrl() {
        var base = (window.BASE_URL || '').replace(/\/$/, '');
        return base + '/backend/auth/logout.php';
    }

    function pushApiUrl() {
        var base = (window.BASE_URL || '').replace(/\/$/, '');
        return base + '/backend/auth/push_subscription_api.php';
    }

    function clearDevicePushBeforeLogout() {
        if (window.eventifyPwa && typeof window.eventifyPwa.clearDevicePush === 'function') {
            return window.eventifyPwa.clearDevicePush();
        }
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            return Promise.resolve({ ok: true, skipped: true });
        }
        return navigator.serviceWorker.ready.then(function (reg) {
            return reg.pushManager.getSubscription();
        }).then(function (sub) {
            if (!sub || !sub.endpoint) {
                return { ok: true, skipped: true };
            }
            return fetch(pushApiUrl(), {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'unsubscribe_device',
                    csrf_token: window.csrfToken || '',
                    endpoint: sub.endpoint
                })
            }).then(function (r) {
                return r.json().catch(function () { return { ok: false }; });
            }).catch(function () {
                return { ok: false };
            });
        }).catch(function () {
            return { ok: true, skipped: true };
        });
    }

    function proceedLogout(targetHref) {
        var url = targetHref || logoutUrl();
        var settled = false;
        function go() {
            if (settled) {
                return;
            }
            settled = true;
            window.location.href = url;
        }
        var clearPromise = clearDevicePushBeforeLogout().catch(function () { return null; });
        var timeout = new Promise(function (resolve) {
            setTimeout(resolve, 2500);
        });
        Promise.race([clearPromise, timeout]).then(go).catch(go);
    }

    function ensureLogoutModal() {
        if (document.getElementById('logoutModal')) {
            return document.getElementById('logoutModal');
        }
        var url = logoutUrl();
        var wrap = document.createElement('div');
        wrap.innerHTML =
            '<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">' +
            '<div class="modal-dialog modal-dialog-centered">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title" id="logoutModalLabel">Confirm log out</h5>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
            '</div>' +
            '<div class="modal-body">Are you sure you want to log out?</div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>' +
            '<a href="' + url.replace(/"/g, '&quot;') + '" class="btn btn-danger js-logout-confirm">Yes, log out</a>' +
            '</div></div></div></div>';
        document.body.appendChild(wrap.firstElementChild);
        return document.getElementById('logoutModal');
    }

    function closeNavDrawerIfOpen() {
        if (typeof window.eahCloseNavDrawer === 'function') {
            window.eahCloseNavDrawer();
            return;
        }
        var drawer = document.getElementById('eahNavDrawer');
        if (drawer && drawer.classList.contains('is-open')) {
            drawer.classList.remove('is-open');
            drawer.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('eah-nav-open');
            var openBtn = document.getElementById('eahNavOpen');
            if (openBtn) {
                openBtn.setAttribute('aria-expanded', 'false');
            }
        }
    }

    function showLogoutModal(targetHref) {
        closeNavDrawerIfOpen();
        var modalEl = ensureLogoutModal();
        if (modalEl.parentElement && modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }
        var confirm = modalEl.querySelector('.js-logout-confirm');
        if (confirm && targetHref) {
            confirm.setAttribute('href', targetHref);
        }
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var inst = bootstrap.Modal.getOrCreateInstance(modalEl);
            inst.show();
            return;
        }
        if (window.confirm('Are you sure you want to log out?')) {
            proceedLogout(targetHref || logoutUrl());
        }
    }

    document.addEventListener('click', function (e) {
        var confirmLink = e.target.closest('a.js-logout-confirm');
        if (confirmLink) {
            e.preventDefault();
            proceedLogout(confirmLink.getAttribute('href') || logoutUrl());
            return;
        }

        var link = e.target.closest('a[href*="logout.php"]');
        if (!link) {
            return;
        }
        if (link.getAttribute('data-bs-toggle') === 'modal' || link.getAttribute('data-bs-target') === '#logoutModal') {
            return;
        }
        e.preventDefault();
        showLogoutModal(link.getAttribute('href') || logoutUrl());
    });

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-bs-target="#logoutModal"], [data-logout-confirm]');
        if (!trigger || trigger.getAttribute('href') === '#') {
            return;
        }
        if (trigger.matches('a[href*="logout.php"]') || trigger.classList.contains('js-logout-confirm')) {
            return;
        }
        e.preventDefault();
        showLogoutModal(logoutUrl());
    });
})();
