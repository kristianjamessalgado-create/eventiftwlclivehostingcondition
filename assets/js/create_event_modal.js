(function () {
    'use strict';

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
        form.addEventListener('submit', function (e) {
            var anySpecific = Array.prototype.some.call(specifics, function (c) {
                return c.checked;
            });
            var allOn = allCb && allCb.checked;
            if (!allOn && !anySpecific) {
                e.preventDefault();
                alert('Please choose "All departments" or select at least one department.');
            }
        });
    }

    function initCreateEventLocationPicker() {
        var createModal = document.getElementById('createEventModal');
        if (!createModal || typeof window.initEventLocationPicker !== 'function') {
            return;
        }
        var cePickerInstance = null;
        createModal.addEventListener('shown.bs.modal', function () {
            if (!cePickerInstance && window.L) {
                cePickerInstance = window.initEventLocationPicker({
                    mapElId: 'ceLocationMap',
                    latInputId: 'ceEventLatitude',
                    lngInputId: 'ceEventLongitude',
                    addressInputId: 'ceLocation',
                    searchInputId: 'ceLocSearch',
                    searchBtnId: 'ceLocSearchBtn',
                    useLocationBtnId: 'ceLocUseGps',
                    resultsElId: 'ceLocResults',
                    geocodeBase: window.EVENTIFY_GEOCODE_URL || ''
                });
            }
            if (cePickerInstance && cePickerInstance.map) {
                setTimeout(function () {
                    cePickerInstance.map.invalidateSize(true);
                }, 150);
            }
        });
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
                alert('Please set the venue on the map, search and pick a result, or use your location.');
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
