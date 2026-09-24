<?php

require_once __DIR__ . '/email_sender.php';

function eventify_normalize_email(string $email): string
{
    $email = trim($email);
    if ($email === '') {
        return '';
    }
    // Strip zero-width / BOM characters sometimes pasted from mobile keyboards.
    $email = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00A0}]/u', '', $email) ?? $email;
    return strtolower($email);
}

function eventify_account_otp_table_ready(mysqli $conn): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    $sql = "
        CREATE TABLE IF NOT EXISTS account_email_otps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            purpose ENUM('register','reactivate') NOT NULL,
            email VARCHAR(120) NOT NULL,
            user_id INT NULL,
            otp_hash VARCHAR(255) NOT NULL,
            payload_json TEXT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email_purpose (email, purpose),
            INDEX idx_user_purpose (user_id, purpose),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    try {
        $ready = (bool) $conn->query($sql);
    } catch (Throwable $e) {
        $ready = false;
    }
    if (!$ready) {
        return false;
    }

    // Non-blocking backward-compatible migrations.
    try {
        $colOtp = $conn->query("SHOW COLUMNS FROM account_email_otps LIKE 'attempt_count'");
        $hasOtpAttempt = (bool)($colOtp && $colOtp->num_rows > 0);
        if (!$hasOtpAttempt) {
            $conn->query("ALTER TABLE account_email_otps ADD COLUMN attempt_count INT NOT NULL DEFAULT 0 AFTER used_at");
        }
    } catch (Throwable $e) {
        // Keep OTP available even if migration query fails.
    }

    try {
        $colUser = $conn->query("SHOW COLUMNS FROM users LIKE 'must_change_password'");
        $hasMustChange = (bool)($colUser && $colUser->num_rows > 0);
        if (!$hasMustChange) {
            $conn->query("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER failed_attempts");
        }
    } catch (Throwable $e) {
        // Keep OTP available even if migration query fails.
    }

    return $ready;
}

function eventify_can_issue_email_otp(mysqli $conn, string $purpose, string $email): array
{
    $purpose = $purpose === 'reactivate' ? 'reactivate' : 'register';
    $email = eventify_normalize_email($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid email'];
    }

    // Cooldown: 60 seconds between OTP sends.
    $cool = $conn->prepare("SELECT created_at FROM account_email_otps WHERE purpose = ? AND email = ? ORDER BY id DESC LIMIT 1");
    if ($cool) {
        $cool->bind_param("ss", $purpose, $email);
        $cool->execute();
        $last = $cool->get_result()->fetch_assoc();
        $cool->close();
        if ($last && !empty($last['created_at'])) {
            $delta = time() - strtotime((string)$last['created_at']);
            if ($delta < 60) {
                return ['ok' => false, 'error' => 'Please wait before requesting another OTP.'];
            }
        }
    }

    // Burst limit: max 5 requests within 1 hour.
    $cnt = $conn->prepare("SELECT COUNT(*) AS c FROM account_email_otps WHERE purpose = ? AND email = ? AND created_at >= (NOW() - INTERVAL 1 HOUR)");
    if ($cnt) {
        $cnt->bind_param("ss", $purpose, $email);
        $cnt->execute();
        $row = $cnt->get_result()->fetch_assoc();
        $cnt->close();
        $count = (int)($row['c'] ?? 0);
        if ($count >= 5) {
            return ['ok' => false, 'error' => 'OTP request limit reached. Try again after 1 hour.'];
        }
    }

    return ['ok' => true];
}

function eventify_generate_email_otp_code(): string
{
    return (string) random_int(100000, 999999);
}

function eventify_create_email_otp(mysqli $conn, string $purpose, string $email, ?int $userId, ?array $payload, int $validMinutes = 10): array
{
    if (!eventify_account_otp_table_ready($conn)) {
        return ['ok' => false, 'error' => 'OTP table unavailable'];
    }

    $purpose = $purpose === 'reactivate' ? 'reactivate' : 'register';
    $email = eventify_normalize_email($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid email'];
    }

    $allow = eventify_can_issue_email_otp($conn, $purpose, $email);
    if (empty($allow['ok'])) {
        return ['ok' => false, 'error' => (string)($allow['error'] ?? 'OTP rate-limited')];
    }

    $clear = $conn->prepare("UPDATE account_email_otps SET used_at = NOW() WHERE purpose = ? AND email = ? AND used_at IS NULL");
    if ($clear) {
        $clear->bind_param("ss", $purpose, $email);
        $clear->execute();
        $clear->close();
    }

    $code = eventify_generate_email_otp_code();
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', time() + ($validMinutes * 60));
    $payloadJson = $payload ? json_encode($payload) : null;

    if ($userId === null || $userId < 1) {
        $stmt = $conn->prepare("INSERT INTO account_email_otps (purpose, email, user_id, otp_hash, payload_json, expires_at) VALUES (?, ?, NULL, ?, ?, ?)");
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Failed to create OTP'];
        }
        $stmt->bind_param('sssss', $purpose, $email, $hash, $payloadJson, $expiresAt);
    } else {
        $stmt = $conn->prepare("INSERT INTO account_email_otps (purpose, email, user_id, otp_hash, payload_json, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Failed to create OTP'];
        }
        $stmt->bind_param('ssisss', $purpose, $email, $userId, $hash, $payloadJson, $expiresAt);
    }
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        return ['ok' => false, 'error' => 'Failed to save OTP'];
    }

    return ['ok' => true, 'code' => $code, 'expires_at' => $expiresAt];
}

function eventify_send_account_otp_email(string $email, string $purpose, string $code): array
{
    $purposeText = $purpose === 'reactivate' ? 'reactivate your EVENTIFY account' : 'finish creating your EVENTIFY account';
    $code = preg_replace('/\D+/', '', $code) ?? $code;
    $subject = 'Your EVENTIFY verification code is ' . $code;
    $body = "Hi,\n\n"
        . "Use this code to {$purposeText}:\n\n"
        . "{$code}\n\n"
        . "This code expires in 10 minutes.\n"
        . "If you did not request this, you can ignore this email.\n";
    $safeCode = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"></head>'
        . '<body style="margin:0;padding:24px;background:#f4f6f8;color:#111;'
        . 'font-family:Arial,Helvetica,sans-serif;line-height:1.5;">'
        . '<div style="max-width:520px;margin:0 auto;background:#fff;padding:24px;'
        . 'border:1px solid #e5e7eb;border-radius:8px;">'
        . '<p style="margin:0 0 12px;">Hi,</p>'
        . '<p style="margin:0 0 16px;">Use this code to ' . htmlspecialchars($purposeText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ':</p>'
        . '<p style="margin:0 0 16px;font-size:28px;letter-spacing:6px;font-weight:700;">' . $safeCode . '</p>'
        . '<p style="margin:0;color:#4b5563;">This code expires in 10 minutes. If you did not request this, you can ignore this email.</p>'
        . '</div></body></html>';

    return eventify_send_email($email, $subject, $body, $html);
}

/**
 * Notify a user that their account was approved / activated and they can log in.
 *
 * @return array{ok:bool, error?:string, via?:string}
 */
function eventify_send_account_approved_email(string $email, string $name = ''): array
{
    $email = trim($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid email'];
    }

    $name = trim($name);
    $greeting = $name !== '' ? "Hi {$name}," : 'Hi,';
    $loginUrl = (defined('BASE_URL') ? rtrim((string) BASE_URL, '/') : '') . '/views/login.php';
    if ($loginUrl === '/views/login.php') {
        $loginUrl = 'the EVENTIFY login page';
    }

    $subject = 'Your EVENTIFY account is ready';
    $body = "{$greeting}\n\n"
        . "Your EVENTIFY account has been approved and is now active.\n\n"
        . "You can sign in here:\n{$loginUrl}\n\n"
        . "If you did not register for EVENTIFY, please ignore this email.\n";

    return eventify_send_email($email, $subject, $body);
}

function eventify_register_otp_required(): bool
{
    if (defined('EVENTIFY_REGISTER_OTP_REQUIRED')) {
        return (bool) EVENTIFY_REGISTER_OTP_REQUIRED;
    }
    $raw = strtolower(trim((string) (function_exists('eventify_env') ? (eventify_env('EVENTIFY_REGISTER_OTP_REQUIRED', 'true') ?? 'true') : 'true')));
    return in_array($raw, ['1', 'true', 'yes', 'on'], true);
}

function eventify_discard_unused_register_otps(mysqli $conn, string $email): void
{
    if (!eventify_account_otp_table_ready($conn)) {
        return;
    }
    $email = eventify_normalize_email($email);
    $purpose = 'register';
    $st = $conn->prepare('UPDATE account_email_otps SET used_at = NOW() WHERE purpose = ? AND email = ? AND used_at IS NULL');
    if ($st) {
        $st->bind_param('ss', $purpose, $email);
        $st->execute();
        $st->close();
    }
}

function eventify_notify_superadmins_pending_account(mysqli $conn, string $name, string $role): void
{
    $saQ = @$conn->query("SELECT id FROM users WHERE role = 'super_admin' AND status = 'active'");
    if (!$saQ) {
        return;
    }
    $notifTitle = 'New account pending approval';
    $notifMsg = 'New registration waiting approval: ' . $name . ' (' . $role . ')';
    $insNotif = $conn->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'account_pending_approval', ?, ?)");
    if (!$insNotif) {
        return;
    }
    while ($sa = $saQ->fetch_assoc()) {
        $superId = (int) ($sa['id'] ?? 0);
        if ($superId > 0) {
            $insNotif->bind_param('iss', $superId, $notifTitle, $notifMsg);
            $insNotif->execute();
        }
    }
    $insNotif->close();
}

/**
 * Create a newly registered user as inactive until super admin activates it.
 *
 * @param array<string, mixed> $payload
 * @return array{ok:bool, user_id?:int, already?:bool, error?:string}
 */
function eventify_create_pending_registered_user(mysqli $conn, string $email, array $payload): array
{
    $email = eventify_normalize_email($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Please enter a valid email address.'];
    }

    $profileFields = dirname(__DIR__, 2) . '/config/student_profile_fields.php';
    if (is_file($profileFields)) {
        require_once $profileFields;
    }

    $name = trim((string) ($payload['name'] ?? ''));
    $passwordHash = (string) ($payload['password_hash'] ?? '');
    $role = (string) ($payload['role'] ?? '');
    $department = $payload['department'] ?? null;
    $studentCourse = trim((string) ($payload['student_course'] ?? ''));
    $studentYearLevel = trim((string) ($payload['student_year_level'] ?? ''));
    $userIdCode = (string) ($payload['user_code'] ?? '');

    if ($name === '' || $passwordHash === '' || $userIdCode === '' || !in_array($role, ['student', 'organizer', 'multimedia', 'admin'], true)) {
        return ['ok' => false, 'error' => 'Invalid registration details.'];
    }
    if ($role === 'student') {
        if (!function_exists('eventify_student_course_program_valid') || !eventify_student_course_program_valid($studentCourse)) {
            return ['ok' => false, 'error' => 'Course / program is required for students.'];
        }
        if ($studentYearLevel === '' || !function_exists('eventify_student_year_level_options') || !array_key_exists($studentYearLevel, eventify_student_year_level_options())) {
            return ['ok' => false, 'error' => 'Year level is required for students.'];
        }
        if (!function_exists('eventify_student_course_matches_department') || !eventify_student_course_matches_department($studentCourse, (string) $department)) {
            return ['ok' => false, 'error' => 'Selected course / program does not match the selected department.'];
        }
        if (function_exists('eventify_users_ensure_student_profile_fields')) {
            eventify_users_ensure_student_profile_fields($conn);
        }
    } else {
        $studentCourse = '';
        $studentYearLevel = '';
    }

    $check = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    if (!$check) {
        return ['ok' => false, 'error' => 'Registration failed. Please try again.'];
    }
    $check->bind_param('s', $email);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();
    if ($exists) {
        return ['ok' => false, 'already' => true, 'error' => 'Email already registered.'];
    }

    $status = 'inactive';
    $ins = $conn->prepare('INSERT INTO users (user_id, name, email, password, role, department, student_course, student_year_level, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$ins) {
        return ['ok' => false, 'error' => 'Registration failed. Please try again.'];
    }
    $ins->bind_param('sssssssss', $userIdCode, $name, $email, $passwordHash, $role, $department, $studentCourse, $studentYearLevel, $status);
    $ok = $ins->execute();
    $newUserId = (int) $conn->insert_id;
    $ins->close();
    if (!$ok || $newUserId < 1) {
        return ['ok' => false, 'error' => 'Registration failed. Please try again.'];
    }

    if (function_exists('log_activity')) {
        log_activity(
            $conn,
            $newUserId,
            $role,
            'register_pending_approval',
            'user',
            $newUserId,
            'Account created and waiting for super admin activation'
        );
    }
    eventify_notify_superadmins_pending_account($conn, $name, $role);

    return ['ok' => true, 'user_id' => $newUserId];
}

/**
 * If a student already started registration (OTP pending), create the inactive account without the code.
 *
 * @return array{ok:bool, already?:bool, error?:string}
 */
function eventify_finalize_pending_register_without_otp(mysqli $conn, string $email): array
{
    $email = eventify_normalize_email($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid email'];
    }

    $exists = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    if ($exists) {
        $exists->bind_param('s', $email);
        $exists->execute();
        $row = $exists->get_result()->fetch_assoc();
        $exists->close();
        if ($row) {
            eventify_discard_unused_register_otps($conn, $email);
            return ['ok' => true, 'already' => true];
        }
    }

    if (!eventify_account_otp_table_ready($conn)) {
        return ['ok' => false, 'error' => 'No pending registration found.'];
    }

    $purpose = 'register';
    $st = $conn->prepare(
        'SELECT id, payload_json FROM account_email_otps
         WHERE purpose = ? AND email = ? AND used_at IS NULL
         ORDER BY id DESC LIMIT 1'
    );
    if (!$st) {
        return ['ok' => false, 'error' => 'No pending registration found.'];
    }
    $st->bind_param('ss', $purpose, $email);
    $st->execute();
    $otpRow = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$otpRow) {
        return ['ok' => false, 'error' => 'No pending registration found.'];
    }

    $payload = json_decode((string) ($otpRow['payload_json'] ?? ''), true);
    if (!is_array($payload)) {
        return ['ok' => false, 'error' => 'No pending registration found.'];
    }

    $created = eventify_create_pending_registered_user($conn, $email, $payload);
    if (!empty($created['ok']) || !empty($created['already'])) {
        eventify_discard_unused_register_otps($conn, $email);
        return ['ok' => true, 'already' => !empty($created['already'])];
    }

    return ['ok' => false, 'error' => (string) ($created['error'] ?? 'Could not finish registration.')];
}

/** One-time flash after register/resend/verify errors — avoids stale "OTP sent" on page refresh. */
function eventify_set_verify_otp_flash(string $purpose, string $email, ?string $success = null, ?string $error = null): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['eventify_verify_otp_flash'] = [
        'purpose' => $purpose === 'reactivate' ? 'reactivate' : 'register',
        'email' => eventify_normalize_email($email),
        'success' => $success !== null && $success !== '' ? $success : null,
        'error' => $error !== null && $error !== '' ? $error : null,
    ];
}

function eventify_consume_verify_otp_flash(string $purpose, string $email): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $purpose = $purpose === 'reactivate' ? 'reactivate' : 'register';
    $email = eventify_normalize_email($email);
    $flash = $_SESSION['eventify_verify_otp_flash'] ?? null;
    unset($_SESSION['eventify_verify_otp_flash']);
    if (!is_array($flash)) {
        return ['success' => '', 'error' => ''];
    }
    if (($flash['purpose'] ?? '') !== $purpose || ($flash['email'] ?? '') !== $email) {
        return ['success' => '', 'error' => ''];
    }
    return [
        'success' => (string) ($flash['success'] ?? ''),
        'error' => (string) ($flash['error'] ?? ''),
    ];
}
