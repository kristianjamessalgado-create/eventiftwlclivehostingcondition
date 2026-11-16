/**
 * EVENTIFY attendance camera — "Take photo" for check-in proof.
 *
 * Opens the camera inside EVENTIFY, then stamps the captured photo with:
 *   • the time (HH : MM : SS boxes), date + weekday
 *   • the address from GPS (reverse lookup via backend/auth/geocode_proxy.php)
 *   • the event / activity name
 *   • "Verified by EVENTIFY" + the EVENTIFY logo
 * and uploads it to backend/auth/upload_attendance_proof.php (same as "Add photo").
 *
 * Button: <button data-att-proof-camera> inside form.att-proof__form.
 */
(function () {
    'use strict';

    var overlay = null;
    var els = {};
    var stream = null;
    var facing = 'environment';
    var activeForm = null;
    var place = { label: '', lat: null, lon: null, state: 'idle' };
    var clockTimer = null;
    var stampedBlob = null;
    var logoImg = null;
    var fallbackMode = false; // live camera unavailable: shutter opens the phone camera

    function baseUrlFrom(form) {
        var action = form ? (form.getAttribute('action') || '') : '';
        var i = action.indexOf('/backend/');
        if (i >= 0) {
            return action.slice(0, i);
        }
        return (window.BASE_URL || '').replace(/\/$/, '');
    }

    function loadLogo(base) {
        if (logoImg) {
            return;
        }
        logoImg = new Image();
        logoImg.src = base + '/assets/img/eventify_logo_stamp.png';
    }

    function pad2(n) {
        return n < 10 ? '0' + n : String(n);
    }

    /* ---------------- UI ---------------- */

    function build() {
        if (overlay) {
            return;
        }
        overlay = document.createElement('div');
        overlay.className = 'att-cam';
        overlay.hidden = true;
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Take attendance photo');
        overlay.innerHTML =
            '<div class="att-cam__top">' +
            '  <button type="button" class="att-cam__icon" data-cam-close aria-label="Close camera"><i class="fas fa-times"></i></button>' +
            '  <span class="att-cam__title">Attendance photo</span>' +
            '  <button type="button" class="att-cam__icon" data-cam-flip aria-label="Switch camera"><i class="fas fa-camera-rotate"></i></button>' +
            '</div>' +
            '<div class="att-cam__stage">' +
            '  <video class="att-cam__video" playsinline muted autoplay></video>' +
            '  <img class="att-cam__preview" alt="Stamped photo preview" hidden>' +
            '  <div class="att-cam__live-stamp" aria-hidden="true">' +
            '    <div class="att-cam__live-time"></div>' +
            '    <div class="att-cam__live-place"><i class="fas fa-location-dot"></i> <span>Getting location…</span></div>' +
            '  </div>' +
            '  <p class="att-cam__msg" hidden></p>' +
            '</div>' +
            '<div class="att-cam__bar">' +
            '  <div class="att-cam__bar-shoot">' +
            '    <button type="button" class="att-cam__shutter" data-cam-shoot aria-label="Take photo"><span></span></button>' +
            '  </div>' +
            '  <div class="att-cam__bar-review" hidden>' +
            '    <button type="button" class="att-cam__btn att-cam__btn--ghost" data-cam-retake><i class="fas fa-rotate-left"></i> Retake</button>' +
            '    <button type="button" class="att-cam__btn" data-cam-use><i class="fas fa-check"></i> Use photo</button>' +
            '  </div>' +
            '</div>' +
            '<input type="file" accept="image/*" capture="environment" class="att-cam__fallback" hidden>' +
            '<canvas class="att-cam__canvas" hidden></canvas>';
        document.body.appendChild(overlay);

        els.video = overlay.querySelector('.att-cam__video');
        els.preview = overlay.querySelector('.att-cam__preview');
        els.liveTime = overlay.querySelector('.att-cam__live-time');
        els.livePlace = overlay.querySelector('.att-cam__live-place span');
        els.msg = overlay.querySelector('.att-cam__msg');
        els.shoot = overlay.querySelector('.att-cam__bar-shoot');
        els.review = overlay.querySelector('.att-cam__bar-review');
        els.useBtn = overlay.querySelector('[data-cam-use]');
        els.fallback = overlay.querySelector('.att-cam__fallback');
        els.canvas = overlay.querySelector('.att-cam__canvas');

        overlay.querySelector('[data-cam-close]').addEventListener('click', close);
        overlay.querySelector('[data-cam-flip]').addEventListener('click', function () {
            facing = facing === 'environment' ? 'user' : 'environment';
            startCamera();
        });
        overlay.querySelector('[data-cam-shoot]').addEventListener('click', captureFromVideo);
        overlay.querySelector('[data-cam-retake]').addEventListener('click', retake);
        els.useBtn.addEventListener('click', upload);
        els.fallback.addEventListener('change', function () {
            var file = els.fallback.files && els.fallback.files[0];
            els.fallback.value = '';
            if (file) {
                captureFromFile(file);
            }
        });
        document.addEventListener('keydown', function (e) {
            if (!overlay.hidden && (e.key === 'Escape' || e.key === 'Esc')) {
                close();
            }
        });
    }

    function showMsg(text) {
        els.msg.textContent = text || '';
        els.msg.hidden = !text;
    }

    function setReviewMode(on) {
        els.shoot.hidden = on;
        els.review.hidden = !on;
        els.preview.hidden = !on;
        els.video.hidden = on;
        overlay.querySelector('.att-cam__live-stamp').hidden = on;
    }

    function tickClock() {
        var now = new Date();
        var h = now.getHours() % 12 || 12;
        els.liveTime.textContent = pad2(h) + ':' + pad2(now.getMinutes()) + ':' + pad2(now.getSeconds())
            + (now.getHours() < 12 ? ' AM' : ' PM');
    }

    /* ---------------- Camera ---------------- */

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
        }
        els.video.srcObject = null;
    }

    function startCamera() {
        stopCamera();
        showMsg('');
        fallbackMode = false;
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            useFallbackCamera('Live camera is not available here. Opening your phone camera instead…');
            return;
        }
        navigator.mediaDevices.getUserMedia({
            video: { facingMode: facing, width: { ideal: 1920 }, height: { ideal: 1080 } },
            audio: false
        }).then(function (s) {
            stream = s;
            els.video.srcObject = s;
            var p = els.video.play();
            if (p && p.catch) {
                p.catch(function () { /* autoplay blocked; user taps shutter */ });
            }
        }).catch(function () {
            useFallbackCamera('Camera permission was blocked. Allow camera access, or use your phone camera.');
        });
    }

    /** Phone's own camera via <input capture>; the photo is still stamped. */
    function useFallbackCamera(message) {
        showMsg(message);
        els.shoot.hidden = false;
        fallbackMode = true;
        try {
            els.fallback.click();
        } catch (e) { /* needs a user tap */ }
    }

    /* ---------------- Location ---------------- */

    function shortenAddress(label) {
        var parts = String(label || '').split(',').map(function (p) { return p.trim(); }).filter(Boolean);
        // Drop the postal code and country noise at the end, keep the useful first parts.
        parts = parts.filter(function (p) { return !/^\d{4,}$/.test(p); });
        return parts.slice(0, 5).join(', ');
    }

    function locate(base) {
        place = { label: '', lat: null, lon: null, state: 'loading' };
        els.livePlace.textContent = 'Getting location…';
        if (!navigator.geolocation) {
            place.state = 'none';
            els.livePlace.textContent = 'Location unavailable';
            return;
        }
        navigator.geolocation.getCurrentPosition(function (pos) {
            place.lat = pos.coords.latitude;
            place.lon = pos.coords.longitude;
            place.state = 'coords';
            els.livePlace.textContent = place.lat.toFixed(5) + ', ' + place.lon.toFixed(5);
            fetch(base + '/backend/auth/geocode_proxy.php?action=reverse&lat=' + encodeURIComponent(place.lat)
                + '&lon=' + encodeURIComponent(place.lon), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.ok && data.label) {
                        place.label = shortenAddress(data.label);
                        place.state = 'address';
                        els.livePlace.textContent = place.label;
                    }
                })
                .catch(function () { /* keep coordinates */ });
        }, function () {
            place.state = 'none';
            els.livePlace.textContent = 'Location off — turn on location for the stamp';
        }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 });
    }

    function placeText() {
        if (place.label) {
            return place.label;
        }
        if (place.lat !== null) {
            return 'Lat ' + place.lat.toFixed(5) + ', Lon ' + place.lon.toFixed(5);
        }
        return 'Location unavailable';
    }

    /* ---------------- Stamp ---------------- */

    function roundRect(ctx, x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    function wrapLines(ctx, text, maxWidth, maxLines) {
        var words = String(text).split(/\s+/);
        var lines = [];
        var line = '';
        words.forEach(function (w) {
            var test = line ? line + ' ' + w : w;
            if (ctx.measureText(test).width > maxWidth && line) {
                lines.push(line);
                line = w;
            } else {
                line = test;
            }
        });
        if (line) {
            lines.push(line);
        }
        if (lines.length > maxLines) {
            lines = lines.slice(0, maxLines);
            lines[maxLines - 1] = lines[maxLines - 1].replace(/\s*\S*$/, '') + '…';
        }
        return lines;
    }

    function drawPin(ctx, x, y, s) {
        ctx.beginPath();
        ctx.arc(x, y, s * 0.42, Math.PI, 0);
        ctx.lineTo(x, y + s * 0.75);
        ctx.closePath();
        ctx.fill();
        ctx.save();
        ctx.globalCompositeOperation = 'destination-out';
        ctx.beginPath();
        ctx.arc(x, y, s * 0.16, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    }

    function drawShieldCheck(ctx, x, y, s) {
        ctx.save();
        ctx.lineWidth = Math.max(2, s * 0.12);
        ctx.beginPath();
        ctx.moveTo(x, y - s * 0.5);
        ctx.lineTo(x + s * 0.42, y - s * 0.32);
        ctx.lineTo(x + s * 0.42, y + s * 0.05);
        ctx.quadraticCurveTo(x + s * 0.4, y + s * 0.4, x, y + s * 0.55);
        ctx.quadraticCurveTo(x - s * 0.4, y + s * 0.4, x - s * 0.42, y + s * 0.05);
        ctx.lineTo(x - s * 0.42, y - s * 0.32);
        ctx.closePath();
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(x - s * 0.18, y + s * 0.02);
        ctx.lineTo(x - s * 0.03, y + s * 0.17);
        ctx.lineTo(x + s * 0.2, y - s * 0.12);
        ctx.stroke();
        ctx.restore();
    }

    /** Draws the EVENTIFY stamp onto the canvas (bottom-left text, bottom-right logo). */
    function drawStamp(ctx, W, H, when, title) {
        var u = Math.min(W, H) / 1000; // scale unit
        var pad = 40 * u;
        var font = '"Inter", "Segoe UI", Arial, sans-serif';

        // Soft dark gradient at the bottom for readability.
        var g = ctx.createLinearGradient(0, H * 0.45, 0, H);
        g.addColorStop(0, 'rgba(0,0,0,0)');
        g.addColorStop(1, 'rgba(0,0,0,0.55)');
        ctx.fillStyle = g;
        ctx.fillRect(0, H * 0.45, W, H * 0.55);

        ctx.textBaseline = 'alphabetic';
        ctx.shadowColor = 'rgba(0,0,0,0.55)';
        ctx.shadowBlur = 6 * u;

        var lines = [];
        ctx.font = '600 ' + (30 * u) + 'px ' + font;
        var addressLines = wrapLines(ctx, placeText(), 560 * u, 3);

        // Measure block height from the bottom up.
        var boxH = 92 * u;
        var lineGap = 40 * u;
        ctx.font = '800 ' + (32 * u) + 'px ' + font;
        var titleLines = title ? wrapLines(ctx, title, 760 * u, 2) : [];
        var blockH = titleLines.length * 40 * u + (titleLines.length ? 6 * u : 0) + boxH + 22 * u + 58 * u + 18 * u + addressLines.length * lineGap + 14 * u + 42 * u;
        var y = H - pad - blockH;
        var x = pad;

        // Event / activity name (gold).
        if (titleLines.length) {
            ctx.fillStyle = '#e6c54a';
            ctx.font = '800 ' + (32 * u) + 'px ' + font;
            titleLines.forEach(function (t, i) {
                ctx.fillText(t, x, y + 32 * u + i * 40 * u);
            });
            y += titleLines.length * 40 * u + 6 * u;
        }

        // Time boxes HH : MM : SS + AM/PM.
        var h12 = when.getHours() % 12 || 12;
        var parts = [pad2(h12), pad2(when.getMinutes()), pad2(when.getSeconds())];
        var boxW = 92 * u;
        var bx = x;
        ctx.font = '800 ' + (54 * u) + 'px ' + font;
        parts.forEach(function (p, i) {
            ctx.shadowBlur = 0;
            ctx.fillStyle = 'rgba(30, 41, 70, 0.62)';
            roundRect(ctx, bx, y, boxW, boxH, 12 * u);
            ctx.fill();
            ctx.fillStyle = '#ffffff';
            ctx.textAlign = 'center';
            ctx.fillText(p, bx + boxW / 2, y + boxH * 0.7);
            ctx.textAlign = 'left';
            bx += boxW;
            if (i < 2) {
                ctx.shadowBlur = 6 * u;
                ctx.fillText(':', bx + 8 * u, y + boxH * 0.68);
                bx += 34 * u;
            }
        });
        ctx.shadowBlur = 6 * u;
        ctx.font = '800 ' + (30 * u) + 'px ' + font;
        ctx.fillText(when.getHours() < 12 ? 'AM' : 'PM', bx + 14 * u, y + boxH * 0.7);
        y += boxH + 22 * u;

        // Date + weekday, gold underline.
        var dateStr = pad2(when.getMonth() + 1) + '-' + pad2(when.getDate()) + '-' + when.getFullYear() + ' '
            + when.toLocaleDateString('en-US', { weekday: 'long' });
        ctx.fillStyle = '#ffffff';
        ctx.font = '800 ' + (46 * u) + 'px ' + font;
        ctx.fillText(dateStr, x, y + 44 * u);
        var dateW = ctx.measureText(dateStr).width;
        y += 58 * u;
        ctx.shadowBlur = 0;
        ctx.fillStyle = '#e6c54a';
        ctx.fillRect(x, y, Math.max(dateW, 300 * u), 6 * u);
        y += 18 * u;

        // Address with pin.
        ctx.shadowBlur = 6 * u;
        ctx.fillStyle = '#ffffff';
        drawPin(ctx, x + 16 * u, y + 16 * u, 34 * u);
        ctx.font = '600 ' + (30 * u) + 'px ' + font;
        addressLines.forEach(function (line, i) {
            ctx.fillText(line, x + 46 * u, y + 26 * u + i * lineGap);
        });
        y += addressLines.length * lineGap + 14 * u;

        // Verified by EVENTIFY.
        ctx.strokeStyle = '#ffffff';
        drawShieldCheck(ctx, x + 16 * u, y + 18 * u, 34 * u);
        ctx.font = '700 ' + (30 * u) + 'px ' + font;
        ctx.fillText('Verified by EVENTIFY', x + 46 * u, y + 30 * u);

        // Logo + wordmark, bottom-right.
        var logoS = 96 * u;
        var lx = W - pad - logoS;
        var ly = H - pad - logoS - 4 * u;
        ctx.shadowBlur = 8 * u;
        if (logoImg && logoImg.complete && logoImg.naturalWidth) {
            ctx.drawImage(logoImg, lx, ly, logoS, logoS);
        }
        ctx.textAlign = 'right';
        ctx.fillStyle = '#ffffff';
        ctx.font = '800 ' + (40 * u) + 'px ' + font;
        ctx.fillText('EVENTIFY', lx - 14 * u, ly + logoS * 0.52);
        ctx.font = '600 ' + (22 * u) + 'px ' + font;
        ctx.fillStyle = '#e6c54a';
        ctx.fillText('Actual Time', lx - 14 * u, ly + logoS * 0.86);
        ctx.textAlign = 'left';
        ctx.shadowBlur = 0;
        return lines;
    }

    function renderStamped(source, sw, sh) {
        var maxSide = 1600;
        var scale = Math.min(1, maxSide / Math.max(sw, sh));
        var W = Math.round(sw * scale);
        var H = Math.round(sh * scale);
        var canvas = els.canvas;
        canvas.width = W;
        canvas.height = H;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(source, 0, 0, W, H);
        var title = activeForm ? (activeForm.getAttribute('data-proof-title') || '') : '';
        drawStamp(ctx, W, H, new Date(), title);
        canvas.toBlob(function (blob) {
            if (!blob) {
                showMsg('Could not create the photo. Please try again.');
                return;
            }
            stampedBlob = blob;
            if (els.preview.src) {
                URL.revokeObjectURL(els.preview.src);
            }
            els.preview.src = URL.createObjectURL(blob);
            setReviewMode(true);
            showMsg('');
        }, 'image/jpeg', 0.86);
    }

    function captureFromVideo() {
        if (fallbackMode) {
            els.fallback.click();
            return;
        }
        var v = els.video;
        if (!v.videoWidth) {
            showMsg('Camera is still starting… try again in a second.');
            return;
        }
        renderStamped(v, v.videoWidth, v.videoHeight);
        stopCamera();
    }

    function captureFromFile(file) {
        var url = URL.createObjectURL(file);
        var img = new Image();
        img.onload = function () {
            renderStamped(img, img.naturalWidth, img.naturalHeight);
            URL.revokeObjectURL(url);
        };
        img.onerror = function () {
            URL.revokeObjectURL(url);
            showMsg('Could not read that photo. Please try again.');
        };
        img.src = url;
    }

    function retake() {
        stampedBlob = null;
        setReviewMode(false);
        startCamera();
    }

    /* ---------------- Upload ---------------- */

    function upload() {
        if (!stampedBlob || !activeForm) {
            return;
        }
        els.useBtn.disabled = true;
        els.useBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
        var fd = new FormData();
        activeForm.querySelectorAll('input[type="hidden"]').forEach(function (inp) {
            fd.append(inp.name, inp.value);
        });
        var stamp = new Date();
        fd.append('photo', stampedBlob, 'attendance_' + stamp.getFullYear() + pad2(stamp.getMonth() + 1) + pad2(stamp.getDate())
            + '_' + pad2(stamp.getHours()) + pad2(stamp.getMinutes()) + pad2(stamp.getSeconds()) + '.jpg');
        fetch(activeForm.getAttribute('action'), { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (res) {
                // The server redirects back with ?proof=ok|error&msg=…
                window.location.href = res.url || window.location.href;
            })
            .catch(function () {
                els.useBtn.disabled = false;
                els.useBtn.innerHTML = '<i class="fas fa-check"></i> Use photo';
                showMsg('Upload failed. Check your connection and try again.');
            });
    }

    /* ---------------- Open / close ---------------- */

    function open(form) {
        build();
        activeForm = form;
        var base = baseUrlFrom(form);
        loadLogo(base);
        stampedBlob = null;
        els.useBtn.disabled = false;
        els.useBtn.innerHTML = '<i class="fas fa-check"></i> Use photo';
        setReviewMode(false);
        overlay.hidden = false;
        document.documentElement.classList.add('att-cam-open');
        tickClock();
        clearInterval(clockTimer);
        clockTimer = setInterval(tickClock, 1000);
        locate(base);
        startCamera();
    }

    function close() {
        if (!overlay) {
            return;
        }
        stopCamera();
        clearInterval(clockTimer);
        overlay.hidden = true;
        document.documentElement.classList.remove('att-cam-open');
        activeForm = null;
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-att-proof-camera]');
        if (!btn) {
            return;
        }
        e.preventDefault();
        var form = btn.closest('form');
        if (form) {
            open(form);
        }
    });

    window.eventifyAttendanceCamera = { open: open, close: close, _drawStamp: function (ctx, W, H, when, title) { return drawStamp(ctx, W, H, when, title); } };
})();
