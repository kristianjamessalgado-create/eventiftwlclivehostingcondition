/**
 * Scan QR modal — student dashboard and activities hub pages.
 */
function initScanQRModal() {
    const modalEl = document.getElementById('scanQRModal');
    const videoEl = document.getElementById('scanQRVideo');
    const canvasEl = document.getElementById('scanQRCanvas');
    const placeholderEl = document.getElementById('scanQRPlaceholder');
    const statusEl = document.getElementById('scanQRStatus');
    if (!modalEl || !videoEl || !canvasEl) return;

    let stream = null;
    let scanAnimationId = null;
    let startTimeoutId = null;
    let hasCameraStarted = false;
    let readyPollId = null;

    function clearStartTimeout() {
        if (startTimeoutId != null) {
            clearTimeout(startTimeoutId);
            startTimeoutId = null;
        }
    }

    function clearReadyPoll() {
        if (readyPollId != null) {
            clearInterval(readyPollId);
            readyPollId = null;
        }
    }

    function stopCamera() {
        clearStartTimeout();
        clearReadyPoll();
        hasCameraStarted = false;
        if (stream) {
            stream.getTracks().forEach(function(t) { t.stop(); });
            stream = null;
        }
        if (scanAnimationId != null) {
            cancelAnimationFrame(scanAnimationId);
            scanAnimationId = null;
        }
        if (videoEl.srcObject) {
            videoEl.srcObject = null;
        }
    }

    function parseCheckinFromQrUrl(urlString) {
        try {
            var url = new URL(urlString);
            var tk = url.searchParams.get('tk');
            if (tk) {
                return { type: 'ticket', token: tk };
            }
            var st = url.searchParams.get('st');
            if (st) {
                return { type: 'activity', token: st };
            }
            var t = url.searchParams.get('t') || url.searchParams.get('token');
            if (t) {
                return { type: 'main', token: t };
            }
        } catch (e) {
            /* ignore */
        }
        return null;
    }

    var pendingCheckin = null;

    function getActivityScanContext() {
        return window.__eahActivityScanContext || null;
    }

    function getScannedActivityTitle(token) {
        var map = window.__eahActivityTokenMap || {};
        return map[token] || 'another activity';
    }

    function shouldWarnActivityMismatch(parsed) {
        if (!parsed || parsed.type !== 'activity' || !parsed.token) {
            return false;
        }
        var ctx = getActivityScanContext();
        if (!ctx || !ctx.token) {
            return false;
        }
        return parsed.token !== ctx.token;
    }

    function navigateToCheckin(parsed) {
        if (!parsed || !parsed.token) return false;
        stopCamera();
        var base = (window.BASE_URL || '').replace(/\/$/, '');
        if (parsed.type === 'ticket') {
            window.location.href = base + '/ticket_checkin.php?tk=' + encodeURIComponent(parsed.token);
        } else if (parsed.type === 'activity') {
            window.location.href = base + '/activity_checkin.php?st=' + encodeURIComponent(parsed.token);
        } else {
            window.location.href = base + '/checkin.php?t=' + encodeURIComponent(parsed.token);
        }
        return true;
    }

    function showActivityMismatchWarning(parsed) {
        var mismatchEl = document.getElementById('scanQrActivityMismatchModal');
        if (!mismatchEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return navigateToCheckin(parsed);
        }
        pendingCheckin = parsed;
        stopCamera();
        var ctx = getActivityScanContext();
        var currentTitleEl = document.getElementById('scanQrMismatchCurrentTitle');
        var scannedTitleEl = document.getElementById('scanQrMismatchScannedTitle');
        if (currentTitleEl) {
            currentTitleEl.textContent = (ctx && ctx.title) ? ctx.title : 'this activity';
        }
        if (scannedTitleEl) {
            scannedTitleEl.textContent = getScannedActivityTitle(parsed.token);
        }
        var scanModal = bootstrap.Modal.getInstance(modalEl);
        if (scanModal) {
            scanModal.hide();
        }
        bootstrap.Modal.getOrCreateInstance(mismatchEl).show();
        return true;
    }

    function showScanFallback() {
        var fallback = document.getElementById('scanQRFallback');
        if (fallback) fallback.style.display = 'block';
    }

    function decodeQrFromImageData(imageData) {
        if (typeof jsQR === 'undefined' || !imageData) return null;
        return jsQR(imageData.data, imageData.width, imageData.height);
    }

    function handleQrText(text) {
        if (!text) return false;
        var parsed = parseCheckinFromQrUrl(text);
        if (parsed && parsed.token) {
            if (shouldWarnActivityMismatch(parsed)) {
                return showActivityMismatchWarning(parsed);
            }
            return navigateToCheckin(parsed);
        }
        return false;
    }

    function tick() {
        if (!videoEl || !videoEl.srcObject || videoEl.readyState !== videoEl.HAVE_ENOUGH_DATA) {
            scanAnimationId = requestAnimationFrame(tick);
            return;
        }
        var w = videoEl.videoWidth;
        var h = videoEl.videoHeight;
        if (!w || !h) {
            scanAnimationId = requestAnimationFrame(tick);
            return;
        }
        canvasEl.width = w;
        canvasEl.height = h;
        var ctx = canvasEl.getContext('2d');
        ctx.drawImage(videoEl, 0, 0, w, h);
        var imageData = ctx.getImageData(0, 0, w, h);
        if (typeof jsQR !== 'undefined') {
            var code = jsQR(imageData.data, imageData.width, imageData.height);
            if (code && code.data && handleQrText(code.data)) {
                return;
            }
        }
        scanAnimationId = requestAnimationFrame(tick);
    }

    function getCameraStream(constraints) {
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            return navigator.mediaDevices.getUserMedia(constraints);
        }
        var legacy = navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia;
        if (legacy) {
            return new Promise(function(resolve, reject) {
                legacy.call(navigator, constraints, resolve, reject);
            });
        }
        return Promise.reject(new Error('Not supported'));
    }

    modalEl.addEventListener('shown.bs.modal', function() {
        placeholderEl.innerHTML = '<span><i class="fas fa-camera fa-2x mb-2 d-block"></i>Starting camera…</span>';
        placeholderEl.style.display = 'flex';
        videoEl.style.display = 'none';
        statusEl.textContent = 'Requesting camera permission...';
        clearStartTimeout();
        hasCameraStarted = false;
        startTimeoutId = setTimeout(function () {
            if (hasCameraStarted) return;
            statusEl.innerHTML = 'Camera is taking too long to start. Tap <strong>Close</strong>, allow camera permission, then try again. ' +
                'If your phone blocks this page camera, use your phone camera app to scan the QR.';
        }, 9000);
        var constraints = { video: { facingMode: 'environment', width: { ideal: 640 }, height: { ideal: 480 } } };
        getCameraStream(constraints).then(function(mediaStream) {
            stream = mediaStream;
            videoEl.srcObject = stream;
            videoEl.setAttribute('playsinline', true);
            videoEl.setAttribute('autoplay', 'autoplay');
            placeholderEl.style.display = 'none';
            videoEl.style.display = 'block';
            statusEl.textContent = 'Position the event QR code within the frame.';
            var onReady = function () {
                if (hasCameraStarted) return;
                hasCameraStarted = true;
                clearStartTimeout();
                clearReadyPoll();
                placeholderEl.style.display = 'none';
                videoEl.style.display = 'block';
                statusEl.textContent = 'Position the event QR code within the frame.';
                tick();
            };
            videoEl.onloadedmetadata = onReady;
            videoEl.onloadeddata = onReady;
            videoEl.oncanplay = onReady;
            videoEl.onplaying = onReady;
            clearReadyPoll();
            readyPollId = setInterval(function () {
                if (!videoEl) return;
                if (videoEl.readyState >= 2 && videoEl.videoWidth > 0 && videoEl.videoHeight > 0) {
                    onReady();
                }
            }, 220);
            videoEl.play().then(onReady).catch(function() {
                clearStartTimeout();
                clearReadyPoll();
                statusEl.textContent = 'Could not start video.';
                placeholderEl.style.display = 'none';
            });
        }).catch(function(err) {
            clearStartTimeout();
            clearReadyPoll();
            placeholderEl.innerHTML = '<span><i class="fas fa-video-slash fa-2x mb-2 d-block"></i>Camera not available here</span>';
            placeholderEl.style.display = 'flex';
            statusEl.innerHTML = 'Live camera needs <strong>HTTPS</strong> on phones (not <code>http://192.168…</code>). Use the options below instead.';
            showScanFallback();
        });
    });

    var fileInput = document.getElementById('scanQRFileInput');
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            var file = fileInput.files && fileInput.files[0];
            fileInput.value = '';
            if (!file) return;
            statusEl.textContent = 'Reading QR from photo…';
            var reader = new FileReader();
            reader.onload = function () {
                var img = new Image();
                img.onload = function () {
                    canvasEl.width = img.width;
                    canvasEl.height = img.height;
                    var ctx = canvasEl.getContext('2d');
                    ctx.drawImage(img, 0, 0);
                    var imageData = ctx.getImageData(0, 0, img.width, img.height);
                    var code = decodeQrFromImageData(imageData);
                    if (code && code.data && handleQrText(code.data)) {
                        return;
                    }
                    statusEl.textContent = 'No valid EVENTIFY check-in QR found in that image.';
                };
                img.onerror = function () {
                    statusEl.textContent = 'Could not load that image.';
                };
                img.src = reader.result;
            };
            reader.onerror = function () {
                statusEl.textContent = 'Could not read that file.';
            };
            reader.readAsDataURL(file);
        });
    }

    var linkInput = document.getElementById('scanQRLinkInput');
    var linkGo = document.getElementById('scanQRLinkGo');
    if (linkGo && linkInput) {
        linkGo.addEventListener('click', function () {
            var raw = (linkInput.value || '').trim();
            if (!raw) return;
            if (handleQrText(raw)) return;
            if (/^https?:\/\//i.test(raw)) {
                window.location.href = raw;
                return;
            }
            statusEl.textContent = 'Paste the full check-in URL from the QR (starts with http).';
        });
    }

    modalEl.addEventListener('hidden.bs.modal', function() {
        stopCamera();
        placeholderEl.style.display = 'flex';
        placeholderEl.innerHTML = '<span><i class="fas fa-camera fa-2x mb-2 d-block"></i>Starting camera…</span>';
        statusEl.textContent = 'Position the event QR code within the frame.';
        var fallback = document.getElementById('scanQRFallback');
        if (fallback) fallback.style.display = 'none';
        if (linkInput) linkInput.value = '';
    });

    var mismatchModalEl = document.getElementById('scanQrActivityMismatchModal');
    if (mismatchModalEl) {
        var mismatchContinueBtn = document.getElementById('scanQrMismatchContinue');
        if (mismatchContinueBtn) {
            mismatchContinueBtn.addEventListener('click', function () {
                var parsed = pendingCheckin;
                pendingCheckin = null;
                var mismatchModal = bootstrap.Modal.getInstance(mismatchModalEl);
                if (mismatchModal) {
                    mismatchModal.hide();
                }
                if (parsed) {
                    navigateToCheckin(parsed);
                }
            });
        }
        mismatchModalEl.addEventListener('hidden.bs.modal', function () {
            if (!pendingCheckin) {
                return;
            }
            pendingCheckin = null;
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    initScanQRModal();
});
