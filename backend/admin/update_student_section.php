<?php
/**
 * Admin: assign a student's class section (free text / from class_sections list).
 */
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/student_sections.php';
require_once __DIR__ . '/../lib/activity_logger.php';

function eventify_admin_section_redirect(string $type, string $message): void
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
    eventify_admin_section_redirect('error', 'Invalid or expired session. Refresh and try again.');
}

$id = (int) ($_POST['user_id'] ?? 0);
$sectionRaw = trim((string) ($_POST['student_section'] ?? ''));
$newSection = trim((string) ($_POST['new_section'] ?? ''));

if ($id <= 0) {
    eventify_admin_section_redirect('error', 'Invalid user.');
}

eventify_sections_schema_ensure($conn);

$label = '';
if ($newSection !== '') {
    $add = eventify_add_class_section($conn, $newSection, (int) ($_SESSION['user_id'] ?? 0));
    if (!$add['ok']) {
        $conn->close();
        eventify_admin_section_redirect('error', $add['error'] ?? 'Invalid section.');
    }
    $label = (string) ($add['label'] ?? '');
} elseif ($sectionRaw !== '') {
    $add = eventify_add_class_section($conn, $sectionRaw, (int) ($_SESSION['user_id'] ?? 0));
    if (!$add['ok']) {
        $conn->close();
        eventify_admin_section_redirect('error', $add['error'] ?? 'Invalid section.');
    }
    $label = (string) ($add['label'] ?? '');
}
// Empty label clears the student's section

$stmt = $conn->prepare("SELECT id, role FROM users WHERE id = ? LIMIT 1");
if (!$stmt) {
    $conn->close();
    eventify_admin_section_redirect('error', 'Database error.');
}
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $conn->close();
    eventify_admin_section_redirect('error', 'User not found.');
}
if (($user['role'] ?? '') !== 'student') {
    $conn->close();
    eventify_admin_section_redirect('error', 'Only student accounts have a class section.');
}

if ($label !== '') {
    $upd = $conn->prepare("UPDATE users SET student_section = ? WHERE id = ? AND role = 'student'");
    if (!$upd) {
        $conn->close();
        eventify_admin_section_redirect('error', 'Failed to prepare update.');
    }
    $upd->bind_param('si', $label, $id);
    $upd->execute();
    $upd->close();
    $desc = "Admin set class section to '{$label}' for user ID {$id}";
} else {
    $upd = $conn->prepare("UPDATE users SET student_section = NULL WHERE id = ? AND role = 'student'");
    if (!$upd) {
        $conn->close();
        eventify_admin_section_redirect('error', 'Failed to prepare update.');
    }
    $upd->bind_param('i', $id);
    $upd->execute();
    $upd->close();
    $desc = "Admin cleared class section for user ID {$id}";
}
log_activity(
    $conn,
    (int) ($_SESSION['user_id'] ?? 0),
    (string) ($_SESSION['role'] ?? ''),
    'student_section_updated',
    'user',
    $id,
    $desc
);

$conn->close();
eventify_admin_section_redirect(
    'success',
    $label !== '' ? 'Student section updated.' : 'Student section cleared.'
);
