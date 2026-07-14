<?php

require_once __DIR__ . '/email_sender.php';

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
    $email = trim(strtolower($email));
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
    $email = trim(strtolower($email));
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
    $purposeText = $purpose === 'reactivate' ? 'account reactivation' : 'account registration';
    $subject = '[EVENTIFY] Email verification OTP';
    $body = "Your OTP for {$purposeText} is: {$code}\n\nThis code expires in 10 minutes.\nIf you did not request this, ignore this email.";
    return eventify_send_email($email, $subject, $body);
}

/** One-time flash after register/resend/verify errors — avoids stale "OTP sent" on page refresh. */
function eventify_set_verify_otp_flash(string $purpose, string $email, ?string $success = null, ?string $error = null): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['eventify_verify_otp_flash'] = [
        'purpose' => $purpose === 'reactivate' ? 'reactivate' : 'register',
        'email' => strtolower(trim($email)),
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
    $email = strtolower(trim($email));
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
