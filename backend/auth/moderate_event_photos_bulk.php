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
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_multimedia.php?msg=' . urlencode('Invalid request.') . '&panel=photo_approvals');
    exit();
}

$action = strtolower(trim((string) ($_POST['action'] ?? '')));
$rejectReason = eventify_sanitize_photo_caption((string) ($_POST['reject_reason'] ?? ''), 500);
$redirectTo = trim((string) ($_POST['redirect_to'] ?? ''));
$photoIds = $_POST['photo_ids'] ?? [];
if (!is_array($photoIds)) {
    $photoIds = [$photoIds];
}

if (!in_array($action, ['approve', 'reject'], true)) {
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_multimedia.php?msg=' . urlencode('Invalid moderation request.') . '&panel=photo_approvals');
    exit();
}

if ($action === 'reject' && $rejectReason === '') {
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_multimedia.php?msg=' . urlencode('Please provide a reason when rejecting photos.') . '&panel=photo_approvals');
    exit();
}

$result = eventify_moderator_bulk_moderate_photos($conn, $photoIds, $action, $user_id, $rejectReason);
$conn->close();

$processed = $action === 'approve' ? (int) $result['approved'] : (int) $result['rejected'];
$skipped = (int) $result['skipped'];

if ($processed < 1) {
    $msg = $action === 'reject'
        ? 'No photos were rejected. They may have already been reviewed.'
        : 'No photos were approved. They may have already been reviewed.';
} elseif ($action === 'approve') {
    $msg = $processed === 1
        ? '1 photo approved and published.'
        : $processed . ' photos approved and published.';
} else {
    $msg = $processed === 1
        ? '1 photo rejected.'
        : $processed . ' photos rejected.';
}

if ($skipped > 0) {
    $msg .= ' (' . $skipped . ' skipped — already reviewed.)';
}

if ($redirectTo === 'hub') {
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_multimedia.php?msg=' . urlencode($msg) . '&panel=photo_approvals');
    exit();
}

header('Location: ' . BASE_URL . '/backend/auth/dashboard_multimedia.php?msg=' . urlencode($msg) . '&panel=photo_approvals');
exit();
