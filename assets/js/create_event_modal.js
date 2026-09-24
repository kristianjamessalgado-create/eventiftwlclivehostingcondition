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

    function focusCreateField(el) {
        if (!el || typeof el.focus !== 'function') {
            return;
        }
        try {
            el.scrollIntoView({ block: 'center', behavior: 'smooth' });
        } catch (err) {
            /* ignore */
        }
        try {
            el.focus({ preventScroll: true });
        } catch (errFocus) {
            el.focus();
        }
    }

    function setCreateEventBusy(form, busy) {
        if (!form) {
            return;
        }
        form.classList.toggle('ce-form--publishing', !!busy);
        var btn = document.getElementById('cePublishSubmit');
        if (btn) {
            btn.disabled = !!busy;
            btn.setAttribute('aria-busy', busy ? 'true' : 'false');
            var idle = btn.querySelector('.ce-publish-idle');
            var wait = btn.querySelector('.ce-publish-busy');
            if (idle) {
                idle.hidden = !!busy;
            }
            if (wait) {
                wait.hidden = !busy;
            }
        }
        var cancel = document.getElementById('cePublishCancel');
        if (cancel) {
            cancel.disabled = !!busy;
        }
    }

    function validateCreateEventAudience(form) {
        var allCb = document.getElementById('ceDeptAll');
        var specifics = form.querySelectorAll('.ce-dept-specific');
        var anySpecific = Array.prototype.some.call(specifics, function (c) {
            return c.checked;
        });
        var allOn = allCb && allCb.checked;
        var hasSection = formHasSelectedSection(form);

        if (hasSection) {
            syncDeptForSections(form, allCb);
            allOn = allCb && allCb.checked;
        }

        if (!allOn && !anySpecific && !hasSection) {
            notify('Choose All departments, pick at least one college, or select a class section.', {
                title: 'Who can attend?',
                type: 'warning'
            });
            return false;
        }
        return true;
    }

    function validateCreateEventForm(form) {
        var title = document.getElementById('ceTitle');
        if (!title || !String(title.value || '').trim()) {
            notify('Please enter an event title.', {
                title: 'Event title required',
                type: 'warning'
            });
            focusCreateField(title);
            return false;
        }

        if (form.getAttribute('data-create-mode') === 'admin') {
            var orgSelect = document.getElementById('ceOrganizerId');
            if (orgSelect && !String(orgSelect.value || '').trim()) {
                notify('Please choose who will organize this event (you or another organizer).', {
                    title: 'Organizer required',
                    type: 'warning'
                });
                focusCreateField(orgSelect);
                return false;
            }
        }

        if (!validateCreateEventAudience(form)) {
            return false;
        }

        if (typeof window.eventifySyncScheduleBeforeSubmit === 'function') {
            window.eventifySyncScheduleBeforeSubmit('ce');
        }
        if (typeof window.eventifyValidateScheduleOnSubmit === 'function' && !window.eventifyValidateScheduleOnSubmit('ce')) {
            return false;
        }

        var loc = document.getElementById('ceLocation');
        var locSearch = document.getElementById('ceLocSearch');
        if (loc && !String(loc.value || '').trim() && locSearch && String(locSearch.value || '').trim()) {
            loc.value = String(locSearch.value || '').trim();
        }
        if (!loc || !String(loc.value || '').trim()) {
            notify('Please enter the venue name / address.', {
                title: 'Venue required',
                type: 'warning'
            });
            focusCreateField(loc);
            return false;
        }

        if (form.getAttribute('data-require-geo') === '1') {
            var lat = (document.getElementById('ceEventLatitude') || {}).value;
            var lng = (document.getElementById('ceEventLongitude') || {}).value;
            if (!lat || !lng || isNaN(parseFloat(lat)) || isNaN(parseFloat(lng))) {
                notify('Please set the venue on the map, search and pick a result, or use your location.', {
                    title: 'Venue required',
                    type: 'warning'
                });
                focusCreateField(document.getElementById('ceLocSearch') || loc);
                return false;
            }
        }

        return true;
    }

    function submitCreateEventForm(form) {
        setCreateEventBusy(form, true);
        window.setTimeout(function () {
            form.submit();
        }, 50);
    }

    function initCreateEventFormValidation() {
        var form = document.getElementById('createEventModalForm');
        if (!form) {
            return;
        }

        form.addEventListener('submit', function (e) {
            if (form.getAttribute('data-ce-submitting') === '1') {
                e.preventDefault();
                return;
            }

            e.preventDefault();
            if (!validateCreateEventForm(form)) {
                setCreateEventBusy(form, false);
                return;
            }

            function proceed() {
                form.setAttribute('data-ce-submitting', '1');
                submitCreateEventForm(form);
            }

            if (form.getAttribute('data-create-mode') !== 'admin') {
                proceed();
                return;
            }

            var ask = (typeof window.eventifyConfirm === 'function')
                ? window.eventifyConfirm('Are you sure you want to publish this event? It will go live on the student calendar right away.', {
                    title: 'Publish event',
                    type: 'warning',
                    confirmLabel: 'Yes, publish',
                    cancelLabel: 'No',
                    icon: 'fa-check-circle'
                })
                : Promise.resolve(window.confirm('Are you sure you want to publish this event?'));

            ask.then(function (ok) {
                if (!ok) {
                    setCreateEventBusy(form, false);
                    return;
                }
                proceed();
            });
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

    function applyPrefillDate() {
        var ymd = String(window.__cePrefillDate || '').slice(0, 10);
        if (!ymd) {
            return;
        }
        window.__cePrefillDate = '';
        if (typeof window.eventifySetScheduleDates === 'function') {
            window.eventifySetScheduleDates('ce', [ymd], 'single');
        }
        var dateEl = document.getElementById('ceDate');
        if (dateEl) {
            dateEl.value = ymd;
        }
        var singleMode = document.getElementById('ceModeSingle');
        if (singleMode) {
            singleMode.checked = true;
        }
        var schedule = document.querySelector('#createEventModal .event-schedule-block');
        if (schedule && typeof schedule.scrollIntoView === 'function') {
            schedule.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    function eventifyOpenCreateEventForDate(ymd) {
        window.__cePrefillDate = String(ymd || '').slice(0, 10);
        var createModal = document.getElementById('createEventModal');
        if (!createModal || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return;
        }
        bootstrap.Modal.getOrCreateInstance(createModal).show();
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
        var createModal = document.getElementById('createEventModal');
        if (createModal) {
            createModal.addEventListener('shown.bs.modal', function () {
                applyPrefillDate();
            });
        }
    });

    window.eventifyOpenCreateEventForDate = eventifyOpenCreateEventForDate;
})();
