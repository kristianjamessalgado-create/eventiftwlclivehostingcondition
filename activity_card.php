<?php
/**
 * Student activity card — printable attendance log for teacher signature.
 */
session_start();

include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
include __DIR__ . '/config/csrf.php';
include __DIR__ . '/config/departments.php';
require_once __DIR__ . '/backend/lib/event_day_sessions.php';
require_once __DIR__ . '/backend/lib/activity_card.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ' . BASE_URL . '/views/login.php?redirect=' . urlencode(
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . BASE_URL . '/activity_card.php'
    ));
    exit();
}

$userId = (int) $_SESSION['user_id'];
$menuUserName = trim((string) ($_SESSION['name'] ?? ''));

$studentName = $menuUserName;
$studentDept = '';
$deptStmt = $conn->prepare('SELECT name, department FROM users WHERE id = ? LIMIT 1');
if ($deptStmt) {
    $deptStmt->bind_param('i', $userId);
    $deptStmt->execute();
    $deptRow = $deptStmt->get_result()->fetch_assoc();
    $deptStmt->close();
    if ($deptRow) {
        $studentName = trim((string) ($deptRow['name'] ?? $studentName));
        $studentDept = trim((string) ($deptRow['department'] ?? ''));
    }
}
$deptLabel = $studentDept !== '' && function_exists('eventify_format_department_label')
    ? eventify_format_department_label($studentDept)
    : $studentDept;

$allRows = [];
try {
    $allRows = eventify_activity_card_load_rows($conn, $userId, $studentDept !== '' ? $studentDept : null);
} catch (Throwable $e) {
    $allRows = [];
}
// Digital stamps applied by teachers, and the QR that opens this card for them.
$cardShareToken = '';
try {
    $allRows = eventify_activity_card_attach_stamps($allRows, eventify_activity_card_load_stamps($conn, $userId));
    $cardShareToken = eventify_activity_card_share_token($conn, $userId);
} catch (Throwable $e) {
    $cardShareToken = '';
}
$filterOptions = eventify_activity_card_filter_options($allRows);
$displayRows = $allRows;
for ($i = 0; $i < 4; $i++) {
    $displayRows[] = [
        'kind' => 'blank',
        'event_id' => 0,
        'session_id' => 0,
        'date' => '',
        'time_in' => '',
        'time_out' => '',
        'event' => '',
        'event_title' => '',
        'activity_title' => '',
        'signed' => false,
        'signer' => '',
    ];
}

$hubUrl = BASE_URL . '/activities_hub.php';
$studentHubHomeUrl = $hubUrl;
$mainHubUrl = $hubUrl;
$showMainHubNav = false;
try {
    eventify_event_day_sessions_ensure_enhanced($conn);
    $studentHubHomeUrl = eventify_student_activities_hub_home_url($conn, $userId, $studentDept !== '' ? $studentDept : null);
    $mainHubUrl = eventify_activities_hub_main_url($conn, $userId, 'student', $studentDept !== '' ? $studentDept : null);
    $showMainHubNav = rtrim((string) $mainHubUrl, '/') !== rtrim((string) $hubUrl, '/');
} catch (Throwable $e) {
    $studentHubHomeUrl = $hubUrl;
    $mainHubUrl = $hubUrl;
    $showMainHubNav = false;
}
$conn->close();

$acStudentName = $studentName;
$acDeptLabel = $deptLabel;
$acDisplayRows = $displayRows;
$acTeacherMode = false;
$acQrImageUrl = $cardShareToken !== ''
    ? 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=4&data=' . rawurlencode(eventify_activity_card_view_url($cardShareToken))
    : '';

$dashboardUrl = BASE_URL . '/backend/auth/dashboard_student.php';
$pageUrl = BASE_URL . '/activity_card.php';
$historyUrl = BASE_URL . '/attendance_history.php';

function ac_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function ac_date_label(string $ymd): string
{
    $ymd = trim($ymd);
    if ($ymd === '' || $ymd === '0000-00-00') {
        return '';
    }
    $ts = strtotime($ymd);
    return $ts ? date('M j, Y', $ts) : $ymd;
}

function ac_clock_minutes(string $clock): int
{
    $clock = trim($clock);
    if ($clock === '') {
        return -1;
    }
    $ts = strtotime($clock);
    if ($ts === false) {
        return -1;
    }

    return ((int) date('G', $ts) * 60) + (int) date('i', $ts);
}

function ac_period_from_minutes(int $minutes): string
{
    if ($minutes < 0) {
        return '';
    }

    return $minutes < (12 * 60) ? 'morning' : 'afternoon';
}

$kindCounts = [
    'all' => count($allRows),
    'events' => 0,
    'activities' => 0,
    'checked_in' => 0,
];
foreach ($allRows as $countRow) {
    $rowKind = (string) ($countRow['kind'] ?? '');
    if ($rowKind === 'event') {
        $kindCounts['events']++;
    } elseif ($rowKind === 'activity') {
        $kindCounts['activities']++;
    }
    if (trim((string) ($countRow['time_in'] ?? '')) !== '') {
        $kindCounts['checked_in']++;
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
    <title>My activity card | EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/event_activities_hub.css?v=54">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/eventify_modal.css?v=3">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/activity_card.css?v=6">
</head>
<body class="event-activities-hub event-activities-hub--index event-activities-hub--student ac-page">
<div class="eah-wrap">
    <header class="eah-topbar">
        <button type="button" class="eah-topbar__menu" id="eahNavOpen" aria-label="Open menu" aria-expanded="false" aria-controls="eahNavDrawer">
            <i class="fas fa-bars"></i>
        </button>
        <a class="eah-topbar__logo" href="<?= ac_h($studentHubHomeUrl) ?>"><i class="fas fa-calendar-alt" aria-hidden="true"></i><span>EVENTIFY</span></a>
        <div class="eah-topbar__actions">
            <a class="eah-topbar__action" href="<?= ac_h($historyUrl) ?>">
                <i class="fas fa-clipboard-check me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">History</span>
            </a>
            <a class="eah-topbar__action" href="<?= ac_h($dashboardUrl) ?>" aria-label="Dashboard">
                <i class="fas fa-gauge-high me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Dashboard</span>
            </a>
            <a class="eah-topbar__action is-active" href="<?= ac_h($pageUrl) ?>" aria-current="page">
                <i class="fas fa-file-signature me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Activity card</span>
            </a>
        </div>
    </header>

    <div class="eah-nav-drawer" id="eahNavDrawer" aria-hidden="true">
        <div class="eah-nav-drawer__backdrop" id="eahNavBackdrop" tabindex="-1"></div>
        <nav class="eah-nav-drawer__panel" id="eahNavPanel" role="navigation" aria-label="Activity card menu">
            <div class="eah-nav-drawer__head">
                <div>
                    <div class="eah-nav-drawer__brand">EVENTIFY</div>
                    <?php if ($menuUserName !== ''): ?>
                        <div class="eah-nav-drawer__user"><?= ac_h($menuUserName) ?></div>
                    <?php endif; ?>
                </div>
                <button type="button" class="eah-nav-drawer__close" id="eahNavClose" aria-label="Close menu">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php
                $studentNavActive = 'activity_card';
                $studentNavDashboardUrl = $dashboardUrl;
                $studentNavHubUrl = $hubUrl;
                $studentNavMainHubUrl = $mainHubUrl;
                $studentNavShowMainHub = $showMainHubNav;
                $studentNavMainHubLabel = 'This event';
                $studentNavMainHubHint = 'Continue where you left off';
                $studentNavTicketsUrl = BASE_URL . '/backend/auth/dashboard_student.php?panel=tickets';
                $studentNavTicketsHint = 'Your digital passes';
                $studentNavAttendanceUrl = $historyUrl;
                include __DIR__ . '/views/partials/student_hub_nav_items.php';
            ?>
            <div class="eah-nav-drawer__footer">
                <a class="eah-nav-drawer__link eah-nav-drawer__link--danger" href="<?= ac_h(BASE_URL . '/backend/auth/logout.php') ?>" data-logout-confirm>
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Log out</span>
                </a>
            </div>
        </nav>
    </div>

    <div class="eah-hub-panel eah-hub-panel--student">
        <div class="eah-student-hub-shell">
            <header class="eah-student-hub-hero ac-screen-only">
                <nav class="eah-student-hub-hero__crumb" aria-label="Breadcrumb">
                    <a class="eah-student-hub-hero__crumb-link" href="<?= ac_h($dashboardUrl) ?>">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i> Dashboard
                    </a>
                </nav>
                <div class="eah-student-hub-hero__main">
                    <div class="eah-student-hub-hero__icon" aria-hidden="true"><i class="fas fa-file-signature"></i></div>
                    <div class="eah-student-hub-hero__copy">
                        <p class="eah-student-hub-hero__eyebrow">Teacher confirmation</p>
                        <h1 class="eah-student-hub-hero__title">My activity card</h1>
                        <p class="eah-student-hub-hero__subtitle">
                            Print this card and have your teacher sign each row, or let them scan the QR code to stamp it digitally.
                        </p>
                    </div>
                </div>
            </header>

            <div class="eah-student-hub-body">
                <div class="ac-toolbar ac-screen-only">
                    <p class="ac-toolbar__hint">
                        Choose Format 1 or Format 2, then filter before you print. Times fill in after QR check-in.
                    </p>
                    <div class="ac-toolbar__actions">
                        <a class="ac-btn ac-btn--ghost" href="<?= ac_h($historyUrl) ?>">
                            <i class="fas fa-clipboard-check" aria-hidden="true"></i> Check-in history
                        </a>
                        <button type="button" class="ac-btn ac-btn--print" id="acPrintBtn">
                            <i class="fas fa-print" aria-hidden="true"></i> Print card
                        </button>
                    </div>
                </div>

                <div class="ac-filters ac-screen-only" id="acFilterBar" data-total="<?= (int) $kindCounts['all'] ?>">
                    <div class="ac-filters__chips ac-filters__chips--format" role="group" aria-label="Card format">
                        <button type="button" class="ac-filters__chip is-active" data-ac-format="log" aria-pressed="true">Format 1</button>
                        <button type="button" class="ac-filters__chip" data-ac-format="daily" aria-pressed="false">Format 2</button>
                    </div>
                    <div class="ac-filters__chips" role="group" aria-label="Show">
                        <?php
                        $kindChips = [
                            'all' => 'All',
                            'events' => 'Events',
                            'activities' => 'Activities',
                            'checked_in' => 'Checked in',
                        ];
                        foreach ($kindChips as $chipKey => $chipLabel):
                        ?>
                        <button type="button" class="ac-filters__chip<?= $chipKey === 'all' ? ' is-active' : '' ?>" data-ac-kind="<?= ac_h($chipKey) ?>"<?= $chipKey === 'all' ? ' aria-pressed="true"' : ' aria-pressed="false"' ?>>
                            <?= ac_h($chipLabel) ?>
                            <?php if ((int) ($kindCounts[$chipKey] ?? 0) > 0): ?>
                                <span class="ac-filters__count"><?= (int) $kindCounts[$chipKey] ?></span>
                            <?php endif; ?>
                        </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="ac-filters__fields">
                        <label class="ac-filters__field">
                            <span>Date</span>
                            <select id="acFilterDate" aria-label="Filter by date">
                                <option value="">Any date</option>
                                <?php foreach ($filterOptions['dates'] as $ymd): ?>
                                    <option value="<?= ac_h($ymd) ?>"><?= ac_h(ac_date_label($ymd)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="ac-filters__field">
                            <span>Event</span>
                            <select id="acFilterEvent" aria-label="Filter by event">
                                <option value="">Any event</option>
                                <?php foreach ($filterOptions['events'] as $eid => $eventTitle): ?>
                                    <option value="<?= (int) $eid ?>"><?= ac_h($eventTitle) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="ac-filters__field">
                            <span>Activity</span>
                            <select id="acFilterActivity" aria-label="Filter by activity">
                                <option value="">Any activity</option>
                                <?php foreach ($filterOptions['activities'] as $sid => $activityTitle): ?>
                                    <option value="<?= (int) $sid ?>"><?= ac_h($activityTitle) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <div class="ac-filters__meta">
                        <p class="ac-filters__summary" id="acFilterSummary">
                            Showing <?= (int) $kindCounts['all'] ?> of <?= (int) $kindCounts['all'] ?>
                            <?= $kindCounts['all'] === 1 ? 'row' : 'rows' ?>
                        </p>
                        <button type="button" class="ac-filters__clear" id="acFilterClear" hidden>Clear filters</button>
                    </div>
                </div>

                <p class="ac-filters__empty ac-screen-only" id="acFilterEmpty" hidden>No rows match these filters. Clear them or pick another date, event, or activity.</p>

                <div class="ac-sheet-wrap">
                    <?php include __DIR__ . '/views/partials/activity_card_sheet.php'; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/logout_confirm.js?v=2"></script>
<script src="<?= BASE_URL ?>/assets/js/event_activities_hub_nav.js"></script>
<script src="<?= BASE_URL ?>/assets/js/activity_card.js?v=4"></script>
<?php include __DIR__ . '/views/partials/student_scan_qr_footer.php'; ?>
</body>
</html>
