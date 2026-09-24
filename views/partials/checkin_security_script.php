<?php
/** @var bool $geo_required */
/** @var bool $focus_confirm_mobile */
/** @var float|null $checkin_venue_lat */
/** @var float|null $checkin_venue_lng */
/** @var float $geo_radius_m */
$geo_required = !empty($geo_required);
$focus_confirm_mobile = !empty($focus_confirm_mobile);
$checkin_venue_lat = isset($checkin_venue_lat) && is_numeric($checkin_venue_lat) ? (float) $checkin_venue_lat : null;
$checkin_venue_lng = isset($checkin_venue_lng) && is_numeric($checkin_venue_lng) ? (float) $checkin_venue_lng : null;
$geo_radius_m = isset($geo_radius_m) && is_numeric($geo_radius_m) ? (float) $geo_radius_m : 300.0;
?>
<script>
(function() {
  var form = document.getElementById('checkinForm');
  if (!form) return;
  var fLat = document.getElementById('geo_lat');
  var fLng = document.getElementById('geo_lng');
  var fAcc = document.getElementById('geo_accuracy');
  var fTs = document.getElementById('geo_ts');
  var fHash = document.getElementById('device_hash');
  var confirmBtn = document.getElementById('confirmBtn');
  var statusEl = document.getElementById('checkinGeoStatus');
  var geoRequired = <?= json_encode($geo_required) ?>;
  var focusConfirmMobile = <?= json_encode($focus_confirm_mobile) ?>;
  var venueLat = <?= json_encode($checkin_venue_lat) ?>;
  var venueLng = <?= json_encode($checkin_venue_lng) ?>;
  var geoRadiusM = <?= json_encode($geo_radius_m) ?>;

  function setStatus(msg, kind) {
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.classList.remove('is-error', 'is-ok');
    if (kind === 'error') statusEl.classList.add('is-error');
    if (kind === 'ok') statusEl.classList.add('is-ok');
  }

  function setCanConfirm(canConfirm) {
    if (!confirmBtn) return;
    confirmBtn.disabled = !canConfirm;
  }

  function haversineM(lat1, lon1, lat2, lon2) {
    var earth = 6371000;
    var dLat = (lat2 - lat1) * Math.PI / 180;
    var dLon = (lon2 - lon1) * Math.PI / 180;
    var a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
      + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180)
      * Math.sin(dLon / 2) * Math.sin(dLon / 2);
    return earth * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  }

  function withinVenue(lat, lng) {
    if (venueLat === null || venueLng === null) return true;
    return haversineM(lat, lng, venueLat, venueLng) <= geoRadiusM;
  }

  function randomDeviceId() {
    try {
      if (window.crypto && typeof window.crypto.randomUUID === 'function') {
        return window.crypto.randomUUID();
      }
    } catch (e) {}
    var bytes = new Uint8Array(16);
    try {
      window.crypto.getRandomValues(bytes);
    } catch (e2) {
      for (var i = 0; i < bytes.length; i++) {
        bytes[i] = Math.floor(Math.random() * 256);
      }
    }
    return Array.from(bytes).map(function (b) {
      return b.toString(16).padStart(2, '0');
    }).join('');
  }

  function readStoredDeviceId() {
    var key = 'eventify_device_id_v2';
    var stores = [];
    try { stores.push(window.localStorage); } catch (e) {}
    try { stores.push(window.sessionStorage); } catch (e2) {}
    var i;
    for (i = 0; i < stores.length; i++) {
      try {
        var existing = stores[i].getItem(key);
        if (existing && existing.length >= 16) {
          return existing;
        }
      } catch (e3) {}
    }
    var created = randomDeviceId();
    for (i = 0; i < stores.length; i++) {
      try {
        stores[i].setItem(key, created);
      } catch (e4) {}
    }
    return created;
  }

  async function buildDeviceHash() {
    // One random ID per browser on this phone — not phone model/UA.
    // Same-model phones were colliding and blocking students who never checked in.
    var fpRaw = 'v2|' + readStoredDeviceId();
    try {
      var enc = new TextEncoder();
      var data = enc.encode(fpRaw);
      var hashBuf = await crypto.subtle.digest('SHA-256', data);
      var hashArr = Array.from(new Uint8Array(hashBuf));
      return hashArr.map(function(b) { return b.toString(16).padStart(2, '0'); }).join('');
    } catch (e) {
      return btoa(unescape(encodeURIComponent(fpRaw))).slice(0, 96);
    }
  }

  function evaluateLocation(lat, lng) {
    if (!fHash || !fHash.value) {
      setCanConfirm(false);
      return;
    }
    if (!geoRequired) {
      setStatus('QR check-in ready. Tap Confirm check-in.', 'ok');
      setCanConfirm(true);
      return;
    }
    if (venueLat !== null && venueLng !== null && !withinVenue(lat, lng)) {
      var dist = Math.round(haversineM(lat, lng, venueLat, venueLng));
      setStatus('You are about ' + dist + 'm from the venue. Move within ' + Math.round(geoRadiusM) + 'm to check in.', 'error');
      setCanConfirm(false);
      return;
    }
    setStatus('Location verified. You can confirm check-in.', 'ok');
    setCanConfirm(true);
  }

  function requestLocation() {
    if (!geoRequired) {
      evaluateLocation(null, null);
      return;
    }
    if (!window.isSecureContext) {
      setStatus('Location requires HTTPS or localhost. Open the site via https:// or http://localhost/… on this device.', 'error');
      setCanConfirm(false);
      return;
    }
    if (!navigator.geolocation) {
      setStatus('This browser does not support location. Try Chrome or Safari on your phone.', 'error');
      setCanConfirm(false);
      return;
    }
    setStatus('Getting your location…', null);
    setCanConfirm(false);
    navigator.geolocation.getCurrentPosition(function(pos) {
      var c = pos.coords || {};
      var lat = Number(c.latitude);
      var lng = Number(c.longitude);
      if (fLat) fLat.value = String(lat || '');
      if (fLng) fLng.value = String(lng || '');
      if (fAcc) fAcc.value = String(c.accuracy || '');
      if (fTs) fTs.value = String(Date.now());
      evaluateLocation(lat, lng);
    }, function(err) {
      var msg = 'Could not read your location. Allow location access for this site and try again.';
      if (err && err.code === 1) {
        msg = 'Location permission denied. Allow location in your browser settings, then refresh this page.';
      } else if (err && err.code === 3) {
        msg = 'Location timed out. Move to an open area with better signal and try again.';
      }
      setStatus(msg, 'error');
      setCanConfirm(false);
    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
  }

  buildDeviceHash().then(function(h) {
    if (fHash) fHash.value = h || '';
    requestLocation();
  });

  if (focusConfirmMobile && confirmBtn && window.matchMedia && window.matchMedia('(max-width: 768px)').matches) {
    setTimeout(function() {
      try { confirmBtn.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {}
    }, 200);
  }

  form.addEventListener('submit', function(e) {
    if (!fHash || !fHash.value) {
      e.preventDefault();
      setStatus('Device verification failed. Refresh the page and try again.', 'error');
      return;
    }
    if (geoRequired && (!fLat || !fLat.value || !fLng || !fLng.value)) {
      e.preventDefault();
      requestLocation();
      return;
    }
    if (geoRequired && venueLat !== null && venueLng !== null) {
      var lat = Number(fLat.value);
      var lng = Number(fLng.value);
      if (!withinVenue(lat, lng)) {
        e.preventDefault();
        evaluateLocation(lat, lng);
      }
    }
  });
})();
</script>
