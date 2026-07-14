<?php
require_once __DIR__ . '/../../config/session.php';

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/activity_logger.php';

function eventify_admin_users_redirect(string $type, string $message): void
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
    eventify_admin_users_redirect('error', 'Invalid or expired session. Refresh and try again.');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    eventify_admin_users_redirect('error', 'Invalid user.');
}

// Only act on a brand-new, email-verified pending account (never super admin,
// inactive, and not a locked existing account).
$stmt = $conn->prepare("SELECT id, role, status, failed_attempts FROM users WHERE id = ? LIMIT 1");
if (!$stmt) {
    $conn->close();
    eventify_admin_users_redirect('error', 'Database error.');
}
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $conn->close();
    eventify_admin_users_redirect('error', 'User not found.');
}
if (in_array(($user['role'] ?? ''), ['super_admin', 'admin'], true)) {
    $conn->close();
    eventify_admin_users_redirect('error', 'Only a Super Admin can approve admin accounts.');
}
if (($user['status'] ?? '') !== 'inactive') {
    $conn->close();
    eventify_admin_users_redirect('error', 'This account is not pending activation.');
}
if ((int) ($user['failed_attempts'] ?? 0) > 0) {
    $conn->close();
    eventify_admin_users_redirect('error', 'This is a locked account. Only a Super Admin can reactivate it via OTP.');
}

$upd = $conn->prepare("UPDATE users SET status = 'active', failed_attempts = 0 WHERE id = ? AND status = 'inactive'");
$upd->bind_param('i', $id);
$upd->execute();
$changed = $upd->affected_rows > 0;
$upd->close();

if (!$changed) {
    $conn->close();
    eventify_admin_users_redirect('error', 'No changes made. The account may already be active.');
}

log_activity(
    $conn,
    (int) ($_SESSION['user_id'] ?? 0),
    (string) ($_SESSION['role'] ?? ''),
    'user_activated',
    'user',
    $id,
    "Admin approved pending account ID {$id}"
);

$conn->close();
eventify_admin_users_redirect('success', 'Account approved and activated.');
