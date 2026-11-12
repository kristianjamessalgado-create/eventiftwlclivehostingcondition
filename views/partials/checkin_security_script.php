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
<style>
  .efy-geo-note { display: none; border-radius: 10px; padding: 0.7rem 0.85rem; margin: 0 0 0.75rem; font-size: 0.9rem; line-height: 1.45; text-align: left; }
  .efy-geo-note.is-visible { display: block; }
  .efy-geo-note.is-info { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
  .efy-geo-note.is-ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
  .efy-geo-note.is-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
  .efy-geo-note__title { font-weight: 700; display: block; margin-bottom: 0.15rem; }
  .efy-geo-note__steps { margin: 0.35rem 0 0; padding-left: 1.1rem; }
  .efy-geo-note__retry { margin-top: 0.55rem; border: 1px solid currentColor; background: #fff; color: inherit; border-radius: 8px; padding: 0.35rem 0.75rem; font-weight: 600; font-size: 0.85rem; cursor: pointer; }
</style>
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
  var geoRequired = <?= json_encode($geo_required) ?>;
  var focusConfirmMobile = <?= json_encode($focus_confirm_mobile) ?>;
  var venueLat = <?= json_encode($checkin_venue_lat) ?>;
  var venueLng = <?= json_encode($checkin_venue_lng) ?>;
  var geoRadiusM = <?= json_encode($geo_radius_m) ?>;

  // Note box above the Confirm button explaining why it is (or is not) enabled.
  var statusEl = document.getElementById('checkinGeoStatus');
  if (!statusEl) {
    statusEl = document.createElement('div');
    statusEl.id = 'checkinGeoStatus';
    if (confirmBtn && confirmBtn.parentNode) {
      confirmBtn.parentNode.insertBefore(statusEl, confirmBtn);
    } else {
      form.insertBefore(statusEl, form.firstChild);
    }
  }
  statusEl.className = 'efy-geo-note';
  statusEl.setAttribute('role', 'status');
  statusEl.setAttribute('aria-live', 'polite');

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function formatDistance(m) {
    return m >= 1000 ? (m / 1000).toFixed(1) + ' km' : Math.round(m) + ' m';
  }

  /**
   * @param {string} msg  Main text (or title when opts.detail is given)
   * @param {string|null} kind  'error' | 'ok' | null (info)
   * @param {{detail?: string, steps?: string[], retry?: boolean}} [opts]
   */
  function setStatus(msg, kind, opts) {
    opts = opts || {};
    if (!msg) {
      statusEl.innerHTML = '';
      statusEl.className = 'efy-geo-note';
      return;
    }
    var html = '';
    if (opts.detail) {
      html += '<span class="efy-geo-note__title">' + escapeHtml(msg) + '</span>' + escapeHtml(opts.detail);
    } else {
      html += escapeHtml(msg);
    }
    if (opts.steps && opts.steps.length) {
      html += '<ul class="efy-geo-note__steps">' + opts.steps.map(function (s) {
        return '<li>' + escapeHtml(s) + '</li>';
      }).join('') + '</ul>';
    }
    if (opts.retry) {
      html += '<div><button type="button" class="efy-geo-note__retry" data-geo-retry="1">'
        + '<i class="fas fa-location-crosshairs me-1"></i>Check my location again</button></div>';
    }
    statusEl.innerHTML = html;
    statusEl.className = 'efy-geo-note is-visible ' + (kind === 'error' ? 'is-error' : kind === 'ok' ? 'is-ok' : 'is-info');
  }

  statusEl.addEventListener('click', function (e) {
    if (e.target && e.target.closest && e.target.closest('[data-geo-retry]')) {
      requestLocation();
    }
  });

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
      setStatus('QR check-in ready. Tap Confirm to record your attendance.', 'ok');
      setCanConfirm(true);
      return;
    }
    if (venueLat !== null && venueLng !== null && !withinVenue(lat, lng)) {
      var dist = haversineM(lat, lng, venueLat, venueLng);
      setStatus('You are too far from the venue', 'error', {
        detail: 'Your phone shows you are about ' + formatDistance(dist) + ' away from the event location. '
          + 'Attendance can only be confirmed within ' + formatDistance(geoRadiusM) + ' of the venue, '
          + 'so the Confirm button stays off until you are there.',
        steps: [
          'Go to the event venue, then tap "Check my location again".',
          'Already at the venue? Turn on Wi-Fi and step near a window or outside for a better GPS signal.'
        ],
        retry: true
      });
      setCanConfirm(false);
      return;
    }
    setStatus('Location verified — you are at the venue. Tap Confirm to record your attendance.', 'ok');
    setCanConfirm(true);
  }

  function requestLocation() {
    if (!geoRequired) {
      evaluateLocation(null, null);
      return;
    }
    if (!window.isSecureContext) {
      setStatus('Location cannot be checked on this link', 'error', {
        detail: 'Open the check-in page using https:// so your phone can share its location.'
      });
      setCanConfirm(false);
      return;
    }
    if (!navigator.geolocation) {
      setStatus('Your browser cannot share location', 'error', {
        detail: 'Open this QR link in Chrome or Safari, then try again.',
        retry: true
      });
      setCanConfirm(false);
      return;
    }
    setStatus('Checking your location… Attendance is only recorded at the venue, so please allow location access if your phone asks.', null);
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
      if (err && err.code === 1) {
        setStatus('Location access is blocked', 'error', {
          detail: 'Attendance can only be confirmed at the venue, so EVENTIFY needs your location. Turn it on, then reload this page:',
          steps: [
            'iPhone: Settings → Privacy & Security → Location Services → On, then Safari Websites (or your browser) → While Using the App.',
            'Android: tap the lock icon next to the address bar → Permissions → Location → Allow.'
          ],
          retry: true
        });
      } else if (err && err.code === 3) {
        setStatus('Your location took too long', 'error', {
          detail: 'Your phone could not get a GPS fix in time. Turn on Wi-Fi, move near a window or outside, then try again.',
          retry: true
        });
      } else {
        setStatus('Could not read your location', 'error', {
          detail: 'Make sure Location (GPS) is turned on in your phone settings, then try again.',
          retry: true
        });
      }
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
    var geoAgeMs = fTs && fTs.value ? Date.now() - Number(fTs.value) : Infinity;
    if (geoRequired && (!fLat || !fLat.value || !fLng || !fLng.value || geoAgeMs > 90000)) {
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
