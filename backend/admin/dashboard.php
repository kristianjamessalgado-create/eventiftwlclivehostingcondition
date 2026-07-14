<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/event_status_auto.php';
require_once __DIR__ . '/../lib/staff_messaging.php';
require_once __DIR__ . '/../lib/event_feedback_schema.php';
require_once __DIR__ . '/../lib/event_evaluation.php';
require_once __DIR__ . '/../lib/event_calendar.php';
require_once __DIR__ . '/../lib/event_day_sessions.php';
require_once __DIR__ . '/../lib/event_organizer_assign.php';
require_once __DIR__ . '/../../config/departments.php';
require_once __DIR__ . '/../../config/student_profile_fields.php';
require_once __DIR__ . '/../lib/event_ticketing.php';

// Only admin users can access this dashboard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "/views/login.php?error=Access denied");
    exit();
}

require_once __DIR__ . '/../lib/event_ticketing.php';

eventify_run_dashboard_maintenance($conn);

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
            header("Location: " . BASE_URL . "/views/change_password.php?from=required&next=" . urlencode(BASE_URL . "/backend/admin/dashboard.php"));
            exit();
        }
    }
}

$session_user_id = (int) $_SESSION['user_id'];
$usersHasOtpContactColumns = false;
$otpTableReady = false;
try {
    $colCheck = $conn->query("SHOW COLUMNS FROM users WHERE Field IN ('organizer_contact_email','organizer_phone','organizer_contact_method')");
    $usersHasOtpContactColumns = (bool) ($colCheck && $colCheck->num_rows >= 3);
} catch (Throwable $e) {
    $usersHasOtpContactColumns = false;
}
try {
    $otpTbl = $conn->query("SHOW TABLES LIKE 'event_approval_otps'");
    $otpTableReady = (bool) ($otpTbl && $otpTbl->num_rows > 0);
} catch (Throwable $e) {
    $otpTableReady = false;
}

// Fetch admin info (for header/profile)
$stmt = $conn->prepare("SELECT name, email, profile_picture FROM users WHERE id = ?");
$stmt->bind_param("i", $session_user_id);
$stmt->execute();
$stmt->bind_result($db_name, $db_email, $db_profile_picture);
$stmt->fetch();
$stmt->close();

$admin_name  = $db_name ?: 'Admin';
$admin_email = $db_email ?: '';
$admin_user = [
    'name' => $admin_name,
    'email' => $admin_email,
    'profile_picture' => $db_profile_picture ?: null,
];

// Admin settings (created lazily for backwards compatibility)
$adminSettings = [
    'notify_email_new_event' => 1,
    'notify_pending_reminder' => 1,
    'notification_retention_days' => 30,
    'otp_required_sensitive_actions' => 1,
    'otp_expiry_minutes' => 10,
    'otp_max_attempts' => 5,
    'event_lead_days' => 3,
    'auto_complete_past_events' => 1,
    'max_event_photos' => 10,
    'max_upload_size_mb' => 10,
    'session_timeout_minutes' => 30,
    'force_relogin_sensitive_actions' => 1,
    'default_dashboard_view' => 'calendar',
    'calendar_legend_visible' => 1,
    'table_page_size' => 10,
];
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
    $stmtSettings = $conn->prepare("
        SELECT notify_email_new_event, notify_pending_reminder, notification_retention_days,
               otp_required_sensitive_actions, otp_expiry_minutes, otp_max_attempts,
               event_lead_days, auto_complete_past_events, max_event_photos, max_upload_size_mb,
               session_timeout_minutes, force_relogin_sensitive_actions, default_dashboard_view,
               calendar_legend_visible, table_page_size
        FROM admin_settings
        WHERE admin_user_id = ?
        LIMIT 1
    ");
    if ($stmtSettings) {
        $stmtSettings->bind_param('i', $session_user_id);
        if ($stmtSettings->execute()) {
            $resSettings = $stmtSettings->get_result();
            if ($resSettings && ($settingsRow = $resSettings->fetch_assoc())) {
                $adminSettings = array_merge($adminSettings, $settingsRow);
            }
        }
        $stmtSettings->close();
    }
} catch (Throwable $e) {
    // Dashboard should remain available even if settings table is unavailable.
}

// Admin in-app notifications
$admin_notifications = [];
$admin_unread_count = 0;
try {
    $stmtN = $conn->prepare("SELECT id, type, title, message, event_id, read_at, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 40");
    if ($stmtN) {
        $stmtN->bind_param("i", $session_user_id);
        if ($stmtN->execute()) {
            $r = $stmtN->get_result();
            if ($r) {
                $admin_notifications = $r->fetch_all(MYSQLI_ASSOC);
            }
        }
        $stmtN->close();
    }
    foreach ($admin_notifications as $n) {
        if (empty($n['read_at'])) {
            $admin_unread_count++;
        }
    }
} catch (Throwable $e) {
    $admin_notifications = [];
    $admin_unread_count = 0;
}

// Fetch all events for calendar (only approved + pending for visibility)
$events = [];
$result = $conn->query("
    SELECT e.*, u.name AS organizer_name
    FROM events e
    JOIN users u ON e.organizer_id = u.id
    ORDER BY e.date ASC, e.id ASC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}
eventify_events_attach_schedule_dates($conn, $events);

// Fetch count of pending events and pending list for modal
$pendingCount = 0;
$pendingEvents = [];
$pendingSql = "
    SELECT e.id, e.organizer_id, e.title, e.description, e.date, e.location, e.department, e.status, e.registration_mode, e.created_at,
           u.name AS organizer_name, u.email AS organizer_email";
if ($usersHasOtpContactColumns) {
    $pendingSql .= ", u.organizer_contact_email, u.organizer_phone, u.organizer_contact_method";
}
$pendingSql .= "
    FROM events e
    JOIN users u ON e.organizer_id = u.id
    WHERE e.status = 'pending'
    ORDER BY e.date ASC, e.id ASC
";
$stmtPending = $conn->prepare($pendingSql);
if ($stmtPending && $stmtPending->execute()) {
    $resP = $stmtPending->get_result();
    if ($resP) {
        $pendingEvents = $resP->fetch_all(MYSQLI_ASSOC);
        $pendingCount = count($pendingEvents);
    }
    $stmtPending->close();
}

// Upcoming events (active + pending, today onward) — shown in sidebar modal on demand only
$todayAdmin = date('Y-m-d');
$upcomingAdminEvents = [];
$stmtUp = $conn->prepare("
    SELECT e.id, e.title, e.description, e.date, e.end_date, e.start_time, e.end_time, e.location, e.department, e.status, e.registration_mode,
           u.name AS organizer_name
    FROM events e
    JOIN users u ON e.organizer_id = u.id
    WHERE e.date >= ? AND e.status IN ('active', 'pending')
    ORDER BY e.date ASC, e.start_time ASC, e.id ASC
");
if ($stmtUp) {
    $stmtUp->bind_param('s', $todayAdmin);
    if ($stmtUp->execute()) {
        $ru = $stmtUp->get_result();
        if ($ru) {
            $upcomingAdminEvents = $ru->fetch_all(MYSQLI_ASSOC);
        }
    }
    $stmtUp->close();
}
$upcomingAdminCount = count($upcomingAdminEvents);

// Event stats for dashboard cards
$eventStats = ['total' => 0, 'pending' => 0, 'active' => 0, 'rejected' => 0, 'closed' => 0, 'completed' => 0];
$resStats = $conn->query("SELECT status, COUNT(*) AS cnt FROM events GROUP BY status");
if ($resStats) {
    while ($row = $resStats->fetch_assoc()) {
        $eventStats['total'] += (int) $row['cnt'];
        if (isset($eventStats[$row['status']])) {
            $eventStats[$row['status']] = (int) $row['cnt'];
        }
    }
}
// Treat completed as closed for legacy card labels.
$eventStats['closed'] += (int)($eventStats['completed'] ?? 0);

// Chart data: events by department
$chartDeptLabels = [];
$chartDeptCounts = [];
$resDept = $conn->query("SELECT COALESCE(NULLIF(TRIM(department), ''), 'ALL') AS dept, COUNT(*) AS cnt FROM events GROUP BY dept ORDER BY cnt DESC, dept ASC");
if ($resDept) {
    while ($row = $resDept->fetch_assoc()) {
        $d = $row['dept'] ?? 'ALL';
        $chartDeptLabels[] = $d === 'ALL' ? 'All departments' : $d;
        $chartDeptCounts[] = (int) $row['cnt'];
    }
}

// Chart data: events by status (normalized buckets so legend matches slice colors)
$statusBuckets = ['Pending' => 0, 'Active' => 0, 'Rejected' => 0, 'Closed' => 0];
$resStatusChart = $conn->query("SELECT COALESCE(NULLIF(TRIM(status), ''), 'unknown') AS s, COUNT(*) AS cnt FROM events GROUP BY s");
if ($resStatusChart) {
    while ($row = $resStatusChart->fetch_assoc()) {
        $raw = strtolower((string) ($row['s'] ?? 'unknown'));
        $cnt = (int) ($row['cnt'] ?? 0);
        if ($raw === 'pending') {
            $statusBuckets['Pending'] += $cnt;
        } elseif ($raw === 'active') {
            $statusBuckets['Active'] += $cnt;
        } elseif ($raw === 'rejected') {
            $statusBuckets['Rejected'] += $cnt;
        } elseif (in_array($raw, ['closed', 'completed', 'unknown'], true)) {
            $statusBuckets['Closed'] += $cnt;
        } else {
            $statusBuckets['Closed'] += $cnt;
        }
    }
}
$chartStatusLabels = [];
$chartStatusCounts = [];
foreach ($statusBuckets as $label => $cnt) {
    if ($cnt > 0) {
        $chartStatusLabels[] = $label;
        $chartStatusCounts[] = $cnt;
    }
}

// Feedback analytics (requires event_feedback table)
$feedbackStats = [
    'total_feedback' => 0,
    'avg_rating' => 0.0,
    'rating_labels' => ['1★', '2★', '3★', '4★', '5★'],
    'rating_counts' => [0, 0, 0, 0, 0],
];
try {
    $resFb = $conn->query("SELECT COUNT(*) AS total_feedback, AVG(rating) AS avg_rating FROM event_feedback");
    if ($resFb && ($row = $resFb->fetch_assoc())) {
        $feedbackStats['total_feedback'] = (int) ($row['total_feedback'] ?? 0);
        $feedbackStats['avg_rating'] = (float) ($row['avg_rating'] ?? 0);
    }
    $resFbDist = $conn->query("SELECT rating, COUNT(*) AS cnt FROM event_feedback GROUP BY rating ORDER BY rating ASC");
    if ($resFbDist) {
        while ($r = $resFbDist->fetch_assoc()) {
            $rating = (int) ($r['rating'] ?? 0);
            if ($rating >= 1 && $rating <= 5) {
                $feedbackStats['rating_counts'][$rating - 1] = (int) ($r['cnt'] ?? 0);
            }
        }
    }
} catch (Throwable $e) {
    // Keep dashboard available even when event_feedback is not migrated.
}

// Recent student feedback (all events) for admin review
$admin_feedback_list = [];
try {
    if (eventify_event_feedback_ensure_schema($conn)) {
        $fbStmt = $conn->query("
            SELECT ef.rating, ef.comment, ef.created_at, ef.evaluation_json,
                   e.title AS event_title, e.id AS event_id,
                   org.name AS organizer_name,
                   u.department AS student_department
            FROM event_feedback ef
            JOIN events e ON e.id = ef.event_id
            JOIN users org ON org.id = e.organizer_id
            LEFT JOIN users u ON u.id = ef.user_id
            ORDER BY ef.created_at DESC
            LIMIT 100
        ");
        if ($fbStmt) {
            while ($row = $fbStmt->fetch_assoc()) {
                $admin_feedback_list[] = $row;
            }
        }
    }
} catch (Throwable $e) {
    $admin_feedback_list = [];
}

$admin_evaluation_averages = [];
try {
    if (eventify_event_feedback_ensure_schema($conn)) {
        $evStmt = $conn->query("
            SELECT evaluation_json
            FROM event_feedback
            WHERE evaluation_json IS NOT NULL AND evaluation_json != ''
        ");
        $evalRows = [];
        if ($evStmt) {
            while ($row = $evStmt->fetch_assoc()) {
                $evalRows[] = $row;
            }
        }
        $admin_evaluation_averages = eventify_evaluation_aggregate_from_rows($evalRows);
    }
} catch (Throwable $e) {
    $admin_evaluation_averages = [];
}

// Admin ↔ Organizer messaging (inbox context for embedded panel)
$peersList = [];
$myName = $admin_name ?? '';
$messaging_error = null;
$initialWith = 0;
$staff_messaging_unread = 0;
try {
    $admin_msgr_ctx = eventify_staff_messenger_load_context(
        $conn,
        $session_user_id,
        'admin',
        (int) ($_GET['with'] ?? 0)
    );
    $peersList = $admin_msgr_ctx['peers_list'];
    $myName = $admin_msgr_ctx['my_name'] ?: $myName;
    $messaging_error = $admin_msgr_ctx['messaging_error'];
    $initialWith = $admin_msgr_ctx['initial_with'];
    $staff_messaging_unread = $admin_msgr_ctx['unread_total'];
} catch (Throwable $e) {
    $peersList = [];
    $messaging_error = 'Messaging is temporarily unavailable.';
    $staff_messaging_unread = 0;
}

// Recent audit log entries (shown in dashboard modal)
$auditLogs = [];
$resAudit = $conn->query("
    SELECT l.id, l.actor_id, l.actor_role, l.action, l.target_type, l.target_id, l.details, l.created_at,
           u.name AS actor_name
    FROM activity_logs l
    LEFT JOIN users u ON l.actor_id = u.id
    ORDER BY l.created_at DESC
    LIMIT 200
");
if ($resAudit) {
    while ($row = $resAudit->fetch_assoc()) {
        $auditLogs[] = $row;
    }
}

$activities_hub_events = [];
try {
    $activities_hub_events = eventify_load_activities_hub_picker_events(
        $conn,
        $session_user_id,
        'admin'
    );
} catch (Throwable $e) {
    $activities_hub_events = [];
}

$assignableOrganizers = eventify_fetch_assignable_organizers($conn);

// Pending events waiting more than 24 hours (dashboard reminder widget)
$stalePendingEvents = [];
$stalePendingCount = 0;
try {
    $resStale = $conn->query("
        SELECT e.id, e.title, e.created_at, u.name AS organizer_name
        FROM events e
        JOIN users u ON e.organizer_id = u.id
        WHERE e.status = 'pending'
          AND e.created_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY e.created_at ASC
        LIMIT 50
    ");
    if ($resStale) {
        while ($row = $resStale->fetch_assoc()) {
            $stalePendingEvents[] = $row;
        }
        $stalePendingCount = count($stalePendingEvents);
    }
} catch (Throwable $e) {
    $stalePendingEvents = [];
    $stalePendingCount = 0;
}

// All user accounts the admin may oversee (super admins are never exposed).
// Pending new registrations (inactive + never locked) are listed first.
$allUsers = [];
$pendingAccountCount = 0;
try {
    $resUsers = $conn->query("
        SELECT id, user_id, name, email, role, department, status, failed_attempts, created_at,
               student_course, student_year_level
        FROM users
        WHERE role <> 'super_admin'
        ORDER BY (status = 'inactive' AND failed_attempts = 0) DESC, created_at DESC
        LIMIT 500
    ");
    if ($resUsers) {
        while ($row = $resUsers->fetch_assoc()) {
            $allUsers[] = $row;
            // Badge counts only accounts the admin can actually act on
            // (pending, never-locked, and not an admin-role sign-up).
            if (($row['status'] ?? '') === 'inactive'
                && (int) ($row['failed_attempts'] ?? 0) === 0
                && ($row['role'] ?? '') !== 'admin') {
                $pendingAccountCount++;
            }
        }
    }
} catch (Throwable $e) {
    $allUsers = [];
    $pendingAccountCount = 0;
}

// Ticket revenue rollup (all paid events; demo/simulate excluded from totals).
try {
    $revenueOverview = eventify_revenue_overview($conn);
} catch (Throwable $e) {
    $revenueOverview = [
        'total_revenue' => 0.0, 'tickets_sold' => 0, 'orders_paid' => 0,
        'pending_amount' => 0.0, 'pending_orders' => 0, 'demo_revenue' => 0.0,
        'demo_orders' => 0, 'by_method' => [], 'by_event' => [],
    ];
}

// RSVP and check-in counts for calendar event details
$rsvpCountByEvent = [];
$checkinCountByEvent = [];
try {
    $rc = $conn->query('SELECT event_id, COUNT(*) AS cnt FROM registrations GROUP BY event_id');
    if ($rc) {
        while ($row = $rc->fetch_assoc()) {
            $rsvpCountByEvent[(int) ($row['event_id'] ?? 0)] = (int) ($row['cnt'] ?? 0);
        }
    }
    $cc = $conn->query("SELECT event_id, COUNT(*) AS cnt FROM registrations WHERE status = 'present' AND time_in IS NOT NULL GROUP BY event_id");
    if ($cc) {
        while ($row = $cc->fetch_assoc()) {
            $checkinCountByEvent[(int) ($row['event_id'] ?? 0)] = (int) ($row['cnt'] ?? 0);
        }
    }
} catch (Throwable $e) {
    $rsvpCountByEvent = [];
    $checkinCountByEvent = [];
}

$conn->close();

$admin_panel = (string) ($_GET['panel'] ?? '');
$admin_events_panel_open = ($admin_panel === 'events');
$admin_users_panel_open = ($admin_panel === 'users');
$admin_messages_panel_open = ($admin_panel === 'messages');
$admin_pending_panel_open = ($admin_panel === 'pending');
$admin_feedback_panel_open = ($admin_panel === 'feedback');
$admin_audit_panel_open = ($admin_panel === 'audit');
$admin_revenue_panel_open = ($admin_panel === 'revenue');
$admin_analytics_panel_open = ($admin_panel === 'analytics');
$admin_upcoming_panel_open = ($admin_panel === 'upcoming');
$admin_focus_pending_event_id = max(0, (int) ($_GET['focus_event'] ?? 0));
$admin_dashboard_panel_open = $admin_events_panel_open
    || $admin_users_panel_open
    || $admin_messages_panel_open
    || $admin_pending_panel_open
    || $admin_feedback_panel_open
    || $admin_audit_panel_open
    || $admin_revenue_panel_open
    || $admin_analytics_panel_open
    || $admin_upcoming_panel_open;
$admin_events_count = count($events);
$admin_users_count = count($allUsers);
$dashboardHref = BASE_URL . '/backend/admin/dashboard.php';

define('EVENTIFY_ADMIN_DASHBOARD_LOADED', true);
include __DIR__ . '/../../admin/dashboard.php';

