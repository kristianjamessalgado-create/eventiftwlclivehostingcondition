<?php
if (defined('EVENTIFY_SUPERADMIN_CONTROLLER_RUNNING')) {
    return;
}
define('EVENTIFY_SUPERADMIN_CONTROLLER_RUNNING', true);

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

register_shutdown_function(static function () {
    $err = error_get_last();
    if (!$err || !in_array((int) $err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        return;
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<pre style="margin:1rem;padding:1rem;background:#fff3cd;border:1px solid #f59e0b;font:14px/1.4 Consolas,monospace;white-space:pre-wrap">';
    echo 'Super admin dashboard failed to load.' . "\n";
    echo htmlspecialchars((string) $err['message'], ENT_QUOTES, 'UTF-8') . "\n";
    echo htmlspecialchars(basename((string) $err['file']) . ':' . (string) $err['line'], ENT_QUOTES, 'UTF-8');
    echo '</pre>';
});

try {
    eventify_run_dashboard_maintenance($conn);
} catch (Throwable $e) {
    /* keep dashboard usable if maintenance fails */
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

// Filters for the Users and All Events tables. They run in SQL so they search every page,
// not just the 20 rows currently shown.
$saFilters = [
    'u_q' => trim((string) ($_GET['u_q'] ?? '')),
    'u_role' => trim((string) ($_GET['u_role'] ?? '')),
    'u_status' => trim((string) ($_GET['u_status'] ?? '')),
    'e_q' => trim((string) ($_GET['e_q'] ?? '')),
    'e_status' => trim((string) ($_GET['e_status'] ?? '')),
    'e_dept' => trim((string) ($_GET['e_dept'] ?? '')),
];
if (!in_array($saFilters['u_role'], ['', 'super_admin', 'admin', 'organizer', 'multimedia', 'student'], true)) {
    $saFilters['u_role'] = '';
}
if (!in_array($saFilters['u_status'], ['', 'active', 'inactive', 'pending', 'locked'], true)) {
    $saFilters['u_status'] = '';
}
if (!in_array($saFilters['e_status'], ['', 'pending', 'active', 'rejected', 'closed'], true)) {
    $saFilters['e_status'] = '';
}

/** Build a dashboard URL keeping current filters/pages, with overrides. */
function eventify_sa_url(array $overrides = []): string
{
    global $saFilters, $usersPage, $eventsPage;
    $q = array_merge($saFilters, [
        'users_page' => $usersPage ?? 1,
        'events_page' => $eventsPage ?? 1,
    ], $overrides);
    $q = array_filter($q, static function ($v) {
        return $v !== '' && $v !== null;
    });
    return BASE_URL . '/backend/super_admin/dashboardsuperadmin.php?' . http_build_query($q);
}

$userWhere = [];
$userTypes = '';
$userParams = [];
if ($saFilters['u_q'] !== '') {
    $userWhere[] = '(name LIKE ? OR email LIKE ?)';
    $like = '%' . $saFilters['u_q'] . '%';
    $userTypes .= 'ss';
    array_push($userParams, $like, $like);
}
if ($saFilters['u_role'] !== '') {
    $userWhere[] = 'role = ?';
    $userTypes .= 's';
    $userParams[] = $saFilters['u_role'];
}
if ($saFilters['u_status'] === 'active') {
    $userWhere[] = "status = 'active'";
} elseif ($saFilters['u_status'] === 'inactive') {
    $userWhere[] = "status = 'inactive'";
} elseif ($saFilters['u_status'] === 'pending') {
    $userWhere[] = "status = 'inactive' AND COALESCE(failed_attempts, 0) = 0";
} elseif ($saFilters['u_status'] === 'locked') {
    $userWhere[] = "status = 'inactive' AND COALESCE(failed_attempts, 0) > 0";
}
$userWhereSql = $userWhere !== [] ? ' WHERE ' . implode(' AND ', $userWhere) : '';

// Fetch users (paginated)
$usersPage = max(1, (int) ($_GET['users_page'] ?? 1));
$usersPerPage = 20;
$usersTotal = 0;
$usersTotalPages = 1;
$cntStmt = $conn->prepare('SELECT COUNT(*) AS c FROM users' . $userWhereSql);
if ($cntStmt) {
    if ($userTypes !== '') {
        $cntStmt->bind_param($userTypes, ...$userParams);
    }
    $cntStmt->execute();
    $usersTotal = (int) ($cntStmt->get_result()->fetch_assoc()['c'] ?? 0);
    $cntStmt->close();
}
$usersTotalPages = max(1, (int) ceil($usersTotal / $usersPerPage));
if ($usersPage > $usersTotalPages) {
    $usersPage = $usersTotalPages;
}
$usersOffset = ($usersPage - 1) * $usersPerPage;
try {
    eventify_users_ensure_multimedia_moderator_column($conn);
} catch (Throwable $e) {
    /* column may already exist or lack ALTER privilege */
}
$users = [];
$stmt = $conn->prepare("SELECT id, name, email, role, status, failed_attempts, is_multimedia_moderator FROM users{$userWhereSql} ORDER BY id DESC LIMIT ? OFFSET ?");
if (!$stmt) {
    $stmt = $conn->prepare("SELECT id, name, email, role, status, failed_attempts FROM users{$userWhereSql} ORDER BY id DESC LIMIT ? OFFSET ?");
}
if ($stmt) {
    $userPageParams = array_merge($userParams, [$usersPerPage, $usersOffset]);
    $stmt->bind_param($userTypes . 'ii', ...$userPageParams);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

// Event approval and ticket revenue belong to admins/organizers; the super admin
// dashboard focuses on accounts, security and system-wide oversight.

// Fetch ALL events for Event Management and Calendar modals (paginated list)
$eventsPage = max(1, (int) ($_GET['events_page'] ?? 1));
$eventsPerPage = 20;
$eventsTotal = 0;
$eventsTotalPages = 1;

$evWhere = [];
$evTypes = '';
$evParams = [];
if ($saFilters['e_q'] !== '') {
    $evWhere[] = '(e.title LIKE ? OR e.location LIKE ? OR u.name LIKE ?)';
    $like = '%' . $saFilters['e_q'] . '%';
    $evTypes .= 'sss';
    array_push($evParams, $like, $like, $like);
}
if ($saFilters['e_status'] === 'closed') {
    $evWhere[] = "e.status IN ('closed','completed')";
} elseif ($saFilters['e_status'] !== '') {
    $evWhere[] = 'e.status = ?';
    $evTypes .= 's';
    $evParams[] = $saFilters['e_status'];
}
if ($saFilters['e_dept'] === 'ALL') {
    // School-wide events only.
    $evWhere[] = "(e.department IS NULL OR TRIM(e.department) IN ('', 'ALL'))";
} elseif ($saFilters['e_dept'] !== '') {
    // Single department, or a multi-department JSON list that contains it.
    $evWhere[] = '(TRIM(e.department) = ? OR e.department LIKE ?)';
    $evTypes .= 'ss';
    $evParams[] = $saFilters['e_dept'];
    $evParams[] = '%' . json_encode($saFilters['e_dept'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '%';
}
$evWhereSql = $evWhere !== [] ? ' WHERE ' . implode(' AND ', $evWhere) : '';

$evCnt = $conn->prepare('SELECT COUNT(*) AS c FROM events e JOIN users u ON e.organizer_id = u.id' . $evWhereSql);
if ($evCnt) {
    if ($evTypes !== '') {
        $evCnt->bind_param($evTypes, ...$evParams);
    }
    $evCnt->execute();
    $eventsTotal = (int) ($evCnt->get_result()->fetch_assoc()['c'] ?? 0);
    $evCnt->close();
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
    {$evWhereSql}
    ORDER BY e.date DESC, e.id DESC
    LIMIT ? OFFSET ?
");
if ($stmtAll) {
    $evPageParams = array_merge($evParams, [$eventsPerPage, $eventsOffset]);
    $stmtAll->bind_param($evTypes . 'ii', ...$evPageParams);
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

// Calendar shows every event, not only the current table page.
$saCalendarSource = [];
$resCal = $conn->query("
    SELECT e.id, e.title, e.description, e.date, e.end_date, e.start_time, e.end_time, e.location, e.department, e.status, e.created_at,
           u.name AS organizer_name, u.email AS organizer_email
    FROM events e
    JOIN users u ON e.organizer_id = u.id
    ORDER BY e.date DESC, e.id DESC
");
if ($resCal) {
    $saCalendarSource = $resCal->fetch_all(MYSQLI_ASSOC);
}
eventify_events_attach_schedule_dates($conn, $saCalendarSource);

$saCalendarEvents = eventify_events_to_fullcalendar_list($saCalendarSource, static function ($e) {
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

// User counts by role and status — counted across the whole users table
// (not just the 20 rows on the current page, and not affected by table filters).
$userStats = [
    'total'        => 0,
    'super_admin'  => 0,
    'admin'        => 0,
    'organizer'    => 0,
    'multimedia'   => 0,
    'student'      => 0,
    'active'       => 0,
    'inactive'     => 0,
];
if ($resRoleCounts = $conn->query("SELECT role, status, COUNT(*) AS cnt FROM users GROUP BY role, status")) {
    while ($row = $resRoleCounts->fetch_assoc()) {
        $cnt = (int) ($row['cnt'] ?? 0);
        $role = (string) ($row['role'] ?? '');
        $status = (string) ($row['status'] ?? '');
        $userStats['total'] += $cnt;
        if (isset($userStats[$role]) && $role !== 'total') {
            $userStats[$role] += $cnt;
        }
        if ($status === 'active') {
            $userStats['active'] += $cnt;
        } elseif ($status === 'inactive') {
            $userStats['inactive'] += $cnt;
        }
    }
}

// Account security: locked (5 wrong passwords), pending activation, and active accounts with failed attempts.
$saLockedAccounts = [];
$saPendingAccounts = [];
$saAtRiskAccounts = [];
if ($resSec = $conn->query("
    SELECT id, name, email, role, status, COALESCE(failed_attempts, 0) AS failed_attempts
    FROM users
    WHERE role <> 'super_admin'
      AND (status = 'inactive' OR COALESCE(failed_attempts, 0) > 0)
    ORDER BY failed_attempts DESC, id DESC
    LIMIT 300
")) {
    while ($row = $resSec->fetch_assoc()) {
        $fa = (int) $row['failed_attempts'];
        if (($row['status'] ?? '') === 'inactive') {
            if ($fa > 0) {
                $saLockedAccounts[] = $row;
            } else {
                $saPendingAccounts[] = $row;
            }
        } elseif ($fa > 0) {
            $saAtRiskAccounts[] = $row;
        }
    }
}

// Security-related audit entries (sign-ins, OTP reactivation, password resets, role and status changes).
$saSecurityLogs = [];
if ($resSecLogs = $conn->query("
    SELECT l.action, l.actor_role, l.details, l.created_at, u.name AS actor_name
    FROM activity_logs l
    LEFT JOIN users u ON l.actor_id = u.id
    WHERE l.action LIKE 'login%'
       OR l.action LIKE 'password%'
       OR l.action LIKE 'account%'
       OR l.action LIKE '%role%'
       OR l.action LIKE '%unlock%'
       OR l.action LIKE '%activat%'
       OR l.action LIKE '%delete_user%'
       OR l.action LIKE 'user_%'
    ORDER BY l.created_at DESC
    LIMIT 30
")) {
    $saSecurityLogs = $resSecLogs->fetch_all(MYSQLI_ASSOC);
}

// Successful sign-ins for the last 7 days (chart).
$saLoginTrend = ['labels' => [], 'counts' => []];
$loginByDay = [];
if ($resTrend = $conn->query("
    SELECT DATE(created_at) AS d, COUNT(*) AS cnt
    FROM activity_logs
    WHERE action = 'login_success' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
")) {
    while ($row = $resTrend->fetch_assoc()) {
        $loginByDay[(string) $row['d']] = (int) $row['cnt'];
    }
}
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $saLoginTrend['labels'][] = date('M j', strtotime($day));
    $saLoginTrend['counts'][] = $loginByDay[$day] ?? 0;
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

$viewFile = __DIR__ . '/../../super_admin/dashboardsuperadmin.php';
if (!is_file($viewFile)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p style="margin:1rem;font-family:sans-serif">Missing <code>super_admin/dashboardsuperadmin.php</code>. Upload that HTML view file.</p>';
    exit();
}
if (!defined('EVENTIFY_SUPERADMIN_DASHBOARD_LOADED')) {
    define('EVENTIFY_SUPERADMIN_DASHBOARD_LOADED', true);
}
include $viewFile;
