<?php
require_once __DIR__ . '/../../config/session.php';

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/account_email_otp.php';

function eventify_resend_otp_redirect(string $purpose, string $email, ?string $error = null, ?string $success = null): void
{
    $purpose = $purpose === 'reactivate' ? 'reactivate' : 'register';
    eventify_set_verify_otp_flash($purpose, $email, $success, $error);
    $params = [
        'auth_modal' => 'verify',
        'verify_purpose' => $purpose,
        'verify_email' => $email,
    ];
    if ($error !== null && $error !== '') {
        $params['auth_error'] = $error;
    }
    header('Location: ' . BASE_URL . '/index.php?' . http_build_query($params));
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !csrf_validate()) {
    $failPurpose = (($_POST['purpose'] ?? 'register') === 'reactivate') ? 'reactivate' : 'register';
    $failEmail = trim(strtolower((string) ($_POST['email'] ?? '')));
    eventify_resend_otp_redirect($failPurpose, $failEmail, 'Invalid or expired session. Refresh the page and try again.');
}

$purpose = ($_POST['purpose'] ?? 'register') === 'reactivate' ? 'reactivate' : 'register';
$email = trim(strtolower((string) ($_POST['email'] ?? '')));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    eventify_resend_otp_redirect($purpose, $email, 'Enter a valid email address.');
}

if (!eventify_account_otp_table_ready($conn)) {
    eventify_resend_otp_redirect($purpose, $email, 'OTP system unavailable.');
}

$payload = null;
$userId = null;
$stmt = $conn->prepare(
    'SELECT user_id, payload_json FROM account_email_otps
     WHERE purpose = ? AND email = ? AND used_at IS NULL
     ORDER BY id DESC LIMIT 1'
);
if ($stmt) {
    $stmt->bind_param('ss', $purpose, $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $userId = isset($row['user_id']) ? (int) $row['user_id'] : null;
        $decoded = json_decode((string) ($row['payload_json'] ?? ''), true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }
}

if ($purpose === 'register' && $payload === null) {
    eventify_resend_otp_redirect($purpose, $email, 'No pending registration found for this email. Please register again.');
}

$otpCreate = eventify_create_email_otp($conn, $purpose, $email, $userId, $payload, 10);
if (empty($otpCreate['ok'])) {
    eventify_resend_otp_redirect($purpose, $email, (string) ($otpCreate['error'] ?? 'Could not create a new OTP.'));
}

$sendRes = eventify_send_account_otp_email($email, $purpose, (string) $otpCreate['code']);
if (empty($sendRes['ok'])) {
    eventify_resend_otp_redirect(
        $purpose,
        $email,
        'OTP email failed: ' . ($sendRes['error'] ?? 'unknown error') . ' Configure config/smtp.local.php and run tools/test_smtp.php.'
    );
}

$via = (string) ($sendRes['via'] ?? 'smtp');
$success = $via === 'mail'
    ? 'A new OTP was requested. If nothing arrives in a few minutes, check Spam/Junk — XAMPP mail() often does not deliver real emails. Set up SMTP in config/smtp.local.php.'
    : 'A new OTP was sent to your email. Check your inbox and Spam/Junk folder.';

eventify_resend_otp_redirect($purpose, $email, null, $success);
