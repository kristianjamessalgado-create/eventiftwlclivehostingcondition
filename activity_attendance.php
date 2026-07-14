<?php
session_start();
include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/student_profile_fields.php';
require_once __DIR__ . '/config/departments.php';
require_once __DIR__ . '/backend/lib/event_day_sessions.php';
require_once __DIR__ . '/backend/lib/event_calendar.php';

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

eventify_event_day_sessions_ensure_enhanced($conn);
$rawCols = explode(', ', eventify_day_sessions_select_columns($conn));
$colList = implode(', ', array_map(static function ($c) {
    return 's.' . trim($c);
}, $rawCols));
$stmt = $conn->prepare("SELECT {$colList}, e.id AS event_id, e.title AS event_title, e.organizer_id FROM event_day_sessions s JOIN events e ON e.id = s.event_id WHERE s.id = ? LIMIT 1");
$stmt->bind_param('i', $session_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

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

eventify_users_ensure_student_profile_fields($conn);

$attendees = [];
$tableOk = false;
$chk = $conn->query("SHOW TABLES LIKE 'event_day_session_attendance'");
if ($chk && $chk->num_rows > 0) {
    $tableOk = true;
    $st = $conn->prepare(
        'SELECT a.checked_in_at, u.name, u.user_id AS student_school_id,
                u.student_course, u.student_year_level, u.student_academic_year, u.department
         FROM event_day_session_attendance a
         JOIN users u ON u.id = a.user_id
         WHERE a.session_id = ?
         ORDER BY a.checked_in_at ASC'
    );
    if ($st) {
        $st->bind_param('i', $session_id);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) {
            $attendees[] = $r;
        }
        $st->close();
    }
}

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

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    $safeTitle = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) ($session['title'] ?? 'activity'));
    header('Content-Disposition: attachment; filename="activity_attendance_' . $session_id . '_' . $safeTitle . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['#', 'Student Name', 'Student ID', 'Course', 'Year level', 'School year (AY)', 'Department / college', 'Check-in time']);
    $i = 1;
    foreach ($attendees as $attRow) {
        $rawDept = trim((string) ($attRow['department'] ?? ''));
        $deptLabel = $rawDept === ''
            ? ''
            : (function_exists('eventify_format_department_label')
                ? eventify_format_department_label($rawDept)
                : $rawDept);
        fputcsv($out, [
            $i++,
            $attRow['name'] ?? '',
            $attRow['student_school_id'] ?? '',
            $attRow['student_course'] ?? '',
            $attRow['student_year_level'] ?? '',
            $attRow['student_academic_year'] ?? '',
            $deptLabel,
            $attRow['checked_in_at'] ?? '',
        ]);
    }
    fclose($out);
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

$pageTitle = htmlspecialchars((string) ($session['title'] ?? 'Activity')) . ' – Attendance';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --school-green-900: #064e3b;
            --school-green-700: #047857;
            --school-gold-600: #ca8a04;
            --school-bg: #f0f9f4;
            --school-border: #cfe7d8;
        }
        body {
            padding: 1.5rem;
            background:
                radial-gradient(900px 360px at 0% -10%, rgba(6, 95, 70, 0.18), transparent 60%),
                radial-gradient(700px 320px at 100% -5%, rgba(234, 179, 8, 0.14), transparent 60%),
                var(--school-bg);
        }
        .att-card {
            max-width: 1100px;
            margin: 0 auto;
            border-radius: 14px;
            box-shadow: 0 10px 28px rgba(6, 78, 59, 0.14);
            overflow: hidden;
            border: 1px solid var(--school-border);
        }
        .att-header {
            background: linear-gradient(120deg, var(--school-green-900) 0%, var(--school-green-700) 72%, var(--school-gold-600) 100%);
            color: #fff;
            padding: 1.25rem;
        }
        .att-body { padding: 1.5rem; background: #fff; }
        .event-meta { color: rgba(255,255,255,0.92); font-size: 0.9rem; margin-top: 0.25rem; }
        .table-attendees { margin-bottom: 0; border: 1px solid #e2e8f0; }
        .table-attendees th {
            background: #ecfdf5;
            color: #0f172a;
            font-weight: 700;
            border-bottom: 1px solid #bbf7d0;
        }
        .table-attendees tbody tr:nth-of-type(odd) { background: #fcfffd; }
        .table-attendees tbody tr:hover { background: #f0fdf4; }
        .att-count { color: #065f46 !important; font-weight: 600; }
        .att-stats { display: flex; flex-wrap: wrap; gap: 0.75rem 1.25rem; margin-bottom: 1rem; }
        .att-stat {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            padding: 0.5rem 0.85rem;
            font-size: 0.875rem;
        }
        .att-stat strong { color: #065f46; }
        .btn-export { border-color: #16a34a; color: #166534; }
        .btn-export:hover { background: #16a34a; border-color: #16a34a; color: #fff; }
        .btn-back { border-color: #ca8a04; color: #854d0e; }
        .btn-back:hover { background: #fef3c7; border-color: #ca8a04; color: #713f12; }
    </style>
</head>
<body>
    <div class="att-card card border-0">
        <div class="att-header">
            <h1 class="h5 mb-0"><i class="fas fa-clipboard-check me-2"></i>Activity attendance</h1>
            <p class="event-meta mb-0"><?= htmlspecialchars((string) ($session['title'] ?? 'Activity')) ?></p>
            <p class="event-meta mb-0"><?= htmlspecialchars((string) ($row['event_title'] ?? '')) ?></p>
            <?php if (!empty($session['schedule_date'])): ?>
                <p class="event-meta mb-0">
                    <?= date('l, M j, Y', strtotime((string) $session['schedule_date'])) ?>
                    <?php if ($timeStr !== ''): ?> · <?= htmlspecialchars($timeStr) ?><?php endif; ?>
                    <?php if (!empty($session['location'])): ?> · <?= htmlspecialchars($session['location']) ?><?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
        <div class="att-body">
            <?php if (!$tableOk): ?>
                <p class="text-muted mb-0">Activity check-in is not available yet. Run the database migration for day activities.</p>
            <?php else: ?>
            <div class="att-stats">
                <div class="att-stat"><strong><?= count($attendees) ?></strong> checked in</div>
                <?php if ($rsvpCount > 0): ?>
                    <div class="att-stat"><strong><?= $rsvpCount ?></strong> RSVP'd</div>
                <?php endif; ?>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <span class="text-muted att-count"><?= count($attendees) ?> <?= count($attendees) === 1 ? 'student' : 'students' ?> scanned the activity QR</span>
                <div class="d-flex flex-wrap gap-2">
                    <?php if (!empty($attendees)): ?>
                        <a href="<?= htmlspecialchars(BASE_URL . '/activity_attendance.php?id=' . $session_id . '&export=csv') ?>" class="btn btn-sm btn-outline-primary btn-export">
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
            <?php if (empty($attendees)): ?>
                <p class="text-muted mb-0">No one has checked in yet. Students scan the <strong>activity check-in QR</strong> at the venue to appear here.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-attendees table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Student ID</th>
                                <th>Course</th>
                                <th>Year level</th>
                                <th>School year (AY)</th>
                                <th>Department</th>
                                <th>Check-in time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendees as $i => $a): ?>
                                <?php
                                $course = trim((string) ($a['student_course'] ?? ''));
                                $yrl = trim((string) ($a['student_year_level'] ?? ''));
                                $ay = trim((string) ($a['student_academic_year'] ?? ''));
                                $rawDept = trim((string) ($a['department'] ?? ''));
                                $deptDisp = $rawDept === '' ? '—' : eventify_format_department_label($rawDept);
                                ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars($a['name'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($a['student_school_id'] ?? '—') ?></td>
                                    <td><?= $course !== '' ? htmlspecialchars($course) : '—' ?></td>
                                    <td><?= $yrl !== '' ? htmlspecialchars($yrl) : '—' ?></td>
                                    <td><?= $ay !== '' ? htmlspecialchars($ay) : '—' ?></td>
                                    <td><?= htmlspecialchars($deptDisp) ?></td>
                                    <td><?= !empty($a['checked_in_at']) ? date('M j, Y g:i A', strtotime($a['checked_in_at'])) : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
