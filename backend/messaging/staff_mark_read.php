<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/staff_messaging.php';

header('Content-Type: application/json; charset=utf-8');

$uid = (int)($_SESSION['user_id'] ?? 0);
$role = strtolower((string)($_SESSION['role'] ?? ''));
if ($uid < 1 || !in_array($role, ['admin', 'organizer'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_validate()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
    exit;
}

$other = (int)($_POST['other_user_id'] ?? 0);
if ($other < 1 || $other === $uid) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid peer.']);
    exit;
}

if (!eventify_staff_messaging_pair_allowed($conn, $uid, $other)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid conversation.']);
    exit;
}

if (!eventify_staff_messages_ensure_table($conn)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Messaging is unavailable.']);
    exit;
}

$stmt = $conn->prepare('UPDATE staff_messages SET read_at = NOW() WHERE recipient_id = ? AND sender_id = ? AND read_at IS NULL');
if ($stmt) {
    $stmt->bind_param('ii', $uid, $other);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
echo json_encode(['ok' => true]);
