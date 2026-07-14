<?php
/**
 * JSON list of student's valid paid tickets (for PWA offline cache).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

include __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../lib/event_ticketing.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit();
}

eventify_ticketing_ensure_schema($conn);
$userId = (int) $_SESSION['user_id'];
$eventId = (int) ($_GET['event_id'] ?? 0);
$rows = eventify_load_user_tickets($conn, $userId, $eventId > 0 ? $eventId : null);
$conn->close();

$tickets = array_map(static function (array $t) {
    $code = (string) ($t['ticket_code'] ?? '');
    return [
        'ticket_code'     => $code,
        'event_title'     => (string) ($t['event_title'] ?? ''),
        'type_name'       => (string) ($t['type_name'] ?? ''),
        'event_date'      => substr((string) ($t['event_date'] ?? ''), 0, 10),
        'event_location'  => (string) ($t['event_location'] ?? ''),
        'status'          => (string) ($t['status'] ?? 'valid'),
        'pass_url'        => BASE_URL . '/ticket_pass.php?code=' . urlencode($code),
        'cached_at'       => date('c'),
    ];
}, $rows);

echo json_encode([
    'ok'       => true,
    'tickets'  => $tickets,
    'cached_at' => date('c'),
], JSON_UNESCAPED_UNICODE);
