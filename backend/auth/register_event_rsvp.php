<?php
/**
 * Student RSVP (POST + CSRF). Respects max_capacity when column exists.
 */
session_start();
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
include __DIR__ . '/../../config/departments.php';
if (is_file(__DIR__ . '/../../config/student_sections.php')) {
    require_once __DIR__ . '/../../config/student_sections.php';
}
require_once __DIR__ . '/student_rsvp_ajax.php';
require_once __DIR__ . '/../lib/event_calendar.php';
require_once __DIR__ . '/../lib/event_ticketing.php';
require_once __DIR__ . '/../lib/web_push.php';

eventify_sections_schema_ensure($conn);

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

$user_id  = (int) $_SESSION['user_id'];
$event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
$msg      = 'Invalid event.';
$ok       = false;

$eventsHasMaxCapacity = false;
try {
    $mcCol = $conn->query("SHOW COLUMNS FROM events WHERE Field = 'max_capacity'");
    if ($mcCol && $mcCol->num_rows >= 1) {
        $eventsHasMaxCapacity = true;
    }
} catch (Throwable $e) {
    $eventsHasMaxCapacity = false;
}

if ($event_id > 0) {
    $hasTargetSec = eventify_events_has_target_sections($conn);
    $tsCol = $hasTargetSec ? ', e.target_sections' : '';
    if ($eventsHasMaxCapacity) {
        $stmt = $conn->prepare("SELECT e.id, e.title, e.organizer_id, e.status, e.registration_mode, e.max_capacity, e.department{$tsCol}, e.date, e.end_date, e.start_time, e.end_time, e.end_time_na, u.department AS student_department, u.student_section FROM events e JOIN users u ON u.id = ? WHERE e.id = ?");
    } else {
        $stmt = $conn->prepare("SELECT e.id, e.title, e.organizer_id, e.status, e.registration_mode, e.department{$tsCol}, e.date, e.end_date, e.start_time, e.end_time, e.end_time_na, u.department AS student_department, u.student_section FROM events e JOIN users u ON u.id = ? WHERE e.id = ?");
    }
    if (!$stmt) {
        if ($wantsAjax) {
            student_rsvp_json_response($conn, ['ok' => false, 'message' => 'Server error.', 'event_id' => $event_id]);
        }
        $conn->close();
        header("Location: " . BASE_URL . "/backend/auth/dashboard_student.php?msg=" . urlencode("Server error."));
        exit();
    }
    $stmt->bind_param("ii", $user_id, $event_id);
    $stmt->execute();
    $ev = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($ev && !array_key_exists('max_capacity', $ev)) {
        $ev['max_capacity'] = null;
    }

    if ($ev) {
        $evRows = [$ev];
        eventify_events_attach_schedule_dates($conn, $evRows);
        $ev = $evRows[0];
    }

    if (!$ev || ($ev['status'] ?? '') !== 'active') {
        $msg = 'Event not found or not open for registration.';
    } elseif (eventify_event_uses_paid_ticketing($ev)) {
        $msg = 'This event requires a paid ticket. Use Buy tickets on the event page.';
    } elseif (eventify_event_registration_mode($ev) === 'open') {
        $msg = 'This event does not use RSVP. Just attend or check in with QR when available.';
    } elseif (!eventify_event_is_upcoming($ev)) {
        $msg = 'This event has ended. RSVP is no longer available.';
    } elseif (!eventify_student_may_access_event($ev, [
        'department' => $ev['student_department'] ?? null,
        'student_section' => $ev['student_section'] ?? null,
    ])) {
        $msg = 'This event is not available for your department or class section.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM registrations WHERE user_id = ? AND event_id = ?");
        $stmt->bind_param("ii", $user_id, $event_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $stmt->close();
            $msg = 'You are already registered for this event.';
        } else {
            $stmt->close();
            $maxCap = isset($ev['max_capacity']) && $ev['max_capacity'] !== null && $ev['max_capacity'] !== ''
                ? (int) $ev['max_capacity']
                : null;
            if ($maxCap !== null && $maxCap > 0) {
                $cStmt = $conn->prepare("SELECT COUNT(*) AS c FROM registrations WHERE event_id = ?");
                $cStmt->bind_param("i", $event_id);
                $cStmt->execute();
                $cStmt->bind_result($cnt);
                $cStmt->fetch();
                $cStmt->close();
                if ((int) $cnt >= $maxCap) {
                    $msg = 'This event is full. No more seats available.';
                    if ($wantsAjax) {
                        $meta = student_event_registration_meta($conn, $event_id, $user_id);
                        student_rsvp_json_response($conn, array_merge([
                            'ok' => false,
                            'message' => $msg,
                            'event_id' => $event_id,
                        ], $meta));
                    }
                    header("Location: " . BASE_URL . "/backend/auth/dashboard_student.php?msg=" . urlencode($msg));
                    exit();
                }
            }
            $ins = $conn->prepare("INSERT INTO registrations (user_id, event_id) VALUES (?, ?)");
            $ins->bind_param("ii", $user_id, $event_id);
            if ($ins->execute()) {
                $msg = 'Successfully registered!';
                $ok = true;
                // Student notification copy (for in-app history)
                try {
                    $evTitle = (string)($ev['title'] ?? '');
                    $organizerId = (int)($ev['organizer_id'] ?? 0);
                    $studentName = (string)($_SESSION['name'] ?? 'A student');
                    $nt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, 'rsvp_confirmed', 'RSVP confirmed', ?, ?)");
                    if ($nt) {
                        $nMsg = 'You are registered for "' . ($evTitle ?: 'this event') . '".';
                        $nt->bind_param("isi", $user_id, $nMsg, $event_id);
                        if ($nt->execute()) {
                            eventify_push_notify_user($conn, $user_id, 'rsvp_confirmed', 'RSVP confirmed', $nMsg, $event_id);
                        }
                        $nt->close();
                    }
                    if ($organizerId > 0) {
                        $orgNotif = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, 'event_rsvp_new', 'New RSVP', ?, ?)");
                        if ($orgNotif) {
                            $orgMsg = $studentName . ' confirmed RSVP for "' . ($evTitle ?: 'your event') . '".';
                            $orgNotif->bind_param("isi", $organizerId, $orgMsg, $event_id);
                            $orgNotif->execute();
                            $orgNotif->close();
                        }
                    }
                } catch (Throwable $e) {
                    // ignore if notifications table is unavailable
                }
            } else {
                $msg = 'Could not register. Please try again.';
            }
            $ins->close();
        }
    }
}

if ($wantsAjax) {
    $meta = student_event_registration_meta($conn, $event_id, $user_id);
    student_rsvp_json_response($conn, array_merge([
        'ok' => $ok,
        'message' => $msg,
        'event_id' => $event_id,
    ], $meta));
}

$conn->close();
header("Location: " . BASE_URL . "/backend/auth/dashboard_student.php?msg=" . urlencode($msg));
exit();
