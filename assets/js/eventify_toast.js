/**
 * EVENTIFY toast notifications — replaces alert() for non-blocking feedback.
 */
(function (global) {
    var container = null;

    function ensureContainer() {
        if (container && document.body.contains(container)) {
            return container;
        }
        container = document.createElement('div');
        container.id = 'eventifyToastHost';
        container.className = 'eventify-toast-host';
        container.setAttribute('aria-live', 'polite');
        container.setAttribute('aria-atomic', 'false');
        document.body.appendChild(container);
        return container;
    }

    function iconFor(type) {
        if (type === 'success') return 'fa-circle-check';
        if (type === 'error') return 'fa-circle-exclamation';
        if (type === 'warning') return 'fa-triangle-exclamation';
        return 'fa-circle-info';
    }

    function showToast(message, type, durationMs) {
        type = type || 'info';
        durationMs = durationMs == null ? 4200 : durationMs;
        var host = ensureContainer();
        var el = document.createElement('div');
        el.className = 'eventify-toast eventify-toast--' + type;
        el.setAttribute('role', type === 'error' ? 'alert' : 'status');
        el.innerHTML =
            '<i class="fas ' + iconFor(type) + '" aria-hidden="true"></i>' +
            '<span class="eventify-toast__text"></span>' +
            '<button type="button" class="eventify-toast__close" aria-label="Dismiss"><i class="fas fa-times"></i></button>';
        el.querySelector('.eventify-toast__text').textContent = String(message || '');
        host.appendChild(el);
        requestAnimationFrame(function () {
            el.classList.add('is-visible');
        });
        function dismiss() {
            if (!el.parentNode) return;
            el.classList.remove('is-visible');
            setTimeout(function () {
                if (el.parentNode) el.parentNode.removeChild(el);
            }, 220);
        }
        el.querySelector('.eventify-toast__close').addEventListener('click', dismiss);
        if (durationMs > 0) {
            setTimeout(dismiss, durationMs);
        }
        return dismiss;
    }

    global.eventifyToast = {
        show: showToast,
        success: function (msg, ms) { return showToast(msg, 'success', ms); },
        error: function (msg, ms) { return showToast(msg, 'error', ms); },
        warning: function (msg, ms) { return showToast(msg, 'warning', ms); },
        info: function (msg, ms) { return showToast(msg, 'info', ms); }
    };
})(window);
