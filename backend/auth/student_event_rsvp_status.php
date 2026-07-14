<?php
/**
 * JSON: current main-event RSVP status for the logged-in student.
 */
session_start();
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/student_rsvp_ajax.php';
require_once __DIR__ . '/../lib/event_calendar.php';

require_once __DIR__ . '/../lib/event_ticketing.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not logged in.']);
    exit;
}

$eventId = (int) ($_GET['event_id'] ?? 0);
$userId = (int) $_SESSION['user_id'];

if ($eventId < 1) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid event.']);
    exit;
}

$meta = student_event_registration_meta($conn, $eventId, $userId);
$allowsRsvp = false;
$stmt = $conn->prepare('SELECT id, status, registration_mode, date, end_date, start_time, end_time, end_time_na FROM events WHERE id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $ev = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($ev) {
        $rows = [$ev];
        eventify_events_attach_schedule_dates($conn, $rows);
        $ev = $rows[0];
        $regMode = eventify_event_registration_mode($ev);
        $allowsRsvp = eventify_event_is_upcoming($ev)
            && !in_array($regMode, ['paid_ticket', 'open'], true);
    }
}

$conn->close();
echo json_encode(array_merge(['ok' => true, 'event_id' => $eventId, 'event_allows_rsvp' => $allowsRsvp], $meta));
