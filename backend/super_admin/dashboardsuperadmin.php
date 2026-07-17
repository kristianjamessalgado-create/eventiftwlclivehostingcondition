<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/event_status_auto.php';
require_once __DIR__ . '/../lib/event_calendar.php';
require_once __DIR__ . '/../lib/event_day_sessions.php';
require_once __DIR__ . '/../lib/multimedia_moderator.php';
require_once __DIR__ . '/../lib/event_ticketing.php';

// Only super_admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: " . BASE_URL . "/index.php?auth_modal=login");
    exit();
}

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
            header("Location: " . BASE_URL . "/views/change_password.php?from=required&next=" . urlencode(BASE_URL . "/backend/super_admin/dashboardsuperadmin.php"));
            exit();
        }
    }
}

// Logged-in super admin name (from session, or fetch from DB)
$superadmin_name = $_SESSION['name'] ?? '';
if ($superadmin_name === '') {
    $stmtUser = $conn->prepare("SELECT name FROM users WHERE id = ? AND role = 'super_admin'");
    $stmtUser->bind_param("i", $_SESSION['user_id']);
    $stmtUser->execute();
    $stmtUser->bind_result($superadmin_name);
    $stmtUser->fetch();
    $stmtUser->close();
    if ($superadmin_name === null || $superadmin_name === '') {
        $superadmin_name = 'Super Admin';
    }
}

// Fetch users (paginated)
$usersPage = max(1, (int) ($_GET['users_page'] ?? 1));
$usersPerPage = 20;
$usersTotal = 0;
$usersTotalPages = 1;
if ($resUsersCount = $conn->query("SELECT COUNT(*) AS c FROM users")) {
    if ($row = $resUsersCount->fetch_assoc()) {
        $usersTotal = (int) ($row['c'] ?? 0);
    }
}
$usersTotalPages = max(1, (int) ceil($usersTotal / $usersPerPage));
if ($usersPage > $usersTotalPages) {
    $usersPage = $usersTotalPages;
}
$usersOffset = ($usersPage - 1) * $usersPerPage;
eventify_users_ensure_multimedia_moderator_column($conn);
$users = [];
$stmt = $conn->prepare("SELECT id, name, email, role, status, failed_attempts, is_multimedia_moderator FROM users ORDER BY id DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $usersPerPage, $usersOffset);
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch pending events (for modal)
$pendingEvents = [];
$stmtPending = $conn->prepare("
    SELECT e.id, e.title, e.description, e.date, e.start_time, e.end_time, e.location, e.department, e.status,
           u.name AS organizer_name, u.email AS organizer_email
    FROM events e
    JOIN users u ON e.organizer_id = u.id
    WHERE e.status = 'pending'
    ORDER BY e.date ASC, e.id ASC
");
if ($stmtPending && $stmtPending->execute()) {
    $resPending = $stmtPending->get_result();
    if ($resPending) {
        $pendingEvents = $resPending->fetch_all(MYSQLI_ASSOC);
    }
    $stmtPending->close();
}

// Fetch ALL events for Event Management and Calendar modals (paginated list)
$eventsPage = max(1, (int) ($_GET['events_page'] ?? 1));
$eventsPerPage = 20;
$eventsTotal = 0;
$eventsTotalPages = 1;
if ($resEventsCount = $conn->query("SELECT COUNT(*) AS c FROM events")) {
    if ($row = $resEventsCount->fetch_assoc()) {
        $eventsTotal = (int) ($row['c'] ?? 0);
    }
}
$eventsTotalPages = max(1, (int) ceil($eventsTotal / $eventsPerPage));
if ($eventsPage > $eventsTotalPages) {
    $eventsPage = $eventsTotalPages;
}
$eventsOffset = ($eventsPage - 1) * $eventsPerPage;
$allEvents = [];
$stmtAll = $conn->prepare("
    SELECT e.id, e.title, e.description, e.date, e.end_date, e.start_time, e.end_time, e.location, e.department, e.status, e.created_at,
           u.name AS organizer_name, u.email AS organizer_email
    FROM events e
    JOIN users u ON e.organizer_id = u.id
    ORDER BY e.date DESC, e.id DESC
    LIMIT ? OFFSET ?
");
if ($stmtAll) {
    $stmtAll->bind_param("ii", $eventsPerPage, $eventsOffset);
    $stmtAll->execute();
    $resAll = $stmtAll->get_result();
    if ($resAll) {
        while ($row = $resAll->fetch_assoc()) {
            $allEvents[] = $row;
        }
    }
    $stmtAll->close();
}
eventify_events_attach_schedule_dates($conn, $allEvents);

$saCalendarEvents = eventify_events_to_fullcalendar_list($allEvents, static function ($e) {
    return [
        'location' => $e['location'] ?? '',
        'department' => $e['department'] ?? 'ALL',
        'status' => $e['status'] ?? '',
        'organizer_name' => $e['organizer_name'] ?? '',
        'start_time' => $e['start_time'] ?? null,
        'end_time'   => $e['end_time'] ?? null,
    ];
});
$saCalendarEvents = array_values(array_filter($saCalendarEvents, static function ($entry) {
    return !empty($entry['start']);
}));

// Fetch recent activity logs (latest 20)
$logs = [];
$sqlLogs = "
    SELECT l.id, l.actor_id, l.actor_role, l.action, l.target_type, l.target_id, l.details, l.created_at,
           u.name AS actor_name
    FROM activity_logs l
    LEFT JOIN users u ON l.actor_id = u.id
    ORDER BY l.created_at DESC
    LIMIT 20
";
if ($resLogs = $conn->query($sqlLogs)) {
    while ($row = $resLogs->fetch_assoc()) {
        $logs[] = $row;
    }
}

// Quick stats for dashboard cards

// User counts by role and status
$userStats = [
    'total'        => (int)$usersTotal,
    'super_admin'  => 0,
    'admin'        => 0,
    'organizer'    => 0,
    'multimedia'   => 0,
    'student'      => 0,
    'active'       => 0,
    'inactive'     => 0,
];
foreach ($users as $u) {
    $role = $u['role'] ?? '';
    $status = $u['status'] ?? '';
    if (isset($userStats[$role])) {
        $userStats[$role]++;
    }
    if ($status === 'active') {
        $userStats['active']++;
    } elseif ($status === 'inactive') {
        $userStats['inactive']++;
    }
}

$saUserRoleLabels = ['Super Admin', 'Admin', 'Organizer', 'Multimedia', 'Student'];
$saUserRoleCounts = [
    (int) ($userStats['super_admin'] ?? 0),
    (int) ($userStats['admin'] ?? 0),
    (int) ($userStats['organizer'] ?? 0),
    (int) ($userStats['multimedia'] ?? 0),
    (int) ($userStats['student'] ?? 0),
];

// Event stats
$eventStats = [
    'total'    => 0,
    'pending'  => 0,
    'active'   => 0,
    'rejected' => 0,
    'closed'   => 0,
    'completed'=> 0,
];
$sqlEvents = "
    SELECT status, COUNT(*) AS cnt
    FROM events
    GROUP BY status
";
if ($resEvents = $conn->query($sqlEvents)) {
    while ($row = $resEvents->fetch_assoc()) {
        $status = $row['status'] ?? '';
        $count  = (int)($row['cnt'] ?? 0);
        $eventStats['total'] += $count;
        if (isset($eventStats[$status])) {
            $eventStats[$status] += $count;
        }
    }
}
// Treat completed as closed in super-admin analytics for compatibility.
$eventStats['closed'] += (int)($eventStats['completed'] ?? 0);

$saEventStatusLabels = ['Pending', 'Active', 'Rejected', 'Closed'];
$saEventStatusCounts = [
    (int) ($eventStats['pending'] ?? 0),
    (int) ($eventStats['active'] ?? 0),
    (int) ($eventStats['rejected'] ?? 0),
    (int) ($eventStats['closed'] ?? 0),
];

// Logins today (from activity_logs)
$loginTodayCount = 0;
$sqlLoginsToday = "
    SELECT COUNT(*) AS total
    FROM activity_logs
    WHERE action = 'login_success'
      AND DATE(created_at) = CURDATE()
";
if ($resLogins = $conn->query($sqlLoginsToday)) {
    if ($row = $resLogins->fetch_assoc()) {
        $loginTodayCount = (int)($row['total'] ?? 0);
    }
}

// We keep connection open in case the view needs it later

// Success message
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

$activities_hub_events = [];
try {
    $activities_hub_events = eventify_load_activities_hub_picker_events(
        $conn,
        (int) ($_SESSION['user_id'] ?? 0),
        'super_admin'
    );
} catch (Throwable $e) {
    $activities_hub_events = [];
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

// Load view
define('EVENTIFY_SUPERADMIN_DASHBOARD_LOADED', true);
include __DIR__ . '/../../super_admin/dashboardsuperadmin.php';
