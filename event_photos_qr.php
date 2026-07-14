<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';

// Only multimedia, admin, super_admin can generate photo QR
$allowed_roles = ['multimedia', 'admin', 'super_admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $allowed_roles, true)) {
    header('Location: ' . BASE_URL . '/views/login.php?error=' . urlencode('Access denied'));
    exit();
}

$event_id = (int) ($_GET['id'] ?? 0);
if ($event_id < 1) {
    header('Location: ' . BASE_URL . '?error=Invalid event');
    exit();
}

$stmt = $conn->prepare("SELECT id, title, date, start_time, end_time, location, status, department, checkin_token FROM events WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$res = $stmt->get_result();
$event = $res->fetch_assoc();
$stmt->close();

if (!$event) {
    $conn->close();
    header('Location: ' . BASE_URL . '?error=Event not found');
    exit();
}

// Ensure the event has a token we can reuse for photos
if (empty($event['checkin_token'])) {
    $event['checkin_token'] = bin2hex(random_bytes(16));
    $up = $conn->prepare("UPDATE events SET checkin_token = ? WHERE id = ?");
    $up->bind_param("si", $event['checkin_token'], $event_id);
    $up->execute();
    $up->close();
}

// Build gallery URL (students will see photos via this link)
$base_host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$gallery_url = $base_host . BASE_URL . '/photo_gallery.php?t=' . urlencode($event['checkin_token']);
$qr_image_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($gallery_url);

$conn->close();

$role = $_SESSION['role'] ?? '';
$back_url = BASE_URL . '/backend/auth/dashboard_multimedia.php';
if ($role === 'admin') {
    $back_url = BASE_URL . '/backend/admin/dashboard.php';
} elseif ($role === 'super_admin') {
    $back_url = BASE_URL . '/backend/super_admin/dashboardsuperadmin.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Photos QR - <?= htmlspecialchars($event['title']) ?> | EVENTIFY</title>
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
            --school-gold-dim: #b88f2a;
            --school-border: rgba(230, 197, 74, 0.42);
        }
        body {
            padding: 2rem 1rem;
            background: linear-gradient(180deg, var(--school-olive-top) 0%, var(--school-forest-mid) 42%, var(--school-forest-deep) 100%);
            background-attachment: fixed;
        }
        .qr-card {
            max-width: 460px;
            margin: 0 auto;
            border-radius: 16px;
            border: 2px solid var(--school-border);
            box-shadow: 0 14px 36px rgba(0,0,0,0.32);
            overflow: hidden;
        }
        .qr-header {
            background: linear-gradient(180deg, var(--school-forest-card) 0%, #021a08 100%);
            color: #fff;
            padding: 1.25rem;
            text-align: center;
            border-bottom: 3px solid var(--school-gold);
        }
        .qr-body {
            padding: 1.35rem;
            background: linear-gradient(180deg, #ffffff 0%, var(--school-cream) 100%);
        }
        .qr-body h5 { color: var(--school-forest-card); font-weight: 800; }
        .qr-body img {
            display: block;
            margin: 0 auto 1rem;
            border: 4px solid rgba(1, 50, 32, 0.18);
            border-radius: 12px;
            background: #fff;
        }
        .gallery-url { font-size: 0.8rem; word-break: break-all; color: #4b5563; }
        .event-meta { color: #4b5563; font-size: 0.9rem; }
        .event-meta i { color: var(--school-forest-card); }
        .dept-pill {
            display:inline-flex;
            align-items:center;
            padding:0.22rem 0.55rem;
            border-radius:999px;
            font-size:0.75rem;
            border:1px solid rgba(1, 50, 32, 0.25);
            background: rgba(27, 74, 27, 0.08);
            color: var(--school-forest-card);
            margin-top:0.35rem;
            font-weight: 700;
        }
        .btn-back-dashboard {
            background: rgba(255,255,255,0.85);
            border: 1px solid rgba(1, 50, 32, 0.35);
            color: var(--school-forest-card);
        }
        .btn-back-dashboard:hover {
            background: rgba(230, 197, 74, 0.22);
            border-color: var(--school-gold-dim);
            color: var(--school-forest-card);
        }
        @media (max-width: 576px) {
            body { padding: 1rem 0.6rem; }
            .qr-body { padding: 1rem; }
            .qr-body img { width: 260px; height: 260px; }
        }
        @media print {
            .no-print { display: none !important; }
            body {
                padding: 0;
                background: #fff;
            }
            .qr-card {
                max-width: none;
                margin: 0;
                box-shadow: none;
                border: 1px solid #cbd5e1;
            }
            .gallery-url { font-size: 0.7rem; }
        }
    </style>
</head>
<body>
    <div class="d-flex flex-wrap gap-2 justify-content-center mb-3 no-print" style="max-width: 460px; margin: 0 auto 1rem;">
        <button type="button" class="btn btn-light btn-sm border" onclick="window.print()">
            <i class="fas fa-print me-1"></i>Print / Save PDF
        </button>
        <a href="<?= $back_url ?>" class="btn btn-back-dashboard btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to dashboard</a>
    </div>
    <div class="qr-card card border-0">
        <div class="qr-header">
            <h1 class="h5 mb-1"><i class="fas fa-qrcode me-2"></i>Event Photos QR</h1>
            <p class="small mb-0 opacity-75">Print or display this so students can view the official photos.</p>
        </div>
        <div class="qr-body">
            <h5 class="mb-1"><?= htmlspecialchars($event['title']) ?></h5>
            <div class="event-meta mb-2">
                <?php if (!empty($event['date'])): ?>
                    <div><i class="fas fa-calendar-day me-2"></i><?= date('M j, Y', strtotime($event['date'])) ?><?php if (!empty($event['start_time'])): ?> · <?= date('g:i A', strtotime($event['start_time'])) ?><?php endif; ?></div>
                <?php endif; ?>
                <?php if (!empty($event['location'])): ?>
                    <div><i class="fas fa-map-marker-alt me-2"></i><?= htmlspecialchars($event['location']) ?></div>
                <?php endif; ?>
                <?php if (!empty($event['department']) && $event['department'] !== 'ALL'): ?>
                    <div class="dept-pill"><i class="fas fa-building me-1"></i><?= htmlspecialchars($event['department']) ?> only</div>
                <?php else: ?>
                    <div class="dept-pill"><i class="fas fa-users me-1"></i>All departments</div>
                <?php endif; ?>
            </div>
            <img src="<?= htmlspecialchars($qr_image_url) ?>" alt="QR Code for event photos" width="300" height="300">
            <p class="small text-muted mb-1">Students scan this QR to open the photo gallery (students only, filtered by department).</p>
            <p class="gallery-url mb-0" title="<?= htmlspecialchars($gallery_url) ?>"><?= htmlspecialchars($gallery_url) ?></p>
        </div>
    </div>
</body>
</html>

