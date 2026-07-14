<?php
session_start();
include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
require_once __DIR__ . '/backend/lib/event_day_sessions.php';
require_once __DIR__ . '/backend/lib/event_calendar.php';
require_once __DIR__ . '/backend/lib/event_checkin_security.php';

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
$stmt = $conn->prepare("SELECT {$colList}, e.title AS event_title, e.organizer_id FROM event_day_sessions s JOIN events e ON e.id = s.event_id WHERE s.id = ? LIMIT 1");
$stmt->bind_param('i', $session_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    header('Location: ' . BASE_URL . '?error=Activity not found');
    exit();
}

$organizerId = (int) ($row['organizer_id'] ?? 0);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? '';
if ($role === 'organizer' && $organizerId !== $userId) {
    header('Location: ' . BASE_URL . '/backend/auth/dashboardorganizer.php?error=' . urlencode('Access denied'));
    exit();
}

$session = eventify_map_day_session_row($row);
$token = eventify_ensure_session_checkin_token($conn, $session_id);
$conn->close();

$base_host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$landing_url = $base_host . BASE_URL . '/index.php?st=' . urlencode((string) $token);
$qr_image_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($landing_url);
$timeStr = eventify_format_session_time_range($session['start_time'] ?? null, $session['end_time'] ?? null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity QR - <?= htmlspecialchars($session['title']) ?> | EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { padding: 2rem; background: #f0f9f4; }
        .qr-card { max-width: 420px; margin: 0 auto; border-radius: 16px; box-shadow: 0 10px 30px rgba(6, 78, 59, 0.16); overflow: hidden; }
        .qr-header { background: linear-gradient(120deg, #064e3b, #047857); color: #fff; padding: 1.25rem; text-align: center; }
        .qr-body { padding: 1.5rem; background: #fff; }
        .qr-body img { display: block; margin: 0 auto 1rem; border: 4px solid #dcfce7; border-radius: 12px; }
        .checkin-url { font-size: 0.8rem; word-break: break-all; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.5rem; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; background: #fff; }
            .qr-card { box-shadow: none; max-width: none; }
        }
    </style>
</head>
<body>
    <div class="d-flex flex-wrap gap-2 justify-content-center mb-3 no-print">
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
            <i class="fas fa-print me-1"></i>Print / Save PDF
        </button>
    </div>
    <div class="qr-card card border-0">
        <div class="qr-header">
            <h1 class="h5 mb-0"><i class="fas fa-qrcode me-2"></i>Activity Check-in QR</h1>
            <p class="small mb-0 opacity-90 mt-1"><?= htmlspecialchars($row['event_title'] ?? '') ?></p>
        </div>
        <div class="qr-body">
            <h5 class="mb-2"><?= htmlspecialchars($session['title']) ?></h5>
            <div class="text-muted small mb-3">
                <?php if (!empty($session['schedule_date'])): ?>
                    <div><i class="fas fa-calendar-day me-2"></i><?= htmlspecialchars(date('M j, Y', strtotime($session['schedule_date']))) ?></div>
                <?php endif; ?>
                <?php if ($timeStr !== ''): ?>
                    <div><i class="fas fa-clock me-2"></i><?= htmlspecialchars($timeStr) ?></div>
                <?php endif; ?>
                <?php if (!empty($session['location'])): ?>
                    <div><i class="fas fa-map-marker-alt me-2"></i><?= htmlspecialchars($session['location']) ?></div>
                <?php endif; ?>
            </div>
            <img src="<?= htmlspecialchars($qr_image_url) ?>" alt="QR Code" width="300" height="300">
            <p class="small text-muted mb-1">Students scan this QR to check in for this activity.</p>
            <?php if (!eventify_checkin_config_geo_when_pinned()): ?>
                <!-- <p class="small text-info mb-1"><i class="fas fa-info-circle me-1"></i> Distance check is off for testing — QR check-in still works.</p> -->
            <?php endif; ?>
            <p class="checkin-url mb-0"><?= htmlspecialchars($landing_url) ?></p>
            <div class="mt-3 d-flex flex-wrap gap-2 no-print">
                <a href="<?= BASE_URL ?>/activity_attendance.php?id=<?= (int) $session_id ?>" class="btn btn-outline-info btn-sm">
                    <i class="fas fa-clipboard-check me-1"></i>View attendance
                </a>
                <?php
                $back_url = BASE_URL . '/backend/auth/dashboardorganizer.php';
                if ($role === 'admin') {
                    $back_url = BASE_URL . '/backend/admin/dashboard.php';
                }
                if ($role === 'super_admin') {
                    $back_url = BASE_URL . '/backend/super_admin/dashboardsuperadmin.php';
                }
                ?>
                <a href="<?= $back_url ?>" class="btn btn-outline-secondary btn-sm">Back to dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>
