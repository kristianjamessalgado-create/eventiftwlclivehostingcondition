<?php
require_once __DIR__ . '/../../config/session.php';

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/student_profile_fields.php';
require_once __DIR__ . '/../lib/activity_logger.php';
require_once __DIR__ . '/../lib/account_email_otp.php';

function eventify_redirect_verify_otp_ui(string $purpose, string $email, ?string $error = null, ?string $success = null, string $authModal = 'verify'): void
{
    $purpose = $purpose === 'reactivate' ? 'reactivate' : 'register';
    if ($authModal === 'verify') {
        eventify_set_verify_otp_flash($purpose, $email, $success, $error);
    }
    $params = [
        'auth_modal' => $authModal,
        'verify_purpose' => $purpose,
        'verify_email' => $email,
    ];
    if ($authModal === 'verify' && $error !== null && $error !== '') {
        $params['auth_error'] = $error;
    }
    if ($authModal === 'login' && $error !== null && $error !== '') {
        $params['auth_error'] = $error;
    }
    if ($authModal === 'login' && $success !== null && $success !== '') {
        $params['auth_success'] = $success;
    }
    header('Location: ' . BASE_URL . '/index.php?' . http_build_query($params));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    $failPurpose = (($_POST['purpose'] ?? 'register') === 'reactivate') ? 'reactivate' : 'register';
    $failEmail = trim(strtolower((string) ($_POST['email'] ?? '')));
    eventify_redirect_verify_otp_ui(
        $failPurpose,
        $failEmail,
        'Invalid or expired session. Refresh the page and try again.'
    );
}

$purpose = ($_POST['purpose'] ?? 'register') === 'reactivate' ? 'reactivate' : 'register';
$email = trim(strtolower($_POST['email'] ?? ''));
$otpCode = trim($_POST['otp_code'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/', $otpCode)) {
    eventify_redirect_verify_otp_ui($purpose, $email, 'Invalid verification input.');
}

if (!eventify_account_otp_table_ready($conn)) {
    eventify_redirect_verify_otp_ui($purpose, $email, 'OTP system unavailable.');
}

$stmt = $conn->prepare("SELECT id, user_id, otp_hash, payload_json, expires_at, attempt_count FROM account_email_otps WHERE purpose = ? AND email = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1");
$stmt->bind_param("ss", $purpose, $email);
$stmt->execute();
$otpRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$otpRow) {
    eventify_redirect_verify_otp_ui($purpose, $email, 'No active OTP found.');
}
if (strtotime((string)$otpRow['expires_at']) < time()) {
    eventify_redirect_verify_otp_ui($purpose, $email, 'OTP expired. Tap Resend OTP below to get a new code.');
}
if (!password_verify($otpCode, (string)$otpRow['otp_hash'])) {
    // Increment failed attempt counter and invalidate this OTP after too many attempts.
    $otpId = (int)($otpRow['id'] ?? 0);
    $attempts = (int)($otpRow['attempt_count'] ?? 0) + 1;
    $upFail = $conn->prepare("UPDATE account_email_otps SET attempt_count = ? WHERE id = ?");
    if ($upFail) {
        $upFail->bind_param("ii", $attempts, $otpId);
        $upFail->execute();
        $upFail->close();
    }
    if ($attempts >= 5) {
        $expireOtp = $conn->prepare("UPDATE account_email_otps SET used_at = NOW() WHERE id = ? AND used_at IS NULL");
        if ($expireOtp) {
            $expireOtp->bind_param("i", $otpId);
            $expireOtp->execute();
            $expireOtp->close();
        }
        eventify_redirect_verify_otp_ui($purpose, $email, 'Too many incorrect OTP attempts. Request a new OTP.');
    }
    eventify_redirect_verify_otp_ui($purpose, $email, 'Incorrect OTP.');
}

$conn->begin_transaction();
try {
    $mark = $conn->prepare("UPDATE account_email_otps SET used_at = NOW() WHERE id = ? AND used_at IS NULL");
    $otpId = (int)($otpRow['id'] ?? 0);
    $mark->bind_param("i", $otpId);
    $mark->execute();
    $mark->close();

    if ($purpose === 'register') {
        $payload = json_decode((string)($otpRow['payload_json'] ?? ''), true);
        if (!is_array($payload)) {
            throw new Exception('Missing registration payload');
        }
        $name = trim((string)($payload['name'] ?? ''));
        $passwordHash = (string)($payload['password_hash'] ?? '');
        $role = (string)($payload['role'] ?? '');
        $department = $payload['department'] ?? null;
        $studentCourse = trim((string)($payload['student_course'] ?? ''));
        $studentYearLevel = trim((string)($payload['student_year_level'] ?? ''));
        $userIdCode = (string)($payload['user_code'] ?? '');
        if ($name === '' || $passwordHash === '' || $userIdCode === '' || !in_array($role, ['student', 'organizer', 'multimedia', 'admin'], true)) {
            throw new Exception('Invalid registration payload');
        }
        if ($role === 'student') {
            if (!eventify_student_course_program_valid($studentCourse)) {
                throw new Exception('Course / program is required for student registration');
            }
            if ($studentYearLevel === '' || !array_key_exists($studentYearLevel, eventify_student_year_level_options())) {
                throw new Exception('Year level is required for student registration');
            }
            if (!eventify_student_course_matches_department($studentCourse, (string)$department)) {
                throw new Exception('Selected course / program does not match the selected department');
            }
            eventify_users_ensure_student_profile_fields($conn);
        } else {
            $studentCourse = '';
            $studentYearLevel = '';
        }

        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        $check->close();
        if ($exists) {
            throw new Exception('Email already registered');
        }

        // After OTP verification, all new accounts stay pending until super admin approval.
        $status = 'inactive';
        $ins = $conn->prepare("INSERT INTO users (user_id, name, email, password, role, department, student_course, student_year_level, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->bind_param("sssssssss", $userIdCode, $name, $email, $passwordHash, $role, $department, $studentCourse, $studentYearLevel, $status);
        $ins->execute();
        $newUserId = (int)$conn->insert_id;
        $ins->close();
        log_activity($conn, $newUserId, $role, 'register_email_verified', 'user', $newUserId, 'Completed registration via OTP email verification');

        // Notify all active super admins that a new verified account is awaiting approval.
        $saQ = $conn->query("SELECT id FROM users WHERE role = 'super_admin' AND status = 'active'");
        if ($saQ) {
            $notifTitle = 'New account pending approval';
            $notifMsg = 'Email-verified registration waiting approval: ' . $name . ' (' . $role . ')';
            $insNotif = $conn->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'account_pending_approval', ?, ?)");
            if ($insNotif) {
                while ($sa = $saQ->fetch_assoc()) {
                    $superId = (int)($sa['id'] ?? 0);
                    if ($superId > 0) {
                        $insNotif->bind_param("iss", $superId, $notifTitle, $notifMsg);
                        $insNotif->execute();
                    }
                }
                $insNotif->close();
            }
        }

        $success = "Email verified. Registration is now pending super admin approval.";
    } else {
        $userId = (int)($otpRow['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new Exception('Missing user for reactivation');
        }
        // Reactivation OTP immediately unlocks the account and resets lock attempts.
        $upd = $conn->prepare("UPDATE users SET status = 'active', failed_attempts = 0, must_change_password = 1 WHERE id = ?");
        $upd->bind_param("i", $userId);
        $upd->execute();
        $upd->close();
        // Load role/name for auto-login after OTP verification.
        $uStmt = $conn->prepare("SELECT id, role, name FROM users WHERE id = ? LIMIT 1");
        if (!$uStmt) {
            throw new Exception('Failed to load reactivated account');
        }
        $uStmt->bind_param("i", $userId);
        $uStmt->execute();
        $u = $uStmt->get_result()->fetch_assoc();
        $uStmt->close();
        if (!$u) {
            throw new Exception('Reactivated account not found');
        }

        $_SESSION['user_id'] = (int)($u['id'] ?? 0);
        $_SESSION['role'] = (string)($u['role'] ?? '');
        $_SESSION['name'] = (string)($u['name'] ?? '');
        session_regenerate_id(true);

        $role = (string)($u['role'] ?? '');
        $dashboardRedirect = BASE_URL . "/index.php";
        if ($role === 'super_admin') {
            $dashboardRedirect = BASE_URL . "/backend/super_admin/dashboardsuperadmin.php";
        } elseif ($role === 'admin') {
            $dashboardRedirect = BASE_URL . "/backend/admin/dashboard.php";
        } elseif ($role === 'organizer') {
            $dashboardRedirect = BASE_URL . "/backend/auth/dashboardorganizer.php";
        } elseif ($role === 'student') {
            $dashboardRedirect = BASE_URL . "/backend/auth/dashboard_student.php";
        } elseif ($role === 'multimedia') {
            $dashboardRedirect = BASE_URL . "/backend/auth/dashboard_multimedia.php";
        }
        $redirect = BASE_URL . "/views/change_password.php?from=reactivation&next=" . urlencode($dashboardRedirect);

        log_activity($conn, $userId, $role ?: 'user', 'account_reactivated_by_otp', 'user', $userId, 'User completed reactivation OTP verification and was auto-logged in');
    }

    $conn->commit();
    if ($purpose === 'register') {
        eventify_redirect_verify_otp_ui('register', $email, null, $success, 'login');
    }
    if (isset($redirect)) {
        header('Location: ' . $redirect);
        exit();
    }
    eventify_redirect_verify_otp_ui($purpose, $email, null, $success ?? '', 'login');
    exit();
} catch (Throwable $e) {
    $conn->rollback();
    eventify_redirect_verify_otp_ui($purpose, $email, $e->getMessage());
}
