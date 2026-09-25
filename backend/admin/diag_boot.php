<?php
/**
 * Temporary deploy diagnostic — delete after fixing HTTP 500.
 * Open: /backend/admin/diag_boot.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

echo "EVENTIFY admin boot diagnostic\n";
echo 'PHP ' . PHP_VERSION . "\n\n";

$conn = null;
include __DIR__ . '/../../config/db.php';
if (!$conn instanceof mysqli) {
    echo "FAIL  Database connection\n";
    exit(1);
}
echo "OK    Database connection\n";

$steps = [
    'config/config.php' => static function () {
        include __DIR__ . '/../../config/config.php';
        return defined('BASE_URL');
    },
    'config/departments.php' => static function () {
        require_once __DIR__ . '/../../config/departments.php';
        return function_exists('eventify_parse_stored_departments_list');
    },
    'config/student_sections.php' => static function () {
        $path = __DIR__ . '/../../config/student_sections.php';
        if (!is_file($path)) {
            return false;
        }
        require_once $path;
        return function_exists('eventify_format_target_sections_label');
    },
    'backend/lib/event_calendar.php' => static function () {
        require_once __DIR__ . '/../lib/event_calendar.php';
        return function_exists('eventify_events_ensure_checkin_times');
    },
    'backend/lib/event_day_sessions.php' => static function () {
        require_once __DIR__ . '/../lib/event_day_sessions.php';
        return function_exists('eventify_student_ids_for_session_audience');
    },
    'backend/lib/event_reminders.php' => static function () {
        require_once __DIR__ . '/../lib/event_reminders.php';
        return function_exists('eventify_maybe_run_event_reminders');
    },
    'views/partials/event_checkin_time_fields.php' => static function () {
        return is_file(__DIR__ . '/../../views/partials/event_checkin_time_fields.php');
    },
    'views/partials/event_session_audience_fields.php' => static function () {
        return is_file(__DIR__ . '/../../views/partials/event_session_audience_fields.php');
    },
    'DB audience columns' => static function () use (&$conn) {
        require_once __DIR__ . '/../lib/event_day_sessions.php';
        eventify_event_day_sessions_ensure_audience_columns($conn);
        $r = $conn->query("SHOW COLUMNS FROM event_day_sessions LIKE 'department'");
        return (bool) ($r && $r->num_rows > 0);
    },
    'Dashboard maintenance' => static function () use (&$conn) {
        require_once __DIR__ . '/../lib/event_status_auto.php';
        eventify_run_dashboard_maintenance($conn);
        return true;
    },
];

foreach ($steps as $label => $fn) {
    try {
        $ok = (bool) $fn();
        echo ($ok ? 'OK  ' : 'FAIL') . '  ' . $label . "\n";
    } catch (Throwable $e) {
        echo 'ERR  ' . $label . ': ' . $e->getMessage() . "\n";
        echo '     at ' . $e->getFile() . ':' . $e->getLine() . "\n";
    }
}

echo "\nNext: open /backend/admin/diag_admin.php for a full dashboard test.\n";
echo "Delete these diag files when finished.\n";
