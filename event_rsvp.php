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

$back_url = BASE_URL . '/backend/auth/dashboardorganizer.php?panel=events';
if ($role === 'admin') {
    $back_url = BASE_URL . '/backend/admin/dashboard.php';
}
if ($role === 'super_admin') {
    $back_url = BASE_URL . '/backend/super_admin/dashboardsuperadmin.php';
}

$totalCount = count($rows);
$presentCount = 0;
foreach ($rows as $row) {
    if (strtolower(trim((string) ($row['status'] ?? ''))) === 'present' || !empty($row['time_in'])) {
        $presentCount++;
    }
}
$absentCount = max(0, $totalCount - $presentCount);

$eventDateLabel = '';
$rawDate = trim((string) ($event['date'] ?? ''));
if ($rawDate !== '') {
    $ts = strtotime($rawDate);
    $eventDateLabel = $ts ? date('M j, Y', $ts) : $rawDate;
}

$pageTitle = htmlspecialchars($event['title']) . ' – RSVP list';

function rsvp_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function rsvp_format_datetime(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date('M j, Y · g:i A', $ts) : $value;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0A3C26">
    <title><?= $pageTitle ?> | EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --rsvp-nav: #0A3C26;
            --rsvp-sidebar: #062618;
            --rsvp-gold: #E5B22D;
            --rsvp-gold-dim: #C99A26;
            --rsvp-forest: #064e3b;
            --rsvp-text: #0f172a;
            --rsvp-muted: #64748b;
            --rsvp-border: rgba(1, 50, 32, 0.12);
            --rsvp-surface: #f8fafc;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, system-ui, -apple-system, sans-serif;
            color: var(--rsvp-text);
            background:
                radial-gradient(ellipse 70% 40% at 10% 0%, rgba(229, 178, 45, 0.18), transparent 55%),
                linear-gradient(165deg, #0A3C26 0%, #062618 45%, #041910 100%);
            padding: 1.15rem clamp(0.75rem, 3vw, 1.5rem) 2rem;
        }
        .rsvp-shell {
            max-width: 1100px;
            margin: 0 auto;
            background: linear-gradient(145deg, rgba(46, 204, 113, 0.1) 0%, #ffffff 40%, #f8fafc 100%);
            border: 1px solid rgba(1, 50, 32, 0.12);
            border-radius: 20px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.28);
            overflow: hidden;
            position: relative;
        }
        .rsvp-shell::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--rsvp-gold) 0%, #2ecc71 55%, var(--rsvp-gold-dim) 100%);
        }
        .rsvp-hero {
            padding: 1.15rem 1.2rem 1rem;
            border-bottom: 1px solid rgba(1, 50, 32, 0.08);
            background: rgba(255, 255, 255, 0.55);
        }
        .rsvp-crumb {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin: 0 0 0.85rem;
            font-size: 0.78rem;
            font-weight: 700;
            color: #047857;
            text-decoration: none;
        }
        .rsvp-crumb:hover { color: var(--rsvp-forest); text-decoration: underline; }
        .rsvp-hero__row {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.85rem 1rem;
        }
        .rsvp-hero__main {
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
            min-width: 0;
            flex: 1 1 16rem;
        }
        .rsvp-hero__icon {
            flex-shrink: 0;
            width: 3rem;
            height: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            background: linear-gradient(135deg, #064e3b 0%, #15803d 100%);
            color: var(--rsvp-gold);
            font-size: 1.2rem;
            box-shadow: 0 8px 20px rgba(6, 78, 59, 0.22);
        }
        .rsvp-hero__eyebrow {
            margin: 0 0 0.2rem;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #047857;
        }
        .rsvp-hero__title {
            margin: 0 0 0.3rem;
            font-size: clamp(1.2rem, 3vw, 1.45rem);
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--rsvp-forest);
            line-height: 1.25;
        }
        .rsvp-hero__subtitle {
            margin: 0;
            font-size: 0.86rem;
            line-height: 1.45;
            color: var(--rsvp-muted);
        }
        .rsvp-hero__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
        }
        .rsvp-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 0.95rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
            text-decoration: none;
            border: 1px solid transparent;
            transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
        }
        .rsvp-btn--primary {
            background: linear-gradient(180deg, #F0C85A 0%, var(--rsvp-gold) 100%);
            color: #0A3C26;
            border-color: var(--rsvp-gold-dim);
            box-shadow: 0 4px 12px rgba(229, 178, 45, 0.28);
        }
        .rsvp-btn--primary:hover { color: #062618; filter: brightness(1.03); }
        .rsvp-btn--ghost {
            background: #fff;
            color: var(--rsvp-forest);
            border-color: var(--rsvp-border);
        }
        .rsvp-btn--ghost:hover {
            border-color: rgba(229, 178, 45, 0.45);
            background: rgba(249, 237, 0, 0.12);
            color: #064e3b;
        }
        .rsvp-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin-top: 0.9rem;
        }
        .rsvp-stat {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: #064e3b;
            background: rgba(249, 237, 0, 0.22);
            border: 1px solid rgba(230, 197, 74, 0.42);
            border-radius: 999px;
            padding: 0.38rem 0.72rem;
        }
        .rsvp-stat--ok {
            background: rgba(46, 204, 113, 0.14);
            border-color: rgba(46, 204, 113, 0.35);
            color: #047857;
        }
        .rsvp-stat--muted {
            background: rgba(100, 116, 139, 0.1);
            border-color: rgba(100, 116, 139, 0.22);
            color: #475569;
        }
        .rsvp-stat i { color: var(--rsvp-gold); }
        .rsvp-stat--ok i { color: #16a34a; }
        .rsvp-stat--muted i { color: #64748b; }
        .rsvp-body { padding: 1rem 1.15rem 1.25rem; }
        .rsvp-empty {
            text-align: center;
            padding: 2.5rem 1.25rem;
            background: #fff;
            border: 1px dashed rgba(1, 50, 32, 0.18);
            border-radius: 16px;
        }
        .rsvp-empty__icon {
            width: 3.25rem;
            height: 3.25rem;
            margin: 0 auto 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            background: rgba(6, 78, 59, 0.08);
            color: var(--rsvp-gold);
            font-size: 1.35rem;
        }
        .rsvp-empty__title {
            margin: 0 0 0.35rem;
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--rsvp-forest);
        }
        .rsvp-empty__text {
            margin: 0;
            color: var(--rsvp-muted);
            font-size: 0.88rem;
        }
        .rsvp-table-wrap {
            background: #fff;
            border: 1px solid var(--rsvp-border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
        }
        .rsvp-table {
            width: 100%;
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
        }
        .rsvp-table thead th {
            background: rgba(6, 78, 59, 0.06);
            color: #047857;
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            border-bottom: 1px solid var(--rsvp-border);
            padding: 0.75rem 0.85rem;
            white-space: nowrap;
        }
        .rsvp-table tbody td {
            border-bottom: 1px solid rgba(1, 50, 32, 0.08);
            padding: 0.85rem;
            vertical-align: middle;
            font-size: 0.88rem;
        }
        .rsvp-table tbody tr:last-child td { border-bottom: none; }
        .rsvp-table tbody tr:hover { background: rgba(249, 237, 0, 0.1); }
        .rsvp-name {
            font-weight: 700;
            color: var(--rsvp-text);
        }
        .rsvp-meta {
            display: block;
            margin-top: 0.15rem;
            font-size: 0.76rem;
            color: var(--rsvp-muted);
        }
        .rsvp-num {
            font-weight: 700;
            color: #64748b;
            font-size: 0.8rem;
        }
        .rsvp-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.28rem 0.65rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.02em;
        }
        .rsvp-pill--present {
            background: rgba(46, 204, 113, 0.16);
            color: #047857;
            border: 1px solid rgba(46, 204, 113, 0.35);
        }
        .rsvp-pill--absent {
            background: rgba(100, 116, 139, 0.12);
            color: #475569;
            border: 1px solid rgba(100, 116, 139, 0.25);
        }
        .rsvp-pill--other {
            background: rgba(6, 78, 59, 0.1);
            color: #064e3b;
            border: 1px solid rgba(6, 78, 59, 0.18);
        }
        .rsvp-checkin {
            font-size: 0.8rem;
            font-weight: 600;
            color: #047857;
        }
        .rsvp-checkin--empty { color: #94a3b8; font-weight: 500; }
        @media (max-width: 760px) {
            .rsvp-table thead { display: none; }
            .rsvp-table, .rsvp-table tbody, .rsvp-table tr, .rsvp-table td {
                display: block;
                width: 100%;
            }
            .rsvp-table tr {
                padding: 0.85rem;
                border-bottom: 1px solid rgba(1, 50, 32, 0.08);
            }
            .rsvp-table tr:last-child { border-bottom: none; }
            .rsvp-table td {
                border: none !important;
                padding: 0.2rem 0 !important;
            }
            .rsvp-table td::before {
                content: attr(data-label);
                display: block;
                font-size: 0.65rem;
                font-weight: 800;
                letter-spacing: 0.06em;
                text-transform: uppercase;
                color: #047857;
                margin-bottom: 0.15rem;
            }
            .rsvp-table td[data-label=""]::before { display: none; }
            .rsvp-num { margin-bottom: 0.35rem; }
        }
    </style>
</head>
<body>
<div class="rsvp-shell">
    <header class="rsvp-hero">
        <a class="rsvp-crumb" href="<?= rsvp_h($back_url) ?>">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Back to dashboard
        </a>
        <div class="rsvp-hero__row">
            <div class="rsvp-hero__main">
                <div class="rsvp-hero__icon" aria-hidden="true"><i class="fas fa-user-check"></i></div>
                <div>
                    <p class="rsvp-hero__eyebrow">Registrations</p>
                    <h1 class="rsvp-hero__title">RSVP list</h1>
                    <p class="rsvp-hero__subtitle">
                        <?= rsvp_h((string) ($event['title'] ?? 'Event')) ?>
                        <?php if ($eventDateLabel !== ''): ?>
                            · <?= rsvp_h($eventDateLabel) ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="rsvp-hero__actions">
                <a class="rsvp-btn rsvp-btn--ghost" href="<?= rsvp_h($back_url) ?>">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> Back
                </a>
                <a class="rsvp-btn rsvp-btn--primary" href="?id=<?= (int) $event_id ?>&export=csv">
                    <i class="fas fa-download" aria-hidden="true"></i> Export CSV
                </a>
            </div>
        </div>
        <div class="rsvp-stats" aria-label="RSVP summary">
            <span class="rsvp-stat">
                <i class="fas fa-users" aria-hidden="true"></i>
                <?= (int) $totalCount ?> registered
            </span>
            <span class="rsvp-stat rsvp-stat--ok">
                <i class="fas fa-check-circle" aria-hidden="true"></i>
                <?= (int) $presentCount ?> checked in
            </span>
            <span class="rsvp-stat rsvp-stat--muted">
                <i class="fas fa-user-clock" aria-hidden="true"></i>
                <?= (int) $absentCount ?> not checked in
            </span>
        </div>
    </header>

    <div class="rsvp-body">
        <?php if ($rows === []): ?>
            <div class="rsvp-empty">
                <div class="rsvp-empty__icon" aria-hidden="true"><i class="fas fa-user-plus"></i></div>
                <h2 class="rsvp-empty__title">No registrations yet</h2>
                <p class="rsvp-empty__text">When students RSVP for this event, they will appear here.</p>
            </div>
        <?php else: ?>
            <div class="rsvp-table-wrap">
                <div class="table-responsive">
                    <table class="rsvp-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th>ID</th>
                                <th>Registered</th>
                                <th>Status</th>
                                <th>Check-in</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $n = 1; foreach ($rows as $row): ?>
                                <?php
                                    $statusRaw = strtolower(trim((string) ($row['status'] ?? 'registered')));
                                    $checkedIn = !empty($row['time_in']) || $statusRaw === 'present';
                                    $statusLabel = $checkedIn ? 'Checked in' : ($statusRaw === 'absent' ? 'Not checked in' : ucfirst($statusRaw !== '' ? $statusRaw : 'Registered'));
                                    $pillClass = $checkedIn ? 'rsvp-pill--present' : ($statusRaw === 'absent' ? 'rsvp-pill--absent' : 'rsvp-pill--other');
                                ?>
                                <tr>
                                    <td data-label="" class="rsvp-num"><?= $n++ ?></td>
                                    <td data-label="Student">
                                        <span class="rsvp-name"><?= rsvp_h((string) ($row['name'] ?? '')) ?></span>
                                        <span class="rsvp-meta"><?= rsvp_h((string) ($row['email'] ?? '')) ?></span>
                                    </td>
                                    <td data-label="ID"><?= rsvp_h((string) ($row['school_id'] ?? '—')) ?></td>
                                    <td data-label="Registered"><?= rsvp_h(rsvp_format_datetime($row['registration_date'] ?? null)) ?></td>
                                    <td data-label="Status">
                                        <span class="rsvp-pill <?= $pillClass ?>">
                                            <?php if ($checkedIn): ?><i class="fas fa-check" aria-hidden="true"></i><?php endif; ?>
                                            <?= rsvp_h($statusLabel) ?>
                                        </span>
                                    </td>
                                    <td data-label="Check-in">
                                        <?php if (!empty($row['time_in'])): ?>
                                            <span class="rsvp-checkin"><?= rsvp_h(rsvp_format_datetime($row['time_in'])) ?></span>
                                        <?php else: ?>
                                            <span class="rsvp-checkin rsvp-checkin--empty">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
