<?php
/**
 * Digital ticket pass with QR for venue entry.
 */
session_start();
include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
require_once __DIR__ . '/backend/lib/event_ticketing.php';

$code = trim($_GET['code'] ?? '');
if ($code === '') {
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_student.php?panel=tickets');
    exit();
}

eventify_ticketing_ensure_schema($conn);
$ticket = eventify_load_ticket_by_code($conn, $code);
if (!$ticket) {
    $conn->close();
    http_response_code(404);
    echo 'Ticket not found.';
    exit();
}

$isOwner = isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] === (int) ($ticket['user_id'] ?? 0);
$isStaff = in_array($_SESSION['role'] ?? '', ['organizer', 'admin', 'super_admin'], true);

if (!$isOwner && !$isStaff) {
    $conn->close();
    header('Location: ' . BASE_URL . '/views/login.php?error=' . urlencode('Log in to view your ticket'));
    exit();
}

$checkinUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . BASE_URL . '/ticket_checkin.php?tk=' . urlencode((string) ($ticket['checkin_token'] ?? ''));

$qrApi = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . urlencode($checkinUrl);
$status = (string) ($ticket['status'] ?? 'valid');
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital pass — <?= htmlspecialchars($ticket['ticket_code'] ?? '') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/event_tickets.css">
    <link rel="manifest" href="<?= BASE_URL ?>/manifest-student.php">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/pwa/icon-192.png">
    <meta name="theme-color" content="#064e3b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/pwa_student.css">
</head>
<body class="ticket-pass-page">
<div id="pwaOfflinePassNotice" class="pwa-offline-notice mx-auto" style="max-width: 380px;" hidden>Offline — showing your saved pass. Reconnect to refresh status.</div>
<div class="ticket-pass-card mx-auto">
    <div class="ticket-pass-card__header">
        <div class="small opacity-75">EVENTIFY · Digital pass</div>
        <h1 class="h5 mb-0 mt-1" id="ticketPassEventTitle"><?= htmlspecialchars($ticket['event_title'] ?? 'Event') ?></h1>
    </div>
    <div class="ticket-pass-card__body text-center">
        <?php if ($status === 'used'): ?>
            <div class="alert alert-secondary py-2 small">This ticket was already used for entry.</div>
        <?php endif; ?>
        <p class="mb-1 fw-semibold" id="ticketPassType"><?= htmlspecialchars($ticket['type_name'] ?? '') ?></p>
        <p class="text-muted small mb-3" id="ticketPassCode"><?= htmlspecialchars($ticket['ticket_code'] ?? '') ?></p>
        <img src="<?= htmlspecialchars($qrApi) ?>" alt="Check-in QR" width="220" height="220" class="ticket-pass-qr mb-2" id="ticketPassQr">
        <p class="small text-muted mb-0">Show this QR at the entrance. One scan per ticket.</p>
        <hr>
        <div class="text-start small">
            <div><strong>Holder:</strong> <?= htmlspecialchars($ticket['holder_name'] ?? '') ?></div>
            <?php if (!empty($ticket['holder_student_id'])): ?>
                <div><strong>ID:</strong> <?= htmlspecialchars($ticket['holder_student_id']) ?></div>
            <?php endif; ?>
            <?php if (!empty($ticket['event_date'])): ?>
                <div><strong>Date:</strong> <?= htmlspecialchars($ticket['event_date']) ?></div>
            <?php endif; ?>
            <?php if (!empty($ticket['event_location'])): ?>
                <div><strong>Venue:</strong> <?= htmlspecialchars($ticket['event_location']) ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<div class="text-center mt-3 no-print">
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
    <a href="<?= BASE_URL ?>/backend/auth/dashboard_student.php?panel=tickets" class="btn btn-link btn-sm">My tickets</a>
</div>
<script>window.BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
<script>window.__ticketPassBootstrap = <?= json_encode([
    'ticket_code' => (string) ($ticket['ticket_code'] ?? ''),
    'event_title' => (string) ($ticket['event_title'] ?? ''),
    'type_name' => (string) ($ticket['type_name'] ?? ''),
    'event_date' => (string) ($ticket['event_date'] ?? ''),
    'event_location' => (string) ($ticket['event_location'] ?? ''),
    'holder_name' => (string) ($ticket['holder_name'] ?? ''),
    'holder_student_id' => (string) ($ticket['holder_student_id'] ?? ''),
    'checkin_url' => $checkinUrl,
    'qr_url' => $qrApi,
    'status' => $status,
], JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= BASE_URL ?>/assets/js/eventify_alert_modal.js?v=1"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_pwa.js?v=17"></script>
</body>
</html>
