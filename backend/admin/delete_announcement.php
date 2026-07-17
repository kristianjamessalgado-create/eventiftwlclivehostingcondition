<?php
/**
 * Admin: delete an announcement and related student bell notifications.
 */
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/admin_announcements.php';
require_once __DIR__ . '/../lib/activity_logger.php';

function eventify_admin_announcement_delete_redirect(string $type, string $message): void
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
    eventify_admin_announcement_delete_redirect('error', 'Invalid or expired session. Refresh and try again.');
}

$adminId = (int) ($_SESSION['user_id'] ?? 0);
$id = (int) ($_POST['announcement_id'] ?? 0);

$existing = eventify_get_admin_announcement($conn, $id);
$title = $existing ? (string) ($existing['title'] ?? '') : '';

$result = eventify_delete_admin_announcement($conn, $id);
if (!$result['ok']) {
    $conn->close();
    eventify_admin_announcement_delete_redirect('error', (string) ($result['error'] ?? 'Failed to delete announcement.'));
}

if (function_exists('log_activity')) {
    log_activity(
        $conn,
        $adminId,
        'admin',
        'admin_announcement_deleted',
        'announcement',
        $id,
        "Admin deleted announcement #{$id}" . ($title !== '' ? ": {$title}" : '')
    );
}

$conn->close();
eventify_admin_announcement_delete_redirect('success', 'Announcement deleted.');
