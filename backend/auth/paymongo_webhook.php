<?php
/**
 * PayMongo webhook receiver (for public/HTTPS deployments).
 *
 * On localhost this never fires (PayMongo can't reach your machine) — the
 * browser return handler (ticket_payment_return.php) settles orders for the demo.
 * On a real deployment, register this URL in the PayMongo dashboard for the
 * `checkout_session.payment.paid` / `payment.paid` events.
 *
 * Security: we ALWAYS re-fetch the checkout session from the PayMongo API and
 * confirm it is actually paid before issuing tickets, so a forged webhook body
 * cannot fulfill an order. Optional signature verification runs first when a
 * webhook secret is configured.
 */
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../lib/event_ticketing.php';
require_once __DIR__ . '/../lib/paymongo.php';

http_response_code(200); // Always 200 so PayMongo doesn't retry on app-level no-ops.

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    echo 'no body';
    exit();
}

// Optional signature verification (only if a webhook secret is configured).
if (defined('EVENTIFY_PAYMONGO_WEBHOOK_SECRET') && trim((string) EVENTIFY_PAYMONGO_WEBHOOK_SECRET) !== '') {
    $secret = trim((string) EVENTIFY_PAYMONGO_WEBHOOK_SECRET);
    $sigHeader = (string) ($_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '');
    $parts = [];
    foreach (explode(',', $sigHeader) as $kv) {
        $kv = trim($kv);
        if (strpos($kv, '=') !== false) {
            [$k, $v] = explode('=', $kv, 2);
            $parts[trim($k)] = trim($v);
        }
    }
    $timestamp = $parts['t'] ?? '';
    $provided = $parts['li'] ?? ($parts['te'] ?? '');
    if ($timestamp === '' || $provided === '') {
        echo 'bad signature';
        exit();
    }
    $expected = hash_hmac('sha256', $timestamp . '.' . $raw, $secret);
    if (!hash_equals($expected, $provided)) {
        echo 'signature mismatch';
        exit();
    }
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    echo 'bad json';
    exit();
}

$eventType = (string) ($payload['data']['attributes']['type'] ?? '');
$resource = $payload['data']['attributes']['data'] ?? [];
$resourceId = (string) ($resource['id'] ?? '');

if (stripos($eventType, 'paid') === false) {
    echo 'ignored';
    exit();
}

eventify_ticketing_ensure_schema($conn);

// Resolve the checkout session id from the event resource.
$sessionId = '';
if (stripos($resourceId, 'cs_') === 0) {
    $sessionId = $resourceId;
} else {
    // payment.paid: the checkout session id may live in the order reference.
    $ref = (string) ($resource['attributes']['reference_number'] ?? '');
    if ($ref !== '') {
        $stmt = $conn->prepare("SELECT payment_reference FROM ticket_orders WHERE order_ref = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $ref);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row && stripos((string) ($row['payment_reference'] ?? ''), 'cs_') === 0) {
                $sessionId = (string) $row['payment_reference'];
            }
        }
    }
}

if ($sessionId === '') {
    $conn->close();
    echo 'no session';
    exit();
}

// Find the pending order holding this checkout session id.
$orderId = 0;
$stmt = $conn->prepare("SELECT id FROM ticket_orders WHERE payment_reference = ? AND status = 'pending' LIMIT 1");
if ($stmt) {
    $stmt->bind_param('s', $sessionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $orderId = (int) ($row['id'] ?? 0);
}

if ($orderId < 1) {
    $conn->close();
    echo 'no pending order';
    exit();
}

// Authoritative check against the PayMongo API before issuing tickets.
$check = eventify_paymongo_retrieve_checkout_session($sessionId);
if (empty($check['ok']) || empty($check['paid'])) {
    $conn->close();
    echo 'not paid';
    exit();
}

$paymentRef = (string) ($check['payment_id'] ?? '');
if ($paymentRef === '') {
    $paymentRef = $sessionId;
}

eventify_fulfill_ticket_order($conn, $orderId, 'gcash_paymongo', $paymentRef);
$conn->close();
echo 'ok';
exit();
