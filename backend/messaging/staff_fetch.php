<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../lib/staff_messaging.php';

header('Content-Type: application/json; charset=utf-8');

$uid = (int)($_SESSION['user_id'] ?? 0);
$role = strtolower((string)($_SESSION['role'] ?? ''));
if ($uid < 1 || !in_array($role, ['admin', 'organizer'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

$with = (int)($_GET['with'] ?? 0);
if ($with < 1) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing or invalid peer.']);
    exit;
}

if (!eventify_staff_messaging_pair_allowed($conn, $uid, $with)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid conversation.']);
    exit;
}

if (!eventify_staff_messages_ensure_table($conn)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Messaging is unavailable.']);
    exit;
}

$sql = "
    SELECT m.id, m.sender_id, m.recipient_id, m.body, m.attachment_path, m.created_at, m.read_at, u.name AS sender_name
    FROM staff_messages m
    JOIN users u ON u.id = m.sender_id
    WHERE (m.sender_id = ? AND m.recipient_id = ?)
       OR (m.sender_id = ? AND m.recipient_id = ?)
    ORDER BY m.id ASC
    LIMIT 300
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Query failed.']);
    exit;
}
$stmt->bind_param('iiii', $uid, $with, $with, $uid);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $row['mine'] = ((int)$row['sender_id'] === $uid);
        $rows[] = $row;
    }
}
$stmt->close();
$conn->close();

echo json_encode(['ok' => true, 'messages' => $rows]);
