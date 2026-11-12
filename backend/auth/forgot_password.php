<?php
/**
 * Forgot password (landing page modal).
 *   action=request : email a 6-digit reset code
 *   action=reset   : check the code and save the new password
 */
require_once __DIR__ . '/../../config/session.php';

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/activity_logger.php';
require_once __DIR__ . '/../lib/account_email_otp.php';

function eventify_forgot_redirect(string $modal, string $email, ?string $error = null, ?string $success = null): void
{
    $params = ['auth_modal' => $modal];
    if ($modal === 'login') {
        if ($email !== '') {
            $params['reactivate_email'] = $email; // pre-fills the sign-in email field
        }
    } elseif ($email !== '') {
        $params['verify_email'] = $email;
    }
    if ($error !== null && $error !== '') {
        $params['auth_error'] = $error;
    }
    if ($success !== null && $success !== '') {
        $params['auth_success'] = $success;
    }
    header('Location: ' . BASE_URL . '/index.php?' . http_build_query($params));
    exit();
}

/** Accounts that may reset: active ones, and ones locked by wrong-password attempts (not pending approval). */
function eventify_forgot_find_user(mysqli $conn, string $email): ?array
{
    $stmt = $conn->prepare(
        "SELECT id, role, status, COALESCE(failed_attempts, 0) AS failed_attempts
         FROM users WHERE LOWER(TRIM(email)) = ? LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    $isActive = ($row['status'] ?? '') === 'active';
    $isLocked = !$isActive && (int) $row['failed_attempts'] > 0;
    return ($isActive || $isLocked) ? $row : null;
}

$action = ($_POST['action'] ?? '') === 'reset' ? 'reset' : 'request';
$email = eventify_normalize_email((string) ($_POST['email'] ?? ''));
$failModal = $action === 'reset' ? 'reset' : 'forgot';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !csrf_validate()) {
    eventify_forgot_redirect($failModal, $email, 'Invalid or expired session. Refresh the page and try again.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    eventify_forgot_redirect($failModal, $email, 'Enter a valid email address.');
}
if (!eventify_account_otp_table_ready($conn)) {
    eventify_forgot_redirect($failModal, $email, 'Password reset is unavailable right now. Please try again later.');
}

// Same message whether or not the email exists, so the form does not reveal registered accounts.
$sentMessage = 'If an account exists for that email, we sent a 6-digit code. Check your inbox and Spam/Junk folder.';

if ($action === 'request') {
    $user = eventify_forgot_find_user($conn, $email);
    if ($user === null) {
        eventify_forgot_redirect('reset', $email, null, $sentMessage);
    }

    $otp = eventify_create_email_otp($conn, 'reset', $email, (int) $user['id'], null, 10);
    if (empty($otp['ok'])) {
        eventify_forgot_redirect('forgot', $email, (string) ($otp['error'] ?? 'Could not create a reset code.'));
    }

    $send = eventify_send_account_otp_email($email, 'reset', (string) $otp['code']);
    if (empty($send['ok'])) {
        eventify_forgot_redirect('forgot', $email, 'Could not send the reset email. Please try again later.');
    }

    log_activity($conn, (int) $user['id'], (string) $user['role'], 'password_reset_requested', 'user', (int) $user['id'], 'Forgot-password code emailed');
    eventify_forgot_redirect('reset', $email, null, $sentMessage);
}

// action === 'reset'
$otpCode = trim((string) ($_POST['otp_code'] ?? ''));
$new = (string) ($_POST['new_password'] ?? '');
$confirm = (string) ($_POST['confirm_password'] ?? '');

if (!preg_match('/^\d{6}$/', $otpCode)) {
    eventify_forgot_redirect('reset', $email, 'Enter the 6-digit code from your email.');
}
if ($new !== $confirm) {
    eventify_forgot_redirect('reset', $email, 'New passwords do not match.');
}
if (!preg_match('/[A-Z]/', $new) || !preg_match('/[\W_]/', $new) || strlen($new) < 8) {
    eventify_forgot_redirect('reset', $email, 'Password must contain at least 1 uppercase, 1 special character, and 8 characters.');
}

$purpose = 'reset';
$stmt = $conn->prepare(
    'SELECT id, user_id, otp_hash, expires_at, attempt_count FROM account_email_otps
     WHERE purpose = ? AND email = ? AND used_at IS NULL
     ORDER BY id DESC LIMIT 1'
);
$stmt->bind_param('ss', $purpose, $email);
$stmt->execute();
$otpRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$otpRow) {
    eventify_forgot_redirect('reset', $email, 'No active reset code for this email. Request a new code.');
}
$otpId = (int) $otpRow['id'];
if (strtotime((string) $otpRow['expires_at']) < time()) {
    eventify_forgot_redirect('reset', $email, 'Code expired. Tap "Send a new code" to get another one.');
}
if (!password_verify($otpCode, (string) $otpRow['otp_hash'])) {
    $attempts = (int) ($otpRow['attempt_count'] ?? 0) + 1;
    $up = $conn->prepare('UPDATE account_email_otps SET attempt_count = ? WHERE id = ?');
    if ($up) {
        $up->bind_param('ii', $attempts, $otpId);
        $up->execute();
        $up->close();
    }
    if ($attempts >= 5) {
        $kill = $conn->prepare('UPDATE account_email_otps SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
        if ($kill) {
            $kill->bind_param('i', $otpId);
            $kill->execute();
            $kill->close();
        }
        eventify_forgot_redirect('reset', $email, 'Too many incorrect codes. Request a new code.');
    }
    eventify_forgot_redirect('reset', $email, 'Incorrect code.');
}

$user = eventify_forgot_find_user($conn, $email);
if ($user === null || (int) $user['id'] !== (int) ($otpRow['user_id'] ?? 0)) {
    eventify_forgot_redirect('forgot', $email, 'This account cannot be reset. Contact your super admin.');
}
$userId = (int) $user['id'];

$conn->begin_transaction();
try {
    $mark = $conn->prepare('UPDATE account_email_otps SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
    $mark->bind_param('i', $otpId);
    $mark->execute();
    $claimed = $mark->affected_rows === 1;
    $mark->close();
    if (!$claimed) {
        throw new Exception('This code was already used. Request a new code.');
    }

    // Proving email ownership also clears a wrong-password lock.
    $hash = password_hash($new, PASSWORD_DEFAULT);
    $upd = $conn->prepare(
        "UPDATE users SET password = ?, must_change_password = 0, failed_attempts = 0, status = 'active' WHERE id = ?"
    );
    $upd->bind_param('si', $hash, $userId);
    $upd->execute();
    $upd->close();

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    eventify_forgot_redirect('reset', $email, $e->getMessage());
}

log_activity($conn, $userId, (string) $user['role'], 'password_reset_completed', 'user', $userId, 'Password reset with emailed code');
eventify_forgot_redirect('login', $email, null, 'Password updated. Sign in with your new password.');
