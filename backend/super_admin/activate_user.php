<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
include __DIR__ . '/../../backend/lib/activity_logger.php';

function eventify_redirect_superadmin_activate(string $type, string $message): void
{
    $openModal = trim((string)($_POST['open_modal'] ?? 'users'));
    $q = $type . '=' . urlencode($message) . '&open_modal=' . urlencode($openModal);
    header("Location: " . BASE_URL . "/backend/super_admin/dashboardsuperadmin.php?" . $q);
    exit();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header("Location: " . BASE_URL . "/views/login.php?error=Access denied");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    eventify_redirect_superadmin_activate('error', 'Invalid request.');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    eventify_redirect_superadmin_activate('error', 'Invalid user ID.');
}

$userStmt = $conn->prepare("SELECT id, email, status, failed_attempts FROM users WHERE id = ? LIMIT 1");
if (!$userStmt) {
    $conn->close();
    eventify_redirect_superadmin_activate('error', 'Failed to validate user for activation.');
}
$userStmt->bind_param("i", $id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();
if (!$user) {
    $conn->close();
    eventify_redirect_superadmin_activate('error', 'User not found.');
}

$isLockedAccount = ((int)($user['failed_attempts'] ?? 0) > 0);
if ($isLockedAccount) {
    // Locked accounts require a recent completed reactivation OTP verification first.
    $otpStmt = $conn->prepare("
        SELECT id
        FROM account_email_otps
        WHERE purpose = 'reactivate'
          AND user_id = ?
          AND used_at IS NOT NULL
          AND used_at >= (NOW() - INTERVAL 30 MINUTE)
        ORDER BY used_at DESC, id DESC
        LIMIT 1
    ");
    if (!$otpStmt) {
        $conn->close();
        eventify_redirect_superadmin_activate('error', 'Cannot activate locked account: OTP validation unavailable.');
    }
    $otpStmt->bind_param("i", $id);
    $otpStmt->execute();
    $otpVerified = $otpStmt->get_result()->fetch_assoc();
    $otpStmt->close();

    if (!$otpVerified) {
        $conn->close();
        eventify_redirect_superadmin_activate('error', 'Cannot activate yet: recent reactivation OTP not verified.');
    }
}

$stmt = $conn->prepare("UPDATE users SET status = 'active', failed_attempts = 0 WHERE id = ? AND status <> 'active'");
if (!$stmt) {
    $conn->close();
    eventify_redirect_superadmin_activate('error', 'Failed to prepare activation.');
}
$stmt->bind_param("i", $id);
$stmt->execute();
$changed = $stmt->affected_rows > 0;
$stmt->close();
if (!$changed) {
    $conn->close();
    eventify_redirect_superadmin_activate('error', 'No changes made. User may already be active.');
}

log_activity(
    $conn,
    (int)($_SESSION['user_id'] ?? 0),
    (string)($_SESSION['role'] ?? ''),
    'user_activated',
    'user',
    $id,
    "Activated pending user ID {$id}"
);

$conn->close();
eventify_redirect_superadmin_activate('success', 'User activated.');
