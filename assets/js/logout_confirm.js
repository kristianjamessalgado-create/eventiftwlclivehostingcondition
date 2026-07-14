/**
 * Confirm before navigating to logout.php (unless Bootstrap modal already handles it).
 */
(function () {
    'use strict';

    function logoutUrl() {
        var base = (window.BASE_URL || '').replace(/\/$/, '');
        return base + '/backend/auth/logout.php';
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
            window.location.href = targetHref || logoutUrl();
        }
    }

    document.addEventListener('click', function (e) {
        var link = e.target.closest('a[href*="logout.php"]');
        if (!link) {
            return;
        }
        if (link.classList.contains('js-logout-confirm') || link.closest('#logoutModal .modal-footer')) {
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
        if (trigger.matches('a[href*="logout.php"]')) {
            return;
        }
        e.preventDefault();
        showLogoutModal(logoutUrl());
    });
})();
