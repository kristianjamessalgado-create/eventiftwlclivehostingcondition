<?php
require_once __DIR__ . '/../../config/session.php';
// In production, keep errors out of the browser (log them instead)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Include DB, config, CSRF, and activity logger
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php'; // For BASE_URL
include __DIR__ . '/../../config/csrf.php';
include __DIR__ . '/../../config/student_profile_fields.php';
include __DIR__ . '/../lib/activity_logger.php';
include __DIR__ . '/../lib/account_email_otp.php';

// Allowed departments for students (must match form options)
$allowedDepartments = [
    'High school department',
    'College of Communication, Information and Technology',
    'College of Accountancy and Business',
    'School of Law and Political Science',
    'College of Education',
    'College of Nursing and Allied health sciences',
    'College of Hospitality Management',
];

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!csrf_validate()) {
        if ($action === 'register') {
            header("Location: " . BASE_URL . "/index.php?auth_modal=register&auth_error=" . urlencode("Invalid request. Please try again."));
        } else {
            header("Location: " . BASE_URL . "/views/login.php?error=" . urlencode("Invalid request. Please try again.") . "&form=login");
        }
        exit();
    }

    if ($action === 'register') {
        // --- REGISTRATION ---
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $name = trim($firstName . ' ' . $lastName);
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role     = $_POST['role'] ?? '';
        $department = isset($_POST['department']) ? trim($_POST['department']) : null;
        $studentCourse = trim((string)($_POST['student_course'] ?? ''));
        $studentYearLevel = trim((string)($_POST['student_year_level'] ?? ''));
        $acceptLegal = (string)($_POST['accept_legal'] ?? '');

        $error = '';

        if ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif ($acceptLegal !== '1') {
            $error = "You must agree to the Data Privacy Notice and Terms and Conditions.";
        } elseif (!preg_match('/[A-Z]/', $password)
            || !preg_match('/[\W_]/', $password)
            || strlen($password) < 8) {
            $error = "Password must contain at least 1 uppercase letter, 1 special character, and be at least 8 characters long.";
        } elseif (!in_array($role, ['student', 'organizer', 'multimedia', 'admin'])) {
            $error = "Invalid role selected.";
        } elseif ($role === 'student' && empty($department)) {
            $error = "Department is required for students.";
        } elseif ($role === 'student' && !in_array($department, $allowedDepartments, true)) {
            $error = "Invalid department selected.";
        } elseif ($role === 'student' && !eventify_student_course_program_valid($studentCourse)) {
            $error = "Course / program is required for students.";
        } elseif ($role === 'student' && ($studentYearLevel === '' || !array_key_exists($studentYearLevel, eventify_student_year_level_options()))) {
            $error = "Year level is required for students.";
        } elseif ($role === 'student' && !eventify_student_course_matches_department($studentCourse, (string)$department)) {
            $error = "Selected course / program does not match the selected department.";
        } elseif ($firstName === '' || strlen($firstName) > 50) {
            $error = "First name is required (max 50 characters).";
        } elseif ($lastName === '' || strlen($lastName) > 50) {
            $error = "Last name is required (max 50 characters).";
        } elseif (strlen($name) < 1 || strlen($name) > 100) {
            $error = "Name must be between 1 and 100 characters.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $error = "Email already registered.";
            } else {
                // Use bcrypt for password hashing (secure, salted)
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                if ($role === 'multimedia') {
                    $prefix = 'MUL';
                } elseif ($role === 'super_admin') {
                    $prefix = 'SA';
                } elseif ($role === 'admin') {
                    $prefix = 'ADM';
                } else {
                    $prefix = strtoupper(substr($role, 0, 3));
                }
                $user_id = $prefix . '-' . rand(100, 999);
                $otpCreate = eventify_create_email_otp(
                    $conn,
                    'register',
                    $email,
                    null,
                    [
                        'name' => $name,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'password_hash' => $hashed_password,
                        'role' => $role,
                        'department' => $department,
                        'student_course' => ($role === 'student' ? $studentCourse : ''),
                        'student_year_level' => ($role === 'student' ? $studentYearLevel : ''),
                        'user_code' => $user_id,
                    ],
                    10
                );
                if (empty($otpCreate['ok'])) {
                    $error = "Registration failed. OTP could not be generated.";
                } else {
                    $sendRes = eventify_send_account_otp_email($email, 'register', (string)$otpCreate['code']);
                    if (empty($sendRes['ok'])) {
                        $error = "Registration OTP email failed: " . ($sendRes['error'] ?? 'unknown error') . " Set up config/smtp.local.php (see config/smtp.local.php.example).";
                    } else {
                        $via = (string) ($sendRes['via'] ?? 'smtp');
                        if ($via === 'mail') {
                            $success = 'Registration started. If you do not receive an OTP email within a few minutes, check Spam/Junk or configure SMTP (config/smtp.local.php). You can also tap Resend OTP below.';
                        } else {
                            $success = 'Registration OTP sent to your email. Check your inbox and Spam/Junk folder, then enter the 6-digit code below.';
                        }
                        eventify_set_verify_otp_flash('register', $email, $success, null);
                        $verifyUrl = BASE_URL . '/index.php?' . http_build_query([
                            'auth_modal' => 'verify',
                            'verify_purpose' => 'register',
                            'verify_email' => $email,
                        ]);
                        header('Location: ' . $verifyUrl);
                        exit();
                    }
                }
            }

            $stmt->close();
        }

        if (!empty($error)) {
            header("Location: " . BASE_URL . "/index.php?auth_modal=register&auth_error=" . urlencode($error));
            exit();
        }

    } elseif ($action === 'login') {

        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {
            $error = "Invalid email or password";
        } else {
            $user = $result->fetch_assoc();

            if ($user['status'] !== 'active') {
                $failedAttempts = (int) ($user['failed_attempts'] ?? 0);
                if ($failedAttempts > 0) {
                    // Locked account: OTP reactivation flow is allowed.
                    $redirect = BASE_URL . "/views/login.php?error=" . urlencode("Account is locked. Request OTP reactivation below.") . "&form=login&reactivate_email=" . urlencode($email);
                    header("Location: " . $redirect);
                    exit();
                }
                // New/pending inactive account: no OTP reactivation until super admin approves.
                $error = "Account is pending super admin approval.";
            } else {
                $stored = $user['password'];
                $password_ok = false;

                // Support both bcrypt (new) and legacy SHA-256 hashes
                if (password_verify($password, $stored)) {
                    $password_ok = true;
                } elseif (strlen($stored) === 64 && ctype_xdigit($stored) && hash_equals($stored, hash('sha256', $password))) {
                    $password_ok = true;
                    // Upgrade: rehash to bcrypt and update DB
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $upgrade = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $upgrade->bind_param("si", $newHash, $user['id']);
                    $upgrade->execute();
                    $upgrade->close();
                }

                if (!$password_ok) {
                    // Handle failed attempts per-account in DB (never shared across different emails).
                    $uid = (int)($user['id'] ?? 0);
                    $inc = $conn->prepare("
                        UPDATE users
                        SET failed_attempts = COALESCE(failed_attempts, 0) + 1,
                            status = CASE
                                WHEN COALESCE(failed_attempts, 0) + 1 >= 5 THEN 'inactive'
                                ELSE status
                            END
                        WHERE id = ?
                        LIMIT 1
                    ");
                    if ($inc) {
                        $inc->bind_param("i", $uid);
                        $inc->execute();
                        $inc->close();
                    }

                    $attempts = (int)($user['failed_attempts'] ?? 0) + 1;
                    $readAttempts = $conn->prepare("SELECT failed_attempts FROM users WHERE id = ? LIMIT 1");
                    if ($readAttempts) {
                        $readAttempts->bind_param("i", $uid);
                        if ($readAttempts->execute()) {
                            $raRes = $readAttempts->get_result();
                            if ($raRes && ($ra = $raRes->fetch_assoc())) {
                                $attempts = (int)($ra['failed_attempts'] ?? $attempts);
                            }
                        }
                        $readAttempts->close();
                    }

                    if ($attempts >= 5) {
                        $error = "Account locked due to multiple failed attempts.";
                    } else {
                        $remaining = max(0, 5 - $attempts);
                        $error = "Incorrect password. $remaining attempts left.";
                    }
                } else {
                    // Success: reset failed attempts
                    $update = $conn->prepare("UPDATE users SET failed_attempts=0 WHERE id=?");
                    $update->bind_param("i", $user['id']);
                    $update->execute();
                    $update->close();

                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);

                    // Set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['name'] = $user['name'];

                    // Log successful login
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $details = "Successful login from IP {$ip}" . ($agent ? " | UA: {$agent}" : '');
                    log_activity($conn, $user['id'], $user['role'], 'login_success', 'user', $user['id'], $details);

                    // Decide redirect URL based on role (students may return to check-in page)
                    $redirectUrl = '';
                    switch ($user['role']) {
                        case 'super_admin':
                            $redirectUrl = BASE_URL . "/backend/super_admin/dashboardsuperadmin.php";
                            break;
                        case 'admin':
                            $redirectUrl = BASE_URL . "/backend/admin/dashboard.php";
                            break;
                        case 'organizer':
                            $redirectUrl = BASE_URL . "/backend/auth/dashboardorganizer.php";
                            break;
                        case 'student':
                            $redirectUrl = BASE_URL . "/backend/auth/dashboard_student.php";
                            break;
                        case 'multimedia':
                            $redirectUrl = BASE_URL . "/backend/auth/dashboard_multimedia.php";
                            break;
                        default:
                            $error = "Invalid role";
                            break;
                    }
                    // Only allow same-origin redirect (prevent open redirect)
                    if ($user['role'] === 'student' && !empty($_POST['redirect'])) {
                        $redirect = trim($_POST['redirect']);
                        // Defensive: reject malformed non-URL-ish values (e.g. "[object HTMLInputElement]").
                        if ($redirect === '' || stripos($redirect, '[object') === 0) {
                            $redirect = '';
                        }
                        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'] ?? '';
                        $allowed_prefix = $scheme . '://' . $host . BASE_URL;
                        $same_origin = (strpos($redirect, $allowed_prefix) === 0);
                        $relative_safe = (strpos($redirect, '/') === 0 && strpos($redirect, BASE_URL) === 0);
                        if ($same_origin || $relative_safe) {
                            $redirectUrl = $redirect;
                        }
                    }

                    if (!empty($error)) {
                        $retry = BASE_URL . "/views/login.php?error=" . urlencode($error) . "&form=login";
                        $retryRedirect = trim((string)($_POST['redirect'] ?? ''));
                        if ($retryRedirect !== '') {
                            $retry .= "&redirect=" . urlencode($retryRedirect);
                        }
                        header("Location: " . $retry);
                        exit();
                    }

                    // Enforce immediate password change when account is flagged.
                    $mustChangePassword = ((int)($user['must_change_password'] ?? 0) === 1);
                    if ($mustChangePassword) {
                        $redirectUrl = BASE_URL . "/views/change_password.php?from=required&next=" . urlencode($redirectUrl);
                    }

                    // Always return a normal HTTP redirect after successful login.
                    // This is reliable for both full-page and fetch-based modal login flows.
                    $loc = $redirectUrl;
                    if (strpos($loc, 'http') !== 0) {
                        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'] ?? '';
                        $loc = $scheme . '://' . $host . (strpos($loc, '/') === 0 ? $loc : BASE_URL . '/' . $loc);
                    }
                    header('Location: ' . $loc);
                    exit();
                }
            }
        }

        // Redirect with error if login failed
        if (!empty($error)) {
            $retry = BASE_URL . "/views/login.php?error=" . urlencode($error) . "&form=login";
            $retryRedirect = trim((string)($_POST['redirect'] ?? ''));
            if ($retryRedirect !== '') {
                $retry .= "&redirect=" . urlencode($retryRedirect);
            }
            header("Location: " . $retry);
            exit();
        }
    }
} else {
    // Redirect if accessed directly
    header("Location: " . BASE_URL . "/views/login.php");
    include __DIR__ . '/../../views/login.php';

    exit();
}
