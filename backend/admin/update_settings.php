<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';

if (!isset($_SESSION['user_id']) || (string)($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . BASE_URL . '/views/login.php?error=' . urlencode('Access denied.'));
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . BASE_URL . '/backend/admin/dashboard.php?error=' . urlencode('Invalid request.') . '&open_modal=settings');
    exit();
}

if (!csrf_validate()) {
    header('Location: ' . BASE_URL . '/backend/admin/dashboard.php?error=' . urlencode('Security token mismatch. Please try again.') . '&open_modal=settings');
    exit();
}

$adminUserId = (int)($_SESSION['user_id'] ?? 0);
if ($adminUserId <= 0) {
    header('Location: ' . BASE_URL . '/backend/admin/dashboard.php?error=' . urlencode('Invalid session.') . '&open_modal=settings');
    exit();
}

function eventify_checkbox(string $key): int
{
    return isset($_POST[$key]) && (string)$_POST[$key] === '1' ? 1 : 0;
}

function eventify_int_range(string $key, int $default, int $min, int $max): int
{
    $raw = $_POST[$key] ?? $default;
    $value = (int)$raw;
    if ($value < $min) {
        return $min;
    }
    if ($value > $max) {
        return $max;
    }
    return $value;
}

$defaultView = strtolower(trim((string)($_POST['default_dashboard_view'] ?? 'calendar')));
$allowedViews = ['calendar', 'charts', 'pending'];
if (!in_array($defaultView, $allowedViews, true)) {
    $defaultView = 'calendar';
}

$notifyEmailNewEvent = eventify_checkbox('notify_email_new_event');
$notifyPendingReminder = eventify_checkbox('notify_pending_reminder');
$notificationRetentionDays = eventify_int_range('notification_retention_days', 30, 1, 365);
$otpRequiredSensitiveActions = eventify_checkbox('otp_required_sensitive_actions');
$otpExpiryMinutes = eventify_int_range('otp_expiry_minutes', 10, 3, 30);
$otpMaxAttempts = eventify_int_range('otp_max_attempts', 5, 3, 10);
$eventLeadDays = eventify_int_range('event_lead_days', 3, 0, 30);
$autoCompletePastEvents = eventify_checkbox('auto_complete_past_events');
$maxEventPhotos = eventify_int_range('max_event_photos', 10, 1, 30);
$maxUploadSizeMb = eventify_int_range('max_upload_size_mb', 10, 1, 50);
$sessionTimeoutMinutes = eventify_int_range('session_timeout_minutes', 30, 5, 240);
$forceReloginSensitiveActions = eventify_checkbox('force_relogin_sensitive_actions');
$calendarLegendVisible = eventify_checkbox('calendar_legend_visible');
$tablePageSize = eventify_int_range('table_page_size', 10, 5, 100);

if ($tablePageSize % 5 !== 0) {
    $tablePageSize = (int)(round($tablePageSize / 5) * 5);
    if ($tablePageSize < 5) {
        $tablePageSize = 5;
    } elseif ($tablePageSize > 100) {
        $tablePageSize = 100;
    }
}

try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS admin_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_user_id INT NOT NULL UNIQUE,
            notify_email_new_event TINYINT(1) NOT NULL DEFAULT 1,
            notify_pending_reminder TINYINT(1) NOT NULL DEFAULT 1,
            notification_retention_days INT NOT NULL DEFAULT 30,
            otp_required_sensitive_actions TINYINT(1) NOT NULL DEFAULT 1,
            otp_expiry_minutes INT NOT NULL DEFAULT 10,
            otp_max_attempts INT NOT NULL DEFAULT 5,
            event_lead_days INT NOT NULL DEFAULT 3,
            auto_complete_past_events TINYINT(1) NOT NULL DEFAULT 1,
            max_event_photos INT NOT NULL DEFAULT 10,
            max_upload_size_mb INT NOT NULL DEFAULT 10,
            session_timeout_minutes INT NOT NULL DEFAULT 30,
            force_relogin_sensitive_actions TINYINT(1) NOT NULL DEFAULT 1,
            default_dashboard_view VARCHAR(20) NOT NULL DEFAULT 'calendar',
            calendar_legend_visible TINYINT(1) NOT NULL DEFAULT 1,
            table_page_size INT NOT NULL DEFAULT 10,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_admin_settings_user FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $stmt = $conn->prepare("
        INSERT INTO admin_settings (
            admin_user_id, notify_email_new_event, notify_pending_reminder, notification_retention_days,
            otp_required_sensitive_actions, otp_expiry_minutes, otp_max_attempts, event_lead_days,
            auto_complete_past_events, max_event_photos, max_upload_size_mb, session_timeout_minutes,
            force_relogin_sensitive_actions, default_dashboard_view, calendar_legend_visible, table_page_size
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            notify_email_new_event = VALUES(notify_email_new_event),
            notify_pending_reminder = VALUES(notify_pending_reminder),
            notification_retention_days = VALUES(notification_retention_days),
            otp_required_sensitive_actions = VALUES(otp_required_sensitive_actions),
            otp_expiry_minutes = VALUES(otp_expiry_minutes),
            otp_max_attempts = VALUES(otp_max_attempts),
            event_lead_days = VALUES(event_lead_days),
            auto_complete_past_events = VALUES(auto_complete_past_events),
            max_event_photos = VALUES(max_event_photos),
            max_upload_size_mb = VALUES(max_upload_size_mb),
            session_timeout_minutes = VALUES(session_timeout_minutes),
            force_relogin_sensitive_actions = VALUES(force_relogin_sensitive_actions),
            default_dashboard_view = VALUES(default_dashboard_view),
            calendar_legend_visible = VALUES(calendar_legend_visible),
            table_page_size = VALUES(table_page_size)
    ");

    if (!$stmt) {
        throw new RuntimeException('Failed to prepare settings update.');
    }

    $stmt->bind_param(
        'iiiiiiiiiiiiisii',
        $adminUserId,
        $notifyEmailNewEvent,
        $notifyPendingReminder,
        $notificationRetentionDays,
        $otpRequiredSensitiveActions,
        $otpExpiryMinutes,
        $otpMaxAttempts,
        $eventLeadDays,
        $autoCompletePastEvents,
        $maxEventPhotos,
        $maxUploadSizeMb,
        $sessionTimeoutMinutes,
        $forceReloginSensitiveActions,
        $defaultView,
        $calendarLegendVisible,
        $tablePageSize
    );
    $stmt->execute();
    $stmt->close();

    header('Location: ' . BASE_URL . '/backend/admin/dashboard.php?success=' . urlencode('Admin settings updated successfully.') . '&open_modal=settings');
    exit();
} catch (Throwable $e) {
    header('Location: ' . BASE_URL . '/backend/admin/dashboard.php?error=' . urlencode('Failed to save settings. Please try again.') . '&open_modal=settings');
    exit();
}

