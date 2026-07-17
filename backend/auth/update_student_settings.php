<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';

if (!isset($_SESSION['user_id']) || (string)($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ' . BASE_URL . '/views/login.php?error=' . urlencode('Access denied.'));
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_student.php?error=' . urlencode('Invalid request.'));
    exit();
}

if (!csrf_validate()) {
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_student.php?error=' . urlencode('Security token mismatch. Please try again.'));
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_student.php?error=' . urlencode('Invalid session.'));
    exit();
}

function student_checkbox(string $key): int
{
    return isset($_POST[$key]) && (string)$_POST[$key] === '1' ? 1 : 0;
}

$defaultView = (string)($_POST['default_calendar_view'] ?? 'dayGridMonth');
$allowedViews = ['dayGridMonth', 'timeGridWeek', 'timeGridDay'];
if (!in_array($defaultView, $allowedViews, true)) {
    $defaultView = 'dayGridMonth';
}

$reminderTiming = (string)($_POST['reminder_timing'] ?? '30_min');
$allowedTiming = ['30_min', '1_hour', '1_day'];
if (!in_array($reminderTiming, $allowedTiming, true)) {
    $reminderTiming = '30_min';
}

$eventReminders = student_checkbox('event_reminders');
$rsvpUpdates = student_checkbox('rsvp_updates');
$announcementNotifications = student_checkbox('announcement_notifications');
$notifChannelEmail = student_checkbox('notif_channel_email');
$notifChannelPush = student_checkbox('notif_channel_push');
$showCalendarLegend = student_checkbox('show_calendar_legend');
$autoAddRsvpCalendar = student_checkbox('auto_add_rsvp_calendar');
$hidePastRsvped = student_checkbox('hide_past_rsvped');
$shareProfileWithOrganizers = student_checkbox('share_profile_with_organizers');
$allowPhotoTagging = student_checkbox('allow_photo_tagging');

try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS student_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            event_reminders TINYINT(1) NOT NULL DEFAULT 1,
            rsvp_updates TINYINT(1) NOT NULL DEFAULT 1,
            announcement_notifications TINYINT(1) NOT NULL DEFAULT 1,
            notif_channel_email TINYINT(1) NOT NULL DEFAULT 1,
            notif_channel_push TINYINT(1) NOT NULL DEFAULT 1,
            default_calendar_view VARCHAR(20) NOT NULL DEFAULT 'dayGridMonth',
            show_calendar_legend TINYINT(1) NOT NULL DEFAULT 1,
            auto_add_rsvp_calendar TINYINT(1) NOT NULL DEFAULT 1,
            reminder_timing VARCHAR(20) NOT NULL DEFAULT '30_min',
            hide_past_rsvped TINYINT(1) NOT NULL DEFAULT 0,
            share_profile_with_organizers TINYINT(1) NOT NULL DEFAULT 1,
            allow_photo_tagging TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_student_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $stmt = $conn->prepare("
        INSERT INTO student_settings (
            user_id, event_reminders, rsvp_updates, announcement_notifications, notif_channel_email, notif_channel_push,
            default_calendar_view, show_calendar_legend, auto_add_rsvp_calendar, reminder_timing,
            hide_past_rsvped, share_profile_with_organizers, allow_photo_tagging
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            event_reminders = VALUES(event_reminders),
            rsvp_updates = VALUES(rsvp_updates),
            announcement_notifications = VALUES(announcement_notifications),
            notif_channel_email = VALUES(notif_channel_email),
            notif_channel_push = VALUES(notif_channel_push),
            default_calendar_view = VALUES(default_calendar_view),
            show_calendar_legend = VALUES(show_calendar_legend),
            auto_add_rsvp_calendar = VALUES(auto_add_rsvp_calendar),
            reminder_timing = VALUES(reminder_timing),
            hide_past_rsvped = VALUES(hide_past_rsvped),
            share_profile_with_organizers = VALUES(share_profile_with_organizers),
            allow_photo_tagging = VALUES(allow_photo_tagging)
    ");

    if (!$stmt) {
        throw new RuntimeException('Failed to prepare student settings update.');
    }

    $stmt->bind_param(
        'iiiiiisissiii',
        $userId,
        $eventReminders,
        $rsvpUpdates,
        $announcementNotifications,
        $notifChannelEmail,
        $notifChannelPush,
        $defaultView,
        $showCalendarLegend,
        $autoAddRsvpCalendar,
        $reminderTiming,
        $hidePastRsvped,
        $shareProfileWithOrganizers,
        $allowPhotoTagging
    );
    $stmt->execute();
    $stmt->close();

    header('Location: ' . BASE_URL . '/backend/auth/dashboard_student.php?msg=' . urlencode('Student settings updated successfully.'));
    exit();
} catch (Throwable $e) {
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_student.php?error=' . urlencode('Failed to update student settings.'));
    exit();
}

