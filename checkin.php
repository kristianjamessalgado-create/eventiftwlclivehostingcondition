<?php
session_start();
include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
include __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/backend/lib/event_calendar.php';
require_once __DIR__ . '/backend/lib/event_checkin_security.php';

$token = trim($_GET['t'] ?? '');
$confirmed = false;
$already_done = false; // Student already confirmed attendance for this event
$error = '';
$event = null;
$geo_required = false;
$rsvp_required = eventify_checkin_config_require_rsvp();
$geo_radius_m = eventify_checkin_geo_radius_m();
$focus_confirm_mobile = false;
$needs_rsvp_first = false;
$checkin_signup_action = '';
$checkin_signup_message = '';
$checkin_signup_cta_url = BASE_URL . '/backend/auth/dashboard_student.php';
$checkin_signup_cta_label = 'Go to dashboard';
$checkin_schedule_label = '';
$student_checkin_home_url = BASE_URL . '/backend/auth/dashboard_student.php';
$checkin_unavailable = null;

if ($token === '') {
    $error = 'Invalid or missing check-in link. Please scan the event QR code again.';
} else {
    // Load event by check-in token; ensure token exists (generate for old events)
    $eventHasGeo = false;
    try {
        $geoColCheck = $conn->query("SHOW COLUMNS FROM events WHERE Field IN ('latitude','longitude')");
        if ($geoColCheck && $geoColCheck->num_rows >= 2) {
            $eventHasGeo = true;
        }
    } catch (Throwable $e) {
        $eventHasGeo = false;
    }

    $eventSelect = 'id, title, organizer_id, date, end_date, start_time, end_time, end_time_na, location, status, registration_mode, checkin_token';
    if ($eventHasGeo) {
        $eventSelect .= ', latitude, longitude';
    }
    $stmt = $conn->prepare("SELECT {$eventSelect} FROM events WHERE checkin_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();
    $event = $res->fetch_assoc();
    $stmt->close();

    if (!$event) {
        $error = 'This check-in link is invalid or has expired.';
    } elseif (!in_array(strtolower($event['status'] ?? ''), ['active'], true)) {
        $st = strtolower($event['status'] ?? '');
        $error = in_array($st, ['closed', 'completed'], true)
            ? 'This event has ended. Check-in is no longer available.'
            : 'Check-in is only available for approved, active events.';
    } else {
        $events = [$event];
        eventify_events_attach_schedule_dates($conn, $events);
        $event = $events[0];
        $displayDates = eventify_event_calendar_display_dates($event);
        if (count($displayDates) > 1) {
            $checkin_schedule_label = date('M j', strtotime($displayDates[0]))
                . ' – ' . date('M j, Y', strtotime($displayDates[count($displayDates) - 1]));
        } elseif (count($displayDates) === 1) {
            $checkin_schedule_label = date('M j, Y', strtotime($displayDates[0]));
        } elseif (!empty($event['date'])) {
            $checkin_schedule_label = date('M j, Y', strtotime((string) $event['date']));
        }
        if (!eventify_event_allows_checkin($event)) {
            $checkin_unavailable = eventify_event_checkin_student_details($event);
            $error = $checkin_unavailable['reason'];
            if ($checkin_schedule_label === '' && $checkin_unavailable['schedule_label'] !== '') {
                $checkin_schedule_label = $checkin_unavailable['schedule_label'];
            }
        } else {
            $geo_required = eventify_event_checkin_geo_required($event);
            $rsvp_required = eventify_event_checkin_rsvp_required($event);
        }
    }
}

// Require student login to confirm attendance
if (!$error && $event && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!isset($_SESSION['user_id'])) {
        $returnUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . BASE_URL . '/checkin.php?t=' . urlencode($token);
        header('Location: ' . BASE_URL . '/views/login.php?redirect=' . urlencode($returnUrl));
        exit();
    }
    if ($_SESSION['role'] !== 'student') {
        $error = 'Only students can confirm attendance. Please log in with a student account.';
        $event = null;
    } else {
        // Check if this student already confirmed attendance for this event
        $uid = (int) $_SESSION['user_id'];
        $eid = (int) $event['id'];
        $chk = $conn->prepare("SELECT 1 FROM registrations WHERE user_id = ? AND event_id = ? AND status = 'present' LIMIT 1");
        $chk->bind_param("ii", $uid, $eid);
        $chk->execute();
        $chk->store_result();
        $already_done = $chk->num_rows > 0;
        $chk->close();
        $signupGap = eventify_checkin_main_signup_gap($conn, $event, $uid);
        if (!$already_done && !empty($signupGap['needed'])) {
            $needs_rsvp_first = true;
            $checkin_signup_action = (string) ($signupGap['action'] ?? 'rsvp');
            $checkin_signup_message = (string) ($signupGap['message'] ?? '');
            if ($checkin_signup_action === 'ticket') {
                $checkin_signup_cta_url = BASE_URL . '/event_tickets.php?event_id=' . $eid;
                $checkin_signup_cta_label = 'Buy tickets';
            } else {
                $checkin_signup_cta_url = BASE_URL . '/backend/auth/dashboard_student.php?event_id=' . $eid;
                $checkin_signup_cta_label = 'Go to dashboard & RSVP';
            }
        }
        $focus_confirm_mobile = !$already_done && !$needs_rsvp_first;
    }
}

// Handle confirm attendance (POST)
if (!$error && $event && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && isset($_SESSION['user_id']) && $_SESSION['role'] === 'student') {
    if (!csrf_validate()) {
        $error = 'Invalid request. Please try again.';
    } elseif (!eventify_event_allows_checkin($event)) {
        $checkin_unavailable = eventify_event_checkin_student_details($event);
        $error = $checkin_unavailable['reason'];
    } else {
        $user_id = (int) $_SESSION['user_id'];
        $event_id = (int) $event['id'];
        $result = eventify_process_main_event_checkin($conn, $event, $user_id, $_POST);
        if ($result['ok']) {
            $confirmed = true;
            try {
                $organizerId = (int) ($event['organizer_id'] ?? 0);
                if ($organizerId > 0) {
                    $studentName = (string) ($_SESSION['name'] ?? 'A student');
                    $eventTitle = (string) ($event['title'] ?? 'your event');
                    $n = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, 'event_attendance_confirmed', 'Attendance confirmed', ?, ?)");
                    if ($n) {
                        $nMsg = $studentName . ' confirmed attendance for "' . $eventTitle . '".';
                        $n->bind_param('isi', $organizerId, $nMsg, $event_id);
                        $n->execute();
                        $n->close();
                    }
                }
            } catch (Throwable $e) {
                /* ignore */
            }
        } else {
            $error = $result['error'] ?? 'Could not record attendance. Please try again.';
        }
    }
}

if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'student') {
    require_once __DIR__ . '/backend/lib/event_day_sessions.php';
    try {
        $student_checkin_home_url = eventify_student_activities_hub_home_url(
            $conn,
            (int) $_SESSION['user_id'],
            null
        );
    } catch (Throwable $e) {
        $student_checkin_home_url = BASE_URL . '/activities_hub.php';
    }
}

$conn->close();

$pageTitle = $event ? htmlspecialchars($event['title']) : 'Event Check-in';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Check-in | EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --school-white: #ffffff;
            --school-cream: #f7f4e7;
            --school-olive-top: #b7be77;
            --school-forest-mid: #3f6a2a;
            --school-forest-deep: #153313;
            --school-forest-card: #1b4a1b;
            --school-gold: #e6c54a;
            --school-gold-dim: #b88f2a;
            --school-border: rgba(230, 197, 74, 0.42);
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(180deg, var(--school-olive-top) 0%, var(--school-forest-mid) 42%, var(--school-forest-deep) 100%);
            background-attachment: fixed;
            padding: 1rem;
            color: #1f2937;
        }

        .checkin-card {
            width: 100%;
            max-width: 460px;
            border-radius: 18px;
            border: 2px solid var(--school-border);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
            overflow: hidden;
        }

        .checkin-header {
            background: linear-gradient(180deg, var(--school-forest-card) 0%, #021a08 100%);
            color: var(--school-white);
            border-bottom: 3px solid var(--school-gold);
            padding: 1.2rem 1.25rem;
            text-align: center;
        }

        .checkin-body {
            padding: 1.25rem;
            background: linear-gradient(180deg, #ffffff 0%, var(--school-cream) 100%);
        }

        .checkin-body h5 {
            color: var(--school-forest-card);
            font-weight: 800;
        }

        .event-meta {
            color: #4b5563;
            font-size: 0.92rem;
            margin-bottom: 0.45rem;
        }

        .event-meta i {
            color: var(--school-forest-card);
        }

        .btn-confirm {
            background: linear-gradient(180deg, var(--school-forest-card) 0%, var(--school-forest-deep) 100%);
            color: var(--school-gold);
            border: 2px solid var(--school-gold);
            padding: 0.75rem 1rem;
            font-weight: 700;
            border-radius: 10px;
        }

        .btn-confirm:hover {
            color: #fff7a8;
            border-color: #fff7a8;
            background: var(--school-forest-deep);
        }

        .checkin-unavailable {
            text-align: center;
        }

        .checkin-unavailable-icon {
            width: 3rem;
            height: 3rem;
            margin: 0 auto 0.75rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(185, 28, 28, 0.1);
            color: #b91c1c;
            font-size: 1.35rem;
        }

        .checkin-unavailable h5 {
            color: #991b1b;
            font-weight: 800;
            margin-bottom: 0.75rem;
        }

        .checkin-reason-box {
            background: #fff5f5;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 0.9rem 1rem;
            color: #991b1b;
            font-weight: 600;
            line-height: 1.45;
            text-align: left;
        }

        .checkin-context-list {
            list-style: none;
            padding: 0;
            margin: 0.85rem 0 0;
            text-align: left;
        }

        .checkin-context-list li {
            display: flex;
            gap: 0.55rem;
            align-items: flex-start;
            color: #4b5563;
            font-size: 0.9rem;
            margin-bottom: 0.45rem;
        }

        .checkin-context-list i {
            color: var(--school-forest-card);
            margin-top: 0.15rem;
        }

        @media (max-width: 576px) {
            .checkin-card { max-width: 100%; border-radius: 14px; }
            .checkin-header { padding: 1rem; }
            .checkin-body { padding: 1rem; }
        }
    </style>
</head>
<body>
    <div class="checkin-card card border-0 overflow-hidden">
        <div class="checkin-header">
            <h1 class="h4 mb-0"><i class="fas fa-qrcode me-2"></i>EVENTIFY Check-in</h1>
        </div>
        <div class="checkin-body">
            <?php if ($error): ?>
                <?php
                if ($checkin_unavailable === null && $event) {
                    $checkin_unavailable = eventify_event_checkin_student_details($event);
                    if ($checkin_schedule_label === '' && ($checkin_unavailable['schedule_label'] ?? '') !== '') {
                        $checkin_schedule_label = $checkin_unavailable['schedule_label'];
                    }
                }
                ?>
                <div class="checkin-unavailable">
                    <div class="checkin-unavailable-icon" aria-hidden="true">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h5>Can't check in yet</h5>
                    <?php if ($event && !empty($event['title'])): ?>
                        <p class="mb-2"><strong><?= htmlspecialchars((string) $event['title']) ?></strong></p>
                    <?php endif; ?>
                    <div class="checkin-reason-box">
                        <?= htmlspecialchars($error) ?>
                    </div>
                    <?php if ($checkin_unavailable): ?>
                        <ul class="checkin-context-list">
                            <?php if ($checkin_schedule_label !== ''): ?>
                                <li><i class="fas fa-calendar-day"></i><span>Event schedule: <strong><?= htmlspecialchars($checkin_schedule_label) ?></strong></span></li>
                            <?php endif; ?>
                            <li><i class="fas fa-hourglass-half"></i><span>Right now: <strong><?= htmlspecialchars($checkin_unavailable['now_label']) ?> <?= htmlspecialchars($checkin_unavailable['timezone_short']) ?></strong> (<?= htmlspecialchars($checkin_unavailable['today_date_label']) ?>)</span></li>
                            <?php if (($checkin_unavailable['today_window'] ?? '') !== ''): ?>
                                <li><i class="fas fa-door-open"></i><span>Today's check-in window: <strong><?= htmlspecialchars($checkin_unavailable['today_window']) ?></strong></span></li>
                            <?php endif; ?>
                        </ul>
                    <?php elseif ($checkin_schedule_label !== ''): ?>
                        <p class="event-meta mt-2 mb-0"><i class="fas fa-calendar-day me-2"></i>Scheduled: <?= htmlspecialchars($checkin_schedule_label) ?></p>
                    <?php endif; ?>
                </div>
                <div class="d-grid gap-2 mt-3">
                    <a href="<?= htmlspecialchars($student_checkin_home_url) ?>" class="btn btn-confirm" target="_top">Activities hub</a>
                    <a href="<?= BASE_URL ?>/backend/auth/dashboard_student.php" class="btn btn-outline-secondary btn-sm" target="_top">Dashboard</a>
                </div>
            <?php elseif ($confirmed): ?>
                <p class="text-success mb-2"><i class="fas fa-check-circle me-2"></i><strong>Attendance confirmed.</strong></p>
                <p class="text-muted small mb-0">You have been marked present for this event.</p>
                <a href="<?= BASE_URL ?>/backend/auth/dashboard_student.php" class="btn btn-primary mt-3" target="_top">Back to dashboard</a>
            <?php elseif ($already_done): ?>
                <p class="text-success mb-2"><i class="fas fa-check-circle me-2"></i><strong>You're done with attendance already.</strong></p>
                <p class="text-muted small mb-0">You have already confirmed your attendance for this event. No need to check in again.</p>
                <a href="<?= BASE_URL ?>/backend/auth/dashboard_student.php" class="btn btn-primary mt-3" target="_top">Back to dashboard</a>
            <?php elseif ($event): ?>
                <h5 class="mb-3"><?= htmlspecialchars($event['title']) ?></h5>
                <div class="event-meta">
                    <?php if (!empty($event['date'])): ?>
                        <div><i class="fas fa-calendar-day me-2"></i><?= date('l, M j, Y', strtotime($event['date'])) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($event['location'])): ?>
                        <div><i class="fas fa-map-marker-alt me-2"></i><?= htmlspecialchars($event['location']) ?></div>
                    <?php endif; ?>
                </div>
                <p class="small text-muted mb-2">Scan this QR at the venue to confirm attendance. Only students can check in.</p>
                <?php if ($needs_rsvp_first): ?>
                    <div class="alert alert-warning small mb-3">
                        <i class="fas fa-<?= $checkin_signup_action === 'ticket' ? 'ticket-alt' : 'user-plus' ?> me-1"></i>
                        <?php if ($checkin_signup_message !== ''): ?>
                            <?= htmlspecialchars($checkin_signup_message) ?>
                        <?php else: ?>
                            <strong>RSVP required.</strong> Register for this event on your dashboard first, then return here to check in.
                        <?php endif; ?>
                    </div>
                    <a href="<?= htmlspecialchars($checkin_signup_cta_url) ?>" class="btn btn-primary w-100 mb-2" target="_top"><?= htmlspecialchars($checkin_signup_cta_label) ?></a>
                <?php else: ?>
                <p class="small text-muted mb-2"><strong>Anti-fake check-in:</strong> one device = one student account per event<?= $geo_required ? ', plus you must be at the venue (GPS within ' . (int) $geo_radius_m . 'm)' : '' ?>.</p>
                <form method="POST" id="checkinForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="geo_lat" id="geo_lat" value="">
                    <input type="hidden" name="geo_lng" id="geo_lng" value="">
                    <input type="hidden" name="geo_accuracy" id="geo_accuracy" value="">
                    <input type="hidden" name="geo_ts" id="geo_ts" value="">
                    <input type="hidden" name="device_hash" id="device_hash" value="">
                    <button type="submit" name="confirm" value="1" class="btn btn-confirm w-100" id="confirmBtn" disabled>
                        <i class="fas fa-check-double me-2"></i>Confirm my attendance
                    </button>
                </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php include __DIR__ . '/views/partials/checkin_security_script.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
