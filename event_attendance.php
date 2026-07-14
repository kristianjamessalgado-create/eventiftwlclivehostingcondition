<?php
session_start();
include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/student_profile_fields.php';
require_once __DIR__ . '/config/departments.php';

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

$stmt = $conn->prepare("SELECT id, title, date, start_time, end_time, location, organizer_id FROM events WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$res = $stmt->get_result();
$event = $res->fetch_assoc();
$stmt->close();

if (!$event) {
    header('Location: ' . BASE_URL . '?error=Event not found');
    exit();
}

$role = $_SESSION['role'] ?? '';
// Organizers may only view attendance for their own events
if ($role === 'organizer' && (int) $event['organizer_id'] !== (int) $_SESSION['user_id']) {
    header('Location: ' . BASE_URL . '?error=Access denied');
    exit();
}

eventify_users_ensure_student_profile_fields($conn);

// Attendees: students who checked in (status = present)
$attendees = [];
$st = $conn->prepare("
    SELECT r.time_in, u.name, u.user_id AS student_school_id,
           u.student_course, u.student_year_level, u.student_academic_year, u.department
    FROM registrations r
    JOIN users u ON r.user_id = u.id
    WHERE r.event_id = ? AND r.status = 'present'
    ORDER BY r.time_in ASC
");
$st->bind_param("i", $event_id);
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) {
    $attendees[] = $row;
}
$st->close();

// Optional CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="event_attendance_' . (int)$event['id'] . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['#', 'Student Name', 'Student ID', 'Course', 'Year level', 'School year (AY)', 'Department / college', 'Check-in time']);
    $i = 1;
    foreach ($attendees as $row) {
        $rawDept = trim((string) ($row['department'] ?? ''));
        $deptLabel = $rawDept === ''
            ? ''
            : (function_exists('eventify_format_department_label')
                ? eventify_format_department_label($rawDept)
                : $rawDept);
        fputcsv($out, [
            $i++,
            $row['name'] ?? '',
            $row['student_school_id'] ?? '',
            $row['student_course'] ?? '',
            $row['student_year_level'] ?? '',
            $row['student_academic_year'] ?? '',
            $deptLabel,
            $row['time_in'] ?? '',
        ]);
    }
    fclose($out);
    $conn->close();
    exit();
}

$conn->close();

// Back link by role
$back_url = BASE_URL . '/backend/auth/dashboardorganizer.php';
if ($role === 'admin') {
    $back_url = BASE_URL . '/backend/admin/dashboard.php';
}
if ($role === 'super_admin') {
    $back_url = BASE_URL . '/backend/super_admin/dashboardsuperadmin.php';
}

$pageTitle = htmlspecialchars($event['title']) . ' – Attendance';
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
            --school-green-800: #065f46;
            --school-green-700: #047857;
            --school-green-100: #dcfce7;
            --school-gold-500: #eab308;
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
        .btn-export {
            border-color: #16a34a;
            color: #166534;
        }
        .btn-export:hover {
            background: #16a34a;
            border-color: #16a34a;
            color: #fff;
        }
        .btn-back {
            border-color: #ca8a04;
            color: #854d0e;
        }
        .btn-back:hover {
            background: #fef3c7;
            border-color: #ca8a04;
            color: #713f12;
        }
    </style>
</head>
<body>
    <div class="att-card card border-0">
        <div class="att-header">
            <h1 class="h5 mb-0"><i class="fas fa-clipboard-check me-2"></i>Attendance</h1>
            <p class="event-meta mb-0"><?= htmlspecialchars($event['title']) ?></p>
            <?php if (!empty($event['date'])): ?>
                <p class="event-meta mb-0"><?= date('l, M j, Y', strtotime($event['date'])) ?><?= !empty($event['location']) ? ' · ' . htmlspecialchars($event['location']) : '' ?></p>
            <?php endif; ?>
        </div>
        <div class="att-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-muted att-count"><?= count($attendees) ?> <?= count($attendees) === 1 ? 'attendee' : 'attendees' ?></span>
                <div class="d-flex gap-2">
                    <?php if (!empty($attendees)): ?>
                        <a href="<?= htmlspecialchars(BASE_URL . '/event_attendance.php?id=' . (int)$event['id'] . '&export=csv') ?>" class="btn btn-sm btn-outline-primary btn-export">
                            <i class="fas fa-file-export me-1"></i>Export CSV
                        </a>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-outline-secondary btn-sm btn-back"><i class="fas fa-arrow-left me-1"></i>Back to dashboard</a>
                </div>
            </div>
            <?php if (empty($attendees)): ?>
                <p class="text-muted mb-0">No one has checked in yet. Students can scan the event QR to confirm attendance.</p>
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
                                    <td><?= $a['time_in'] ? date('M j, Y g:i A', strtotime($a['time_in'])) : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
