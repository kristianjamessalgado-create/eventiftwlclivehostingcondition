<?php
require_once __DIR__ . '/../../config/session.php';

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/student_profile_fields.php';
require_once __DIR__ . '/../lib/activity_logger.php';

function eventify_admin_course_redirect(string $type, string $message): void
{
    $param = $type === 'success' ? 'success' : 'error';
    $q = $param . '=' . urlencode($message);
    $returnPanel = trim((string) ($_POST['return_panel'] ?? ''));
    $openModal = trim((string) ($_POST['open_modal'] ?? ''));
    if ($returnPanel !== '') {
        $q .= '&panel=' . urlencode($returnPanel);
    } elseif ($openModal === 'accounts') {
        $q .= '&panel=users';
    } elseif ($openModal !== '') {
        $q .= '&open_modal=' . urlencode($openModal);
    }
    header('Location: ' . BASE_URL . '/backend/admin/dashboard.php?' . $q);
    exit();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . BASE_URL . '/views/login.php?error=' . urlencode('Access denied'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    eventify_admin_course_redirect('error', 'Invalid or expired session. Refresh and try again.');
}

$id = (int) ($_POST['user_id'] ?? 0);
$course = trim((string) ($_POST['student_course'] ?? ''));

if ($id <= 0) {
    eventify_admin_course_redirect('error', 'Invalid user.');
}
if (!eventify_student_course_program_valid($course)) {
    eventify_admin_course_redirect('error', 'Please choose a valid course / program.');
}
$department = eventify_student_course_program_department($course);
if ($department === '') {
    eventify_admin_course_redirect('error', 'Could not determine the department for that course / program.');
}

eventify_users_ensure_student_profile_fields($conn);

// Only students have a course / program — never touch other roles.
$stmt = $conn->prepare("SELECT id, role FROM users WHERE id = ? LIMIT 1");
if (!$stmt) {
    $conn->close();
    eventify_admin_course_redirect('error', 'Database error.');
}
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $conn->close();
    eventify_admin_course_redirect('error', 'User not found.');
}
if (($user['role'] ?? '') !== 'student') {
    $conn->close();
    eventify_admin_course_redirect('error', 'Only student accounts have a course / program.');
}

$upd = $conn->prepare("UPDATE users SET student_course = ?, department = ? WHERE id = ? AND role = 'student'");
if (!$upd) {
    $conn->close();
    eventify_admin_course_redirect('error', 'Failed to prepare update.');
}
$upd->bind_param('ssi', $course, $department, $id);
$upd->execute();
$upd->close();

log_activity(
    $conn,
    (int) ($_SESSION['user_id'] ?? 0),
    (string) ($_SESSION['role'] ?? ''),
    'student_course_updated',
    'user',
    $id,
    "Admin set course/program to '{$course}' (department: {$department}) for user ID {$id}"
);

$conn->close();
eventify_admin_course_redirect('success', 'Student course / program updated.');
