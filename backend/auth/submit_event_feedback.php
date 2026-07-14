<?php
/**
 * Student post-event feedback (rating + optional comment).
 */
session_start();
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/event_feedback_schema.php';
require_once __DIR__ . '/../lib/event_evaluation.php';
require_once __DIR__ . '/../lib/event_calendar.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: " . BASE_URL . "/views/login.php?error=" . urlencode("Access denied"));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    header("Location: " . BASE_URL . "/backend/auth/dashboard_student.php?msg=" . urlencode("Invalid request."));
    exit();
}

$user_id  = (int) $_SESSION['user_id'];
$event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
$evalInput = isset($_POST['eval']) && is_array($_POST['eval']) ? $_POST['eval'] : [];
$parsedEval = eventify_evaluation_parse_scores($evalInput);
$rating   = (int) ($parsedEval['scores']['overall_event'] ?? 0);
$comment  = trim($_POST['comment'] ?? '');
$is_anonymous = 1;
$msg      = 'Please answer all evaluation questions (1 = lowest, 5 = highest).';

if ($event_id < 1 || !$parsedEval['valid']) {
    header("Location: " . BASE_URL . "/backend/auth/dashboard_student.php?msg=" . urlencode($msg));
    exit();
}

if (strlen($comment) > 2000) {
    $comment = mb_substr($comment, 0, 2000);
}

try {
    $stmt = $conn->prepare("SELECT id, date, end_date, start_time, end_time, end_time_na, status FROM events WHERE id = ? AND status IN ('active','completed','closed')");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $ev = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$ev) {
        $msg = 'Event not found.';
    } else {
        if (!eventify_event_ended_for_feedback($ev)) {
            $msg = 'Feedback is only available after the event has ended, or once the organizer marks the event as ended.';
        } else {
            // Only students with recorded attendance (check-in) may submit feedback; stored with user_id for one-per-student, shown anonymously to organizers.
            $stmt = $conn->prepare("SELECT id FROM registrations WHERE user_id = ? AND event_id = ? AND status = 'present' AND time_in IS NOT NULL LIMIT 1");
            $stmt->bind_param("ii", $user_id, $event_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                $stmt->close();
                $msg = 'Only students who attended (QR check-in) can leave feedback for this event.';
            } else {
                $stmt->close();

                $chk = $conn->prepare("SELECT id FROM event_feedback WHERE event_id = ? AND user_id = ? LIMIT 1");
                if ($chk) {
                    $chk->bind_param("ii", $event_id, $user_id);
                    $chk->execute();
                    $chkRes = $chk->get_result();
                    $already = (bool)($chkRes && $chkRes->num_rows > 0);
                    $chk->close();
                } else {
                    $already = false;
                }

                if ($already) {
                    $msg = 'You already submitted your evaluation for this event.';
                    $_SESSION['eventify_feedback_ack'] = $_SESSION['eventify_feedback_ack'] ?? [];
                    if (!in_array($event_id, $_SESSION['eventify_feedback_ack'], true)) {
                        $_SESSION['eventify_feedback_ack'][] = $event_id;
                    }
                } else {
                    eventify_event_feedback_ensure_schema($conn);
                    $evalJson = json_encode($parsedEval['scores'], JSON_UNESCAPED_UNICODE);
                    $hasEvalCol = false;
                    $hasAnonCol = false;
                    try {
                        $chkEval = $conn->query("SHOW COLUMNS FROM event_feedback LIKE 'evaluation_json'");
                        $hasEvalCol = (bool) ($chkEval && $chkEval->num_rows > 0);
                        $chkCol = $conn->query("SHOW COLUMNS FROM event_feedback LIKE 'is_anonymous'");
                        $hasAnonCol = (bool) ($chkCol && $chkCol->num_rows > 0);
                    } catch (Throwable $e) {
                        $hasEvalCol = false;
                        $hasAnonCol = false;
                    }
                    if ($hasEvalCol && $hasAnonCol) {
                        $ins = $conn->prepare("INSERT INTO event_feedback (event_id, user_id, rating, comment, evaluation_json, is_anonymous) VALUES (?, ?, ?, ?, ?, ?)");
                        if ($ins) {
                            $ins->bind_param("iiissi", $event_id, $user_id, $rating, $comment, $evalJson, $is_anonymous);
                        }
                    } elseif ($hasAnonCol) {
                        $ins = $conn->prepare("INSERT INTO event_feedback (event_id, user_id, rating, comment, is_anonymous) VALUES (?, ?, ?, ?, ?)");
                        if ($ins) {
                            $ins->bind_param("iiisi", $event_id, $user_id, $rating, $comment, $is_anonymous);
                        }
                    } else {
                        $ins = $conn->prepare("INSERT INTO event_feedback (event_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
                        if ($ins) {
                            $ins->bind_param("iiis", $event_id, $user_id, $rating, $comment);
                        }
                    }
                    if ($ins && $ins->execute()) {
                        $msg = 'Thank you — your evaluation was saved. Organizers and admin see your responses anonymously; only your department may be shown.';
                        $_SESSION['eventify_feedback_ack'] = $_SESSION['eventify_feedback_ack'] ?? [];
                        if (!in_array($event_id, $_SESSION['eventify_feedback_ack'], true)) {
                            $_SESSION['eventify_feedback_ack'][] = $event_id;
                        }
                    } elseif ($ins) {
                        $msg = 'Could not save feedback right now. Please try again.';
                    } else {
                        $msg = 'Could not save feedback right now. Please try again.';
                    }
                    if ($ins) {
                        $ins->close();
                    }
                }
            }
        }
    }
} catch (Throwable $e) {
    $msg = 'Feedback is not available yet. Ask admin to run the database migration for event_feedback.';
}

$conn->close();
header("Location: " . BASE_URL . "/backend/auth/dashboard_student.php?msg=" . urlencode($msg));
exit();
