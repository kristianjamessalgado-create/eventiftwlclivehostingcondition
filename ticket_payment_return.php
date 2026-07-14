<?php
/**
 * PayMongo GCash return handler.
 *
 * PayMongo redirects the buyer here (success_url) after the GCash authorization.
 * We retrieve the checkout session server-side, confirm it was actually paid,
 * then fulfill the order (issue digital passes). This is the source of truth for
 * the demo; on a public deployment the webhook does the same job asynchronously.
 */
session_start();
include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
require_once __DIR__ . '/backend/lib/event_ticketing.php';
require_once __DIR__ . '/backend/lib/paymongo.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ' . BASE_URL . '/views/login.php');
    exit();
}

eventify_ticketing_ensure_schema($conn);
$userId = (int) $_SESSION['user_id'];
$orderId = (int) ($_GET['order_id'] ?? 0);

$order = eventify_load_ticket_order($conn, $orderId, $userId);
if (!$order) {
    $conn->close();
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_student.php?error=' . urlencode('Order not found'));
    exit();
}

// Already settled (e.g. webhook beat us here, or a double redirect).
if (($order['status'] ?? '') === 'paid') {
    $conn->close();
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_student.php?panel=tickets&order_id=' . $orderId . '&msg=' . urlencode('Payment confirmed! Your digital passes are ready.'));
    exit();
}

$sessionId = trim((string) ($order['payment_reference'] ?? ''));
if ($sessionId === '' || stripos($sessionId, 'cs_') !== 0) {
    $conn->close();
    header('Location: ' . BASE_URL . '/ticket_payment.php?order_id=' . $orderId . '&error=' . urlencode('No active GCash payment found for this order.'));
    exit();
}

$check = eventify_paymongo_retrieve_checkout_session($sessionId);
if (empty($check['ok'])) {
    $conn->close();
    header('Location: ' . BASE_URL . '/ticket_payment.php?order_id=' . $orderId . '&error=' . urlencode($check['error'] ?? 'Could not verify your payment. Please try again.'));
    exit();
}

if (empty($check['paid'])) {
    $conn->close();
    header('Location: ' . BASE_URL . '/ticket_payment.php?order_id=' . $orderId . '&error=' . urlencode('Payment not completed yet. If you already paid, wait a moment and refresh.'));
    exit();
}

$paymentRef = (string) ($check['payment_id'] ?? '');
if ($paymentRef === '') {
    $paymentRef = $sessionId;
}

$result = eventify_fulfill_ticket_order($conn, $orderId, 'gcash_paymongo', $paymentRef);
$conn->close();

if (!empty($result['ok'])) {
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_student.php?panel=tickets&order_id=' . $orderId . '&msg=' . urlencode('Payment confirmed! Your digital passes are ready.'));
    exit();
}

header('Location: ' . BASE_URL . '/ticket_payment.php?order_id=' . $orderId . '&error=' . urlencode($result['error'] ?? 'Payment verified but tickets could not be issued. Contact the organizer.'));
exit();
