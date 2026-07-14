<?php
session_start();
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/student_rsvp_ajax.php';
require_once __DIR__ . '/../lib/event_day_sessions.php';
require_once __DIR__ . '/../lib/event_calendar.php';
require_once __DIR__ . '/../lib/web_push.php';

$wantsAjax = student_rsvp_wants_ajax();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: " . BASE_URL . "/views/login.php?error=" . urlencode("Access denied"));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    if ($wantsAjax) {
        student_rsvp_json_response($conn, ['ok' => false, 'message' => 'Invalid request.']);
    }
    header("Location: " . BASE_URL . "/backend/auth/dashboard_student.php?msg=" . urlencode("Invalid request."));
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

if ($event_id < 1) {
    if ($wantsAjax) {
        student_rsvp_json_response($conn, ['ok' => false, 'message' => 'Invalid event.', 'event_id' => $event_id]);
    }
    $conn->close();
    header("Location: " . BASE_URL . "/backend/auth/dashboard_student.php?msg=" . urlencode("Invalid event."));
    exit();
}

$eStmt = $conn->prepare("SELECT * FROM events WHERE id = ? LIMIT 1");
$eStmt->bind_param("i", $event_id);
$eStmt->execute();
$event = $eStmt->get_result()->fetch_assoc();
$eStmt->close();

if (!$event) {
    if ($wantsAjax) {
        student_rsvp_json_response($conn, ['ok' => false, 'message' => 'Event not found.', 'event_id' => $event_id]);
    }
    $conn->close();
    header("Location: " . BASE_URL . "/backend/auth/dashboard_student.php?msg=" . urlencode("Event not found."));
    exit();
}

$eventRows = [$event];
eventify_events_attach_schedule_dates($conn, $eventRows);
$event = $eventRows[0];

if (!eventify_event_is_upcoming($event)) {
    if ($wantsAjax) {
        student_rsvp_json_response($conn, ['ok' => false, 'message' => 'Past events can no longer be cancelled.', 'event_id' => $event_id]);
    }
    $conn->close();
    header("Location: " . BASE_URL . "/backend/auth/dashboard_student.php?msg=" . urlencode("Past events can no longer be cancelled."));
    exit();
}

$dStmt = $conn->prepare("DELETE FROM registrations WHERE user_id = ? AND event_id = ? LIMIT 1");
$dStmt->bind_param("ii", $user_id, $event_id);
$dStmt->execute();
$deleted = $dStmt->affected_rows > 0;
$dStmt->close();

$activityRsvpsCancelled = 0;
if ($deleted) {
    $activityRsvpsCancelled = eventify_cancel_all_session_rsvps_for_event($conn, $event_id, $user_id);
    try {
        $title = (string) ($event['title'] ?? 'this event');
        $organizerId = (int)($event['organizer_id'] ?? 0);
        $studentName = (string)($_SESSION['name'] ?? 'A student');
        $n = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, 'rsvp_cancelled', 'RSVP cancelled', ?, ?)");
        if ($n) {
            $nMsg = 'You cancelled your RSVP for "' . $title . '".';
            $n->bind_param("isi", $user_id, $nMsg, $event_id);
            if ($n->execute()) {
                eventify_push_notify_user($conn, $user_id, 'rsvp_cancelled', 'RSVP cancelled', $nMsg, $event_id);
            }
            $n->close();
        }
        if ($organizerId > 0) {
            $orgNotif = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, 'event_rsvp_cancelled', 'RSVP cancelled', ?, ?)");
            if ($orgNotif) {
                $orgMsg = $studentName . ' cancelled RSVP for "' . $title . '".';
                $orgNotif->bind_param("isi", $organizerId, $orgMsg, $event_id);
                $orgNotif->execute();
                $orgNotif->close();
            }
        }
    } catch (Throwable $e) {
        // ignore notifications failures
    }
}
$msg = $deleted ? "RSVP cancelled successfully." : "You are not registered for this event.";
if ($deleted && $activityRsvpsCancelled > 0) {
    $msg .= ' Your RSVPs for ' . $activityRsvpsCancelled . ' activit' . ($activityRsvpsCancelled === 1 ? 'y were' : 'ies were') . ' also cancelled.';
}
$ok = $deleted;

if ($wantsAjax) {
    $meta = student_event_registration_meta($conn, $event_id, $user_id);
    student_rsvp_json_response($conn, array_merge([
        'ok' => $ok,
        'message' => $msg,
        'event_id' => $event_id,
        'activity_rsvps_cancelled' => $activityRsvpsCancelled,
    ], $meta));
}

$conn->close();
header("Location: " . BASE_URL . "/backend/auth/dashboard_student.php?msg=" . urlencode($msg));
exit();
