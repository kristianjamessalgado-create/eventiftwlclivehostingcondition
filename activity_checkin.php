<?php
session_start();
include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
include __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/backend/lib/event_day_sessions.php';
require_once __DIR__ . '/backend/lib/event_checkin_security.php';

$token = trim($_GET['st'] ?? '');
$already_done = false;
$error = '';
$checkin_error = '';
$session = null;
$geo_required = false;
$geo_radius_m = eventify_checkin_geo_radius_m();
$needs_rsvp_first = false;
$needs_session_rsvp_first = false;
$focus_confirm_mobile = false;
$justConfirmed = isset($_GET['checked_in']) && (string) $_GET['checked_in'] === '1';
$checkin_venue_lat = null;
$checkin_venue_lng = null;
$checkin_unavailable = null;

if ($token === '') {
    $error = 'Invalid or missing activity check-in link.';
} else {
    eventify_event_day_sessions_ensure_enhanced($conn);
    $session = eventify_load_session_by_checkin_token($conn, $token);
    if (!$session) {
        $error = 'This activity check-in link is invalid or has expired.';
    } elseif (($session['event_status'] ?? '') !== 'active') {
        $st = strtolower((string) ($session['event_status'] ?? ''));
        $error = in_array($st, ['closed', 'completed'], true)
            ? 'This event has ended. Activity check-in is no longer available.'
            : 'Check-in is only available for approved, active events.';
    } elseif (($session['status'] ?? '') === 'cancelled') {
        $error = 'This activity has been cancelled.';
    } elseif (eventify_session_is_completed($session)) {
        $error = 'This activity was ended early. Check-in is no longer available.';
    } else {
        if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'student') {
            $student_uid = (int) $_SESSION['user_id'];
            $sid = (int) ($session['id'] ?? 0);
            if ($sid > 0) {
                $chk = $conn->prepare('SELECT 1 FROM event_day_session_attendance WHERE session_id = ? AND user_id = ? LIMIT 1');
                if ($chk) {
                    $chk->bind_param('ii', $sid, $student_uid);
                    $chk->execute();
                    $chk->store_result();
                    $already_done = $chk->num_rows > 0;
                    $chk->close();
                }
            }
        }
        if (!$already_done && !eventify_session_allows_checkin($session)) {
            $checkin_unavailable = eventify_session_checkin_student_details($session);
            $error = $checkin_unavailable['reason'];
        } else {
            $geo_required = eventify_session_checkin_geo_required($session);
            $venue = eventify_session_checkin_venue_coords($session);
            $checkin_venue_lat = $venue['lat'];
            $checkin_venue_lng = $venue['lng'];
        }
    }
}

if (!$error && $session && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!isset($_SESSION['user_id'])) {
        $returnUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . BASE_URL . '/activity_checkin.php?st=' . urlencode($token);
        header('Location: ' . BASE_URL . '/views/login.php?redirect=' . urlencode($returnUrl));
        exit();
    }
    if (($_SESSION['role'] ?? '') !== 'student') {
        $error = 'Only students can confirm activity attendance.';
        $session = null;
    } elseif (!$already_done) {
        $uid = (int) $_SESSION['user_id'];
        $sid = (int) ($session['id'] ?? 0);
        $eventId = (int) ($session['event_id'] ?? 0);
        if ($eventId > 0 && !eventify_student_has_main_event_access($conn, $uid, $eventId)) {
            $needs_rsvp_first = true;
        }
        if (!$needs_rsvp_first && eventify_session_checkin_requires_rsvp($session)
            && !eventify_student_has_session_rsvp($conn, $uid, $sid)) {
            $needs_session_rsvp_first = true;
        }
        $focus_confirm_mobile = !$needs_rsvp_first && !$needs_session_rsvp_first;
    }
}

if (!$error && $session && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && isset($_SESSION['user_id']) && $_SESSION['role'] === 'student') {
    if (!csrf_validate()) {
        $checkin_error = 'Invalid request. Please try again.';
    } elseif ($already_done) {
        header('Location: ' . BASE_URL . '/activity_checkin.php?st=' . urlencode($token) . '&checked_in=1');
        exit();
    } elseif (!eventify_session_allows_checkin($session)) {
        $checkin_unavailable = eventify_session_checkin_student_details($session);
        $checkin_error = $checkin_unavailable['reason'];
    } else {
        $user_id = (int) $_SESSION['user_id'];
        $event_id = (int) ($session['event_id'] ?? 0);
        $result = eventify_process_activity_checkin($conn, $session, $user_id, $_POST);
        if ($result['ok']) {
            try {
                $orgId = (int) ($session['organizer_id'] ?? 0);
                if ($orgId > 0) {
                    $studentName = (string) ($_SESSION['name'] ?? 'A student');
                    $actTitle = (string) ($session['title'] ?? 'activity');
                    $msg = $studentName . ' checked in for "' . $actTitle . '".';
                    $n = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, 'activity_attendance', 'Activity check-in', ?, ?)");
                    if ($n) {
                        $n->bind_param('isi', $orgId, $msg, $event_id);
                        $n->execute();
                        $n->close();
                    }
                }
            } catch (Throwable $e) {
                /* ignore */
            }
            $conn->close();
            header('Location: ' . BASE_URL . '/activity_checkin.php?st=' . urlencode($token) . '&checked_in=1');
            exit();
        }
        $checkin_error = $result['error'] ?? 'Could not record check-in.';
    }
    if ($checkin_error !== '') {
        $geo_required = eventify_session_checkin_geo_required($session);
        $venue = eventify_session_checkin_venue_coords($session);
        $checkin_venue_lat = $venue['lat'];
        $checkin_venue_lng = $venue['lng'];
        $focus_confirm_mobile = true;
    }
}

$timeStr = $session ? eventify_format_session_time_range($session['start_time'] ?? null, $session['end_time'] ?? null) : '';
$hubUrl = ($session && !empty($session['event_id']))
    ? BASE_URL . '/event_activities.php?id=' . (int) $session['event_id']
    : BASE_URL . '/activities_hub.php';
$dashboardUrl = BASE_URL . '/backend/auth/dashboard_student.php';
$showSuccess = $justConfirmed || $already_done;
$showCheckinForm = $session && !$showSuccess && !$needs_rsvp_first && !$needs_session_rsvp_first;
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Check-in | EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f0f9f4; padding: 1rem; }
        .checkin-card { max-width: 420px; width: 100%; border-radius: 16px; box-shadow: 0 10px 30px rgba(6, 78, 59, 0.12); overflow: hidden; }
        .checkin-header { background: linear-gradient(120deg, #064e3b, #047857); color: #fff; padding: 1.25rem; text-align: center; }
        .checkin-body { padding: 1.5rem; background: #fff; }
        .checkin-nav { display: grid; gap: 0.55rem; margin-top: 1.25rem; }
        .checkin-nav .btn { width: 100%; padding: 0.65rem 1rem; font-weight: 600; border-radius: 12px; }
        .checkin-geo-status { min-height: 1.25rem; }
        .checkin-geo-status.is-error { color: #b91c1c; }
        .checkin-geo-status.is-ok { color: #047857; }
        .checkin-unavailable { text-align: center; }
        .checkin-unavailable-icon {
            width: 3rem; height: 3rem; margin: 0 auto 0.75rem; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            background: rgba(185, 28, 28, 0.1); color: #b91c1c; font-size: 1.35rem;
        }
        .checkin-unavailable h5 { color: #991b1b; font-weight: 800; margin-bottom: 0.75rem; }
        .checkin-reason-box {
            background: #fff5f5; border: 1px solid #fecaca; border-radius: 12px;
            padding: 0.9rem 1rem; color: #991b1b; font-weight: 600; line-height: 1.45; text-align: left;
        }
        .checkin-context-list { list-style: none; padding: 0; margin: 0.85rem 0 0; text-align: left; }
        .checkin-context-list li {
            display: flex; gap: 0.55rem; align-items: flex-start; color: #4b5563;
            font-size: 0.9rem; margin-bottom: 0.45rem;
        }
        .checkin-context-list i { color: #047857; margin-top: 0.15rem; }
    </style>
</head>
<body>
    <div class="checkin-card card border-0">
        <div class="checkin-header">
            <h1 class="h5 mb-0"><i class="fas fa-clipboard-check me-2"></i>Activity Check-in</h1>
        </div>
        <div class="checkin-body">
            <?php if ($error): ?>
                <?php
                if ($checkin_unavailable === null && $session) {
                    $checkin_unavailable = eventify_session_checkin_student_details($session);
                }
                ?>
                <div class="checkin-unavailable">
                    <div class="checkin-unavailable-icon" aria-hidden="true"><i class="fas fa-clock"></i></div>
                    <h5>Can't check in yet</h5>
                    <?php if ($session && !empty($session['title'])): ?>
                        <p class="mb-2"><strong><?= htmlspecialchars((string) $session['title']) ?></strong></p>
                    <?php endif; ?>
                    <div class="checkin-reason-box"><?= htmlspecialchars($error) ?></div>
                    <?php if ($checkin_unavailable): ?>
                        <ul class="checkin-context-list">
                            <?php if (($checkin_unavailable['activity_date_label'] ?? '') !== ''): ?>
                                <li><i class="fas fa-calendar-day"></i><span>Activity day: <strong><?= htmlspecialchars($checkin_unavailable['activity_date_label']) ?></strong></span></li>
                            <?php endif; ?>
                            <?php if (($checkin_unavailable['time_window'] ?? '') !== ''): ?>
                                <li><i class="fas fa-door-open"></i><span>Check-in window: <strong><?= htmlspecialchars($checkin_unavailable['time_window']) ?></strong></span></li>
                            <?php endif; ?>
                            <li><i class="fas fa-hourglass-half"></i><span>Right now: <strong><?= htmlspecialchars($checkin_unavailable['now_label']) ?> <?= htmlspecialchars($checkin_unavailable['timezone_short']) ?></strong> (<?= htmlspecialchars($checkin_unavailable['today_date_label']) ?>)</span></li>
                        </ul>
                    <?php endif; ?>
                </div>
                <div class="checkin-nav">
                    <a href="<?= htmlspecialchars($hubUrl) ?>" class="btn btn-success">Activities hub</a>
                    <a href="<?= htmlspecialchars($dashboardUrl) ?>" class="btn btn-outline-primary">Dashboard</a>
                </div>
            <?php elseif ($showSuccess): ?>
                <div class="text-center">
                    <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                    <h5><?= $justConfirmed ? 'Check-in confirmed!' : 'Already checked in' ?></h5>
                    <?php if ($session): ?>
                        <p class="text-muted mb-0"><?= htmlspecialchars($session['title']) ?></p>
                        <?php if (!empty($session['event_title'])): ?>
                            <p class="small text-muted"><?= htmlspecialchars($session['event_title']) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <p class="small text-muted mt-2 mb-0">Where would you like to go next?</p>
                    <div class="checkin-nav">
                        <a href="<?= htmlspecialchars($hubUrl) ?>" class="btn btn-success">Activities hub</a>
                        <a href="<?= htmlspecialchars($dashboardUrl) ?>" class="btn btn-outline-primary">Dashboard</a>
                    </div>
                </div>
            <?php elseif ($session): ?>
                <h5 class="mb-1"><?= htmlspecialchars($session['title']) ?></h5>
                <p class="text-muted small mb-2"><?= htmlspecialchars($session['event_title'] ?? '') ?></p>
                <?php if ($timeStr !== ''): ?>
                    <p class="small mb-1"><i class="fas fa-clock me-1"></i><?= htmlspecialchars($timeStr) ?></p>
                <?php endif; ?>
                <?php if (!empty($session['location'])): ?>
                    <p class="small mb-3"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($session['location']) ?></p>
                <?php endif; ?>
                <?php if ($needs_rsvp_first): ?>
                    <div class="alert alert-warning small mb-3">
                        Register for the main event on your dashboard first, then scan this activity QR again.
                    </div>
                    <div class="checkin-nav">
                        <a href="<?= htmlspecialchars($dashboardUrl) ?>" class="btn btn-success">Dashboard</a>
                        <a href="<?= htmlspecialchars($hubUrl) ?>" class="btn btn-outline-primary">Activities hub</a>
                    </div>
                <?php elseif ($needs_session_rsvp_first): ?>
                    <div class="alert alert-warning small mb-3">
                        RSVP for this activity in the <strong>Activities hub</strong> first, then scan this QR again to check in.
                    </div>
                    <div class="checkin-nav">
                        <a href="<?= htmlspecialchars($hubUrl) ?>" class="btn btn-success">Activities hub</a>
                        <a href="<?= htmlspecialchars($dashboardUrl) ?>" class="btn btn-outline-primary">Dashboard</a>
                    </div>
                <?php elseif ($showCheckinForm): ?>
                    <?php if ($checkin_error !== ''): ?>
                        <div class="alert alert-danger small mb-3"><?= htmlspecialchars($checkin_error) ?></div>
                    <?php endif; ?>
                    <?php if (!$geo_required): ?>
                        <div class="alert alert-info small mb-3 mb-md-2">
                            <i class="fas fa-qrcode me-1"></i> QR check-in is enabled. Distance check is off for testing — tap <strong>Confirm check-in</strong> after scanning.
                        </div>
                    <?php endif; ?>
                    <p class="small text-muted mb-2">One device per student for this activity<?= $geo_required ? '; you must be within ' . (int) $geo_radius_m . 'm of the venue' : '' ?>.</p>
                    <p class="small checkin-geo-status mb-2" id="checkinGeoStatus" role="status" aria-live="polite"></p>
                    <form method="POST" id="checkinForm">
                        <?= csrf_field() ?>
                        <input type="hidden" name="confirm" value="1">
                        <input type="hidden" name="geo_lat" id="geo_lat" value="">
                        <input type="hidden" name="geo_lng" id="geo_lng" value="">
                        <input type="hidden" name="geo_accuracy" id="geo_accuracy" value="">
                        <input type="hidden" name="geo_ts" id="geo_ts" value="">
                        <input type="hidden" name="device_hash" id="device_hash" value="">
                        <button type="submit" class="btn btn-success w-100" id="confirmBtn" disabled>
                            <i class="fas fa-check me-1"></i>Confirm check-in
                        </button>
                    </form>
                    <?php include __DIR__ . '/views/partials/checkin_security_script.php'; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
