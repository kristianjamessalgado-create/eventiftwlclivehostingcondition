<?php
/**
 * Student attendance history — main event and activity QR check-ins.
 */
session_start();

include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
require_once __DIR__ . '/backend/lib/event_day_sessions.php';
require_once __DIR__ . '/backend/lib/event_checkin_security.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ' . BASE_URL . '/views/login.php?redirect=' . urlencode(
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . BASE_URL . '/attendance_history.php'
    ));
    exit();
}

$userId = (int) $_SESSION['user_id'];
$menuUserName = trim((string) ($_SESSION['name'] ?? ''));
$filter = strtolower(trim((string) ($_GET['filter'] ?? 'all')));
if (!in_array($filter, ['all', 'events', 'activities'], true)) {
    $filter = 'all';
}

$history = ['items' => [], 'counts' => ['events' => 0, 'activities' => 0, 'total' => 0]];
$studentDept = null;
$deptStmt = $conn->prepare('SELECT department FROM users WHERE id = ? LIMIT 1');
if ($deptStmt) {
    $deptStmt->bind_param('i', $userId);
    $deptStmt->execute();
    $deptRow = $deptStmt->get_result()->fetch_assoc();
    $deptStmt->close();
    $studentDept = $deptRow['department'] ?? null;
}
try {
    $history = eventify_load_student_attendance_history($conn, $userId, $filter, 200);
} catch (Throwable $e) {
    $history = ['items' => [], 'counts' => ['events' => 0, 'activities' => 0, 'total' => 0]];
}
$items = $history['items'];
$counts = $history['counts'];

$hubUrl = BASE_URL . '/activities_hub.php';
$studentHubHomeUrl = $hubUrl;
$mainHubUrl = $hubUrl;
$showMainHubNav = false;
try {
    eventify_event_day_sessions_ensure_enhanced($conn);
    $studentHubHomeUrl = eventify_student_activities_hub_home_url($conn, $userId, $studentDept);
    $mainHubUrl = eventify_activities_hub_main_url($conn, $userId, 'student', $studentDept);
    $showMainHubNav = rtrim((string) $mainHubUrl, '/') !== rtrim((string) $hubUrl, '/');
} catch (Throwable $e) {
    $studentHubHomeUrl = $hubUrl;
    $mainHubUrl = $hubUrl;
    $showMainHubNav = false;
}
$conn->close();

$dashboardUrl = BASE_URL . '/backend/auth/dashboard_student.php';
$pageUrl = BASE_URL . '/attendance_history.php';

function ah_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/** @param array<string, mixed> $item */
function ah_attendance_item_href(array $item): string
{
    $eventId = (int) ($item['event_id'] ?? 0);
    if ($eventId < 1) {
        return BASE_URL . '/activities_hub.php';
    }
    $url = BASE_URL . '/event_activities.php?id=' . $eventId;
    if (($item['kind'] ?? '') === 'activity' && (int) ($item['session_id'] ?? 0) > 0) {
        $url .= '&activity=' . (int) $item['session_id'];
    }
    return $url;
}

/** @param array<string, mixed> $item */
function ah_attendance_time_label(array $item): string
{
    $checkedIn = trim((string) ($item['checked_in_at'] ?? ''));
    if ($checkedIn !== '') {
        $ts = strtotime($checkedIn);
        if ($ts) {
            return date('g:i A', $ts);
        }
    }
    if (($item['kind'] ?? '') === 'activity') {
        $start = trim((string) ($item['start_time'] ?? ''));
        if ($start !== '') {
            $ts = strtotime('1970-01-01 ' . substr($start, 0, 8));
            if ($ts) {
                return date('g:i A', $ts);
            }
        }
    }
    return '—';
}

$byDate = [];
foreach ($items as $item) {
    $checkedIn = trim((string) ($item['checked_in_at'] ?? ''));
    $ymd = $checkedIn !== '' ? substr($checkedIn, 0, 10) : substr((string) ($item['schedule_date'] ?? ''), 0, 10);
    if ($ymd === '') {
        $ymd = 'unknown';
    }
    if (!isset($byDate[$ymd])) {
        $byDate[$ymd] = [];
    }
    $byDate[$ymd][] = $item;
}

$thisYear = date('Y');
$thisYearCount = 0;
foreach ($items as $item) {
    $checkedIn = trim((string) ($item['checked_in_at'] ?? ''));
    if ($checkedIn !== '' && substr($checkedIn, 0, 4) === $thisYear) {
        $thisYearCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0A3C26">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="EVENTIFY">
    <link rel="manifest" href="<?= BASE_URL ?>/manifest-student.php">
    <title>Check-in history | EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/event_activities_hub.css?v=46">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/eventify_modal.css?v=3">
</head>
<body class="event-activities-hub event-activities-hub--index event-activities-hub--student">
<div class="eah-wrap">
    <header class="eah-topbar">
        <button type="button" class="eah-topbar__menu" id="eahNavOpen" aria-label="Open menu" aria-expanded="false" aria-controls="eahNavDrawer">
            <i class="fas fa-bars"></i>
        </button>
        <a class="eah-topbar__logo" href="<?= ah_h($studentHubHomeUrl) ?>"><i class="fas fa-calendar-alt" aria-hidden="true"></i><span>EVENTIFY</span></a>
        <div class="eah-topbar__actions">
            <a class="eah-topbar__action" href="<?= ah_h($hubUrl) ?>">
                <i class="fas fa-th-large me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">My registrations</span>
            </a>
            <a class="eah-topbar__action" href="<?= ah_h($dashboardUrl) ?>" aria-label="Dashboard">
                <i class="fas fa-gauge-high me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Dashboard</span>
            </a>
            <a class="eah-topbar__action is-active" href="<?= ah_h($pageUrl) ?>" aria-current="page">
                <i class="fas fa-clipboard-check me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">History</span>
            </a>
        </div>
    </header>

    <div class="eah-nav-drawer" id="eahNavDrawer" aria-hidden="true">
        <div class="eah-nav-drawer__backdrop" id="eahNavBackdrop" tabindex="-1"></div>
        <nav class="eah-nav-drawer__panel" id="eahNavPanel" role="navigation" aria-label="Check-in history menu">
            <div class="eah-nav-drawer__head">
                <div>
                    <div class="eah-nav-drawer__brand">EVENTIFY</div>
                    <?php if ($menuUserName !== ''): ?>
                        <div class="eah-nav-drawer__user"><?= ah_h($menuUserName) ?></div>
                    <?php endif; ?>
                </div>
                <button type="button" class="eah-nav-drawer__close" id="eahNavClose" aria-label="Close menu">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php
                $studentNavActive = 'attendance';
                $studentNavDashboardUrl = $dashboardUrl;
                $studentNavHubUrl = $hubUrl;
                $studentNavMainHubUrl = $mainHubUrl;
                $studentNavShowMainHub = $showMainHubNav;
                $studentNavMainHubLabel = 'This event';
                $studentNavMainHubHint = 'Continue where you left off';
                $studentNavTicketsUrl = BASE_URL . '/backend/auth/dashboard_student.php?panel=tickets';
                $studentNavTicketsHint = 'Your digital passes';
                $studentNavAttendanceCount = (int) ($counts['total'] ?? 0);
                include __DIR__ . '/views/partials/student_hub_nav_items.php';
            ?>
            <div class="eah-nav-drawer__footer">
                <a class="eah-nav-drawer__link eah-nav-drawer__link--danger" href="<?= ah_h(BASE_URL . '/backend/auth/logout.php') ?>" data-logout-confirm>
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Log out</span>
                </a>
            </div>
        </nav>
    </div>

    <div class="eah-hub-panel eah-hub-panel--student">
        <div class="eah-student-hub-shell">
            <header class="eah-student-hub-hero">
                <nav class="eah-student-hub-hero__crumb" aria-label="Breadcrumb">
                    <a class="eah-student-hub-hero__crumb-link" href="<?= ah_h($hubUrl) ?>">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i> My registrations
                    </a>
                </nav>
                <div class="eah-student-hub-hero__main">
                    <div class="eah-student-hub-hero__icon" aria-hidden="true"><i class="fas fa-clipboard-check"></i></div>
                    <div class="eah-student-hub-hero__copy">
                        <p class="eah-student-hub-hero__eyebrow">Full list</p>
                        <h1 class="eah-student-hub-hero__title">Check-in history</h1>
                        <p class="eah-student-hub-hero__subtitle">
                            Every event and activity you checked into with QR — filter by type below.
                        </p>
                    </div>
                </div>
                <?php if ($counts['total'] > 0): ?>
                <div class="eah-student-hub-hero__stats">
                    <span class="eah-student-hub-stat">
                        <i class="fas fa-check-circle" aria-hidden="true"></i>
                        <?= (int) $counts['total'] ?> check-in<?= $counts['total'] === 1 ? '' : 's' ?>
                    </span>
                    <?php if ($thisYearCount > 0): ?>
                    <span class="eah-student-hub-stat eah-student-hub-stat--live">
                        <i class="fas fa-calendar" aria-hidden="true"></i>
                        <?= (int) $thisYearCount ?> this year
                    </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </header>

            <div class="eah-student-hub-body">
                <div class="eah-hub-toolbar eah-hub-toolbar--attendance">
                    <div class="eah-attendance-filter" role="group" aria-label="Filter check-in history">
                        <?php
                        $filters = [
                            'all' => ['label' => 'All', 'count' => $counts['total']],
                            'events' => ['label' => 'Events', 'count' => $counts['events']],
                            'activities' => ['label' => 'Activities', 'count' => $counts['activities']],
                        ];
                        foreach ($filters as $key => $meta):
                            $active = $filter === $key;
                        ?>
                        <a class="eah-attendance-filter__chip<?= $active ? ' is-active' : '' ?>"
                           href="<?= ah_h($pageUrl . '?filter=' . $key) ?>"
                           <?= $active ? 'aria-current="page"' : '' ?>>
                            <?= ah_h($meta['label']) ?>
                            <?php if ($meta['count'] > 0): ?>
                                <span class="eah-attendance-filter__count"><?= (int) $meta['count'] ?></span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($items === []): ?>
                    <div class="eah-student-hub-empty">
                        <div class="eah-student-hub-empty__icon" aria-hidden="true"><i class="fas fa-clipboard-check"></i></div>
                        <h3 class="eah-student-hub-empty__title">No check-ins yet</h3>
                        <p class="eah-student-hub-empty__text">
                            <?php if ($filter === 'events'): ?>
                                You have not checked in to a main event yet. Scan the event QR at the venue from your dashboard.
                            <?php elseif ($filter === 'activities'): ?>
                                You have not checked in to any activities yet. RSVP in the hub, then scan the activity QR during the scheduled time.
                            <?php else: ?>
                                When you scan event or activity QR codes, your attendance will appear here.
                            <?php endif; ?>
                        </p>
                        <div class="eah-student-hub-empty__actions">
                            <button type="button" class="eah-student-hub-empty__cta" data-bs-toggle="modal" data-bs-target="#scanQRModal">
                                <i class="fas fa-qrcode" aria-hidden="true"></i> Scan QR
                            </button>
                            <a class="eah-student-hub-empty__cta eah-student-hub-empty__cta--ghost" href="<?= ah_h($hubUrl) ?>">
                                <i class="fas fa-th-large" aria-hidden="true"></i> My registrations
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="eah-timeline-list eah-timeline-list--grouped eah-attendance-timeline">
                        <?php foreach ($byDate as $ymd => $dayItems): ?>
                            <div class="eah-day-group">
                                <?php if ($ymd !== 'unknown'): ?>
                                    <h3 class="eah-day-group__title"><?= ah_h(date('l, F j, Y', strtotime($ymd))) ?></h3>
                                <?php endif; ?>
                                <?php foreach ($dayItems as $item): ?>
                                    <?php
                                        $kind = (string) ($item['kind'] ?? 'event');
                                        $href = ah_attendance_item_href($item);
                                        $timeLabel = ah_attendance_time_label($item);
                                        $isEvent = $kind === 'event';
                                    ?>
                                    <a class="eah-activity-row eah-activity-row--timeline eah-attendance-row" href="<?= ah_h($href) ?>">
                                        <div class="eah-row-time">
                                            <span class="eah-row-time__value"><?= ah_h($timeLabel) ?></span>
                                        </div>
                                        <div class="eah-row-main">
                                            <div class="eah-row-title"><?= ah_h((string) ($item['title'] ?? '')) ?></div>
                                            <div class="eah-row-meta">
                                                <?php if (!$isEvent && !empty($item['event_title'])): ?>
                                                    <span><i class="fas fa-calendar-day"></i> <?= ah_h((string) $item['event_title']) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($item['location'])): ?>
                                                    <span><i class="fas fa-map-marker-alt"></i> <?= ah_h(mb_strimwidth((string) $item['location'], 0, 48, '…')) ?></span>
                                                <?php endif; ?>
                                                <?php
                                                    $checkedIn = trim((string) ($item['checked_in_at'] ?? ''));
                                                    if ($checkedIn !== ''):
                                                ?>
                                                    <span><i class="fas fa-check"></i> Checked in <?= ah_h(date('M j, g:i A', strtotime($checkedIn))) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="eah-row-status">
                                            <span class="eah-badge <?= $isEvent ? 'eah-badge-attended' : 'eah-badge-rsvp' ?>">
                                                <?= $isEvent ? 'Event' : 'Activity' ?>
                                            </span>
                                            <i class="fas fa-chevron-right eah-row-chevron"></i>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/logout_confirm.js?v=2"></script>
<script src="<?= BASE_URL ?>/assets/js/event_activities_hub_nav.js"></script>
<?php include __DIR__ . '/views/partials/student_scan_qr_footer.php'; ?>
</body>
</html>
