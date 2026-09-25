<?php
/**
 * Admin dashboard boot test — delete after fixing HTTP 500.
 * Open: /backend/admin/diag_admin.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

echo "EVENTIFY admin dashboard diagnostic\n";
echo 'PHP ' . PHP_VERSION . "\n\n";

$fail = null;
register_shutdown_function(static function () use (&$fail) {
    if ($fail !== null) {
        return;
    }
    $err = error_get_last();
    if (!$err) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'], $fatalTypes, true)) {
        return;
    }
    echo "\nFATAL: {$err['message']}\n";
    echo "       {$err['file']}:{$err['line']}\n";
});

function diag_step(string $label, callable $fn): bool
{
    try {
        $ok = (bool) $fn();
        echo ($ok ? 'OK  ' : 'FAIL') . '  ' . $label . "\n";
        return $ok;
    } catch (Throwable $e) {
        echo 'ERR  ' . $label . ': ' . $e->getMessage() . "\n";
        echo '     at ' . $e->getFile() . ':' . $e->getLine() . "\n";
        return false;
    }
}

$conn = null;
diag_step('Database connect', static function () use (&$conn) {
    include __DIR__ . '/../../config/db.php';
    return $conn instanceof mysqli;
});

diag_step('Core config + libraries', static function () {
    include __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../lib/event_status_auto.php';
    require_once __DIR__ . '/../lib/staff_messaging.php';
    require_once __DIR__ . '/../lib/event_feedback_schema.php';
    require_once __DIR__ . '/../lib/event_evaluation.php';
    require_once __DIR__ . '/../lib/event_calendar.php';
    require_once __DIR__ . '/../lib/event_day_sessions.php';
    require_once __DIR__ . '/../lib/event_organizer_assign.php';
    require_once __DIR__ . '/../../config/departments.php';
    require_once __DIR__ . '/../../config/student_profile_fields.php';
    if (is_file(__DIR__ . '/../../config/student_sections.php')) {
        require_once __DIR__ . '/../../config/student_sections.php';
    }
    require_once __DIR__ . '/../lib/event_ticketing.php';
    require_once __DIR__ . '/../lib/admin_announcements.php';
    return true;
});

diag_step('Dashboard maintenance', static function () use (&$conn) {
    eventify_run_dashboard_maintenance($conn);
    return true;
});

diag_step('Audience DB columns', static function () use (&$conn) {
    eventify_event_day_sessions_ensure_audience_columns($conn);
    $r = $conn->query("SHOW COLUMNS FROM event_day_sessions LIKE 'department'");
    return (bool) ($r && $r->num_rows > 0);
});

diag_step('Check-in time columns', static function () use (&$conn) {
    return function_exists('eventify_events_ensure_checkin_times')
        && eventify_events_ensure_checkin_times($conn);
});

diag_step('Load events + schedule', static function () use (&$conn) {
    $events = [];
    $result = $conn->query(
        'SELECT e.*, u.name AS organizer_name FROM events e JOIN users u ON e.organizer_id = u.id ORDER BY e.date ASC LIMIT 20'
    );
    if (!$result) {
        throw new RuntimeException($conn->error ?: 'events query failed');
    }
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    eventify_events_attach_schedule_dates($conn, $events);
    return $events !== [] || true;
});

diag_step('Create event modal partial', static function () use (&$conn) {
    require_once __DIR__ . '/../../config/organizer_departments.php';
    $organizer_department_choices = eventify_organizer_department_choices();
    $eventsHasGeo = false;
    $eventsHasEndDate = eventify_events_has_end_date($conn);
    $eventsHasCheckinTimes = function_exists('eventify_events_ensure_checkin_times')
        && eventify_events_ensure_checkin_times($conn);
    $createEventMode = 'admin';
    $adminCreateOrganizerOptions = [];
    ob_start();
    include __DIR__ . '/../../views/partials/create_event_modal.php';
    $html = ob_get_clean();
    return is_string($html) && $html !== '';
});

diag_step('Admin dashboard view partial', static function () use (&$conn) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';

    $adminId = 0;
    $res = $conn->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id ASC LIMIT 1");
    if ($res && ($row = $res->fetch_assoc())) {
        $adminId = (int) ($row['id'] ?? 0);
    }
    if ($adminId > 0) {
        $_SESSION['user_id'] = $adminId;
    }

    ob_start();
    include __DIR__ . '/dashboard.php';
    $out = ob_get_clean();
    if (!is_string($out) || $out === '') {
        throw new RuntimeException('Admin controller produced no output.');
    }
    return true;
});

echo "\nIf all lines are OK, open /backend/admin/dashboard.php again.\n";
echo "Delete diag_boot.php and diag_admin.php when finished.\n";
