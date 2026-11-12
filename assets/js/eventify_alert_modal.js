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
            '<p class="efy-confirm-message mb-0" style="white-space:pre-line" id="' + MODAL_ID + 'Body"></p>' +
            '<div class="mt-3" id="' + MODAL_ID + 'InputWrap" hidden>' +
            '<label class="form-label small fw-semibold" for="' + MODAL_ID + 'Input" id="' + MODAL_ID + 'InputLabel">Details</label>' +
            '<textarea class="form-control" rows="3" id="' + MODAL_ID + 'Input"></textarea>' +
            '</div>' +
            '</div>' +
            '<div class="modal-footer efy-modal__footer">' +
            '<button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal" id="' + MODAL_ID + 'Cancel" hidden>Cancel</button>' +
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

    var pendingConfirm = null;

    function settleConfirm(result) {
        if (typeof pendingConfirm !== 'function') {
            return;
        }
        var done = pendingConfirm;
        pendingConfirm = null;
        done(result && typeof result === 'object' ? result : !!result);
    }

    function setInput(cfg) {
        var wrap = document.getElementById(MODAL_ID + 'InputWrap');
        var input = document.getElementById(MODAL_ID + 'Input');
        var label = document.getElementById(MODAL_ID + 'InputLabel');
        if (!wrap || !input) {
            return null;
        }
        if (!cfg) {
            wrap.hidden = true;
            input.value = '';
            return null;
        }
        wrap.hidden = false;
        input.value = cfg.value || '';
        input.placeholder = cfg.placeholder || '';
        if (label) {
            label.textContent = cfg.label || 'Details';
        }
        return input;
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
        var cancelBtn = document.getElementById(MODAL_ID + 'Cancel');

        if (!modalEl || !bodyEl) {
            window.alert(msg);
            return;
        }

        settleConfirm(false);

        if (titleEl) {
            titleEl.textContent = options.title || 'Notifications';
        }
        if (iconEl) {
            iconEl.className = 'fas ' + (options.icon || iconFor(type));
        }
        bodyEl.textContent = msg;
        setInput(null);
        if (cancelBtn) {
            cancelBtn.hidden = true;
        }
        if (okBtn) {
            var label = options.okLabel || 'OK';
            okBtn.className = 'btn efy-btn-primary';
            okBtn.setAttribute('data-bs-dismiss', 'modal');
            okBtn.innerHTML = '<i class="fas fa-check me-1" aria-hidden="true"></i>' + label;
            okBtn.onclick = null;
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

    /**
     * @param {string} message
     * @param {{ title?: string, type?: string, confirmLabel?: string, cancelLabel?: string, icon?: string }} [options]
     * @returns {Promise<boolean>}
     */
    function showConfirm(message, options) {
        options = options || {};
        var msg = String(message || '').trim() || 'Are you sure?';
        var type = options.type || 'warning';

        if (typeof global.bootstrap === 'undefined' || !global.bootstrap.Modal) {
            return Promise.resolve(window.confirm(msg));
        }

        return new Promise(function (resolve) {
            var modalEl = ensureModal();
            var titleEl = document.getElementById(MODAL_ID + 'Title');
            var bodyEl = document.getElementById(MODAL_ID + 'Body');
            var iconEl = document.getElementById(MODAL_ID + 'Icon');
            var okBtn = document.getElementById(MODAL_ID + 'Ok');
            var cancelBtn = document.getElementById(MODAL_ID + 'Cancel');

            if (!modalEl || !bodyEl || !okBtn) {
                resolve(window.confirm(msg));
                return;
            }

            settleConfirm(false);
            pendingConfirm = resolve;

            if (titleEl) {
                titleEl.textContent = options.title || 'Please confirm';
            }
            if (iconEl) {
                iconEl.className = 'fas ' + (options.icon || iconFor(type));
            }
            bodyEl.textContent = msg;
            var inputEl = setInput(options.input || null);
            if (cancelBtn) {
                cancelBtn.hidden = false;
                cancelBtn.textContent = options.cancelLabel || 'Cancel';
            }
            okBtn.removeAttribute('data-bs-dismiss');
            okBtn.className = type === 'danger' ? 'btn btn-danger' : (type === 'warning' ? 'btn btn-warning' : 'btn efy-btn-primary');
            okBtn.innerHTML = '<i class="fas fa-check me-1" aria-hidden="true"></i>' + (options.confirmLabel || 'Yes');
            okBtn.onclick = function (e) {
                e.preventDefault();
                settleConfirm(inputEl ? { value: inputEl.value } : true);
                if (instance) {
                    instance.hide();
                }
            };

            prepareStacked(modalEl);
            instance = createInstance(modalEl);
            modalEl.addEventListener('hidden.bs.modal', function onHidden() {
                okBtn.onclick = null;
                if (okBtn) {
                    okBtn.setAttribute('data-bs-dismiss', 'modal');
                    okBtn.className = 'btn efy-btn-primary';
                }
                if (cancelBtn) {
                    cancelBtn.hidden = true;
                }
                modalEl.style.removeProperty('z-index');
                modalEl.classList.remove('eventify-alert-modal--stacked');
                forceCleanupIfIdle();
                settleConfirm(false);
            }, { once: true });
            if (instance) {
                instance.show();
            }
        });
    }

    /**
     * Branded replacement for window.prompt(). Resolves with the text, or null when cancelled.
     * @param {string} message
     * @param {{ title?: string, label?: string, placeholder?: string, value?: string, confirmLabel?: string, type?: string, icon?: string }} [options]
     * @returns {Promise<string|null>}
     */
    function showPrompt(message, options) {
        options = options || {};
        if (typeof global.bootstrap === 'undefined' || !global.bootstrap.Modal) {
            return Promise.resolve(window.prompt(message, options.value || ''));
        }
        var opts = {};
        Object.keys(options).forEach(function (k) { opts[k] = options[k]; });
        opts.input = { label: options.label, placeholder: options.placeholder, value: options.value };
        opts.type = options.type || 'info';
        opts.icon = options.icon || 'fa-pen-to-square';
        opts.confirmLabel = options.confirmLabel || 'Submit';
        return showConfirm(message, opts).then(function (res) {
            if (res && typeof res === 'object') {
                return String(res.value || '');
            }
            return null;
        });
    }

    /*
     * Declarative confirm for forms (replaces onsubmit="return confirm(...)"):
     * <form data-efy-confirm="Message?" data-efy-confirm-title="..." data-efy-confirm-label="Yes"
     *       data-efy-confirm-type="danger|warning|info">
     */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.matches || !form.matches('form[data-efy-confirm]')) {
            return;
        }
        if (form.getAttribute('data-efy-confirmed') === '1') {
            form.removeAttribute('data-efy-confirmed');
            return;
        }
        e.preventDefault();
        var submitter = e.submitter || null;
        showConfirm(form.getAttribute('data-efy-confirm'), {
            title: form.getAttribute('data-efy-confirm-title') || 'Please confirm',
            confirmLabel: form.getAttribute('data-efy-confirm-label') || 'Yes',
            type: form.getAttribute('data-efy-confirm-type') || 'warning'
        }).then(function (ok) {
            if (!ok) {
                return;
            }
            form.setAttribute('data-efy-confirmed', '1');
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
            } else {
                form.removeAttribute('data-efy-confirmed');
                form.submit();
            }
        });
    });

    global.eventifyAlert = showAlert;
    global.eventifyConfirm = showConfirm;
    global.eventifyPrompt = showPrompt;
    global.eventifyAlertModal = {
        show: showAlert,
        confirm: showConfirm,
        prompt: showPrompt,
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
