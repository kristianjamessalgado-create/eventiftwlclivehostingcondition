<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
include __DIR__ . '/../../backend/lib/activity_logger.php';
include __DIR__ . '/../../backend/lib/account_email_otp.php';

function eventify_redirect_superadmin_reactivate(string $type, string $message): void
{
    $openModal = trim((string)($_POST['open_modal'] ?? 'users'));
    $q = $type . '=' . urlencode($message) . '&open_modal=' . urlencode($openModal);
    header("Location: " . BASE_URL . "/backend/super_admin/dashboardsuperadmin.php?" . $q);
    exit();
}

// Only super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: " . BASE_URL . "/views/login.php?error=Access denied");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    eventify_redirect_superadmin_reactivate('error', 'Invalid request.');
}

// Validate ID
if (!isset($_POST['id']) || !ctype_digit((string)$_POST['id'])) {
    eventify_redirect_superadmin_reactivate('error', 'Invalid user ID');
}

$id = (int)$_POST['id'];

$uStmt = $conn->prepare("SELECT id, email, name, status FROM users WHERE id = ? LIMIT 1");
$uStmt->bind_param("i", $id);
$uStmt->execute();
$user = $uStmt->get_result()->fetch_assoc();
$uStmt->close();
if (!$user) {
    $conn->close();
    eventify_redirect_superadmin_reactivate('error', 'User not found.');
}

if (($user['status'] ?? '') !== 'inactive') {
    $conn->close();
    eventify_redirect_superadmin_reactivate('error', 'User is not inactive; reactivation OTP is not required.');
}

// Reactivation OTP is intended for locked accounts only (any non-zero failed attempts while inactive).
$lockStmt = $conn->prepare("SELECT failed_attempts FROM users WHERE id = ? LIMIT 1");
if ($lockStmt) {
    $lockStmt->bind_param("i", $id);
    $lockStmt->execute();
    $lockRow = $lockStmt->get_result()->fetch_assoc();
    $lockStmt->close();
    $failedAttempts = (int)($lockRow['failed_attempts'] ?? 0);
    if ($failedAttempts <= 0) {
        $conn->close();
        eventify_redirect_superadmin_reactivate('error', 'Cannot send reactivation OTP: account is not locked.');
    }
}

$email = trim((string)($user['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $conn->close();
    eventify_redirect_superadmin_reactivate('error', 'Cannot reactivate: invalid email on account.');
}

$otpCreate = eventify_create_email_otp($conn, 'reactivate', $email, $id, null, 10);
if (empty($otpCreate['ok'])) {
    $conn->close();
    eventify_redirect_superadmin_reactivate('error', 'Reactivation OTP failed to generate.');
}
$otpSend = eventify_send_account_otp_email($email, 'reactivate', (string)$otpCreate['code']);
if (empty($otpSend['ok'])) {
    $conn->close();
    eventify_redirect_superadmin_reactivate('error', "Reactivation OTP email failed: " . ($otpSend['error'] ?? 'unknown error'));
}

// Log activity
$actorId   = $_SESSION['user_id'] ?? null;
$actorRole = $_SESSION['role'] ?? null;
$details   = "Sent reactivation OTP to user ID {$id}";
log_activity($conn, $actorId, $actorRole, 'user_reactivated', 'user', (int)$id, $details);

$conn->close();

eventify_redirect_superadmin_reactivate('success', "Reactivation OTP sent to {$email}. User must verify OTP to activate account.");
