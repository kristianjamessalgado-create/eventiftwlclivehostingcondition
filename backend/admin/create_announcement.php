<?php
/**
 * Admin: compose and send a targeted announcement to students (bell + push).
 */
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/admin_announcements.php';
require_once __DIR__ . '/../lib/activity_logger.php';

function eventify_admin_announcement_redirect(string $type, string $message): void
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
    eventify_admin_announcement_redirect('error', 'Invalid or expired session. Refresh and try again.');
}

$adminId = (int) ($_SESSION['user_id'] ?? 0);
$title = trim((string) ($_POST['title'] ?? ''));
$body = trim((string) ($_POST['body'] ?? ''));

$parsed = eventify_parse_announcement_filters_from_request($_POST);
if (!$parsed['ok']) {
    $conn->close();
    eventify_admin_announcement_redirect('error', (string) ($parsed['error'] ?? 'Invalid audience filters.'));
}

// Persist typed section into catalog when used as a filter.
if (function_exists('eventify_sections_schema_ensure')) {
    eventify_sections_schema_ensure($conn);
}
$newSection = trim((string) ($_POST['new_section'] ?? ''));
if ($newSection !== '' && function_exists('eventify_add_class_section')) {
    eventify_add_class_section($conn, $newSection, $adminId);
}

try {
    $result = eventify_send_admin_announcement($conn, $adminId, $title, $body, $parsed['filters']);
} catch (Throwable $e) {
    error_log('EVENTIFY announcement send failed: ' . $e->getMessage());
    $conn->close();
    eventify_admin_announcement_redirect(
        'error',
        'The announcement could not be sent. Please upload the latest notification files and try again.'
    );
}
if (!$result['ok']) {
    $conn->close();
    eventify_admin_announcement_redirect('error', (string) ($result['error'] ?? 'Failed to send announcement.'));
}

$recipientCount = (int) ($result['recipient_count'] ?? 0);
$announcementId = (int) ($result['announcement_id'] ?? 0);

if (function_exists('log_activity')) {
    log_activity(
        $conn,
        $adminId,
        'admin',
        'admin_announcement_sent',
        'announcement',
        $announcementId > 0 ? $announcementId : null,
        "Admin sent announcement to {$recipientCount} student(s): {$title}"
    );
}

$conn->close();
eventify_admin_announcement_redirect(
    'success',
    "Announcement sent to {$recipientCount} student" . ($recipientCount === 1 ? '' : 's') . '.'
);
