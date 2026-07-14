<?php
/**
 * Activities hub landing — choose an event to browse day activities, schedules, and check-ins.
 */
session_start();

include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/departments.php';
require_once __DIR__ . '/backend/lib/event_day_sessions.php';
require_once __DIR__ . '/backend/lib/event_calendar.php';
require_once __DIR__ . '/backend/lib/event_ticketing.php';
require_once __DIR__ . '/backend/lib/event_checkin_security.php';

eventify_events_department_ensure_varchar($conn);
eventify_ticketing_ensure_schema($conn);

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/views/login.php?redirect=' . urlencode(
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . BASE_URL . '/activities_hub.php'
    ));
    exit();
}

$userId = (int) $_SESSION['user_id'];
$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
$isStudent = $role === 'student';
$isOrganizer = $role === 'organizer';
$menuUserName = trim((string) ($_SESSION['name'] ?? ''));
$todayYmd = eventify_today_ymd();

$studentDept = null;
if ($role === 'student') {
    $du = $conn->prepare('SELECT department FROM users WHERE id = ? LIMIT 1');
    if ($du) {
        $du->bind_param('i', $userId);
        $du->execute();
        $dr = $du->get_result()->fetch_assoc();
        $du->close();
        $studentDept = $dr['department'] ?? null;
    }
}

$backUrl = BASE_URL . '/backend/auth/dashboard_student.php';
if ($role === 'organizer') {
    $backUrl = BASE_URL . '/backend/auth/dashboardorganizer.php';
} elseif ($role === 'admin') {
    $backUrl = BASE_URL . '/backend/admin/dashboard.php';
} elseif ($role === 'super_admin') {
    $backUrl = BASE_URL . '/backend/super_admin/dashboardsuperadmin.php';
} elseif ($role === 'multimedia') {
    $backUrl = BASE_URL . '/backend/auth/dashboard_multimedia.php';
}

$hubUrl = BASE_URL . '/activities_hub.php';
$activities_hub_events = [];
$studentTodayActivities = [];
$studentRegisteredEvents = [];
$studentAttendanceCounts = ['events' => 0, 'activities' => 0, 'total' => 0];

try {
    eventify_event_day_sessions_ensure_enhanced($conn);
} catch (Throwable $e) {
    // Non-fatal — hub event list must still load when migrations are unavailable.
}

if ($role === 'student') {
    $activities_hub_events = eventify_load_student_activities_hub_list($conn, $userId, $studentDept);

    try {
        if (function_exists('eventify_load_student_merged_hub_events')) {
            $mergedHub = eventify_load_student_merged_hub_events($conn, $userId, $studentDept);
            if ($mergedHub !== []) {
                $activities_hub_events = eventify_merge_hub_events_by_id(array_merge($activities_hub_events, $mergedHub));
            }
        }
    } catch (Throwable $e) {
        // Keep calendar-parity list above.
    }

    try {
        $studentTodayActivities = eventify_load_student_today_activities($conn, $userId, $studentDept, $todayYmd);
    } catch (Throwable $e) {
        $studentTodayActivities = [];
    }

    try {
        $studentRegisteredEvents = eventify_load_student_registered_hub_events($conn, $userId, $studentDept);
    } catch (Throwable $e) {
        $studentRegisteredEvents = [];
    }

    try {
        $studentAttendanceCounts = eventify_student_attendance_counts($conn, $userId);
    } catch (Throwable $e) {
        $studentAttendanceCounts = ['events' => 0, 'activities' => 0, 'total' => 0];
    }
} else {
    try {
        $activities_hub_events = eventify_load_activities_hub_picker_events($conn, $userId, $role, $studentDept);
    } catch (Throwable $e) {
        $activities_hub_events = [];
    }
}

$activities_hub_count = count($activities_hub_events);
$showHubStatusFilter = ($role !== 'student');
$hubStatusOptions = ['active', 'pending', 'closed', 'rejected'];
$hubStatusDefault = ($role === 'organizer')
    ? $hubStatusOptions
    : ['active'];
$hubStatusSelected = $showHubStatusFilter ? $hubStatusDefault : ['active'];
$statusParam = strtolower(trim((string) ($_GET['status'] ?? '')));
if ($showHubStatusFilter && $statusParam !== '') {
    $parsed = array_values(array_filter(array_map('trim', explode(',', $statusParam)), static function ($s) use ($hubStatusOptions) {
        return in_array($s, $hubStatusOptions, true);
    }));
    if ($parsed !== []) {
        $hubStatusSelected = $parsed;
    }
}
$activities_hub_visible_count = $activities_hub_count;
if (!$showHubStatusFilter) {
    $activities_hub_visible_count = eventify_count_hub_events_in_statuses($activities_hub_events, ['active']);
}
$activities_hub_has_visible_list = $showHubStatusFilter
    ? ($activities_hub_count > 0)
    : ($activities_hub_visible_count > 0);
try {
    $mainHubUrl = eventify_activities_hub_main_url($conn, $userId, $role, $studentDept);
} catch (Throwable $e) {
    $mainHubUrl = $hubUrl;
}
$showMainHubNav = rtrim((string) $mainHubUrl, '/') !== rtrim((string) $hubUrl, '/');
$conn->close();

function ah_event_status_filter_bucket(string $status): string
{
    $st = strtolower(trim($status));
    if ($st === 'completed') {
        return 'closed';
    }
    if (in_array($st, ['active', 'pending', 'closed', 'rejected'], true)) {
        return $st;
    }
    return 'closed';
}

function ah_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/** @return array{class: string, label: string, card_class: string} */
function ah_event_status_display(string $status): array
{
    $st = strtolower(trim($status));
    $label = $st !== '' ? ucfirst($st) : 'Unknown';
    if ($st === 'completed') {
        $label = 'Closed';
    }
    $map = [
        'active' => ['eah-status-pill--active', 'eah-picker-card--status-active'],
        'pending' => ['eah-status-pill--pending', 'eah-picker-card--status-pending'],
        'rejected' => ['eah-status-pill--rejected', 'eah-picker-card--status-rejected'],
        'closed' => ['eah-status-pill--closed', 'eah-picker-card--status-closed'],
        'completed' => ['eah-status-pill--closed', 'eah-picker-card--status-closed'],
    ];
    [$pill, $card] = $map[$st] ?? ['eah-status-pill--closed', 'eah-picker-card--status-closed'];

    return [
        'class' => 'eah-status-pill ' . $pill,
        'label' => $label,
        'card_class' => $card,
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($isStudent): ?>
    <meta name="theme-color" content="#0A3C26">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="EVENTIFY">
    <link rel="manifest" href="<?= BASE_URL ?>/manifest-student.php">
    <?php endif; ?>
    <title>Activities hub | EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/event_activities_hub.css?v=45">
    <?php if ($isStudent): ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/eventify_modal.css?v=1">
    <?php endif; ?>
    <?php if ($showHubStatusFilter): ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/activities_hub_filter.css?v=5">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/eventify_spinner.css?v=2">
    <?php endif; ?>
</head>
<body class="event-activities-hub event-activities-hub--index<?= $isStudent ? ' event-activities-hub--student' : '' ?><?= $isOrganizer ? ' event-activities-hub--organizer event-activities-hub--student' : '' ?>">
<div class="eah-wrap">
    <header class="eah-topbar">
        <button type="button" class="eah-topbar__menu" id="eahNavOpen" aria-label="Open menu" aria-expanded="false" aria-controls="eahNavDrawer">
            <i class="fas fa-bars"></i>
        </button>
        <a class="eah-topbar__logo" href="<?= ah_h($hubUrl) ?>"><i class="fas fa-calendar-alt" aria-hidden="true"></i><span>EVENTIFY</span></a>
        <div class="eah-topbar__actions">
            <a class="eah-topbar__action is-active" href="<?= ah_h($hubUrl) ?>" aria-current="page">
                <i class="fas fa-th-large me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Activities hub</span>
            </a>
            <?php if ($showMainHubNav): ?>
            <a class="eah-topbar__action" href="<?= ah_h($mainHubUrl) ?>" aria-label="Main hub">
                <i class="fas fa-calendar-day me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Main hub</span>
            </a>
            <?php endif; ?>
            <a class="eah-topbar__action" href="<?= ah_h($backUrl) ?>">
                <i class="fas fa-gauge-high me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Dashboard</span>
            </a>
        </div>
    </header>

    <div class="eah-nav-drawer" id="eahNavDrawer" aria-hidden="true">
        <div class="eah-nav-drawer__backdrop" id="eahNavBackdrop" tabindex="-1"></div>
        <nav class="eah-nav-drawer__panel" id="eahNavPanel" role="navigation" aria-label="Activities hub menu">
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
            <?php if ($isStudent): ?>
                <?php
                    $studentNavActive = 'hub';
                    $studentNavDashboardUrl = $backUrl;
                    $studentNavHubUrl = $hubUrl;
                    $studentNavMainHubUrl = $mainHubUrl;
                    $studentNavShowMainHub = $showMainHubNav;
                    $studentNavMainHubHint = 'Open last event';
                    $studentNavHubCount = $activities_hub_visible_count;
                    $studentNavAttendanceCount = (int) ($studentAttendanceCounts['total'] ?? 0);
                    include __DIR__ . '/views/partials/student_hub_nav_items.php';
                ?>
            <?php else: ?>
            <ul class="eah-nav-drawer__list">
                <li>
                    <a class="eah-nav-drawer__link" href="<?= ah_h($backUrl) ?>">
                        <i class="fas fa-gauge-high"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <?php if ($showMainHubNav): ?>
                <li>
                    <a class="eah-nav-drawer__link" href="<?= ah_h($mainHubUrl) ?>">
                        <i class="fas fa-calendar-day"></i>
                        <span>Main hub</span>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a class="eah-nav-drawer__link is-active" href="<?= ah_h($hubUrl) ?>" aria-current="page">
                        <i class="fas fa-th-large"></i>
                        <span>Activities hub<?php if ($activities_hub_count > 0): ?> <span class="eah-nav-count"><?= $activities_hub_count > 99 ? '99+' : $activities_hub_count ?></span><?php endif; ?></span>
                    </a>
                </li>
            </ul>
            <?php endif; ?>
            <div class="eah-nav-drawer__footer">
                <a class="eah-nav-drawer__link eah-nav-drawer__link--danger" href="<?= ah_h(BASE_URL . '/backend/auth/logout.php') ?>" data-logout-confirm>
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Log out</span>
                </a>
            </div>
        </nav>
    </div>

    <div class="eah-hub-panel<?= ($isStudent || $isOrganizer) ? ' eah-hub-panel--student' : '' ?>">
        <?php if ($isStudent): ?>
        <div class="eah-student-hub-shell">
            <header class="eah-student-hub-hero">
                <div class="eah-student-hub-hero__main">
                    <div class="eah-student-hub-hero__icon" aria-hidden="true"><i class="fas fa-th-large"></i></div>
                    <div class="eah-student-hub-hero__copy">
                        <p class="eah-student-hub-hero__eyebrow">My Activities and Events</p>
                        <h1 class="eah-student-hub-hero__title">Your events &amp; activities</h1>
                        <p class="eah-student-hub-hero__subtitle">Active events for your department show here automatically — no RSVP needed for <strong>open entry</strong> events. Tap one to open its <strong>Main hub</strong> for day activities and check-in.</p>
                    </div>
                </div>
                <div class="eah-student-hub-hero__stats">
                    <?php if ($studentTodayActivities !== []): ?>
                        <span class="eah-student-hub-stat eah-student-hub-stat--live">
                            <i class="fas fa-bolt" aria-hidden="true"></i>
                            <?= count($studentTodayActivities) ?> today
                        </span>
                    <?php endif; ?>
                    <span class="eah-student-hub-stat" id="eahHubEventCount">
                        <i class="fas fa-calendar-check" aria-hidden="true"></i>
                        <?= $activities_hub_visible_count ?> active event<?= $activities_hub_visible_count === 1 ? '' : 's' ?>
                    </span>
                </div>
            </header>
            <div class="eah-student-hub-body">
        <?php elseif ($isOrganizer): ?>
        <div class="eah-student-hub-shell">
            <header class="eah-student-hub-hero eah-student-hub-hero--organizer">
                <div class="eah-student-hub-hero__main">
                    <div class="eah-student-hub-hero__icon" aria-hidden="true"><i class="fas fa-th-large"></i></div>
                    <div class="eah-student-hub-hero__copy">
                        <p class="eah-student-hub-hero__eyebrow">Activities hub</p>
                        <h1 class="eah-student-hub-hero__title">Your events</h1>
                        <p class="eah-student-hub-hero__subtitle">Pick an event to open its <strong>Main hub</strong> — add day activities, share QR codes, and track attendance.</p>
                    </div>
                </div>
                <div class="eah-student-hub-hero__stats">
                    <span class="eah-student-hub-stat" id="eahHubEventCount">
                        <i class="fas fa-calendar-check" aria-hidden="true"></i>
                        <?= $activities_hub_count ?> event<?= $activities_hub_count === 1 ? '' : 's' ?>
                    </span>
                </div>
            </header>
            <div class="eah-student-hub-body">
        <?php else: ?>
        <div class="eah-event-hero">
            <div class="eah-event-hero__body">
                <p class="eah-event-hero__eyebrow">Activities hub</p>
                <h1 class="eah-event-hero__title">Browse events</h1>
                <p class="eah-event-hero__meta">
                    <span id="eahHubEventCount"><i class="fas fa-th-large" aria-hidden="true"></i> <?= $activities_hub_count ?> event<?= $activities_hub_count === 1 ? '' : 's' ?></span>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($showHubStatusFilter && $activities_hub_count > 0): ?>
        <div class="eah-hub-toolbar">
            <div class="eah-hub-filter">
                <div class="eah-hub-filter__head">
                    <span class="eah-hub-filter__label">Show status</span>
                    <span class="eah-hub-filter__hint">Tap to show or hide</span>
                </div>
                <div class="eah-hub-filter__chips" role="group" aria-label="Filter by event status">
                    <?php foreach ($hubStatusOptions as $statusKey): ?>
                        <?php
                            $chipLabels = [
                                'active' => 'Active',
                                'pending' => 'Pending',
                                'closed' => 'Closed',
                                'rejected' => 'Rejected',
                            ];
                            $isSelected = in_array($statusKey, $hubStatusSelected, true);
                        ?>
                        <button type="button"
                            class="eah-hub-filter__chip eah-hub-filter__chip--<?= ah_h($statusKey) ?><?= $isSelected ? ' is-selected' : '' ?>"
                            data-eah-status="<?= ah_h($statusKey) ?>"
                            aria-pressed="<?= $isSelected ? 'true' : 'false' ?>">
                            <?= ah_h($chipLabels[$statusKey] ?? ucfirst($statusKey)) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="eah-hub-filter__quick">
                    <button type="button" class="eah-hub-filter__quick-btn" data-eah-filter-all>Select all</button>
                    <span class="eah-hub-filter__quick-sep" aria-hidden="true">·</span>
                    <button type="button" class="eah-hub-filter__quick-btn" data-eah-filter-none>Clear all</button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($isStudent && $studentTodayActivities !== []): ?>
        <section class="eah-landing-section eah-landing-section--student" aria-labelledby="eahTodayHeading">
            <div class="eah-landing-section__head">
                <h2 class="eah-landing-section__title" id="eahTodayHeading"><i class="fas fa-bolt" aria-hidden="true"></i> Today's activities</h2>
            </div>
            <div class="eah-landing-scroll">
                <?php foreach ($studentTodayActivities as $idx => $act): ?>
                    <?php
                        $eventId = (int) ($act['event_id'] ?? 0);
                        $activityId = (int) ($act['id'] ?? 0);
                        $actHref = BASE_URL . '/event_activities.php?id=' . $eventId . ($activityId > 0 ? '&activity=' . $activityId : '');
                        $timeStr = eventify_format_session_time_range($act['start_time'] ?? null, $act['end_time'] ?? null);
                        $isLive = eventify_session_is_live_now($act, $todayYmd);
                        $accentClass = ($idx % 2 === 0) ? 'eah-landing-chip--warm' : 'eah-landing-chip--cool';
                    ?>
                    <a class="eah-landing-chip <?= ah_h($accentClass) ?>" href="<?= ah_h($actHref) ?>">
                        <span class="eah-landing-chip__icon"><?= eventify_activity_icon((string) ($act['title'] ?? ''), $act['category'] ?? null) ?></span>
                        <span class="eah-landing-chip__body">
                            <span class="eah-landing-chip__title"><?= ah_h((string) ($act['title'] ?? 'Activity')) ?></span>
                            <span class="eah-landing-chip__meta">
                                <?= ah_h($timeStr) ?>
                                <?php if (!empty($act['event_title'])): ?> · <?= ah_h((string) $act['event_title']) ?><?php endif; ?>
                            </span>
                        </span>
                        <?php if ($isLive): ?><span class="eah-pill eah-pill--live">Live</span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($isStudent && $studentRegisteredEvents !== []): ?>
        <section class="eah-landing-section eah-landing-section--student" aria-labelledby="eahMyEventsHeading">
            <div class="eah-landing-section__head">
                <h2 class="eah-landing-section__title" id="eahMyEventsHeading"><i class="fas fa-id-card-alt" aria-hidden="true"></i> My registered events</h2>
            </div>
            <div class="eah-landing-grid">
                <?php foreach (array_slice($studentRegisteredEvents, 0, 6) as $mev): ?>
                    <?php
                        $meid = (int) ($mev['id'] ?? 0);
                        $meEnd = function_exists('eventify_event_resolve_end_date') ? eventify_event_resolve_end_date($mev) : ($mev['date'] ?? '');
                        $actCount = (int) ($mev['activity_count'] ?? 0);
                    ?>
                    <a class="eah-landing-my-card" href="<?= ah_h(BASE_URL . '/event_activities.php?id=' . $meid . '#eah-sp-all') ?>">
                        <span class="eah-landing-my-card__title"><?= ah_h((string) ($mev['title'] ?? 'Event')) ?></span>
                        <span class="eah-landing-my-card__meta">
                            <i class="fas fa-calendar-day" aria-hidden="true"></i> <?= ah_h((string) ($meEnd ?: ($mev['date'] ?? ''))) ?>
                            · <?= $actCount ?> activit<?= $actCount === 1 ? 'y' : 'ies' ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($isStudent && $studentAttendanceCounts['total'] > 0): ?>
        <section class="eah-landing-section eah-landing-section--student" aria-labelledby="eahAttendedHeading">
            <div class="eah-landing-section__head">
                <h2 class="eah-landing-section__title" id="eahAttendedHeading"><i class="fas fa-clipboard-check" aria-hidden="true"></i> Where you've been</h2>
                <a class="eah-landing-section__link" href="<?= ah_h(BASE_URL . '/attendance_history.php') ?>">View all</a>
            </div>
            <a class="eah-landing-attendance-card" href="<?= ah_h(BASE_URL . '/attendance_history.php') ?>">
                <span class="eah-landing-attendance-card__icon"><i class="fas fa-history"></i></span>
                <span class="eah-landing-attendance-card__body">
                    <span class="eah-landing-attendance-card__title">My attendance</span>
                    <span class="eah-landing-attendance-card__meta">
                        Full list · <?= (int) $studentAttendanceCounts['total'] ?> check-in<?= $studentAttendanceCounts['total'] === 1 ? '' : 's' ?>
                        <?php if ($studentAttendanceCounts['events'] > 0 && $studentAttendanceCounts['activities'] > 0): ?>
                            · <?= (int) $studentAttendanceCounts['events'] ?> event<?= $studentAttendanceCounts['events'] === 1 ? '' : 's' ?>, <?= (int) $studentAttendanceCounts['activities'] ?> activit<?= $studentAttendanceCounts['activities'] === 1 ? 'y' : 'ies' ?>
                        <?php endif; ?>
                    </span>
                </span>
                <span class="eah-landing-attendance-card__chev" aria-hidden="true"><i class="fas fa-chevron-right"></i></span>
            </a>
        </section>
        <?php endif; ?>

        <?php if ($activities_hub_has_visible_list): ?>
        <section class="eah-landing-section<?= ($isStudent || $isOrganizer) ? ' eah-landing-section--student' : '' ?>" aria-labelledby="eahAllEventsHeading">
            <div class="eah-landing-section__head">
                <h2 class="eah-landing-section__title" id="eahAllEventsHeading">
                    <i class="fas fa-th-large" aria-hidden="true"></i>
                    <?= $isStudent ? 'Upcoming events' : ($role === 'organizer' ? 'Your events' : 'Events with activities') ?>
                </h2>
            </div>
            <div class="eah-picker-list-host" id="eahHubEventListHost">
                <div class="eventify-filter-loading" id="eahHubFilterLoading" hidden aria-hidden="true">
                    <div class="eventify-spinner" role="status" aria-live="polite">
                        <span class="eventify-spinner__sr">Updating filters</span>
                    </div>
                    <p class="eventify-filter-loading__text">Updating filters…</p>
                </div>
            <div class="eah-picker-list" id="eahHubEventList">
                <?php foreach ($activities_hub_events as $ev): ?>
                    <?php
                        $eid = (int) ($ev['id'] ?? 0);
                        $st = strtolower((string) ($ev['status'] ?? ''));
                        $statusUi = ah_event_status_display($st);
                        $actCount = (int) ($ev['activity_count'] ?? 0);
                        $eventHref = BASE_URL . '/event_activities.php?id=' . $eid . '#eah-sp-all';
                        $filterBucket = ah_event_status_filter_bucket($st);
                        $isVisible = in_array($filterBucket, $hubStatusSelected, true);
                        $regModeUi = $isStudent ? eventify_registration_mode_ui($ev) : null;
                    ?>
                    <a class="eah-picker-card <?= ah_h($statusUi['card_class']) ?>" href="<?= ah_h($eventHref) ?>" data-filter-status="<?= ah_h($filterBucket) ?>"<?= !$isVisible ? ' hidden' : '' ?>>
                        <div class="eah-picker-card__main">
                            <h3 class="eah-picker-card__title">
                                <i class="fas fa-calendar-day eah-picker-card__title-icon" aria-hidden="true"></i>
                                <?= ah_h((string) ($ev['title'] ?? 'Untitled')) ?>
                            </h3>
                            <p class="eah-picker-card__meta">
                                <?php if ($isStudent && $regModeUi && ($regModeUi['mode'] ?? '') === 'open'): ?>
                                    <span class="eah-picker-card__open-tag"><i class="fas fa-door-open" aria-hidden="true"></i> Open entry</span>
                                <?php endif; ?>
                                <?php if (!empty($ev['date'])): ?>
                                    <span><i class="fas fa-clock" aria-hidden="true"></i> <?= ah_h(date('M j, Y', strtotime((string) $ev['date']))) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($ev['location'])): ?>
                                    <span><i class="fas fa-map-marker-alt" aria-hidden="true"></i> <?= ah_h(mb_strimwidth((string) $ev['location'], 0, 56, '…')) ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="eah-picker-card__aside">
                            <?php if ($actCount > 0): ?>
                                <span class="eah-picker-card__badge"><?= $actCount ?> activit<?= $actCount === 1 ? 'y' : 'ies' ?></span>
                            <?php else: ?>
                                <span class="eah-picker-card__badge eah-picker-card__badge--muted">Schedule coming</span>
                            <?php endif; ?>
                            <?php if ($st !== '' && $showHubStatusFilter): ?>
                                <span class="<?= ah_h($statusUi['class']) ?>"><?= ah_h($statusUi['label']) ?></span>
                            <?php endif; ?>
                            <span class="eah-picker-card__chev-wrap" aria-hidden="true"><i class="fas fa-chevron-right"></i></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            </div>
            <div class="eah-hub-filter-empty eah-student-hub-empty<?= ($isStudent || $isOrganizer) ? ' eah-student-hub-empty--inline' : '' ?>" id="eahHubFilterEmpty"<?= ($showHubStatusFilter || $activities_hub_visible_count > 0) ? ' hidden' : '' ?>>
                <div class="eah-student-hub-empty__icon" aria-hidden="true"><i class="fas fa-calendar-day"></i></div>
                <h3 class="eah-student-hub-empty__title"><?= $showHubStatusFilter ? 'No events match' : 'No active events' ?></h3>
                <p class="eah-student-hub-empty__text mb-0" id="eahHubFilterEmptyText"><?= $showHubStatusFilter ? 'Select at least one status above, or tap <strong>Select all</strong>.' : 'RSVP for ticketed or RSVP-only events on your dashboard. <strong>Open entry</strong> events for your department show here automatically when activities are published.' ?></p>
                <?php if ($isStudent): ?>
                <div class="eah-student-hub-empty__actions">
                    <a class="eah-student-hub-empty__cta" href="<?= ah_h($backUrl) ?>">
                        <i class="fas fa-calendar-alt" aria-hidden="true"></i> Open dashboard calendar
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </section>
        <?php else: ?>
            <div class="eah-student-hub-empty<?= $isStudent ? '' : ' eah-empty' ?>">
                <?php if ($isStudent): ?>
                <div class="eah-student-hub-empty__icon" aria-hidden="true"><i class="fas fa-calendar-plus"></i></div>
                <h3 class="eah-student-hub-empty__title">No events yet</h3>
                <p class="eah-student-hub-empty__text"><strong>Open entry</strong> and other active events for your department appear here once the event is approved. RSVP-only events also show after you register on your dashboard.</p>
                <div class="eah-student-hub-empty__actions">
                    <a class="eah-student-hub-empty__cta" href="<?= ah_h($backUrl) ?>">
                        <i class="fas fa-calendar-alt" aria-hidden="true"></i> Browse events on dashboard
                    </a>
                    <?php if ($studentAttendanceCounts['total'] > 0): ?>
                    <a class="eah-student-hub-empty__cta eah-student-hub-empty__cta--ghost" href="<?= ah_h(BASE_URL . '/attendance_history.php') ?>">
                        <i class="fas fa-clipboard-check" aria-hidden="true"></i> My attendance
                    </a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="eah-empty-icon"><i class="fas fa-calendar-plus"></i></div>
                <div class="eah-empty-title">No events with activities yet</div>
                <p class="eah-empty-text">
                    <?php if ($role === 'organizer'): ?>
                        Create an event on your dashboard, then open it here to add day activities.
                    <?php else: ?>
                        When organizers publish day activities for an event, it will appear here.
                    <?php endif; ?>
                </p>
                <div class="eah-empty-actions">
                    <a class="eah-btn eah-btn-primary" href="<?= ah_h($backUrl) ?>">
                        <i class="fas fa-home me-1" aria-hidden="true"></i> Back to dashboard
                    </a>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($isStudent || $isOrganizer): ?>
            </div><!-- .eah-student-hub-body -->
        </div><!-- .eah-student-hub-shell -->
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/logout_confirm.js"></script>
<?php if ($showHubStatusFilter): ?>
<script>
window.__eahHubStatusDefault = <?= json_encode(array_values($hubStatusDefault), JSON_UNESCAPED_UNICODE) ?>;
window.__eahHubStatusInitial = <?= json_encode(array_values($hubStatusSelected), JSON_UNESCAPED_UNICODE) ?>;
window.__eahHubStatusOptions = <?= json_encode($hubStatusOptions, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/eventify_spinner.js?v=2"></script>
<script src="<?= BASE_URL ?>/assets/js/activities_hub_filter.js?v=4"></script>
<?php endif; ?>
<script src="<?= BASE_URL ?>/assets/js/event_activities_hub_nav.js"></script>
<?php if ($isStudent): ?>
<?php include __DIR__ . '/views/partials/student_scan_qr_footer.php'; ?>
<?php endif; ?>
</body>
</html>
