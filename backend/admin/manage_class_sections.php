<?php
/**
 * Admin: add / delete class section labels (free text).
 */
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/student_sections.php';
require_once __DIR__ . '/../lib/activity_logger.php';

function eventify_admin_sections_redirect(string $type, string $message): void
{
    $param = $type === 'success' ? 'success' : 'error';
    $q = $param . '=' . urlencode($message) . '&panel=users';
    header('Location: ' . BASE_URL . '/backend/admin/dashboard.php?' . $q);
    exit();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . BASE_URL . '/views/login.php?error=' . urlencode('Access denied'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    eventify_admin_sections_redirect('error', 'Invalid or expired session. Refresh and try again.');
}

$adminId = (int) ($_SESSION['user_id'] ?? 0);
$action = trim((string) ($_POST['action'] ?? 'add'));
eventify_sections_schema_ensure($conn);

if ($action === 'delete') {
    $id = (int) ($_POST['section_id'] ?? 0);
    $res = eventify_delete_class_section($conn, $id);
    if (!$res['ok']) {
        $conn->close();
        eventify_admin_sections_redirect('error', $res['error'] ?? 'Could not delete section.');
    }
    log_activity($conn, $adminId, 'admin', 'class_section_deleted', 'class_section', $id, 'Deleted class section #' . $id);
    $conn->close();
    eventify_admin_sections_redirect('success', 'Section removed from the list.');
}

$label = (string) ($_POST['label'] ?? '');
$add = eventify_add_class_section($conn, $label, $adminId);
if (!$add['ok']) {
    $conn->close();
    eventify_admin_sections_redirect('error', $add['error'] ?? 'Could not add section.');
}
log_activity(
    $conn,
    $adminId,
    'admin',
    'class_section_added',
    'class_section',
    (int) ($add['id'] ?? 0),
    "Added class section '" . (string) ($add['label'] ?? '') . "'"
);
$conn->close();
eventify_admin_sections_redirect('success', 'Section saved: ' . (string) ($add['label'] ?? ''));
