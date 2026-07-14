<?php
/**
 * JSON helpers for in-page student main-event RSVP (no full-page redirect).
 */
function student_rsvp_wants_ajax(): bool
{
    return !empty($_POST['ajax']);
}

function student_rsvp_json_response(mysqli $conn, array $payload): void
{
    if ($conn instanceof mysqli) {
        $conn->close();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function student_event_registration_meta(mysqli $conn, int $eventId, int $userId): array
{
    $count = 0;
    $isRegistered = false;
    if ($eventId < 1) {
        return ['registration_count' => 0, 'is_registered' => false];
    }
    $cStmt = $conn->prepare('SELECT COUNT(*) AS c FROM registrations WHERE event_id = ?');
    if ($cStmt) {
        $cStmt->bind_param('i', $eventId);
        $cStmt->execute();
        $cStmt->bind_result($count);
        $cStmt->fetch();
        $cStmt->close();
    }
    $uStmt = $conn->prepare('SELECT id FROM registrations WHERE user_id = ? AND event_id = ? LIMIT 1');
    if ($uStmt) {
        $uStmt->bind_param('ii', $userId, $eventId);
        $uStmt->execute();
        $isRegistered = (bool) $uStmt->get_result()->fetch_assoc();
        $uStmt->close();
    }
    return ['registration_count' => (int) $count, 'is_registered' => $isRegistered];
}
