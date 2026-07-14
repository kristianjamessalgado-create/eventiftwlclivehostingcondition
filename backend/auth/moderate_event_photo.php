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

$photo_id = isset($_POST['photo_id']) ? (int) $_POST['photo_id'] : 0;
$action = strtolower(trim((string) ($_POST['action'] ?? '')));
$event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
$session_id = isset($_POST['session_id']) ? (int) $_POST['session_id'] : 0;
$redirect_to = trim((string) ($_POST['redirect_to'] ?? ''));

if ($photo_id < 1 || !in_array($action, ['approve', 'reject'], true)) {
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_multimedia.php?msg=' . urlencode('Invalid moderation request.'));
    exit();
}

$rejectReason = eventify_sanitize_photo_caption((string) ($_POST['reject_reason'] ?? ''), 500);
if ($action === 'reject' && $rejectReason === '') {
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_multimedia.php?msg=' . urlencode('Please provide a reason when rejecting a photo.') . '&panel=photo_approvals');
    exit();
}

$stmt = $conn->prepare('SELECT event_id, session_id FROM event_photos WHERE id = ? AND status = ? LIMIT 1');
$draft = 'draft';
$stmt->bind_param('is', $photo_id, $draft);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    $conn->close();
    $msg = 'Photo not found or already reviewed.';
    if ($redirect_to === 'hub' && $event_id > 0 && $session_id > 0) {
        eventify_multimedia_photo_redirect($event_id, $session_id, $msg);
    }
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_multimedia.php?msg=' . urlencode($msg));
    exit();
}

$event_id = (int) ($row['event_id'] ?? $event_id);
$session_id = (int) ($row['session_id'] ?? $session_id);

$ok = $action === 'approve'
    ? eventify_moderator_approve_photo($conn, $photo_id, $user_id)
    : eventify_moderator_reject_photo($conn, $photo_id, $user_id, $rejectReason);

if ($ok) {
    eventify_sync_moderator_pending_photo_notifications($conn, $user_id);
}

$conn->close();

$rejectReason = eventify_sanitize_photo_caption((string) ($_POST['reject_reason'] ?? ''), 500);
$msg = !$ok
    ? 'Could not update photo status.'
    : ($action === 'approve'
        ? 'Photo approved and published.'
        : 'Photo rejected. The uploader will see your reason.');

if ($redirect_to === 'hub' && $event_id > 0 && $session_id > 0) {
    eventify_multimedia_photo_redirect($event_id, $session_id, $msg);
}
if ($redirect_to === 'hub' && $event_id > 0) {
    eventify_multimedia_photo_redirect($event_id, $session_id, $msg);
}

header('Location: ' . BASE_URL . '/backend/auth/dashboard_multimedia.php?msg=' . urlencode($msg) . '&panel=photo_approvals');
exit();
