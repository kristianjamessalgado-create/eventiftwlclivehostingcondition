<?php
/**
 * RSVP list (all registrations) for an event — organizer / admin / super_admin.
 * CSV export: ?id=1&export=csv
 */
session_start();
include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';

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

$stmt = $conn->prepare("SELECT id, title, date, location, organizer_id FROM events WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    header('Location: ' . BASE_URL . '?error=Event not found');
    exit();
}

$role = $_SESSION['role'] ?? '';
if ($role === 'organizer' && (int) $event['organizer_id'] !== (int) $_SESSION['user_id']) {
    header('Location: ' . BASE_URL . '?error=Access denied');
    exit();
}

$rows = [];
$st = $conn->prepare("
    SELECT r.user_id, r.registration_date, r.status, r.time_in, u.name, u.email, u.user_id AS school_id
    FROM registrations r
    JOIN users u ON r.user_id = u.id
    WHERE r.event_id = ?
    ORDER BY r.registration_date ASC
");
$st->bind_param("i", $event_id);
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}
$st->close();

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="event_rsvp_' . (int) $event['id'] . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['#', 'Name', 'Email', 'Student/Staff ID', 'Registered at', 'RSVP status', 'Check-in time']);
    $i = 1;
    foreach ($rows as $row) {
        fputcsv($out, [
            $i++,
            $row['name'] ?? '',
            $row['email'] ?? '',
            $row['school_id'] ?? '',
            $row['registration_date'] ?? '',
            $row['status'] ?? '',
            $row['time_in'] ?? '',
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
}
if ($role === 'super_admin') {
    $back_url = BASE_URL . '/backend/super_admin/dashboardsuperadmin.php';
}

$pageTitle = htmlspecialchars($event['title']) . ' – RSVP list';
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
            --school-cream: #f7f4e7;
            --school-olive-top: #b7be77;
            --school-forest-mid: #3f6a2a;
            --school-forest-deep: #153313;
            --school-forest-card: #1b4a1b;
            --school-gold: #e6c54a;
            --school-border: rgba(230, 197, 74, 0.42);
        }
        body {
            padding: 1.25rem;
            background: linear-gradient(180deg, var(--school-olive-top) 0%, var(--school-forest-mid) 42%, var(--school-forest-deep) 100%);
            background-attachment: fixed;
        }
        .rsvp-card {
            max-width: 980px;
            margin: 0 auto;
            border-radius: 14px;
            border: 2px solid var(--school-border);
            box-shadow: 0 12px 32px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .rsvp-header {
            background: linear-gradient(180deg, var(--school-forest-card) 0%, #021a08 100%);
            border-bottom: 3px solid var(--school-gold);
            color: #fff;
        }
        .rsvp-title { color: #fff; font-weight: 800; }
        .rsvp-subtitle { color: rgba(247, 244, 231, 0.86) !important; }
        .rsvp-body { background: linear-gradient(180deg, #ffffff 0%, var(--school-cream) 100%); }
        .rsvp-table thead th {
            background: rgba(27, 74, 27, 0.10);
            color: var(--school-forest-card);
            font-size: 0.78rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            border-bottom: 1px solid rgba(1, 50, 32, 0.2);
        }
        .rsvp-table tbody td { border-color: rgba(1, 50, 32, 0.12); vertical-align: middle; }
        .rsvp-table tbody tr:hover { background: rgba(230, 197, 74, 0.16); }
        .rsvp-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            border: 1px solid rgba(1, 50, 32, 0.2);
            background: rgba(27, 74, 27, 0.08);
            color: var(--school-forest-card);
        }
        .btn-rsvp-back {
            border-color: rgba(230, 197, 74, 0.65);
            color: #fff;
            background: rgba(255, 255, 255, 0.08);
        }
        .btn-rsvp-back:hover {
            color: var(--school-gold);
            border-color: var(--school-gold);
            background: rgba(230, 197, 74, 0.12);
        }
        .btn-rsvp-export {
            background: linear-gradient(180deg, var(--school-forest-card) 0%, var(--school-forest-deep) 100%);
            color: var(--school-gold);
            border: 2px solid var(--school-gold);
        }
        .btn-rsvp-export:hover { color: #fff7a8; border-color: #fff7a8; }
    </style>
</head>
<body>
<div class="card rsvp-card">
    <div class="card-header rsvp-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-0 rsvp-title"><i class="fas fa-user-check me-2"></i>RSVP list</h5>
            <small class="rsvp-subtitle"><?= htmlspecialchars($event['title']) ?> · <?= htmlspecialchars($event['date'] ?? '') ?></small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-rsvp-back btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
            <a href="?id=<?= (int)$event_id ?>&export=csv" class="btn btn-rsvp-export btn-sm"><i class="fas fa-download"></i> Export CSV</a>
        </div>
    </div>
    <div class="card-body p-0 rsvp-body">
        <?php if (empty($rows)): ?>
            <p class="text-muted p-4 mb-0">No registrations yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0 rsvp-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>ID</th>
                            <th>Registered</th>
                            <th>Status</th>
                            <th>Check-in</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $n = 1; foreach ($rows as $row): ?>
                            <tr>
                                <td><?= $n++ ?></td>
                                <td><?= htmlspecialchars($row['name'] ?? '') ?></td>
                                <td class="small"><?= htmlspecialchars($row['email'] ?? '') ?></td>
                                <td class="small"><?= htmlspecialchars($row['school_id'] ?? '') ?></td>
                                <td class="small"><?= htmlspecialchars($row['registration_date'] ?? '') ?></td>
                                <td class="small"><span class="rsvp-pill"><?= htmlspecialchars(ucfirst((string)($row['status'] ?? 'registered'))) ?></span></td>
                                <td class="small"><?= htmlspecialchars($row['time_in'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
