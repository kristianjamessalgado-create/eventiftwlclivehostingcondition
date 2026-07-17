<?php
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/web_push.php';

if (!isset($_SESSION['user_id']) || (string) ($_SESSION['role'] ?? '') !== 'student') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $subCount = eventify_web_push_count_subscriptions($conn, $userId);
    $diag = eventify_web_push_diagnostics();
    echo json_encode(array_merge([
        'ok' => true,
        'configured' => $diag['configured'],
        'publicKey' => eventify_web_push_public_key(),
        'supported' => true,
        'subscription_count' => $subCount,
        'push_ready' => $diag['configured'] && $subCount > 0,
        'vendor_loaded' => $diag['vendor_loaded'],
    ], $diag));
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw !== false ? $raw : '', true);
if (!is_array($data)) {
    $data = $_POST;
}

if (!csrf_validate()) {
    $jsonToken = trim((string) ($data['csrf_token'] ?? ''));
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    if ($jsonToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $jsonToken)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid security token']);
        exit;
    }
}

$action = trim((string) ($data['action'] ?? ''));

if ($action === 'subscribe') {
    $subscription = $data['subscription'] ?? null;
    if (!is_array($subscription)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid subscription']);
        exit;
    }
    if (!eventify_web_push_is_configured()) {
        http_response_code(503);
        echo json_encode(['ok' => false, 'error' => 'Push is not configured on the server yet.']);
        exit;
    }
    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $ok = eventify_web_push_save_subscription($conn, $userId, $subscription, $ua);
    echo json_encode(['ok' => $ok]);
    exit;
}

if ($action === 'unsubscribe' || $action === 'unsubscribe_device') {
    $endpoint = trim((string) ($data['endpoint'] ?? ''));
    // Drop this device endpoint entirely so a logged-out account cannot keep receiving.
    $ok = eventify_web_push_remove_endpoint($conn, $endpoint);
    if (!$ok) {
        $ok = eventify_web_push_remove_subscription($conn, $userId, $endpoint);
    }
    echo json_encode(['ok' => (bool) $ok]);
    exit;
}

if ($action === 'test') {
    if (!eventify_web_push_is_configured()) {
        http_response_code(503);
        echo json_encode(['ok' => false, 'error' => 'Push is not configured on the server yet.']);
        exit;
    }
    $send = eventify_web_push_send_to_user(
        $conn,
        $userId,
        'push_test',
        'EVENTIFY test',
        'If you see this, push notifications are working.',
        null
    );
    echo json_encode(array_merge(['ok' => $send['ok']], $send));
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action']);
