(function () {
    'use strict';

    function notify(message, options) {
        options = options || {};
        options.title = options.title || 'Create event';
        options.type = options.type || 'warning';
        if (typeof window.eventifyAlert === 'function') {
            window.eventifyAlert(message, options);
            return;
        }
        if (window.eventifyAlertModal && typeof window.eventifyAlertModal.show === 'function') {
            window.eventifyAlertModal.show(message, options);
            return;
        }
        window.alert(message);
    }

    function formHasSelectedSection(form) {
        var sectionCbs = form.querySelectorAll('input[name="section[]"]:checked');
        if (sectionCbs.length > 0) {
            return true;
        }
        var newSec = form.querySelector('input[name="new_section"]');
        return !!(newSec && String(newSec.value || '').trim());
    }

    function syncDeptForSections(form, allCb) {
        if (!allCb || !formHasSelectedSection(form)) {
            return;
        }
        if (allCb.checked) {
            allCb.checked = false;
        }
    }

    function initCreateEventDeptAudience() {
        var form = document.getElementById('createEventModalForm');
        if (!form) {
            return;
        }
        var allCb = document.getElementById('ceDeptAll');
        var specifics = form.querySelectorAll('.ce-dept-specific');
        if (allCb) {
            allCb.addEventListener('change', function () {
                if (allCb.checked) {
                    specifics.forEach(function (cb) {
                        cb.checked = false;
                    });
                }
            });
        }
        specifics.forEach(function (cb) {
            cb.addEventListener('change', function () {
                if (cb.checked && allCb) {
                    allCb.checked = false;
                }
            });
        });
        form.querySelectorAll('input[name="section[]"]').forEach(function (cb) {
            cb.addEventListener('change', function () {
                syncDeptForSections(form, allCb);
            });
        });
        var newSec = form.querySelector('input[name="new_section"]');
        if (newSec) {
            newSec.addEventListener('input', function () {
                syncDeptForSections(form, allCb);
            });
        }
        form.addEventListener('submit', function (e) {
            var anySpecific = Array.prototype.some.call(specifics, function (c) {
                return c.checked;
            });
            var allOn = allCb && allCb.checked;
            var hasSection = formHasSelectedSection(form);

            // Section-only: keep All departments unchecked in the UI.
            // Missing department[] posts as campus-wide in PHP, then section gate applies.
            if (hasSection) {
                syncDeptForSections(form, allCb);
                allOn = allCb && allCb.checked;
            }

            if (!allOn && !anySpecific && !hasSection) {
                e.preventDefault();
                notify('Choose All departments, pick at least one college, or select a class section.', {
                    title: 'Who can attend?',
                    type: 'warning'
                });
                return;
            }
            if (form.getAttribute('data-create-mode') === 'admin') {
                var orgSelect = document.getElementById('ceOrganizerId');
                if (orgSelect && !String(orgSelect.value || '').trim()) {
                    e.preventDefault();
                    notify('Please choose who will organize this event (you or another organizer).', {
                        title: 'Organizer required',
                        type: 'warning'
                    });
                    orgSelect.focus();
                }
            }
        });
    }

    function showMapLoadError(mapEl) {
        if (!mapEl) {
            return;
        }
        mapEl.classList.add('is-map-error');
        mapEl.innerHTML = '<div class="eventify-map-fallback">Map library did not load. Refresh the page, or type the venue address below and continue.</div>';
    }

    function initCreateEventLocationPicker() {
        var createModal = document.getElementById('createEventModal');
        if (!createModal || typeof window.initEventLocationPicker !== 'function') {
            return;
        }
        var cePickerInstance = null;
        var initAttempts = 0;

        function refreshPicker() {
            if (cePickerInstance && typeof cePickerInstance.refresh === 'function') {
                cePickerInstance.refresh();
            } else if (cePickerInstance && cePickerInstance.map) {
                try {
                    cePickerInstance.map.invalidateSize(true);
                } catch (e) { /* ignore */ }
            }
        }

        function ensurePicker() {
            var mapEl = document.getElementById('ceLocationMap');
            if (cePickerInstance) {
                refreshPicker();
                return;
            }
            if (!window.L) {
                initAttempts += 1;
                if (initAttempts < 25) {
                    setTimeout(ensurePicker, 200);
                    return;
                }
                showMapLoadError(mapEl);
                return;
            }
            cePickerInstance = window.initEventLocationPicker({
                mapElId: 'ceLocationMap',
                latInputId: 'ceEventLatitude',
                lngInputId: 'ceEventLongitude',
                addressInputId: 'ceLocation',
                searchInputId: 'ceLocSearch',
                searchBtnId: 'ceLocSearchBtn',
                useLocationBtnId: 'ceLocUseGps',
                resultsElId: 'ceLocResults',
                geocodeBase: window.EVENTIFY_GEOCODE_URL || ((window.BASE_URL || '') + '/backend/auth/geocode_proxy.php')
            });
            if (!cePickerInstance) {
                showMapLoadError(mapEl);
                return;
            }
            refreshPicker();
        }

        createModal.addEventListener('shown.bs.modal', function () {
            initAttempts = 0;
            ensurePicker();
        });

        createModal.addEventListener('hide.bs.modal', function () {
            // Keep instance for faster re-open; size will refresh on shown.
        });

        var body = createModal.querySelector('.modal-body, .ce-modal__body');
        if (body) {
            body.addEventListener('scroll', function () {
                refreshPicker();
            }, { passive: true });
        }
    }

    function initCreateEventFormValidation() {
        var ceForm = document.getElementById('createEventModalForm');
        if (!ceForm) {
            return;
        }
        ceForm.addEventListener('submit', function (e) {
            if (typeof window.eventifySyncScheduleBeforeSubmit === 'function') {
                window.eventifySyncScheduleBeforeSubmit('ce');
            }
            if (typeof window.eventifyValidateScheduleOnSubmit === 'function' && !window.eventifyValidateScheduleOnSubmit('ce')) {
                e.preventDefault();
                return false;
            }
            if (ceForm.getAttribute('data-require-geo') !== '1') {
                return;
            }
            var lat = (document.getElementById('ceEventLatitude') || {}).value;
            var lng = (document.getElementById('ceEventLongitude') || {}).value;
            if (!lat || !lng || isNaN(parseFloat(lat)) || isNaN(parseFloat(lng))) {
                e.preventDefault();
                notify('Please set the venue on the map, search and pick a result, or use your location.', {
                    title: 'Venue required',
                    type: 'warning'
                });
                return false;
            }
        });
    }

    function initOpenCreateFromMyEvents() {
        var openFromMyEvents = document.getElementById('openCreateEventFromMyEvents');
        var createModal = document.getElementById('createEventModal');
        if (!openFromMyEvents || !createModal || typeof bootstrap === 'undefined') {
            return;
        }
        openFromMyEvents.addEventListener('click', function () {
            bootstrap.Modal.getOrCreateInstance(createModal).show();
        });
    }

    function maybeOpenCreateFromQuery() {
        var params = new URLSearchParams(window.location.search);
        if (params.get('open_create') !== '1') {
            return;
        }
        var createModal = document.getElementById('createEventModal');
        if (createModal && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(createModal).show();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initCreateEventDeptAudience();
        initCreateEventLocationPicker();
        initCreateEventFormValidation();
        initOpenCreateFromMyEvents();
        maybeOpenCreateFromQuery();
    });
})();
