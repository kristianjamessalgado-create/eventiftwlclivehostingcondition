/**
 * Activities hub — slide-out navigation drawer.
 */
(function () {
    var drawer = document.getElementById('eahNavDrawer');
    var openBtn = document.getElementById('eahNavOpen');
    var closeBtn = document.getElementById('eahNavClose');
    var backdrop = document.getElementById('eahNavBackdrop');
    var panel = document.getElementById('eahNavPanel');
    if (!drawer || !openBtn) {
        return;
    }

    var lastFocus = null;

    function setOpen(isOpen) {
        drawer.classList.toggle('is-open', isOpen);
        drawer.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        openBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        document.body.classList.toggle('eah-nav-open', isOpen);
        if (isOpen) {
            lastFocus = document.activeElement;
            if (closeBtn) {
                closeBtn.focus();
            }
        } else if (lastFocus && typeof lastFocus.focus === 'function') {
            lastFocus.focus();
            lastFocus = null;
        }
    }

    openBtn.addEventListener('click', function () {
        setOpen(true);
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            setOpen(false);
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', function () {
            setOpen(false);
        });
    }

    document.querySelectorAll('[data-eah-close-nav]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (typeof window.eahCloseNavDrawer === 'function') {
                window.eahCloseNavDrawer();
            }
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer.classList.contains('is-open')) {
            setOpen(false);
        }
    });

    window.eahCloseNavDrawer = function () {
        setOpen(false);
    };

    if (panel) {
        panel.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab' || !drawer.classList.contains('is-open')) {
                return;
            }
            var focusable = panel.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])'
            );
            if (focusable.length < 1) {
                return;
            }
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        });
    }
})();
