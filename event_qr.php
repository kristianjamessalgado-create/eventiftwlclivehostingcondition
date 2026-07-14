<?php

session_start();

include __DIR__ . '/config/db.php';

include __DIR__ . '/config/config.php';

require_once __DIR__ . '/backend/lib/event_calendar.php';

require_once __DIR__ . '/backend/lib/event_status_auto.php';

require_once __DIR__ . '/backend/lib/nav_helpers.php';



eventify_run_dashboard_maintenance($conn);



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



$stmt = $conn->prepare("SELECT id, title, date, end_date, start_time, end_time, location, status, checkin_token, organizer_id FROM events WHERE id = ?");

$stmt->bind_param("i", $event_id);

$stmt->execute();

$res = $stmt->get_result();

$event = $res->fetch_assoc();

$stmt->close();



if (!$event) {

    header('Location: ' . BASE_URL . '?error=Event not found');

    exit();

}



if (empty($event['checkin_token'])) {

    $event['checkin_token'] = bin2hex(random_bytes(16));

    $up = $conn->prepare("UPDATE events SET checkin_token = ? WHERE id = ?");

    $up->bind_param("si", $event['checkin_token'], $event_id);

    $up->execute();

    $up->close();

}



$base_host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$landing_url = $base_host . BASE_URL . '/index.php?t=' . urlencode($event['checkin_token']);

$qr_image_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($landing_url);



$eventLive = eventify_event_is_live($event);

$statusLabel = eventify_event_status_ui($event);

$role = $_SESSION['role'] ?? '';

$dashNav = eventify_dashboard_nav_for_role($role);



$conn->close();



$shell_title = 'Event check-in QR';

$shell_subtitle = (string) ($event['title'] ?? '');

$shell_page_title = 'Event QR — ' . $shell_subtitle;

$shell_back_url = $dashNav['url'];

$shell_back_label = $dashNav['label'];

$shell_body_class = 'eventify-standalone' . (in_array($role, ['admin', 'super_admin'], true) ? ' eventify-standalone--admin' : '');

include __DIR__ . '/views/partials/standalone_page_shell_open.php';

?>

<style>

    .qr-card-inner {

        max-width: 420px;

        margin: 0 auto;

        border-radius: 16px;

        border: 1px solid var(--efs-border);

        overflow: hidden;

        background: #fff;

        box-shadow: 0 8px 24px rgba(6, 78, 59, 0.1);

    }

    .qr-card-inner__head {

        background: linear-gradient(120deg, var(--efs-green-900) 0%, var(--efs-green-700) 72%, #ca8a04 100%);

        color: #fff;

        padding: 1.1rem;

        text-align: center;

    }

    .qr-card-inner__body { padding: 1.25rem; text-align: center; }

    .qr-card-inner__body img {

        border: 4px solid #dcfce7;

        border-radius: 12px;

        margin-bottom: 1rem;

    }

    .checkin-url {

        font-size: 0.78rem;

        word-break: break-all;

        color: #475569;

        background: #f8fafc;

        border: 1px solid #e2e8f0;

        border-radius: 8px;

        padding: 0.5rem 0.6rem;

        text-align: left;

    }

    @media print {
        .no-print,
        .efs-topbar,
        .efs-page-head { display: none !important; }
        body.eventify-standalone { background: #fff; }
        .efs-main { max-width: none; padding: 0; margin: 0; }
        .qr-card-inner { box-shadow: none; border: 1px solid #cbd5e1; max-width: none; }
        .checkin-url { font-size: 0.7rem; }
    }

</style>



    <div class="d-flex flex-wrap gap-2 justify-content-center mb-3 no-print">
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
            <i class="fas fa-print me-1"></i>Print / Save PDF
        </button>
    </div>

    <div class="qr-card-inner">

        <div class="qr-card-inner__head">

            <h2 class="h6 mb-0"><i class="fas fa-qrcode me-2"></i>Display at venue</h2>

            <p class="small mb-0 opacity-90 mt-1">Students scan to confirm attendance</p>

        </div>

        <div class="qr-card-inner__body">

            <?php if (!$eventLive): ?>

                <div class="alert alert-warning py-2 small mb-3 text-start">

                    <i class="fas fa-lock me-1"></i>

                    This event is <strong><?= htmlspecialchars($statusLabel['label']) ?></strong>.

                    Students cannot check in with this QR anymore.

                </div>

            <?php endif; ?>

            <div class="text-start mb-3">

                <?php if (!empty($event['date'])): ?>

                    <div class="small text-muted"><i class="fas fa-calendar-day me-2"></i><?= htmlspecialchars(eventify_format_event_date_range($event)) ?></div>

                <?php endif; ?>

                <?php if (!empty($event['location'])): ?>

                    <div class="small text-muted"><i class="fas fa-map-marker-alt me-2"></i><?= htmlspecialchars($event['location']) ?></div>

                <?php endif; ?>

            </div>

            <img src="<?= htmlspecialchars($qr_image_url) ?>" alt="QR Code" width="280" height="280">

            <p class="small text-muted mb-2"><?= $eventLive ? 'Students scan this QR to confirm attendance.' : 'Check-in is disabled for this event.' ?></p>

            <p class="checkin-url mb-0" title="<?= htmlspecialchars($landing_url) ?>"><?= htmlspecialchars($landing_url) ?></p>

        </div>

    </div>



<?php include __DIR__ . '/views/partials/standalone_page_shell_close.php'; ?>

