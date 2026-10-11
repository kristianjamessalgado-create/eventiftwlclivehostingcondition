<?php
session_start();
include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}
if (is_file(__DIR__ . '/config/student_profile_fields.php')) {
    require_once __DIR__ . '/config/student_profile_fields.php';
}
if (is_file(__DIR__ . '/config/departments.php')) {
    require_once __DIR__ . '/config/departments.php';
}
if (is_file(__DIR__ . '/config/student_sections.php')) {
    require_once __DIR__ . '/config/student_sections.php';
}
if (is_file(__DIR__ . '/backend/lib/event_day_sessions.php')) {
    require_once __DIR__ . '/backend/lib/event_day_sessions.php';
}
if (is_file(__DIR__ . '/backend/lib/event_calendar.php')) {
    require_once __DIR__ . '/backend/lib/event_calendar.php';
}
if (is_file(__DIR__ . '/backend/lib/attendance_proof.php')) {
    require_once __DIR__ . '/backend/lib/attendance_proof.php';
}
if (is_file(__DIR__ . '/backend/lib/attendance_report.php')) {
    require_once __DIR__ . '/backend/lib/attendance_report.php';
}

$allowed_roles = ['super_admin', 'admin', 'organizer'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $allowed_roles, true)) {
    header('Location: ' . BASE_URL . '/views/login.php?error=' . urlencode('Access denied'));
    exit();
}

$session_id = (int) ($_GET['id'] ?? 0);
if ($session_id < 1) {
    header('Location: ' . BASE_URL . '?error=Invalid activity');
    exit();
}

try {
    if (function_exists('eventify_sections_schema_ensure')) {
        eventify_sections_schema_ensure($conn);
    }
} catch (Throwable $e) {
    // optional
}
try {
    if (function_exists('eventify_users_ensure_student_profile_fields')) {
        eventify_users_ensure_student_profile_fields($conn);
    }
} catch (Throwable $e) {
    // optional
}
if (function_exists('eventify_event_day_sessions_ensure_enhanced')) {
    try {
        eventify_event_day_sessions_ensure_enhanced($conn);
    } catch (Throwable $e) {
        // optional
    }
}
$rawCols = function_exists('eventify_day_sessions_select_columns')
    ? explode(', ', eventify_day_sessions_select_columns($conn))
    : ['id', 'event_id', 'title', 'schedule_date', 'start_time', 'end_time', 'location'];
$colList = implode(', ', array_map(static function ($c) {
    return 's.' . trim($c);
}, $rawCols));
$row = null;
try {
    $stmt = $conn->prepare("SELECT {$colList}, e.id AS event_id, e.title AS event_title, e.organizer_id FROM event_day_sessions s JOIN events e ON e.id = s.event_id WHERE s.id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $session_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }
} catch (Throwable $e) {
    $row = null;
}

if (!$row) {
    header('Location: ' . BASE_URL . '?error=Activity not found');
    exit();
}

$role = $_SESSION['role'] ?? '';
$organizerId = (int) ($row['organizer_id'] ?? 0);
if ($role === 'organizer' && $organizerId !== (int) $_SESSION['user_id']) {
    header('Location: ' . BASE_URL . '/backend/auth/dashboardorganizer.php?error=' . urlencode('Access denied'));
    exit();
}

$session = eventify_map_day_session_row($row);
$eventId = (int) ($row['event_id'] ?? 0);
$timeStr = eventify_format_session_time_range($session['start_time'] ?? null, $session['end_time'] ?? null);
$timeInOut = function_exists('eventify_entity_uses_time_in_out')
    ? eventify_entity_uses_time_in_out($session)
    : (strtolower(trim((string) ($session['attendance_mode'] ?? 'single'))) === 'time_in_out');

try {
    if (function_exists('eventify_attendance_proof_ensure_columns')) {
        eventify_attendance_proof_ensure_columns($conn);
    }
} catch (Throwable $e) {
    // optional
}

$tableOk = false;
$hasOutCol = false;
try {
    $chk = $conn->query("SHOW TABLES LIKE 'event_day_session_attendance'");
    if ($chk && $chk->num_rows > 0) {
        $tableOk = true;
        $col = $conn->query("SHOW COLUMNS FROM event_day_session_attendance WHERE Field = 'checked_out_at'");
        $hasOutCol = $col && $col->num_rows > 0;
    }
} catch (Throwable $e) {
    $tableOk = false;
}

$attFilters = function_exists('eventify_attendance_report_parse_filters')
    ? eventify_attendance_report_parse_filters($_GET)
    : ['dept' => '', 'course' => '', 'year' => '', 'section' => '', 'view' => 'present', 'view_param' => 'auto', 'has_filter' => false];
$attFilterOptions = ['departments' => [], 'courses' => [], 'years' => [], 'sections' => [], 'course_departments' => []];
$attRows = [];
try {
    if (function_exists('eventify_attendance_report_filter_options')) {
        $attFilterOptions = eventify_attendance_report_filter_options($conn, $attFilters);
    }
    if ($tableOk && function_exists('eventify_attendance_report_load_activity_rows')) {
        $attRows = eventify_attendance_report_load_activity_rows($conn, $session_id, $attFilters, $timeInOut, $hasOutCol);
    }
} catch (Throwable $e) {
    $attRows = [];
}
$attSummary = function_exists('eventify_attendance_report_summarize')
    ? eventify_attendance_report_summarize($attRows)
    : ['total' => count($attRows), 'present' => count($attRows), 'absent' => 0, 'incomplete' => 0, 'unassigned' => 0];
$filterLabels = function_exists('eventify_attendance_report_filter_labels')
    ? eventify_attendance_report_filter_labels($attFilters)
    : [];
$unassignedPresent = function_exists('eventify_attendance_report_count_unassigned_present')
    ? eventify_attendance_report_count_unassigned_present($attRows)
    : 0;

$rsvpCount = 0;
if (function_exists('eventify_session_rsvps_table_exists') && eventify_session_rsvps_table_exists($conn)) {
    $rc = $conn->prepare('SELECT COUNT(*) AS c FROM event_day_session_rsvps WHERE session_id = ?');
    if ($rc) {
        $rc->bind_param('i', $session_id);
        $rc->execute();
        $rsvpCount = (int) ($rc->get_result()->fetch_assoc()['c'] ?? 0);
        $rc->close();
    }
}

$exportQs = eventify_attendance_report_query_string($attFilters, ['id' => $session_id, 'export' => 'csv']);
$clearUrl = BASE_URL . '/activity_attendance.php?id=' . $session_id;

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $safeTitle = eventify_attendance_report_safe_filename((string) ($session['title'] ?? 'activity'));
    $sectionBit = ($attFilters['section'] !== '' && $attFilters['section'] !== '__unassigned__')
        ? $attFilters['section']
        : 'all';
    $filename = 'activity_attendance_' . $session_id . '_' . $safeTitle . '_' . eventify_attendance_report_safe_filename($sectionBit) . '.csv';
    eventify_attendance_report_output_csv($filename, $attRows, $hasOutCol);
    $conn->close();
    exit();
}

$conn->close();

$back_url = BASE_URL . '/backend/auth/dashboardorganizer.php';
if ($role === 'admin') {
    $back_url = BASE_URL . '/backend/admin/dashboard.php';
} elseif ($role === 'super_admin') {
    $back_url = BASE_URL . '/backend/super_admin/dashboardsuperadmin.php';
}
$hubActivityUrl = BASE_URL . '/event_activities.php?id=' . $eventId . '&activity=' . $session_id;
$qrUrl = BASE_URL . '/activity_qr.php?id=' . $session_id;

$generatedBy = trim((string) ($_SESSION['name'] ?? ''));
$generatedAt = date('M j, Y g:i A');
$pageTitle = htmlspecialchars((string) ($session['title'] ?? 'Activity')) . ' – Attendance';
$attFilterAction = BASE_URL . '/activity_attendance.php';
$attFilterId = $session_id;
$attClearUrl = $clearUrl;
$attShowTimeOut = $hasOutCol;
$attShowPhotos = true;
$attRosterView = ($attFilters['view'] === 'roster');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/attendance_proof.css?v=3">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/attendance_report.css?v=1">
</head>
<body class="att-report-page">
    <div class="att-card card border-0">
        <div class="att-header">
            <div class="att-print-only att-print-banner">
                <div>
                    <strong>EVENTIFY</strong>
                    <div>Official activity attendance report</div>
                </div>
                <div class="text-end">
                    <div>Generated <?= htmlspecialchars($generatedAt) ?></div>
                    <?php if ($generatedBy !== ''): ?>
                        <div>By <?= htmlspecialchars($generatedBy) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="att-header__kicker no-print"><i class="fas fa-clipboard-check" aria-hidden="true"></i> Activity attendance</div>
            <h1><?= htmlspecialchars((string) ($session['title'] ?? 'Activity')) ?></h1>
            <p class="event-meta mb-0"><?= htmlspecialchars((string) ($row['event_title'] ?? '')) ?></p>
            <?php if (!empty($session['schedule_date'])): ?>
                <p class="event-meta mb-0">
                    <?= date('l, M j, Y', strtotime((string) $session['schedule_date'])) ?>
                    <?php if ($timeStr !== ''): ?> · <?= htmlspecialchars($timeStr) ?><?php endif; ?>
                    <?php if (!empty($session['location'])): ?> · <?= htmlspecialchars((string) $session['location']) ?><?php endif; ?>
                </p>
            <?php endif; ?>
            <?php if ($filterLabels !== []): ?>
                <p class="event-meta mb-0">Class: <?= htmlspecialchars(implode(' · ', $filterLabels)) ?></p>
            <?php endif; ?>
        </div>
        <div class="att-body">
            <?php if (!$tableOk): ?>
                <p class="text-muted mb-0">Activity check-in is not available yet. Run the database migration for day activities.</p>
            <?php else: ?>
                <?php
                if (is_file(__DIR__ . '/views/partials/attendance_filter_bar.php')) {
                    include __DIR__ . '/views/partials/attendance_filter_bar.php';
                }
                ?>

                <?php if ($filterLabels !== []): ?>
                    <div class="att-filter-chips no-print">
                        <?php foreach ($filterLabels as $chip): ?>
                            <span class="att-chip"><i class="fas fa-check" aria-hidden="true"></i><?= htmlspecialchars($chip) ?></span>
                        <?php endforeach; ?>
                        <span class="att-chip"><?= $attRosterView ? 'Full class roster' : 'Checked in only' ?></span>
                    </div>
                <?php endif; ?>

                <div class="att-stats">
                    <div class="att-stat"><strong><?= (int) $attSummary['present'] ?></strong><span>Present</span></div>
                    <?php if ($attRosterView || $attSummary['absent'] > 0): ?>
                        <div class="att-stat att-stat--absent"><strong><?= (int) $attSummary['absent'] ?></strong><span>Absent</span></div>
                    <?php endif; ?>
                    <?php if ($timeInOut || $attSummary['incomplete'] > 0): ?>
                        <div class="att-stat att-stat--incomplete"><strong><?= (int) $attSummary['incomplete'] ?></strong><span>Incomplete</span></div>
                    <?php endif; ?>
                    <div class="att-stat"><strong><?= (int) $attSummary['total'] ?></strong><span>Total listed</span></div>
                    <?php if ($rsvpCount > 0): ?>
                        <div class="att-stat"><strong><?= (int) $rsvpCount ?></strong><span>RSVP'd</span></div>
                    <?php endif; ?>
                </div>

                <?php if ($unassignedPresent > 0 && ($attFilters['section'] ?? '') !== '__unassigned__'): ?>
                    <div class="att-note no-print">
                        <strong><?= (int) $unassignedPresent ?></strong>
                        checked-in student<?= $unassignedPresent === 1 ? '' : 's' ?>
                        <?= $unassignedPresent === 1 ? 'has' : 'have' ?> no class section.
                        <a href="<?= htmlspecialchars($attFilterAction . '?' . eventify_attendance_report_query_string(array_merge($attFilters, ['section' => '__unassigned__']), ['id' => $session_id])) ?>">View unassigned</a>
                    </div>
                <?php endif; ?>

                <div class="att-toolbar no-print">
                    <span class="att-count">
                        <?php if ($attRosterView): ?>
                            <?= (int) $attSummary['present'] ?> of <?= (int) $attSummary['total'] ?> present
                        <?php else: ?>
                            <?= (int) $attSummary['total'] ?> <?= (int) $attSummary['total'] === 1 ? 'student' : 'students' ?> scanned the activity QR
                        <?php endif; ?>
                    </span>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if ($attRows !== []): ?>
                            <button type="button" class="btn btn-sm btn-print" id="attPrintBtn">
                                <i class="fas fa-file-pdf me-1"></i>Export PDF / Print
                            </button>
                            <a href="<?= htmlspecialchars(BASE_URL . '/activity_attendance.php?' . $exportQs) ?>" class="btn btn-sm btn-outline-primary btn-export">
                                <i class="fas fa-file-export me-1"></i>Export CSV
                            </a>
                        <?php endif; ?>
                        <a href="<?= htmlspecialchars($qrUrl) ?>" class="btn btn-sm btn-outline-success" target="_blank" rel="noopener">
                            <i class="fas fa-qrcode me-1"></i>Check-in QR
                        </a>
                        <a href="<?= htmlspecialchars($hubActivityUrl) ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-th-large me-1"></i>Activity page
                        </a>
                        <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-outline-secondary btn-sm btn-back">
                            <i class="fas fa-arrow-left me-1"></i>Dashboard
                        </a>
                    </div>
                </div>

                <?php
                if (is_file(__DIR__ . '/views/partials/attendance_report_table.php')) {
                    include __DIR__ . '/views/partials/attendance_report_table.php';
                }
                ?>

                <div class="att-signoff">
                    <div>
                        <div class="att-signoff__line">Teacher / Adviser</div>
                    </div>
                    <div>
                        <div class="att-signoff__line">Noted by Admin</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="<?= BASE_URL ?>/assets/js/attendance_proof.js?v=2"></script>
    <script src="<?= BASE_URL ?>/assets/js/attendance_report.js?v=1"></script>
</body>
</html>
