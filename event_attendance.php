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

$event_id = (int) ($_GET['id'] ?? 0);
if ($event_id < 1) {
    header('Location: ' . BASE_URL . '?error=Invalid event');
    exit();
}

try {
    if (function_exists('eventify_sections_schema_ensure')) {
        eventify_sections_schema_ensure($conn);
    }
} catch (Throwable $e) {
    // schema optional
}
try {
    if (function_exists('eventify_users_ensure_student_profile_fields')) {
        eventify_users_ensure_student_profile_fields($conn);
    }
} catch (Throwable $e) {
    // columns optional
}

$hasAttendanceMode = false;
try {
    $modeCol = $conn->query("SHOW COLUMNS FROM events WHERE Field = 'attendance_mode'");
    $hasAttendanceMode = (bool) ($modeCol && $modeCol->num_rows > 0);
} catch (Throwable $e) {
    $hasAttendanceMode = false;
}

$eventSql = $hasAttendanceMode
    ? "SELECT id, title, date, start_time, end_time, location, organizer_id, attendance_mode FROM events WHERE id = ?"
    : "SELECT id, title, date, start_time, end_time, location, organizer_id FROM events WHERE id = ?";
$event = null;
try {
    $stmt = $conn->prepare($eventSql);
    if (!$stmt && $hasAttendanceMode) {
        $stmt = $conn->prepare("SELECT id, title, date, start_time, end_time, location, organizer_id FROM events WHERE id = ?");
    }
    if ($stmt) {
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $event = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }
} catch (Throwable $e) {
    $event = null;
}

if (!$event) {
    header('Location: ' . BASE_URL . '?error=Event not found');
    exit();
}

$role = $_SESSION['role'] ?? '';
if ($role === 'organizer' && (int) $event['organizer_id'] !== (int) $_SESSION['user_id']) {
    header('Location: ' . BASE_URL . '?error=Access denied');
    exit();
}

$timeInOut = strtolower(trim((string) ($event['attendance_mode'] ?? 'single'))) === 'time_in_out';
$attFilters = function_exists('eventify_attendance_report_parse_filters')
    ? eventify_attendance_report_parse_filters($_GET)
    : ['dept' => '', 'course' => '', 'year' => '', 'section' => '', 'view' => 'present', 'view_param' => 'auto', 'has_filter' => false];
$attFilterOptions = ['departments' => [], 'courses' => [], 'years' => [], 'sections' => [], 'course_departments' => []];
$attRows = [];
try {
    if (function_exists('eventify_attendance_report_filter_options')) {
        $attFilterOptions = eventify_attendance_report_filter_options($conn, $attFilters);
    }
    if (function_exists('eventify_attendance_report_load_event_rows')) {
        $attRows = eventify_attendance_report_load_event_rows($conn, $event_id, $attFilters, $timeInOut);
    }
} catch (Throwable $e) {
    $attRows = [];
}
// Last-resort list only when NO filter is chosen; with a filter, an empty list is the right answer.
if ($attRows === [] && empty($attFilters['has_filter'])) {
    try {
        $profileCols = function_exists('eventify_attendance_report_user_select_sql')
            ? eventify_attendance_report_user_select_sql($conn)
            : 'u.name, u.user_id AS student_school_id, u.student_course, u.student_year_level, u.student_academic_year, u.department, u.student_section';
        $fbWhere = "FROM registrations r
            JOIN users u ON r.user_id = u.id
            WHERE r.event_id = ? AND (r.status = 'present' OR r.time_in IS NOT NULL)
            ORDER BY r.time_in ASC";
        $fb = $conn->prepare("SELECT {$profileCols}, r.time_in, r.time_out, r.proof_photo {$fbWhere}");
        if (!$fb) {
            $fb = $conn->prepare("SELECT {$profileCols}, r.time_in, r.time_out {$fbWhere}");
        }
        if (!$fb) {
            $fb = $conn->prepare("SELECT u.name, u.user_id AS student_school_id, u.student_course, u.student_year_level, u.student_academic_year, u.department, r.time_in, r.time_out {$fbWhere}");
        }
        if ($fb) {
            $fb->bind_param('i', $event_id);
            $fb->execute();
            $fbRes = $fb->get_result();
            if ($fbRes && function_exists('eventify_attendance_report_normalize_row')) {
                while ($fbRow = $fbRes->fetch_assoc()) {
                    $attRows[] = eventify_attendance_report_normalize_row($fbRow, $timeInOut);
                }
            } elseif ($fbRes) {
                while ($fbRow = $fbRes->fetch_assoc()) {
                    $attRows[] = [
                        'name' => (string) ($fbRow['name'] ?? ''),
                        'student_school_id' => (string) ($fbRow['student_school_id'] ?? ''),
                        'student_course' => (string) ($fbRow['student_course'] ?? ''),
                        'student_year_level' => (string) ($fbRow['student_year_level'] ?? ''),
                        'student_academic_year' => (string) ($fbRow['student_academic_year'] ?? ''),
                        'department' => (string) ($fbRow['department'] ?? ''),
                        'department_label' => (string) ($fbRow['department'] ?? ''),
                        'student_section' => (string) ($fbRow['student_section'] ?? ''),
                        'time_in' => (string) ($fbRow['time_in'] ?? ''),
                        'time_out' => (string) ($fbRow['time_out'] ?? ''),
                        'proof_photo' => (string) ($fbRow['proof_photo'] ?? ''),
                        'status' => !empty($fbRow['time_in']) ? 'present' : 'absent',
                    ];
                }
            }
            $fb->close();
        }
    } catch (Throwable $e) {
        // keep empty rather than white-screen
    }
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

$exportQs = function_exists('eventify_attendance_report_query_string')
    ? eventify_attendance_report_query_string($attFilters, ['id' => $event_id, 'export' => 'csv'])
    : 'id=' . $event_id . '&export=csv';
$clearUrl = BASE_URL . '/event_attendance.php?id=' . $event_id;

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $sectionBit = ($attFilters['section'] !== '' && $attFilters['section'] !== '__unassigned__')
        ? $attFilters['section']
        : 'all';
    $filename = 'event_attendance_' . (int) $event['id'] . '_' . eventify_attendance_report_safe_filename((string) $event['title'], $sectionBit) . '.csv';
    eventify_attendance_report_output_csv($filename, $attRows, true);
    $conn->close();
    exit();
}

$conn->close();

$back_url = BASE_URL . '/backend/auth/dashboardorganizer.php';
if ($role === 'admin') {
    $back_url = BASE_URL . '/backend/admin/dashboard.php';
}
if ($role === 'super_admin') {
    $back_url = BASE_URL . '/backend/super_admin/dashboardsuperadmin.php';
}

$generatedBy = trim((string) ($_SESSION['name'] ?? ''));
$generatedAt = date('M j, Y g:i A');
$pageTitle = htmlspecialchars((string) $event['title']) . ' – Attendance';
$attFilterAction = BASE_URL . '/event_attendance.php';
$attFilterId = $event_id;
$attClearUrl = $clearUrl;
$attShowTimeOut = true;
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
                    <div>Official attendance report</div>
                </div>
                <div class="text-end">
                    <div>Generated <?= htmlspecialchars($generatedAt) ?></div>
                    <?php if ($generatedBy !== ''): ?>
                        <div>By <?= htmlspecialchars($generatedBy) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="att-header__kicker no-print"><i class="fas fa-clipboard-check" aria-hidden="true"></i> Attendance</div>
            <h1><?= htmlspecialchars((string) $event['title']) ?></h1>
            <?php if (!empty($event['date'])): ?>
                <p class="event-meta mb-0"><?= date('l, M j, Y', strtotime((string) $event['date'])) ?><?= !empty($event['location']) ? ' · ' . htmlspecialchars((string) $event['location']) : '' ?></p>
            <?php endif; ?>
            <?php if ($filterLabels !== []): ?>
                <p class="event-meta mb-0">Class: <?= htmlspecialchars(implode(' · ', $filterLabels)) ?></p>
            <?php endif; ?>
        </div>
        <div class="att-body">
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
            </div>

            <?php if ($unassignedPresent > 0 && ($attFilters['section'] ?? '') !== '__unassigned__'): ?>
                <div class="att-note no-print">
                    <strong><?= (int) $unassignedPresent ?></strong>
                    checked-in student<?= $unassignedPresent === 1 ? '' : 's' ?>
                    <?= $unassignedPresent === 1 ? 'has' : 'have' ?> no class section.
                    Assign their section in Users so they appear in a section report.
                    <a href="<?= htmlspecialchars($attFilterAction . '?' . (function_exists('eventify_attendance_report_query_string') ? eventify_attendance_report_query_string(array_merge($attFilters, ['section' => '__unassigned__']), ['id' => $event_id]) : ('id=' . $event_id . '&section=__unassigned__'))) ?>">View unassigned</a>
                </div>
            <?php endif; ?>

            <div class="att-toolbar no-print">
                <span class="att-count">
                    <?php if ($attRosterView): ?>
                        <?= (int) $attSummary['present'] ?> of <?= (int) $attSummary['total'] ?> present
                    <?php else: ?>
                        <?= (int) $attSummary['total'] ?> <?= (int) $attSummary['total'] === 1 ? 'attendee' : 'attendees' ?>
                    <?php endif; ?>
                </span>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($attRows !== []): ?>
                        <button type="button" class="btn btn-sm btn-print" id="attPrintBtn">
                            <i class="fas fa-file-pdf me-1"></i>Export PDF / Print
                        </button>
                        <a href="<?= htmlspecialchars(BASE_URL . '/event_attendance.php?' . $exportQs) ?>" class="btn btn-sm btn-outline-primary btn-export">
                            <i class="fas fa-file-export me-1"></i>Export CSV
                        </a>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-outline-secondary btn-sm btn-back"><i class="fas fa-arrow-left me-1"></i>Back to dashboard</a>
                </div>
            </div>

            <?php
            if (is_file(__DIR__ . '/views/partials/attendance_report_table.php')) {
                include __DIR__ . '/views/partials/attendance_report_table.php';
            } elseif ($attRows !== []) {
                echo '<div class="table-responsive"><table class="table table-attendees"><thead><tr><th>#</th><th>Name</th><th>Student ID</th><th>Time in</th><th>Time out</th></tr></thead><tbody>';
                foreach ($attRows as $i => $a) {
                    echo '<tr><td>' . ($i + 1) . '</td><td>' . htmlspecialchars((string) ($a['name'] ?? '')) . '</td><td>' . htmlspecialchars((string) ($a['student_school_id'] ?? '')) . '</td><td>' . htmlspecialchars((string) ($a['time_in'] ?? '')) . '</td><td>' . htmlspecialchars((string) ($a['time_out'] ?? '')) . '</td></tr>';
                }
                echo '</tbody></table></div>';
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
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/attendance_proof.js?v=2"></script>
    <script src="<?= BASE_URL ?>/assets/js/attendance_report.js?v=1"></script>
</body>
</html>
