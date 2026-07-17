<?php
/**
 * Admin: edit an announcement title/body (syncs student bell notifications).
 */
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/admin_announcements.php';
require_once __DIR__ . '/../lib/activity_logger.php';

function eventify_admin_announcement_edit_redirect(string $type, string $message): void
{
    $param = $type === 'success' ? 'success' : 'error';
    $q = $param . '=' . urlencode($message) . '&panel=announcements';
    header('Location: ' . BASE_URL . '/backend/admin/dashboard.php?' . $q);
    exit();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . BASE_URL . '/views/login.php?error=' . urlencode('Access denied'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    eventify_admin_announcement_edit_redirect('error', 'Invalid or expired session. Refresh and try again.');
}

$adminId = (int) ($_SESSION['user_id'] ?? 0);
$id = (int) ($_POST['announcement_id'] ?? 0);
$title = trim((string) ($_POST['title'] ?? ''));
$body = trim((string) ($_POST['body'] ?? ''));

try {
    $result = eventify_update_admin_announcement($conn, $id, $title, $body);
} catch (Throwable $e) {
    error_log('EVENTIFY announcement edit failed: ' . $e->getMessage());
    $conn->close();
    eventify_admin_announcement_edit_redirect(
        'error',
        'The announcement could not be updated. Please upload the latest notification files and try again.'
    );
}
if (!$result['ok']) {
    $conn->close();
    eventify_admin_announcement_edit_redirect('error', (string) ($result['error'] ?? 'Failed to update announcement.'));
}

$recipientCount = (int) ($result['recipient_count'] ?? 0);

if (function_exists('log_activity')) {
    log_activity(
        $conn,
        $adminId,
        'admin',
        'admin_announcement_updated',
        'announcement',
        $id,
        "Admin updated announcement #{$id} and re-notified {$recipientCount} student(s): {$title}"
    );
}

$conn->close();
eventify_admin_announcement_edit_redirect(
    'success',
    "Announcement updated and sent again to {$recipientCount} student" . ($recipientCount === 1 ? '' : 's') . '.'
);
