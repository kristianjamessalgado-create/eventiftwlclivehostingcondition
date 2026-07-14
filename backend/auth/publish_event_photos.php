<?php
session_start();
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/event_photos.php';
require_once __DIR__ . '/../lib/multimedia_moderator.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'multimedia') {
    header('Location: ' . BASE_URL . '/views/login.php?error=' . urlencode('Access denied'));
    exit();
}

$user_id = (int) $_SESSION['user_id'];
eventify_moderator_require($conn, $user_id);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_multimedia.php?msg=' . urlencode('Invalid request.'));
    exit();
}

$event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
$session_id = isset($_POST['session_id']) ? (int) $_POST['session_id'] : 0;

if ($event_id < 1) {
    $conn->close();
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_multimedia.php?msg=' . urlencode('Invalid event.'));
    exit();
}

if (!eventify_event_photos_has_status($conn)) {
    $conn->close();
    eventify_multimedia_photo_redirect($event_id, $session_id, 'Publishing workflow requires DB migration.');
}

eventify_event_photos_ensure_session_column($conn);

if ($session_id > 0 && !eventify_validate_activity_for_event($conn, $session_id, $event_id)) {
    $conn->close();
    eventify_multimedia_photo_redirect($event_id, $session_id, 'Activity not found.');
}

$updated = eventify_moderator_approve_event_drafts($conn, $event_id, $session_id, $user_id);
if ($updated >= 0) {
    eventify_sync_moderator_pending_photo_notifications($conn, $user_id);
}
$conn->close();

$msg = $updated > 0
    ? ($session_id > 0
        ? $updated . ' activity photo(s) approved and published.'
        : $updated . ' photo(s) approved and published.')
    : ($session_id > 0 ? 'No pending activity photos to approve.' : 'No pending photos to approve for this event.');
eventify_multimedia_photo_redirect($event_id, $session_id, $msg);
