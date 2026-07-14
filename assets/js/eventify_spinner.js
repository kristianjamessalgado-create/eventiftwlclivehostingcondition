/**
 * EVENTIFY — show a brief loading overlay while filters or async work run.
 */
(function (global) {
    'use strict';

    var DEFAULT_MIN_MS = 320;

    function getOverlay(parent, message) {
        var overlay = parent.querySelector('.eventify-filter-loading');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'eventify-filter-loading';
            overlay.innerHTML =
                '<div class="eventify-spinner" role="status" aria-live="polite">' +
                '<span class="eventify-spinner__sr">Loading</span></div>' +
                '<p class="eventify-filter-loading__text"></p>';
            parent.insertBefore(overlay, parent.firstChild);
        }
        var textEl = overlay.querySelector('.eventify-filter-loading__text');
        if (textEl) {
            textEl.textContent = message || 'Updating filters…';
        }
        return overlay;
    }

    function show(parent, message) {
        if (!parent) {
            return null;
        }
        var overlay = getOverlay(parent, message);
        parent.classList.add('eventify-filter-loading-host', 'is-loading');
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        return overlay;
    }

    function hide(parent) {
        if (!parent) {
            return;
        }
        var overlay = parent.querySelector('.eventify-filter-loading');
        parent.classList.remove('is-loading');
        if (overlay) {
            overlay.hidden = true;
            overlay.setAttribute('aria-hidden', 'true');
        }
    }

    /**
     * Run workFn(finish). Spinner stays visible at least minMs.
     */
    function run(parent, workFn, opts) {
        opts = opts || {};
        var minMs = typeof opts.minMs === 'number' ? opts.minMs : DEFAULT_MIN_MS;
        var message = opts.message || 'Updating filters…';
        if (!parent || typeof workFn !== 'function') {
            return;
        }

        show(parent, message);
        var started = Date.now();

        workFn(function finish() {
            var wait = Math.max(0, minMs - (Date.now() - started));
            global.setTimeout(function () {
                hide(parent);
            }, wait);
        });
    }

    global.EventifySpinner = {
        show: show,
        hide: hide,
        run: run
    };
}(window));
