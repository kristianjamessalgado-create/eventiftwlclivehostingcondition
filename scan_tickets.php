<?php
/**
 * Staff ticket scanner: admin / super admin, or the organizer assigned to the event,
 * scans a student's ticket QR with the camera to verify it and admit them.
 * Optional ?event_id= limits scanning to one event.
 */
require_once __DIR__ . '/config/session.php';
include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
include __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/backend/lib/nav_helpers.php';

$role = $_SESSION['role'] ?? '';
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId < 1 || !in_array($role, ['organizer', 'admin', 'super_admin'], true)) {
    header('Location: ' . BASE_URL . '/views/login.php');
    exit();
}

$dashNav = eventify_dashboard_nav_for_role($role);
$eventId = (int) ($_GET['event_id'] ?? 0);
$event = null;
if ($eventId > 0) {
    $stmt = $conn->prepare('SELECT id, title, organizer_id, status, date FROM events WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$event || ($role === 'organizer' && (int) $event['organizer_id'] !== $userId)) {
        header('Location: ' . $dashNav['url']);
        exit();
    }
}
$conn->close();

$shell_title = 'Scan tickets';
$shell_subtitle = $event
    ? 'Verifying tickets for: ' . $event['title']
    : ($role === 'organizer' ? 'Verifying tickets for your assigned events' : 'Verifying tickets for all events');
$shell_page_title = 'Scan tickets';
$shell_back_url = $event ? BASE_URL . '/manage_event_tickets.php?event_id=' . $eventId : $dashNav['url'];
$shell_back_label = $event ? 'Ticket sales' : $dashNav['label'];
$shell_body_class = 'eventify-standalone' . (in_array($role, ['admin', 'super_admin'], true) ? ' eventify-standalone--admin' : '');
include __DIR__ . '/views/partials/standalone_page_shell_open.php';
?>
<style>
    .sct-wrap { max-width: 560px; margin: 0 auto; }
    .sct-camera { position: relative; background: #0b1f16; border-radius: 12px; overflow: hidden; aspect-ratio: 1 / 1; }
    .sct-camera video { width: 100%; height: 100%; object-fit: cover; display: block; }
    .sct-camera__frame { position: absolute; inset: 15%; border: 3px solid rgba(234, 179, 8, 0.9); border-radius: 14px; box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.35); pointer-events: none; }
    .sct-camera__placeholder { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.75rem; color: #e2e8f0; text-align: center; padding: 1.5rem; }
    .sct-camera__placeholder i { font-size: 2.5rem; color: #eab308; }
    .sct-hint { font-size: 0.85rem; color: var(--efs-muted, #64748b); }
    .sct-result { border-radius: 12px; padding: 1rem 1.1rem; border: 2px solid; }
    .sct-result__head { display: flex; gap: 0.6rem; align-items: flex-start; font-weight: 700; font-size: 1.05rem; line-height: 1.35; }
    .sct-result__head i { font-size: 1.6rem; flex-shrink: 0; margin-top: 0.05rem; }
    .sct-result--valid, .sct-result--admitted { background: #ecfdf5; border-color: #10b981; color: #065f46; }
    .sct-result--used, .sct-result--wrong_event { background: #fffbeb; border-color: #f59e0b; color: #92400e; }
    .sct-result--not_found, .sct-result--cancelled, .sct-result--no_access, .sct-result--error { background: #fef2f2; border-color: #ef4444; color: #991b1b; }
    .sct-details { margin: 0.75rem 0 0; display: grid; grid-template-columns: auto 1fr; gap: 0.2rem 0.75rem; font-size: 0.95rem; color: #1e293b; }
    .sct-details dt { font-weight: 600; color: #475569; }
    .sct-details dd { margin: 0; word-break: break-word; }
    .sct-holder { font-size: 1.2rem; font-weight: 700; }
    .sct-log { list-style: none; padding: 0; margin: 0; font-size: 0.88rem; }
    .sct-log li { display: flex; justify-content: space-between; gap: 0.5rem; padding: 0.45rem 0; border-bottom: 1px solid #e2e8f0; }
    .sct-log li:last-child { border-bottom: 0; }
    .sct-badge { font-size: 0.72rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 999px; white-space: nowrap; }
    .sct-badge--ok { background: #d1fae5; color: #065f46; }
    .sct-badge--warn { background: #fef3c7; color: #92400e; }
    .sct-badge--bad { background: #fee2e2; color: #991b1b; }
</style>

<div class="sct-wrap">
    <div class="efs-card">
        <div class="efs-card__head"><i class="fas fa-camera me-1"></i> Camera scanner</div>
        <div class="efs-card__body">
            <div class="sct-camera mb-2">
                <video id="sctVideo" playsinline muted></video>
                <div class="sct-camera__frame" id="sctFrame" hidden></div>
                <div class="sct-camera__placeholder" id="sctPlaceholder">
                    <i class="fas fa-qrcode"></i>
                    <div>Point the camera at the student's ticket QR.</div>
                    <button type="button" class="btn btn-warning fw-semibold" id="sctStart"><i class="fas fa-camera me-1"></i>Start camera</button>
                </div>
            </div>
            <canvas id="sctCanvas" hidden></canvas>
            <div class="d-flex justify-content-between align-items-center gap-2">
                <span class="sct-hint" id="sctCamStatus">Camera is off.</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="sctStop" hidden><i class="fas fa-stop me-1"></i>Stop camera</button>
            </div>
        </div>
    </div>

    <div class="efs-card" id="sctResultCard" hidden>
        <div class="efs-card__head">Scan result</div>
        <div class="efs-card__body">
            <div class="sct-result" id="sctResult" role="status" aria-live="assertive">
                <div class="sct-result__head"><i class="fas" id="sctResultIcon"></i><span id="sctResultMsg"></span></div>
                <dl class="sct-details" id="sctDetails" hidden>
                    <dt>Holder</dt><dd><span class="sct-holder" id="sctHolder"></span></dd>
                    <dt>Student ID</dt><dd id="sctStudentId"></dd>
                    <dt>Ticket</dt><dd id="sctType"></dd>
                    <dt>Code</dt><dd id="sctCode"></dd>
                    <dt>Event</dt><dd id="sctEvent"></dd>
                    <dt id="sctUsedLabel" hidden>Used at</dt><dd id="sctUsedAt" hidden></dd>
                </dl>
            </div>
            <div class="d-flex gap-2 mt-3">
                <button type="button" class="btn btn-success flex-fill fw-semibold" id="sctAdmit" hidden><i class="fas fa-check me-1"></i>Admit student</button>
                <button type="button" class="btn btn-outline-secondary flex-fill" id="sctNext"><i class="fas fa-qrcode me-1"></i>Scan next</button>
            </div>
        </div>
    </div>

    <div class="efs-card">
        <div class="efs-card__head"><i class="fas fa-keyboard me-1"></i> Type ticket code instead</div>
        <div class="efs-card__body">
            <form id="sctManual" class="d-flex gap-2">
                <input type="text" id="sctManualCode" class="form-control text-uppercase" placeholder="e.g. TKT-565DC703" autocomplete="off" required>
                <button type="submit" class="btn btn-success">Check</button>
            </form>
            <p class="sct-hint mt-2 mb-0">Use this if the QR won't scan. The code is printed under the QR on the student's ticket pass.</p>
        </div>
    </div>

    <div class="efs-card">
        <div class="efs-card__head"><i class="fas fa-list me-1"></i> Scanned this session</div>
        <div class="efs-card__body">
            <p class="sct-hint mb-0" id="sctLogEmpty">No tickets scanned yet.</p>
            <ul class="sct-log" id="sctLog"></ul>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
(function () {
    var API = <?= json_encode(BASE_URL . '/backend/auth/staff_ticket_scan.php') ?>;
    var CSRF = <?= json_encode(csrf_token()) ?>;
    var EVENT_ID = <?= json_encode($eventId) ?>;

    var video = document.getElementById('sctVideo');
    var canvas = document.getElementById('sctCanvas');
    var ctx = canvas.getContext('2d', { willReadFrequently: true });
    var placeholder = document.getElementById('sctPlaceholder');
    var frame = document.getElementById('sctFrame');
    var camStatus = document.getElementById('sctCamStatus');
    var startBtn = document.getElementById('sctStart');
    var stopBtn = document.getElementById('sctStop');
    var resultCard = document.getElementById('sctResultCard');
    var resultBox = document.getElementById('sctResult');
    var admitBtn = document.getElementById('sctAdmit');
    var nextBtn = document.getElementById('sctNext');

    var stream = null;
    var paused = false;       // true while a result is on screen
    var busy = false;
    var lastCode = '';        // code currently shown (for Admit)
    var lastScanAt = 0;

    var ICONS = {
        valid: 'fa-circle-check', admitted: 'fa-circle-check',
        used: 'fa-triangle-exclamation', wrong_event: 'fa-triangle-exclamation',
        not_found: 'fa-circle-xmark', cancelled: 'fa-circle-xmark', no_access: 'fa-lock', error: 'fa-circle-exclamation'
    };

    function setText(id, v) { document.getElementById(id).textContent = v || '—'; }

    function feedback(good) {
        try { if (navigator.vibrate) navigator.vibrate(good ? 120 : [80, 60, 80, 60, 80]); } catch (e) {}
        try {
            var AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) return;
            var ac = feedback.ac || (feedback.ac = new AC());
            var o = ac.createOscillator(), g = ac.createGain();
            o.frequency.value = good ? 880 : 220;
            g.gain.value = 0.15;
            o.connect(g); g.connect(ac.destination);
            o.start(); o.stop(ac.currentTime + (good ? 0.15 : 0.4));
        } catch (e) {}
    }

    function showResult(data) {
        var state = data.state || 'error';
        resultCard.hidden = false;
        resultBox.className = 'sct-result sct-result--' + state;
        document.getElementById('sctResultIcon').className = 'fas ' + (ICONS[state] || ICONS.error);
        setText('sctResultMsg', data.message || 'Something went wrong.');
        var t = data.ticket;
        document.getElementById('sctDetails').hidden = !t;
        if (t) {
            setText('sctHolder', t.holder);
            setText('sctStudentId', t.student_id);
            setText('sctType', t.type);
            setText('sctCode', t.code);
            setText('sctEvent', t.event);
            var showUsed = !!t.used_at;
            document.getElementById('sctUsedLabel').hidden = !showUsed;
            document.getElementById('sctUsedAt').hidden = !showUsed;
            setText('sctUsedAt', t.used_at);
        }
        admitBtn.hidden = state !== 'valid';
        admitBtn.disabled = false;
        resultCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        feedback(state === 'valid' || state === 'admitted');
        if (state !== 'valid') addLog(data);
    }

    function addLog(data) {
        var t = data.ticket || {};
        var map = {
            admitted: ['Admitted', 'ok'], used: ['Already used', 'warn'], wrong_event: ['Wrong event', 'warn'],
            cancelled: ['Cancelled', 'bad'], not_found: ['Fake / unknown', 'bad'], no_access: ['Not your event', 'bad'], error: ['Error', 'bad']
        };
        var m = map[data.state] || ['Checked', 'warn'];
        var li = document.createElement('li');
        var left = document.createElement('span');
        left.textContent = (t.holder ? t.holder + ' · ' : '') + (t.code || 'Unknown code') + ' · '
            + new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        var badge = document.createElement('span');
        badge.className = 'sct-badge sct-badge--' + m[1];
        badge.textContent = m[0];
        li.appendChild(left); li.appendChild(badge);
        var log = document.getElementById('sctLog');
        log.insertBefore(li, log.firstChild);
        document.getElementById('sctLogEmpty').hidden = true;
    }

    function callApi(action, code) {
        busy = true;
        var body = new URLSearchParams();
        body.set('csrf_token', CSRF);
        body.set('action', action);
        body.set('code', code);
        if (EVENT_ID) body.set('event_id', String(EVENT_ID));
        return fetch(API, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .catch(function () { return { ok: false, state: 'error', message: 'No connection. Check the internet and scan again.' }; })
            .finally(function () { busy = false; });
    }

    function checkCode(code) {
        if (busy) return;
        paused = true;
        lastCode = code;
        camStatus.textContent = 'Checking ticket…';
        callApi('lookup', code).then(function (data) {
            showResult(data);
            camStatus.textContent = stream ? 'Paused — tap "Scan next" to continue.' : 'Camera is off.';
        });
    }

    admitBtn.addEventListener('click', function () {
        if (!lastCode || busy) return;
        admitBtn.disabled = true;
        callApi('admit', lastCode).then(showResult);
    });

    nextBtn.addEventListener('click', function () {
        resultCard.hidden = true;
        paused = false;
        lastCode = '';
        camStatus.textContent = stream ? 'Scanning… point at a ticket QR.' : 'Camera is off.';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    document.getElementById('sctManual').addEventListener('submit', function (e) {
        e.preventDefault();
        var input = document.getElementById('sctManualCode');
        var code = input.value.trim().toUpperCase();
        if (!code) return;
        input.value = '';
        checkCode(code);
    });

    function tick() {
        if (!stream) return;
        if (!paused && !busy && video.readyState === video.HAVE_ENOUGH_DATA && Date.now() - lastScanAt > 250) {
            lastScanAt = Date.now();
            var w = video.videoWidth, h = video.videoHeight;
            if (w && h) {
                var scale = Math.min(1, 640 / Math.max(w, h));
                canvas.width = Math.round(w * scale);
                canvas.height = Math.round(h * scale);
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                var img = ctx.getImageData(0, 0, canvas.width, canvas.height);
                var qr = window.jsQR ? jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' }) : null;
                if (qr && qr.data) {
                    checkCode(qr.data.trim());
                }
            }
        }
        requestAnimationFrame(tick);
    }

    function startCamera() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            camStatus.textContent = 'This browser cannot use the camera. Use Chrome or Safari, or type the ticket code below.';
            return;
        }
        if (!window.isSecureContext) {
            camStatus.textContent = 'The camera needs https://. Open the site with https, or type the ticket code below.';
            return;
        }
        camStatus.textContent = 'Starting camera…';
        navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false })
            .then(function (s) {
                stream = s;
                video.srcObject = s;
                return video.play();
            })
            .then(function () {
                placeholder.hidden = true;
                frame.hidden = false;
                stopBtn.hidden = false;
                camStatus.textContent = paused ? 'Paused — tap "Scan next" to continue.' : 'Scanning… point at a ticket QR.';
                requestAnimationFrame(tick);
            })
            .catch(function (err) {
                stopCamera();
                camStatus.textContent = (err && err.name === 'NotAllowedError')
                    ? 'Camera permission was blocked. Allow camera access for this site in your browser settings, then tap Start camera.'
                    : 'Could not start the camera. Close other apps using it, or type the ticket code below.';
            });
    }

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
        }
        stream = null;
        video.srcObject = null;
        placeholder.hidden = false;
        frame.hidden = true;
        stopBtn.hidden = true;
        camStatus.textContent = 'Camera is off.';
    }

    startBtn.addEventListener('click', startCamera);
    stopBtn.addEventListener('click', stopCamera);
    document.addEventListener('visibilitychange', function () {
        if (document.hidden && stream) stopCamera();
    });
})();
</script>

<?php include __DIR__ . '/views/partials/standalone_page_shell_close.php'; ?>
