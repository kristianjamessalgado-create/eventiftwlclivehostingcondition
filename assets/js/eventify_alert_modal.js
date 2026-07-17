/**
 * EVENTIFY alert modal — branded replacement for window.alert().
 * Matches efy-modal / Settings dialog styling and stacks above open modals.
 */
(function (global) {
    'use strict';

    var MODAL_ID = 'eventifyAlertModal';
    var instance = null;

    function countOpenModals() {
        return document.querySelectorAll('.modal.show').length;
    }

    function forceCleanupIfIdle() {
        if (countOpenModals() > 0) {
            return;
        }
        document.querySelectorAll('.modal-backdrop').forEach(function (el) {
            el.remove();
        });
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }

    function iconFor(type) {
        if (type === 'success') return 'fa-circle-check';
        if (type === 'error') return 'fa-circle-exclamation';
        if (type === 'warning') return 'fa-triangle-exclamation';
        return 'fa-bell';
    }

    function ensureModal() {
        var existing = document.getElementById(MODAL_ID);
        if (existing) {
            return existing;
        }
        var wrap = document.createElement('div');
        wrap.innerHTML =
            '<div class="modal fade eventify-alert-modal" id="' + MODAL_ID + '" tabindex="-1" aria-labelledby="' + MODAL_ID + 'Label" aria-hidden="true">' +
            '<div class="modal-dialog modal-dialog-centered">' +
            '<div class="modal-content efy-modal efy-modal--compact">' +
            '<div class="modal-header efy-modal__header">' +
            '<div>' +
            '<span class="efy-modal__eyebrow">EVENTIFY</span>' +
            '<h5 class="modal-title efy-modal__title efy-modal__title--sm" id="' + MODAL_ID + 'Label">' +
            '<i class="fas fa-bell" aria-hidden="true" id="' + MODAL_ID + 'Icon"></i>' +
            '<span id="' + MODAL_ID + 'Title">Notice</span>' +
            '</h5>' +
            '</div>' +
            '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>' +
            '</div>' +
            '<div class="modal-body efy-modal__body efy-modal__body--compact">' +
            '<p class="efy-confirm-message mb-0" id="' + MODAL_ID + 'Body"></p>' +
            '</div>' +
            '<div class="modal-footer efy-modal__footer">' +
            '<button type="button" class="btn efy-btn-primary" data-bs-dismiss="modal" id="' + MODAL_ID + 'Ok">' +
            '<i class="fas fa-check me-1" aria-hidden="true"></i>OK' +
            '</button>' +
            '</div>' +
            '</div></div></div>';
        document.body.appendChild(wrap.firstElementChild);
        return document.getElementById(MODAL_ID);
    }

    function prepareStacked(modalEl) {
        var openCount = countOpenModals();
        if (openCount > 0) {
            modalEl.style.zIndex = String(1060 + openCount * 10);
            modalEl.classList.add('eventify-alert-modal--stacked');
        } else {
            modalEl.style.removeProperty('z-index');
            modalEl.classList.remove('eventify-alert-modal--stacked');
        }
    }

    function createInstance(modalEl) {
        if (typeof global.bootstrap === 'undefined' || !global.bootstrap.Modal) {
            return null;
        }
        var existing = global.bootstrap.Modal.getInstance(modalEl);
        if (existing) {
            existing.dispose();
        }
        var stacked = countOpenModals() > 0;
        return new global.bootstrap.Modal(modalEl, {
            backdrop: !stacked,
            keyboard: true,
            focus: true
        });
    }

    /**
     * @param {string} message
     * @param {{ title?: string, type?: string, okLabel?: string, icon?: string }} [options]
     */
    function showAlert(message, options) {
        options = options || {};
        var msg = String(message || '').trim() || 'Something went wrong.';
        var type = options.type || 'info';
        var modalEl = ensureModal();
        var titleEl = document.getElementById(MODAL_ID + 'Title');
        var bodyEl = document.getElementById(MODAL_ID + 'Body');
        var iconEl = document.getElementById(MODAL_ID + 'Icon');
        var okBtn = document.getElementById(MODAL_ID + 'Ok');

        if (!modalEl || !bodyEl) {
            window.alert(msg);
            return;
        }

        if (titleEl) {
            titleEl.textContent = options.title || 'Notifications';
        }
        if (iconEl) {
            iconEl.className = 'fas ' + (options.icon || iconFor(type));
        }
        bodyEl.textContent = msg;
        if (okBtn) {
            var label = options.okLabel || 'OK';
            okBtn.innerHTML = '<i class="fas fa-check me-1" aria-hidden="true"></i>' + label;
        }

        if (typeof global.bootstrap !== 'undefined' && global.bootstrap.Modal) {
            prepareStacked(modalEl);
            instance = createInstance(modalEl);
            modalEl.addEventListener('hidden.bs.modal', function onHidden() {
                modalEl.style.removeProperty('z-index');
                modalEl.classList.remove('eventify-alert-modal--stacked');
                forceCleanupIfIdle();
            }, { once: true });
            if (instance) {
                instance.show();
            }
            return;
        }

        window.alert(msg);
    }

    global.eventifyAlert = showAlert;
    global.eventifyAlertModal = {
        show: showAlert,
        success: function (msg, opts) {
            opts = opts || {};
            opts.type = 'success';
            if (!opts.icon) opts.icon = 'fa-circle-check';
            return showAlert(msg, opts);
        },
        error: function (msg, opts) {
            opts = opts || {};
            opts.type = 'error';
            if (!opts.icon) opts.icon = 'fa-circle-exclamation';
            return showAlert(msg, opts);
        },
        warning: function (msg, opts) {
            opts = opts || {};
            opts.type = 'warning';
            if (!opts.icon) opts.icon = 'fa-triangle-exclamation';
            return showAlert(msg, opts);
        },
        info: function (msg, opts) {
            opts = opts || {};
            opts.type = 'info';
            return showAlert(msg, opts);
        }
    };
})(window);
