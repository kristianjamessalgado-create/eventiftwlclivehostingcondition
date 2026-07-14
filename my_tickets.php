<?php
/**
 * Legacy entry — my tickets now open inside the student dashboard.
 */
session_start();
require_once __DIR__ . '/config/config.php';

$eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
$orderId = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
$msg = trim((string) ($_GET['msg'] ?? ''));

$url = BASE_URL . '/backend/auth/dashboard_student.php?panel=tickets';
if ($eventId > 0) {
    $url .= '&event_id=' . $eventId;
}
if ($orderId > 0) {
    $url .= '&order_id=' . $orderId;
}
if ($msg !== '') {
    $url .= '&msg=' . urlencode($msg);
}

header('Location: ' . $url);
exit();
