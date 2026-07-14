<?php
require_once __DIR__ . '/../../config/session.php';

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/activity_logger.php';

function eventify_admin_reject_redirect(string $type, string $message): void
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
    eventify_admin_reject_redirect('error', 'Invalid or expired session. Refresh and try again.');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    eventify_admin_reject_redirect('error', 'Invalid user.');
}

// Reject only applies to brand-new pending accounts (never super admin, inactive,
// and not a locked existing account). Rejecting removes the unverified record.
$stmt = $conn->prepare("SELECT id, name, email, role, status, failed_attempts FROM users WHERE id = ? LIMIT 1");
if (!$stmt) {
    $conn->close();
    eventify_admin_reject_redirect('error', 'Database error.');
}
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $conn->close();
    eventify_admin_reject_redirect('error', 'User not found.');
}
if (in_array(($user['role'] ?? ''), ['super_admin', 'admin'], true)) {
    $conn->close();
    eventify_admin_reject_redirect('error', 'Only a Super Admin can manage admin accounts.');
}
if (($user['status'] ?? '') !== 'inactive') {
    $conn->close();
    eventify_admin_reject_redirect('error', 'Only pending accounts can be rejected.');
}
if ((int) ($user['failed_attempts'] ?? 0) > 0) {
    $conn->close();
    eventify_admin_reject_redirect('error', 'This is a locked account, not a pending registration. Only a Super Admin can manage it.');
}

$del = $conn->prepare('DELETE FROM users WHERE id = ? AND status = ?');
$inactive = 'inactive';
$del->bind_param('is', $id, $inactive);
$del->execute();
$removed = $del->affected_rows > 0;
$del->close();

if (!$removed) {
    $conn->close();
    eventify_admin_reject_redirect('error', 'Could not reject the account.');
}

$label = trim((string) ($user['name'] ?? '')) . ' <' . trim((string) ($user['email'] ?? '')) . '>';
log_activity(
    $conn,
    (int) ($_SESSION['user_id'] ?? 0),
    (string) ($_SESSION['role'] ?? ''),
    'user_rejected',
    'user',
    $id,
    "Admin rejected pending account {$label} (ID {$id})"
);

$conn->close();
eventify_admin_reject_redirect('success', 'Pending account rejected and removed.');
