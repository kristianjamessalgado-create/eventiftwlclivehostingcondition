<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/organizer_departments.php';

if (!isset($_SESSION['user_id']) || (string)($_SESSION['role'] ?? '') !== 'organizer') {
    header('Location: ' . BASE_URL . '/views/login.php?error=' . urlencode('Access denied.'));
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . BASE_URL . '/backend/auth/dashboardorganizer.php?error=' . urlencode('Invalid request.'));
    exit();
}

if (!csrf_validate()) {
    header('Location: ' . BASE_URL . '/backend/auth/dashboardorganizer.php?error=' . urlencode('Security token mismatch. Please try again.'));
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: ' . BASE_URL . '/backend/auth/dashboardorganizer.php?error=' . urlencode('Invalid session.'));
    exit();
}

function organizer_checkbox(string $key): int
{
    return isset($_POST[$key]) && (string)$_POST[$key] === '1' ? 1 : 0;
}

$defaultView = trim((string)($_POST['default_calendar_view'] ?? 'dayGridMonth'));
$allowedViews = ['dayGridMonth', 'timeGridWeek', 'timeGridDay'];
if (!in_array($defaultView, $allowedViews, true)) {
    $defaultView = 'dayGridMonth';
}

$deptRaw = trim((string)($_POST['default_department_filter'] ?? 'ALL'));
$allowedDeptKeys = array_keys(eventify_organizer_department_choices());
$defaultDepartment = in_array($deptRaw, $allowedDeptKeys, true) ? $deptRaw : 'ALL';

$showWeekends = organizer_checkbox('show_weekends');
$weekStartsOn = (int)($_POST['week_starts_on'] ?? 0);
$weekStartsOn = ($weekStartsOn === 1) ? 1 : 0;

$notifyEventStatus = organizer_checkbox('notify_email_event_status');
$notifyFeedback = organizer_checkbox('notify_email_feedback');

try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS organizer_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            default_calendar_view VARCHAR(20) NOT NULL DEFAULT 'dayGridMonth',
            default_department_filter VARCHAR(120) NOT NULL DEFAULT 'ALL',
            show_weekends TINYINT(1) NOT NULL DEFAULT 1,
            week_starts_on TINYINT NOT NULL DEFAULT 0,
            notify_email_event_status TINYINT(1) NOT NULL DEFAULT 1,
            notify_email_feedback TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_organizer_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $stmt = $conn->prepare("
        INSERT INTO organizer_settings (
            user_id, default_calendar_view, default_department_filter, show_weekends, week_starts_on,
            notify_email_event_status, notify_email_feedback
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            default_calendar_view = VALUES(default_calendar_view),
            default_department_filter = VALUES(default_department_filter),
            show_weekends = VALUES(show_weekends),
            week_starts_on = VALUES(week_starts_on),
            notify_email_event_status = VALUES(notify_email_event_status),
            notify_email_feedback = VALUES(notify_email_feedback)
    ");

    if (!$stmt) {
        throw new RuntimeException('Prepare failed.');
    }

    $stmt->bind_param(
        'issiiii',
        $userId,
        $defaultView,
        $defaultDepartment,
        $showWeekends,
        $weekStartsOn,
        $notifyEventStatus,
        $notifyFeedback
    );
    $stmt->execute();
    $stmt->close();
    $conn->close();

    header('Location: ' . BASE_URL . '/backend/auth/dashboardorganizer.php?msg=' . urlencode('Settings saved successfully.'));
    exit();
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    header('Location: ' . BASE_URL . '/backend/auth/dashboardorganizer.php?error=' . urlencode('Could not save settings. Please try again.'));
    exit();
}
