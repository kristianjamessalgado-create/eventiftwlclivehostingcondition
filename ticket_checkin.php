<?php
/**
 * Scan purchased ticket QR at venue (one-time entry).
 */
session_start();
include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
include __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/backend/lib/event_ticketing.php';
require_once __DIR__ . '/backend/lib/event_calendar.php';
require_once __DIR__ . '/backend/lib/event_status_auto.php';
require_once __DIR__ . '/backend/lib/event_checkin_security.php';

eventify_run_dashboard_maintenance($conn);

$token = trim($_GET['tk'] ?? '');
$error = '';
$confirmed = false;
$already_done = false;
$ticket = null;
$geo_required = false;
$geo_radius_m = eventify_checkin_geo_radius_m();
$focus_confirm_mobile = false;

if ($token === '') {
    $error = 'Invalid ticket link.';
} else {
    eventify_ticketing_ensure_schema($conn);
    $ticket = eventify_load_ticket_by_checkin_token($conn, $token);
    if (!$ticket) {
        $error = 'This ticket is invalid or expired.';
    } elseif (($ticket['event_status'] ?? '') !== 'active') {
        $error = 'This event is closed. Ticket check-in is no longer available.';
    } elseif (($ticket['status'] ?? '') === 'used') {
        $already_done = true;
    } elseif (($ticket['status'] ?? '') === 'cancelled') {
        $error = 'This ticket has been cancelled.';
    } else {
        $evRow = ['status' => $ticket['event_status'] ?? '', 'date' => $ticket['event_date'] ?? ''];
        if (!eventify_event_is_live($evRow)) {
            $error = 'This event has ended. Ticket check-in is no longer available.';
        } else {
            $geo_required = eventify_event_checkin_geo_required([
                'latitude' => $ticket['latitude'] ?? null,
                'longitude' => $ticket['longitude'] ?? null,
                'checkin_require_geo' => $ticket['checkin_require_geo'] ?? null,
            ]);
        }
    }
}

if (!$error && $ticket && !isset($_SESSION['user_id'])) {
    $returnUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . BASE_URL . '/ticket_checkin.php?tk=' . urlencode($token);
    header('Location: ' . BASE_URL . '/views/login.php?redirect=' . urlencode($returnUrl));
    exit();
}

if (!$error && $ticket && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && ($_SESSION['role'] ?? '') === 'student') {
    if (!csrf_validate()) {
        $error = 'Invalid request.';
    } else {
        $uid = (int) $_SESSION['user_id'];
        $result = eventify_process_ticket_checkin($conn, $ticket, $uid, $_POST);
        if ($result['ok']) {
            $confirmed = true;
            $already_done = true;
        } else {
            $error = $result['error'] ?? 'Check-in failed.';
        }
    }
}

if ($ticket && !$error && !$already_done && isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] !== (int) ($ticket['user_id'] ?? 0)) {
    $error = 'You must be logged in as the ticket holder to check in.';
}

if ($ticket && !$error && !$already_done && ($_SESSION['role'] ?? '') === 'student') {
    $focus_confirm_mobile = true;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket check-in</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="p-4" style="background:#f0f9f4; min-height:100vh;">
<div class="card mx-auto shadow-sm border-0" style="max-width:420px;">
    <div class="card-header bg-success text-white text-center">
        <i class="fas fa-ticket-alt me-2"></i>Ticket entry
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger mb-0"><?= htmlspecialchars($error) ?></div>
        <?php elseif ($confirmed || $already_done): ?>
            <div class="text-center">
                <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                <h5><?= $confirmed ? 'Welcome! Entry recorded.' : 'Already checked in' ?></h5>
                <?php if ($ticket): ?>
                    <p class="text-muted small"><?= htmlspecialchars($ticket['type_name'] ?? '') ?> — <?= htmlspecialchars($ticket['event_title'] ?? '') ?></p>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/backend/auth/dashboard_student.php" class="btn btn-primary btn-sm mt-2">Dashboard</a>
            </div>
        <?php elseif ($ticket): ?>
            <h5><?= htmlspecialchars($ticket['event_title'] ?? '') ?></h5>
            <p class="small text-muted"><?= htmlspecialchars($ticket['type_name'] ?? '') ?> · <?= htmlspecialchars($ticket['ticket_code'] ?? '') ?></p>
            <p class="small text-muted mb-3">One device per student<?= $geo_required ? '; GPS within ' . (int) $geo_radius_m . 'm of the venue' : '' ?>.</p>
            <form method="post" id="checkinForm">
                <?= csrf_field() ?>
                <input type="hidden" name="confirm" value="1">
                <input type="hidden" name="geo_lat" id="geo_lat" value="">
                <input type="hidden" name="geo_lng" id="geo_lng" value="">
                <input type="hidden" name="geo_accuracy" id="geo_accuracy" value="">
                <input type="hidden" name="geo_ts" id="geo_ts" value="">
                <input type="hidden" name="device_hash" id="device_hash" value="">
                <button type="submit" class="btn btn-success w-100" id="confirmBtn" disabled>Confirm entry</button>
            </form>
            <?php include __DIR__ . '/views/partials/checkin_security_script.php'; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
