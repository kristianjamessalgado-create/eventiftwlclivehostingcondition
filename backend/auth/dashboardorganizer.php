<?php
require_once __DIR__ . '/../../config/session.php';
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/organizer_departments.php';
require_once __DIR__ . '/../../config/departments.php';
if (is_file(__DIR__ . '/../../config/student_sections.php')) {
    require_once __DIR__ . '/../../config/student_sections.php';
}
require_once __DIR__ . '/../lib/event_status_auto.php';
require_once __DIR__ . '/../lib/staff_messaging.php';
require_once __DIR__ . '/../lib/event_feedback_schema.php';
require_once __DIR__ . '/../lib/event_evaluation.php';
require_once __DIR__ . '/../lib/event_calendar.php';
require_once __DIR__ . '/../lib/event_day_sessions.php';
require_once __DIR__ . '/../lib/event_ticketing.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    header('Location: ' . BASE_URL . '/index.php?auth_modal=login');
    exit();
}

eventify_run_dashboard_maintenance($conn);
if (function_exists('eventify_sections_schema_ensure')) {
    eventify_sections_schema_ensure($conn);
}

$hasMustChangePasswordColumn = false;
try {
    $cpCol = $conn->query("SHOW COLUMNS FROM users LIKE 'must_change_password'");
    $hasMustChangePasswordColumn = (bool)($cpCol && $cpCol->num_rows > 0);
} catch (Throwable $e) {
    $hasMustChangePasswordColumn = false;
}
if ($hasMustChangePasswordColumn) {
    $forceCp = $conn->prepare("SELECT must_change_password FROM users WHERE id = ? LIMIT 1");
    if ($forceCp) {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $forceCp->bind_param("i", $uid);
        $forceCp->execute();
        $cpRow = $forceCp->get_result()->fetch_assoc();
        $forceCp->close();
        if ((int)($cpRow['must_change_password'] ?? 0) === 1) {
            header("Location: " . BASE_URL . "/views/change_password.php?from=required&next=" . urlencode(BASE_URL . "/backend/auth/dashboardorganizer.php"));
            exit();
        }
    }
}


$session_user_id = $_SESSION['user_id'];

// Fetch user info (for profile editing and display)
$user = ['id' => $session_user_id, 'name' => '', 'profile_picture' => null, 'email' => '', 'organizer_contact_email' => '', 'organizer_phone' => '', 'organizer_contact_method' => 'email'];
$userHasContactColumns = false;
try {
    $colCheck = $conn->query("SHOW COLUMNS FROM users WHERE Field IN ('organizer_contact_email','organizer_phone','organizer_contact_method')");
    $userHasContactColumns = (bool) ($colCheck && $colCheck->num_rows >= 3);
} catch (Throwable $e) {
    $userHasContactColumns = false;
}

if ($userHasContactColumns) {
    $stmt = $conn->prepare("SELECT name, profile_picture, email, organizer_contact_email, organizer_phone, organizer_contact_method FROM users WHERE id = ?");
    $stmt->bind_param("i", $session_user_id);
    $stmt->execute();
    $stmt->bind_result($db_name, $db_profile_picture, $db_email, $db_contact_email, $db_phone, $db_contact_method);
    $stmt->fetch();
    $stmt->close();
} else {
    $stmt = $conn->prepare("SELECT name, profile_picture, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $session_user_id);
    $stmt->execute();
    $stmt->bind_result($db_name, $db_profile_picture, $db_email);
    $stmt->fetch();
    $stmt->close();
    $db_contact_email = '';
    $db_phone = '';
    $db_contact_method = 'email';
}
$user['name'] = $db_name ?? '';
$user['profile_picture'] = $db_profile_picture;
$user['email'] = $db_email ?? '';
$user['organizer_contact_email'] = $db_contact_email ?? '';
$user['organizer_phone'] = $db_phone ?? '';
$user['organizer_contact_method'] = in_array($db_contact_method, ['email', 'phone'], true) ? $db_contact_method : 'email';
$user_name = $user['name'] ?: 'Organizer';

// Fetch events for this organizer
$events = [];
$stmt2 = $conn->prepare("SELECT * FROM events WHERE organizer_id = ? ORDER BY id DESC");
$stmt2->bind_param("i", $session_user_id);
$stmt2->execute();
$result = $stmt2->get_result();
if ($result) {
    $events = $result->fetch_all(MYSQLI_ASSOC);
}
$stmt2->close();
eventify_events_attach_schedule_dates($conn, $events);

require_once __DIR__ . '/../lib/event_approval_otp.php';
foreach ($events as &$evRow) {
    $evId = (int) ($evRow['id'] ?? 0);
    $evRow['has_active_otp'] = (
        strtolower((string) ($evRow['status'] ?? '')) === 'pending'
        && eventify_event_has_active_approval_otp($conn, $evId)
    );
}
unset($evRow);

// Quick stats for organizer dashboard
$today = date('Y-m-d');
$upcomingCount = 0;
$pendingCount = 0;
$thisWeekCount = 0;
$rejectedCount = 0;

foreach ($events as $e) {
    $date = $e['date'] ?? null;
    $status = strtolower($e['status'] ?? '');
    if ($date && $date >= $today) {
        $upcomingCount++;
    }
    if ($status === 'pending') {
        $pendingCount++;
    } elseif ($status === 'rejected') {
        $rejectedCount++;
    }

    if ($date && $date >= $today) {
        $diffDays = (strtotime($date) - strtotime($today)) / 86400;
        if ($diffDays >= 0 && $diffDays <= 7) {
            $thisWeekCount++;
        }
    }
}

$organizerStats = [
    'upcoming' => $upcomingCount,
    'pending'  => $pendingCount,
    'thisWeek' => $thisWeekCount,
    'rejected' => $rejectedCount,
];

// Feedback analytics (requires event_feedback table)
$feedbackStats = [
    'total_feedback' => 0,
    'avg_rating' => 0.0,
    'five_star' => 0,
];
try {
    $fStmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_feedback,
            AVG(ef.rating) AS avg_rating,
            SUM(CASE WHEN ef.rating = 5 THEN 1 ELSE 0 END) AS five_star
        FROM event_feedback ef
        JOIN events e ON e.id = ef.event_id
        WHERE e.organizer_id = ?
    ");
    if ($fStmt) {
        $fStmt->bind_param("i", $session_user_id);
        $fStmt->execute();
        $row = $fStmt->get_result()->fetch_assoc();
        $fStmt->close();
        if ($row) {
            $feedbackStats['total_feedback'] = (int) ($row['total_feedback'] ?? 0);
            $feedbackStats['avg_rating'] = (float) ($row['avg_rating'] ?? 0);
            $feedbackStats['five_star'] = (int) ($row['five_star'] ?? 0);
        }
    }
} catch (Throwable $e) {
    $feedbackStats = ['total_feedback' => 0, 'avg_rating' => 0.0, 'five_star' => 0];
}

// Student evaluations for this organizer's events (anonymous; department only)
$organizer_feedback_list = [];
try {
    if (eventify_event_feedback_ensure_schema($conn)) {
        $cStmt = $conn->prepare("
            SELECT ef.rating, ef.comment, ef.created_at, ef.evaluation_json,
                   e.title AS event_title,
                   u.department AS student_department
            FROM event_feedback ef
            JOIN events e ON e.id = ef.event_id
            LEFT JOIN users u ON u.id = ef.user_id
            WHERE e.organizer_id = ?
            ORDER BY ef.created_at DESC
            LIMIT 60
        ");
        if ($cStmt) {
            $cStmt->bind_param('i', $session_user_id);
            if ($cStmt->execute()) {
                $cr = $cStmt->get_result();
                if ($cr) {
                    $organizer_feedback_list = $cr->fetch_all(MYSQLI_ASSOC);
                }
            }
            $cStmt->close();
        }
    }
} catch (Throwable $e) {
    $organizer_feedback_list = [];
}

$organizer_evaluation_averages = [];
try {
    if (eventify_event_feedback_ensure_schema($conn)) {
        $evStmt = $conn->prepare("
            SELECT ef.evaluation_json
            FROM event_feedback ef
            JOIN events e ON e.id = ef.event_id
            WHERE e.organizer_id = ? AND ef.evaluation_json IS NOT NULL AND ef.evaluation_json != ''
        ");
        if ($evStmt) {
            $evStmt->bind_param('i', $session_user_id);
            if ($evStmt->execute()) {
                $evRes = $evStmt->get_result();
                $evalRows = $evRes ? $evRes->fetch_all(MYSQLI_ASSOC) : [];
                $organizer_evaluation_averages = eventify_evaluation_aggregate_from_rows($evalRows);
            }
            $evStmt->close();
        }
    }
} catch (Throwable $e) {
    $organizer_evaluation_averages = [];
}

// Fetch unread notifications for organizer (approve/reject etc.)
$organizer_notifications = [];
try {
    $notifStmt = $conn->prepare("SELECT id, type, title, message, event_id, created_at FROM notifications WHERE user_id = ? AND read_at IS NULL ORDER BY created_at DESC LIMIT 20");
    if ($notifStmt) {
        $notifStmt->bind_param("i", $session_user_id);
        $notifStmt->execute();
        $notifRes = $notifStmt->get_result();
        if ($notifRes) {
            $organizer_notifications = $notifRes->fetch_all(MYSQLI_ASSOC);
        }
        $notifStmt->close();
    }
} catch (mysqli_sql_exception $e) {
    // Table may not exist yet; use empty list so dashboard still loads
    $organizer_notifications = [];
}

$eventsHasGeo = false;
try {
    $geoColCheck = $conn->query("SHOW COLUMNS FROM events WHERE Field IN ('latitude','longitude')");
    if ($geoColCheck && $geoColCheck->num_rows >= 2) {
        $eventsHasGeo = true;
    }
} catch (Throwable $e) {
    $eventsHasGeo = false;
}

$eventsHasEndDate = eventify_events_has_end_date($conn);

// Admin ↔ Organizer messaging
$messaging_admins = [];
$staff_messaging_unread = 0;
try {
    if (eventify_staff_messages_ensure_table($conn)) {
        $aq = $conn->query("SELECT id, name, email FROM users WHERE role = 'admin' ORDER BY name ASC LIMIT 50");
        if ($aq) {
            while ($r = $aq->fetch_assoc()) {
                $messaging_admins[] = $r;
            }
        }
        $uc = $conn->prepare("
            SELECT COUNT(*) AS c FROM staff_messages m
            INNER JOIN users u ON u.id = m.sender_id AND u.role = 'admin'
            WHERE m.recipient_id = ? AND m.read_at IS NULL
        ");
        if ($uc) {
            $uc->bind_param('i', $session_user_id);
            $uc->execute();
            $ur = $uc->get_result();
            if ($ur && ($row = $ur->fetch_assoc())) {
                $staff_messaging_unread = (int)($row['c'] ?? 0);
            }
            $uc->close();
        }
    }
} catch (Throwable $e) {
    $messaging_admins = [];
    $staff_messaging_unread = 0;
}

$organizer_department_choices = eventify_organizer_department_choices();
$organizer_settings = [
    'default_calendar_view' => 'dayGridMonth',
    'default_department_filter' => 'ALL',
    'show_weekends' => 1,
    'week_starts_on' => 0,
    'notify_email_event_status' => 1,
    'notify_email_feedback' => 1,
];
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
    $osStmt = $conn->prepare("
        SELECT default_calendar_view, default_department_filter, show_weekends, week_starts_on,
               notify_email_event_status, notify_email_feedback
        FROM organizer_settings WHERE user_id = ? LIMIT 1
    ");
    if ($osStmt) {
        $osStmt->bind_param('i', $session_user_id);
        $osStmt->execute();
        $osRow = $osStmt->get_result()->fetch_assoc();
        $osStmt->close();
        if ($osRow) {
            $v = (string)($osRow['default_calendar_view'] ?? 'dayGridMonth');
            $organizer_settings['default_calendar_view'] = in_array($v, ['dayGridMonth', 'timeGridWeek', 'timeGridDay'], true) ? $v : 'dayGridMonth';
            $d = trim((string)($osRow['default_department_filter'] ?? 'ALL'));
            $organizer_settings['default_department_filter'] = array_key_exists($d, $organizer_department_choices) ? $d : 'ALL';
            $organizer_settings['show_weekends'] = (int)!empty($osRow['show_weekends']);
            $organizer_settings['week_starts_on'] = ((int)($osRow['week_starts_on'] ?? 0) === 1) ? 1 : 0;
            $organizer_settings['notify_email_event_status'] = (int)!empty($osRow['notify_email_event_status']);
            $organizer_settings['notify_email_feedback'] = (int)!empty($osRow['notify_email_feedback']);
        }
    }
} catch (Throwable $e) {
    // keep defaults
}

require_once __DIR__ . '/../lib/event_day_sessions.php';

$daySessionsHaveGeo = eventify_day_sessions_have_geo_columns($conn);
$daySessionsEnhanced = eventify_day_sessions_have_enhanced_columns($conn);
$promptActivitiesEventId = (int) ($_GET['prompt_activities'] ?? 0);

$activities_hub_events = [];
try {
    $activities_hub_events = eventify_load_activities_hub_picker_events(
        $conn,
        (int) $session_user_id,
        'organizer'
    );
} catch (Throwable $e) {
    $activities_hub_events = [];
}

$organizer_panel = (string) ($_GET['panel'] ?? '');
$organizer_events_panel_open = ($organizer_panel === 'events');
$organizer_feedback_panel_open = ($organizer_panel === 'feedback');
$organizer_dashboard_panel_open = $organizer_events_panel_open || $organizer_feedback_panel_open;
$organizer_events_count = count($events);

$dashboardClassSections = [];
try {
    if (function_exists('eventify_list_class_sections')) {
        $dashboardClassSections = eventify_list_class_sections($conn);
    }
} catch (Throwable $e) {
    $dashboardClassSections = [];
}
// Alias used by admin modal / shared UI
$adminClassSections = $dashboardClassSections;

$msg = trim((string)($_GET['msg'] ?? ''));
$error = trim((string)($_GET['error'] ?? ''));

include __DIR__ . '/../../views/dashboardorganizer.php';

$conn->close();
