<?php
session_start();
include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
include __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/backend/lib/event_ticketing.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ' . BASE_URL . '/views/login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_student.php?error=' . urlencode('Invalid request'));
    exit();
}

eventify_ticketing_ensure_schema($conn);
$eventId = (int) ($_POST['event_id'] ?? 0);
$qtyRaw = $_POST['qty'] ?? [];
if (!is_array($qtyRaw)) {
    $qtyRaw = [];
}

$lines = [];
foreach ($qtyRaw as $tid => $qty) {
    $qty = (int) $qty;
    $tid = (int) $tid;
    if ($tid > 0 && $qty > 0) {
        $lines[] = ['ticket_type_id' => $tid, 'quantity' => $qty];
    }
}

$result = eventify_create_ticket_order($conn, (int) $_SESSION['user_id'], $eventId, $lines);
$conn->close();

if (!$result['ok']) {
    header('Location: ' . BASE_URL . '/event_tickets.php?id=' . $eventId . '&error=' . urlencode($result['error'] ?? 'Checkout failed'));
    exit();
}

header('Location: ' . BASE_URL . '/ticket_payment.php?order_id=' . (int) $result['order_id']);
exit();
