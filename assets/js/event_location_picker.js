/**
 * Leaflet + OSM map: pick GPS point, search (via backend Nominatim proxy), use device location.
 * Expects Leaflet CSS/JS loaded on the page.
 */
(function (window) {
    'use strict';

    function $(id) {
        return document.getElementById(id);
    }

    function initEventLocationPicker(options) {
        if (!options || !window.L) {
            return null;
        }

        var mapEl = $(options.mapElId);
        var latIn = $(options.latInputId);
        var lngIn = $(options.lngInputId);
        var addrIn = $(options.addressInputId);
        var searchIn = options.searchInputId ? $(options.searchInputId) : null;
        var searchBtn = options.searchBtnId ? $(options.searchBtnId) : null;
        var useLocBtn = options.useLocationBtnId ? $(options.useLocationBtnId) : null;
        var resultsEl = options.resultsElId ? $(options.resultsElId) : null;
        var base = (options.geocodeBase || '').replace(/\/$/, '');

        if (!mapEl || !latIn || !lngIn || !addrIn || !base) {
            return null;
        }

        var defaultLat = typeof options.defaultLat === 'number' ? options.defaultLat : 11.244;
        var defaultLng = typeof options.defaultLng === 'number' ? options.defaultLng : 125.004;

        var lat0 = parseFloat(latIn.value);
        var lng0 = parseFloat(lngIn.value);
        if (!isNaN(lat0) && !isNaN(lng0) && Math.abs(lat0) <= 90 && Math.abs(lng0) <= 180) {
            defaultLat = lat0;
            defaultLng = lng0;
        }

        var map = L.map(mapEl, {
            zoomControl: true,
            attributionControl: true
        }).setView([defaultLat, defaultLng], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);

        var marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);

        function setCoords(lat, lng, skipReverse) {
            lat = Math.round(lat * 1e7) / 1e7;
            lng = Math.round(lng * 1e7) / 1e7;
            latIn.value = String(lat);
            lngIn.value = String(lng);
            marker.setLatLng([lat, lng]);
            map.panTo([lat, lng], { animate: true });
            if (!skipReverse) {
                reverseLabel(lat, lng);
            }
        }

        function reverseLabel(lat, lng) {
            var url = base + '?action=reverse&lat=' + encodeURIComponent(String(lat)) + '&lon=' + encodeURIComponent(String(lng));
            fetch(url, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.ok && data.label && addrIn.value.trim() === '') {
                        addrIn.value = data.label.length > 255 ? data.label.slice(0, 252) + '...' : data.label;
                    }
                })
                .catch(function () {});
        }

        marker.on('dragend', function () {
            var ll = marker.getLatLng();
            setCoords(ll.lat, ll.lng, false);
        });

        map.on('click', function (e) {
            setCoords(e.latlng.lat, e.latlng.lng, false);
        });

        function runSearch() {
            if (!searchIn || !resultsEl) return;
            var q = searchIn.value.trim();
            resultsEl.innerHTML = '';
            resultsEl.style.display = 'none';
            if (q.length < 2) return;
            var url = base + '?action=search&q=' + encodeURIComponent(q);
            fetch(url, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.ok || !data.results || !data.results.length) {
                        resultsEl.innerHTML = '<div class="list-group-item small text-muted">No results</div>';
                        resultsEl.style.display = 'block';
                        return;
                    }
                    resultsEl.innerHTML = '';
                    data.results.forEach(function (item) {
                        var div = document.createElement('button');
                        div.type = 'button';
                        div.className = 'list-group-item list-group-item-action small text-start';
                        div.textContent = item.label || (item.lat + ', ' + item.lon);
                        div.addEventListener('click', function () {
                            addrIn.value = item.label.length > 255 ? item.label.slice(0, 252) + '...' : item.label;
                            setCoords(item.lat, item.lon, true);
                            resultsEl.style.display = 'none';
                            resultsEl.innerHTML = '';
                        });
                        resultsEl.appendChild(div);
                    });
                    resultsEl.style.display = 'block';
                })
                .catch(function () {
                    resultsEl.innerHTML = '<div class="list-group-item small text-danger">Search failed</div>';
                    resultsEl.style.display = 'block';
                });
        }

        if (searchBtn && searchIn) {
            searchBtn.addEventListener('click', runSearch);
            searchIn.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    runSearch();
                }
            });
        }

        if (useLocBtn) {
            useLocBtn.addEventListener('click', function () {
                if (!navigator.geolocation) {
                    alert('Geolocation is not supported in this browser.');
                    return;
                }
                useLocBtn.disabled = true;
                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        useLocBtn.disabled = false;
                        var c = pos.coords;
                        setCoords(c.latitude, c.longitude, false);
                        map.setView([c.latitude, c.longitude], 16);
                    },
                    function () {
                        useLocBtn.disabled = false;
                        alert('Could not get your location. Allow permission or pick a point on the map.');
                    },
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
                );
            });
        }

        setTimeout(function () {
            map.invalidateSize(true);
        }, 200);

        return { map: map, marker: marker, setCoords: setCoords };
    }

    window.initEventLocationPicker = initEventLocationPicker;
})(window);
