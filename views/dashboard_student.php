<?php

// Fallbacks ni if ang variables wa na set sa controller or if ang data wa na pasa
$user_name = $user_name ?? 'Student';
$user      = $user ?? ['name' => 'Student', 'user_id' => 'N/A', 'department' => null, 'student_course' => null, 'student_year_level' => null, 'student_academic_year' => null];
$events    = $events ?? []; // always an array
$msg       = $msg ?? '';
$error     = $error ?? '';
$department = $user['department'] ?? null;
$registered_event_ids   = $registered_event_ids ?? [];
$reg_count_by_event     = $reg_count_by_event ?? [];
$feedback_submitted_ids = $feedback_submitted_ids ?? [];
$pending_urgent_feedback_events = $pending_urgent_feedback_events ?? [];
$today_activities = $today_activities ?? [];
$my_registered_events = $my_registered_events ?? [];
$activity_attendance_records = $activity_attendance_records ?? [];
$student_notifications  = $student_notifications ?? [];
$unread_notif_count     = isset($unread_notif_count) ? (int) $unread_notif_count : 0;
$student_notif_dropdown = array_values(array_filter($student_notifications, static function ($n) {
    return empty($n['read_at']);
}));
$attendance_records = $attendance_records ?? [];
$attended_event_ids = $attended_event_ids ?? [];
$openModal = strtolower((string)($_GET['open_modal'] ?? ''));
$student_tickets_panel_open = !empty($student_tickets_panel_open);
$student_attendance_panel_open = !empty($student_attendance_panel_open);
$student_upcoming_panel_open = !empty($student_upcoming_panel_open);
$student_photos_panel_open = !empty($student_photos_panel_open);
$student_dashboard_panel_open = !empty($student_dashboard_panel_open);
$student_my_tickets = $student_my_tickets ?? [];
$student_tickets_bootstrap = $student_tickets_bootstrap ?? [];
$student_tickets_total_count = (int) ($student_tickets_total_count ?? 0);
$student_tickets_count = $student_tickets_total_count;
$student_attendance_count = (int) ($student_attendance_count ?? count($attendance_records));
$student_upcoming_count = (int) ($student_upcoming_count ?? count($upcoming_events));
$student_photos_gallery_count = (int) ($student_photos_gallery_count ?? 0);
$student_photo_galleries = $student_photo_galleries ?? [];
$student_photos_event_id = (int) ($student_photos_event_id ?? 0);
$student_photo_event = $student_photo_event ?? null;
$student_photo_list = $student_photo_list ?? [];
$student_photo_error = $student_photo_error ?? '';
$today = date('Y-m-d');
$upcoming_events = $upcoming_events ?? array_values(array_filter($events ?? [], static function ($e) {
    return function_exists('eventify_event_is_upcoming') && eventify_event_is_upcoming($e);
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - EVENTIFY</title>

    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

   
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">

   
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/eventify_modal.css?v=3">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/dashboard_student.css?v=19">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/eventify_dashboard_brand.css?v=1">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/calendar_legend.css?v=7">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/event_day_sessions.css?v=4">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/notifications.css?v=4">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/calendar_scroll_fix.css">
    <link rel="manifest" href="<?= BASE_URL; ?>/manifest-student.php">
    <?php if (!empty($eventifyVapidPublicKey ?? '')): ?>
    <meta name="eventify-vapid-key" content="<?= htmlspecialchars($eventifyVapidPublicKey, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <link rel="apple-touch-icon" href="<?= BASE_URL; ?>/assets/pwa/icon-192.png">
    <meta name="theme-color" content="#121212">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="EVENTIFY">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/pwa_student.css?v=5">
</head>
<body class="student-dashboard student-dashboard--app" data-eventify-keyboard-scroll data-eventify-sidebar="studentSidebar" data-eventify-calendar="student-calendar" data-eventify-layout="root" data-eventify-main=".student-dashboard .main-content">


<nav class="top-navbar">
    <div class="navbar-left">
        <button type="button" class="nav-btn sidebar-toggle-mobile" id="sidebarToggleMobile" aria-label="Open menu" title="Menu">
            <i class="fas fa-bars"></i>
        </button>
        <button type="button" class="brand-logo border-0 bg-transparent p-0" data-student-panel="home" title="Back to calendar">
            <i class="fas fa-calendar-alt"></i>
            <span>EVENTIFY</span>
        </button>
    </div>
    <div class="navbar-right">
        <a class="nav-btn" href="<?= BASE_URL ?>/activities_hub.php" title="My registrations" aria-label="My registrations">
            <i class="fas fa-th-large"></i>
        </a>
        <button type="button" class="nav-btn" id="topCalendarShortcutBtn" title="Go to today">
            <i class="fas fa-calendar"></i>
        </button>
        <div class="dropdown">
            <button
                class="nav-btn position-relative dropdown-toggle"
                type="button"
                data-bs-toggle="dropdown"
                data-bs-auto-close="outside"
                aria-expanded="false"
                title="Notifications"
                id="studentNotifDropdownToggle"
                data-eventify-notif-badge
            >
                <i class="fas fa-bell"></i>
                <?php if ($unread_notif_count > 0): ?>
                    <span
                        class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                        style="font-size: 0.55rem;"
                        title="Unread notifications"
                    >
                        <?= $unread_notif_count > 99 ? '99+' : $unread_notif_count ?>
                    </span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end eventify-notif-dropdown" id="studentNotifDropdown">
                <li class="eventify-notif-dropdown__header">
                    <i class="fas fa-bell me-2"></i>Notifications
                    <?php if ($unread_notif_count > 0): ?>
                        <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;"><?= $unread_notif_count ?> new</span>
                    <?php endif; ?>
                </li>
                <li class="eventify-notif-dropdown__scroll">
                    <div class="eventify-notif-scroll eventify-notif-scroll--dropdown">
                        <?php
                            $notifications = $student_notif_dropdown;
                            $empty_title = 'All caught up';
                            $empty_text = 'No new notifications right now.';
                            $notif_interactive = true;
                            include __DIR__ . '/partials/notification_cards.php';
                        ?>
                    </div>
                </li>
                <?php if ($unread_notif_count > 0 || !empty($student_notifications)): ?>
                    <li class="eventify-notif-dropdown__footer">
                        <?php
                            $notif_show_mark_all = $unread_notif_count > 0;
                            $notif_show_clear = !empty($student_notifications);
                            $notif_mark_all_ajax = true;
                            $notif_clear_modal_id = 'studentClearNotifsModal';
                            $notif_context = 'dropdown';
                            include __DIR__ . '/partials/notification_footer_actions.php';
                        ?>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Profile dropdown -->
        <div class="dropdown">
            <button class="profile-avatar profile-toggle dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="<?= htmlspecialchars($user_name) ?>">
                <?php if (!empty($user['profile_picture'])): ?>
                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($user['profile_picture']) ?>" alt="<?= htmlspecialchars($user_name) ?>" class="profile-avatar-img">
                <?php else: ?>
                    <?= strtoupper(substr($user_name, 0, 1)) ?>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end profile-menu">
                <li class="px-3 py-2">
                    <div class="small text-muted">Signed in as</div>
                    <div class="fw-semibold"><?= htmlspecialchars($user_name) ?></div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item" href="<?= BASE_URL ?>/activities_hub.php">
                        <i class="fas fa-th-large me-2"></i> My registrations
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#" onclick="openProfileModal(); return false;">
                        <i class="fas fa-user me-2"></i> Profile
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#settingsModal">
                        <i class="fas fa-cog me-2"></i> Settings
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#helpModal">
                        <i class="fas fa-circle-question me-2"></i> Help
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#" data-student-panel="attendance">
                        <i class="fas fa-clipboard-check me-2"></i> Check-in history
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>
<!-- Left Sidebar (drawer on mobile, fixed panel on desktop) -->
<aside class="sidebar eventify-kb-scroll-zone" id="studentSidebar" tabindex="0" aria-label="Student sidebar — use arrow keys to scroll">
    <button type="button" class="sidebar-close-mobile" id="sidebarCloseMobile" aria-label="Close menu"><i class="fas fa-times"></i></button>
    <!-- User Info Card -->
    <div class="user-info-card">
            <div class="user-avatar-large">
                <?php if (!empty($user['profile_picture'])): ?>
                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($user['profile_picture']) ?>" alt="<?= htmlspecialchars($user_name) ?>" class="profile-picture-img">
                <?php else: ?>
                    <?= strtoupper(substr($user_name, 0, 1)) ?>
                <?php endif; ?>
            </div>
            <h3 class="user-name"><?= htmlspecialchars($user_name) ?></h3>
            <p class="user-id">ID: <?= htmlspecialchars($user['user_id'] ?? 'N/A') ?></p>
            <?php if ($department): ?>
                <span class="user-dept-badge"><?= htmlspecialchars($department) ?></span>
            <?php else: ?>
                <span class="user-dept-badge warning">No Department Set</span>
            <?php endif; ?>
        </div>

        <!-- Mini Calendar -->
        <div class="mini-calendar-widget">
            <div class="mini-calendar-header">
                <button class="mini-cal-nav" id="miniCalPrev"><i class="fas fa-chevron-left"></i></button>
                <span class="mini-cal-month" id="miniCalMonth"><?= date('F Y') ?></span>
                <button class="mini-cal-nav" id="miniCalNext"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="mini-calendar-grid" id="miniCalendar"></div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3 class="section-title">QUICK ACTIONS</h3>
            <button
                type="button"
                class="action-btn w-100 text-start border-0 bg-transparent"
                data-bs-toggle="modal"
                data-bs-target="#scanQRModal"
            >
                <i class="fas fa-qrcode"></i>
                <span>Scan QR</span>
            </button>
            <button
                type="button"
                class="action-btn w-100 text-start border-0 bg-transparent<?= $student_tickets_panel_open ? ' is-active' : '' ?>"
                data-student-panel="tickets"
                id="studentSidebarMyTickets"
            >
                <i class="fas fa-ticket-alt"></i>
                <span>My tickets</span>
                <?php if ($student_tickets_count > 0): ?>
                    <span class="badge bg-success ms-1"><?= $student_tickets_count ?></span>
                <?php endif; ?>
            </button>
            <button
                type="button"
                class="action-btn w-100 text-start border-0 bg-transparent<?= $student_upcoming_panel_open ? ' is-active' : '' ?>"
                data-student-panel="upcoming"
            >
                <i class="fas fa-calendar-check"></i>
                <span>Browse events</span>
                <?php if ($student_upcoming_count > 0): ?>
                    <span class="badge bg-success ms-1"><?= $student_upcoming_count ?></span>
                <?php endif; ?>
            </button>
            <?php
                $activities_hub_use_student_label = true;
                $activities_hub_student_label = 'My registrations';
                include __DIR__ . '/partials/activities_hub_quick_action.php';
            ?>
            <button
                type="button"
                class="action-btn w-100 text-start border-0 bg-transparent<?= $student_photos_panel_open ? ' is-active' : '' ?>"
                data-student-panel="photos"
            >
                <i class="fas fa-images"></i>
                <span>Photo Gallery</span>
                <?php if ($student_photos_gallery_count > 0): ?>
                    <span class="badge bg-success ms-1"><?= $student_photos_gallery_count ?></span>
                <?php endif; ?>
            </button>
            <button
                type="button"
                class="action-btn w-100 text-start border-0 bg-transparent<?= $student_attendance_panel_open ? ' is-active' : '' ?>"
                data-student-panel="attendance"
            >
                <i class="fas fa-clipboard-check"></i>
                <span>Check-in history</span>
                <?php if ($student_attendance_count > 0): ?>
                    <span class="badge bg-success ms-1"><?= $student_attendance_count ?></span>
                <?php endif; ?>
            </button>
        </div>

        <!-- My Department Info -->
        <?php if ($department): ?>
        <div class="department-info">
            <h3 class="section-title">MY DEPARTMENT</h3>
            <div class="department-badge-large" data-dept="<?= htmlspecialchars($department) ?>">
                <div class="dept-avatar"><?= strtoupper(substr($department, 0, 1)) ?></div>
                <span><?= htmlspecialchars($department) ?></span>
            </div>
            <p class="dept-note">You only see events open to your department or all departments.</p>
        </div>
        <?php endif; ?>

        <!-- Account (install + logout — not quick tasks) -->
        <div class="sidebar-account">
            <h3 class="section-title">ACCOUNT</h3>
            <button
                type="button"
                class="action-btn w-100 text-start border-0 bg-transparent"
                id="pwaInstallSidebarBtn"
                hidden
            >
                <i class="fas fa-download"></i>
                <span>Install on phone</span>
            </button>
            <a href="#" class="action-btn logout-btn" data-bs-toggle="modal" data-bs-target="#logoutModal">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
</aside>

<!-- Main Layout -->
<div class="dashboard-layout">
    <!-- Main Content Area -->
    <main class="main-content eventify-kb-scroll-zone" tabindex="0" aria-label="Student calendar — use arrow keys to scroll">
        <!-- Success/Error Message -->
        <?php if ($msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong><?= htmlspecialchars($msg) ?></strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong><?= htmlspecialchars($error) ?></strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div id="pwaOfflineBanner" class="pwa-offline-notice" hidden>
            <i class="fas fa-wifi me-1"></i> You are offline. Saved tickets and passes still work; other features need internet.
        </div>

        <div id="pwaInstallBanner" hidden>
            <div class="pwa-install-banner__text">
                <strong>Install EVENTIFY</strong>
                <span id="pwaInstallBannerHint" class="pwa-install-banner__hint" hidden></span>
                <span class="pwa-install-banner__desc">Add to your home screen for quick access to tickets and QR check-in — works offline for saved passes.</span>
            </div>
            <div class="pwa-install-banner__actions">
                <button type="button" class="pwa-install-banner__btn pwa-install-banner__btn--primary" id="pwaInstallBtn">Install</button>
                <button type="button" class="pwa-install-banner__btn pwa-install-banner__btn--ghost" id="pwaInstallDismiss">Not now</button>
            </div>
        </div>

        <div id="pwaPushBanner" class="pwa-push-banner" hidden>
            <div class="pwa-push-banner__text">
                <strong>Enable event alerts</strong>
                <span class="pwa-push-banner__desc">Get phone notifications for RSVP updates, schedule changes, and tickets.</span>
                <span class="pwa-push-banner__ios-note" hidden data-ios-push-note>On iPhone, install EVENTIFY to Home Screen first, then enable alerts here.</span>
            </div>
            <div class="pwa-push-banner__actions">
                <button type="button" class="pwa-push-banner__btn pwa-push-banner__btn--primary" id="pwaPushEnableBtn" aria-label="Enable push notifications">Enable</button>
                <button type="button" class="pwa-push-banner__btn pwa-push-banner__btn--ghost" id="pwaPushDismiss">Not now</button>
            </div>
        </div>

        <div id="studentDashboardHome" class="<?= $student_dashboard_panel_open ? 'd-none' : '' ?>">

        <!-- My Events hub -->
        <?php if (!empty($my_registered_events) || !empty($today_activities)): ?>
        <section class="student-my-events-hub mb-4" id="studentMyEventsHub">
            <div class="student-my-events-hub__header">
                <h3 class="section-heading mb-0"><i class="fas fa-id-card-alt me-2 text-success"></i>My registrations</h3>
                <div class="student-my-events-hub__actions">
                    <a class="btn btn-outline-success btn-sm student-my-events-hub__action" href="<?= BASE_URL ?>/activities_hub.php">
                        <i class="fas fa-th-large me-1" aria-hidden="true"></i>
                        <span class="student-my-events-hub__label-full">My registrations</span>
                        <span class="student-my-events-hub__label-short">Registrations</span>
                    </a>
                    <button type="button" class="btn btn-success btn-sm student-my-events-hub__action" data-bs-toggle="modal" data-bs-target="#scanQRModal">
                        <i class="fas fa-qrcode me-1" aria-hidden="true"></i>Scan QR
                    </button>
                </div>
            </div>
            <?php if (!empty($my_registered_events)): ?>
            <div class="student-my-events-hub__list">
                <?php foreach (array_slice($my_registered_events, 0, 6) as $mev): ?>
                    <?php
                    $meid = (int) ($mev['id'] ?? 0);
                    $meEnd = function_exists('eventify_event_resolve_end_date') ? eventify_event_resolve_end_date($mev) : ($mev['date'] ?? '');
                    ?>
                    <article class="student-my-event-card" role="button" tabindex="0" data-event-id="<?= $meid ?>" onclick="openStudentEventById(<?= $meid ?>)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openStudentEventById(<?= $meid ?>);}">
                        <div class="student-my-event-card__body">
                            <div class="student-my-event-card__title"><?= htmlspecialchars($mev['title'] ?? 'Event') ?></div>
                            <div class="student-my-event-card__meta">
                                <span><i class="fas fa-calendar-day me-1" aria-hidden="true"></i><?= htmlspecialchars($meEnd ?: ($mev['date'] ?? '')) ?></span>
                                <?php if (!empty($attended_event_ids[$meid])): ?>
                                    <span class="badge bg-success">Checked in</span>
                                <?php else: ?>
                                    <span class="badge bg-primary">RSVP confirmed</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="student-my-event-card__footer">
                            <a class="student-my-event-card__activities" href="<?= BASE_URL ?>/event_activities.php?id=<?= $meid ?>" onclick="event.stopPropagation();">
                                <span><i class="fas fa-layer-group me-1" aria-hidden="true"></i>View activities</span>
                                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-muted small mb-0">RSVP for an event from the calendar to see it here.</p>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <!-- Today's activities (sub-events) -->
        <?php if (!empty($today_activities)): ?>
        <div class="today-activities-section mb-4">
            <div class="today-activities-header">
                <h3 class="section-heading mb-0"><i class="fas fa-bolt me-2 text-success"></i>Today's Activities</h3>
                <a class="btn btn-outline-success btn-sm" href="<?= BASE_URL ?>/activities_hub.php">View hub</a>
            </div>
            <div class="today-activities-scroll" role="list">
                <?php foreach ($today_activities as $idx => $act): ?>
                    <?php
                    $actTime = eventify_format_session_time_range($act['start_time'] ?? null, $act['end_time'] ?? null);
                    $actStatus = (string) ($act['status'] ?? 'scheduled');
                    $actCat = trim((string) ($act['category'] ?? ''));
                    $faIcon = eventify_activity_fa_icon((string) ($act['title'] ?? ''), $actCat !== '' ? $actCat : null);
                    $accentClass = ($idx % 2 === 0) ? 'today-act-card--warm' : 'today-act-card--cool';
                    $isLive = eventify_session_is_live_now($act, $today);
                    $shortLoc = eventify_short_activity_location($act['location'] ?? '');
                    $eventId = (int) ($act['event_id'] ?? 0);
                    $activityId = (int) ($act['id'] ?? 0);
                    $hubUrl = $eventId > 0
                        ? BASE_URL . '/event_activities.php?id=' . $eventId . ($activityId > 0 ? '&activity=' . $activityId : '')
                        : '';
                    $cardClasses = 'today-act-card ' . $accentClass;
                    if ($actStatus === 'cancelled') {
                        $cardClasses .= ' today-act-card--cancelled';
                    }
                    if ($eventId > 0 && $actStatus !== 'cancelled') {
                        $cardClasses .= ' js-student-open-event';
                    }
                    ?>
                    <article
                        class="<?= htmlspecialchars($cardClasses) ?>"
                        role="listitem"
                        <?= ($eventId > 0 && $actStatus !== 'cancelled') ? ' tabindex="0" data-event-id="' . $eventId . '"' : '' ?>
                    >
                        <div class="today-act-card__icon" aria-hidden="true">
                            <i class="fas fa-<?= htmlspecialchars($faIcon) ?>"></i>
                        </div>
                        <div class="today-act-card__blob" aria-hidden="true"></div>
                        <?php if ($hubUrl !== ''): ?>
                            <a class="today-act-card__menu" href="<?= htmlspecialchars($hubUrl) ?>" title="Activity details" aria-label="Open activity" onclick="event.stopPropagation();">
                                <i class="fas fa-ellipsis-v"></i>
                            </a>
                        <?php endif; ?>
                        <div class="today-act-card__content">
                            <h4 class="today-act-card__title"><?= htmlspecialchars($act['title'] ?? 'Activity') ?></h4>
                            <?php if (!empty($act['event_title'])): ?>
                                <p class="today-act-card__event"><?= htmlspecialchars($act['event_title']) ?></p>
                            <?php endif; ?>
                            <?php if ($actTime !== ''): ?>
                                <p class="today-act-card__meta"><i class="fas fa-clock"></i> <?= htmlspecialchars($actTime) ?></p>
                            <?php endif; ?>
                            <?php if ($shortLoc !== ''): ?>
                                <p class="today-act-card__meta today-act-card__meta--loc" title="<?= htmlspecialchars(trim((string) ($act['location'] ?? ''))) ?>">
                                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($shortLoc) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <?php if ($isLive && $actStatus !== 'cancelled'): ?>
                            <span class="today-act-card__live">Live</span>
                        <?php elseif ($actStatus !== 'scheduled'): ?>
                            <span class="today-act-card__status"><?= htmlspecialchars(ucfirst($actStatus)) ?></span>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Upcoming Events List (at top for quick scan) -->
        <div class="upcoming-events-section">
            <h3 class="section-heading">Upcoming Events</h3>
            <?php if (!empty($upcoming_events)): ?>
                <div class="events-list">
                    <?php foreach (array_slice($upcoming_events, 0, 5) as $event): ?>
                        <div class="event-item student-event-link" data-event-id="<?= isset($event['id']) ? (int)$event['id'] : '' ?>" role="button">
                            <div class="event-date-badge">
                                <span class="event-month"><?= date('M', strtotime($event['date'])) ?></span>
                                <span class="event-day"><?= date('d', strtotime($event['date'])) ?></span>
                            </div>
                            <div class="event-details">
                                <h4 class="event-title"><?= htmlspecialchars($event['title'] ?? 'Untitled') ?></h4>
                                <p class="event-meta">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?= htmlspecialchars($event['location'] ?? 'TBA') ?>
                                </p>
                                <?php if (!empty($event['description'])): ?>
                                    <p class="event-desc"><?= htmlspecialchars(substr($event['description'], 0, 100)) ?><?= strlen($event['description']) > 100 ? '...' : '' ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-events">No upcoming events for your department.</p>
            <?php endif; ?>
        </div>

        <!-- Calendar Controls -->
        <div class="calendar-controls" id="studentCalendarSection">
            <div class="controls-left">
                <button class="control-nav" id="calPrev"><i class="fas fa-chevron-left"></i></button>
                <h2 class="calendar-title" id="calendarTitle">September, 2026</h2>
                <button class="control-nav" id="calNext"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="controls-right">
                <button class="view-btn active" data-view="dayGridMonth">Month</button>
                <button class="view-btn" data-view="timeGridWeek">Week</button>
                <button class="view-btn" data-view="timeGridDay">Day</button>
                <button class="view-btn" data-view="today">Today</button>
            </div>
        </div>
        <?php
        $legendId = 'studentCalendarLegend';
        $legendClass = 'eventify-calendar-legend student-calendar-legend';
        include __DIR__ . '/partials/calendar_event_state_legend.php';
        ?>

        <!-- FullCalendar Container -->
        <div class="calendar-container">
            <div id="student-calendar"></div>
        </div>

        </div><!-- #studentDashboardHome -->

        <?php include __DIR__ . '/partials/student_my_tickets_panel.php'; ?>
        <?php include __DIR__ . '/partials/student_attendance_panel.php'; ?>
        <?php include __DIR__ . '/partials/student_upcoming_panel.php'; ?>
        <?php include __DIR__ . '/partials/student_photo_gallery_panel.php'; ?>
    </main>
</div>

<!-- Profile Modal -->
<div id="profileModal" class="profile-modal">
    <div class="profile-modal-content efy-modal">
        <div class="profile-modal-header efy-modal__header">
            <div>
                <span class="efy-modal__eyebrow">Student</span>
                <h2 class="efy-modal__title mb-0"><i class="fas fa-user" aria-hidden="true"></i> My information</h2>
            </div>
            <button type="button" class="profile-close btn-close btn-close-white" onclick="closeProfileModal()" aria-label="Close">&times;</button>
        </div>
        <div class="profile-modal-body efy-modal__body">
        <form id="profileForm" action="<?= BASE_URL ?>/backend/auth/update_student_profile.php" method="POST" enctype="multipart/form-data" onsubmit="event.preventDefault(); confirmProfileChanges(this);">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="profilePictureModal">Profile Picture</label>
                <div class="profile-picture-preview-container">
                    <?php if (!empty($user['profile_picture'])): ?>
                        <img src="<?= BASE_URL ?>/<?= htmlspecialchars($user['profile_picture']) ?>" alt="Current profile picture" id="profilePicturePreview" class="profile-picture-preview profile-picture-clickable" onclick="openProfilePicFullscreen(this.src)" title="Click to view full screen">
                    <?php else: ?>
                        <div class="profile-picture-placeholder" id="profilePicturePreview">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <input
                    type="file"
                    id="profilePictureModal"
                    name="profile_picture"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    class="form-control-file"
                    onchange="previewProfilePicture(this)"
                >
                <small class="text-muted">JPG, PNG, GIF, or WEBP (max 5MB)</small>
            </div>

            <div class="form-group">
                <label for="fullNameModal">Full Name</label>
                <input
                    type="text"
                    id="fullNameModal"
                    name="name"
                    value="<?= htmlspecialchars($user['name'] ?? $user_name) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>Student ID</label>
                <input
                    type="text"
                    value="<?= htmlspecialchars($user['user_id'] ?? 'N/A') ?>"
                    readonly
                >
            </div>

            <div class="form-group">
                <label for="studentCourseModal">Course / program <span class="text-danger">*</span></label>
                <select id="studentCourseModal" name="student_course" required>
                    <?php
                    $courseOpts = eventify_student_course_program_options();
                    $storedCourse = trim((string) ($user['student_course'] ?? ''));
                    $selectedCourse = ($storedCourse !== '' && isset($courseOpts[$storedCourse]) && $storedCourse !== '')
                        ? $storedCourse
                        : '';
                    foreach ($courseOpts as $cv => $clab):
                        $sel = ((string) $cv === (string) $selectedCourse) ? ' selected' : '';
                    ?>
                        <option value="<?= htmlspecialchars((string) $cv) ?>"<?= $sel ?>><?= htmlspecialchars($clab) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Required — shown on attendance lists. Choose the program that matches your enrollment.</small>
            </div>

            <div class="form-group">
                <label for="studentDepartmentModal">Department</label>
                <?php
                $deptFromCourse = function_exists('eventify_student_course_program_department')
                    ? eventify_student_course_program_department((string)($selectedCourse ?? ''))
                    : '';
                $displayDepartment = trim((string)($deptFromCourse !== '' ? $deptFromCourse : ($user['department'] ?? '')));
                ?>
                <input
                    type="text"
                    id="studentDepartmentModal"
                    value="<?= htmlspecialchars($displayDepartment !== '' ? $displayDepartment : 'Department will be set from selected course') ?>"
                    readonly
                >
                <small class="text-muted">Auto-assigned from your selected course / program.</small>
            </div>

            <div class="form-group">
                <label for="studentSectionModal">Class section</label>
                <input
                    type="text"
                    id="studentSectionModal"
                    value="<?= htmlspecialchars(trim((string) ($user['student_section'] ?? '')) !== '' ? (string) $user['student_section'] : 'Not assigned yet') ?>"
                    readonly
                >
                <small class="text-muted">Assigned by admin. Needed for section-only events (e.g. BSIT 4102).</small>
            </div>

            <div class="form-group">
                <label for="studentYearLevelModal">Year level</label>
                <select id="studentYearLevelModal" name="student_year_level">
                    <?php foreach (eventify_student_year_level_options() as $yv => $ylab): ?>
                        <option value="<?= htmlspecialchars($yv) ?>" <?= ((string) ($user['student_year_level'] ?? '') === (string) $yv) ? 'selected' : '' ?>><?= htmlspecialchars($ylab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="studentAcademicYearModal">School year (AY)</label>
                <select id="studentAcademicYearModal" name="student_academic_year">
                    <?php foreach (eventify_student_academic_year_options() as $yv => $ylab): ?>
                        <option value="<?= htmlspecialchars($yv) ?>" <?= ((string) ($user['student_academic_year'] ?? '') === (string) $yv) ? 'selected' : '' ?>><?= htmlspecialchars($ylab) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Academic year (e.g. 2025-2026).</small>
            </div>

            <button type="submit" class="btn efy-btn-primary w-100">Save info</button>
        </form>
        </div>
    </div>
</div>

<!-- Pass PHP events to JS -->
<script>
window.BASE_URL = <?= json_encode(BASE_URL) ?>;
window.currentRole = 'student';
window.csrfToken = <?= json_encode(function_exists('csrf_token') ? csrf_token() : '') ?>;
window.studentEvents = <?= json_encode(eventify_events_to_fullcalendar_list($events, function ($e) use ($registered_event_ids, $reg_count_by_event, $feedback_submitted_ids, $attended_event_ids) {
    $eid = isset($e['id']) ? (int) $e['id'] : 0;
    $mc = $e['max_capacity'] ?? null;
    $maxCap = ($mc !== null && $mc !== '') ? (int) $mc : null;
  return [
        'event_id'            => $eid,
        'location'            => $e['location'] ?? '',
        'description'         => $e['description'] ?? '',
        'start_time'          => $e['start_time'] ?? null,
            'end_time'            => $e['end_time'] ?? null,
            'end_time_na'         => !empty($e['end_time_na']),
        'department'          => $e['department'] ?? 'ALL',
        'department_display'  => eventify_format_department_label((string) ($e['department'] ?? 'ALL')),
        'target_sections'     => $e['target_sections'] ?? null,
        'sections_display'    => function_exists('eventify_format_target_sections_label')
            ? eventify_format_target_sections_label($e['target_sections'] ?? null)
            : '',
        'max_capacity'        => $maxCap,
        'registration_count'  => $reg_count_by_event[$eid] ?? 0,
        'is_registered'       => in_array($eid, $registered_event_ids, true),
        'has_feedback'        => in_array($eid, $feedback_submitted_ids, true),
        'attended'            => in_array($eid, $attended_event_ids, true),
        'status'              => $e['status'] ?? '',
        'registration_mode'   => function_exists('eventify_event_registration_mode') ? eventify_event_registration_mode($e) : 'rsvp',
        'event_is_live'       => function_exists('eventify_event_is_live') ? eventify_event_is_live($e) : (($e['status'] ?? '') === 'active'),
    ];
}), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;

window.currentUser = {
    name: <?= json_encode($user_name) ?>,
    id: <?= json_encode($_SESSION['user_id'] ?? 0) ?>,
    department: <?= json_encode($department) ?>
};
window.__studentSettings = <?= json_encode($studentSettings ?? []) ?>;
window.__eventifyVapidPublicKey = <?= json_encode($eventifyVapidPublicKey ?? '') ?>;
window.__studentOpenModal = <?= json_encode($openModal) ?>;
window.__studentCourseDepartmentMap = <?= json_encode(function_exists('eventify_student_course_program_department_map') ? eventify_student_course_program_department_map() : [], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
window.__studentPendingUrgentFeedback = <?= json_encode($pending_urgent_feedback_events, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
window.__eventEvaluationSections = <?= json_encode(array_values($event_evaluation_sections ?? []), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
</script>

<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/logout_confirm.js?v=2"></script>

<!-- Logout Modal -->
<?php include __DIR__ . '/partials/logout_confirm_modal.php'; ?>

<!-- Settings Modal -->
<div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content efy-modal">
      <form id="studentSettingsForm" method="POST" action="<?= BASE_URL ?>/backend/auth/update_student_settings.php">
        <?= csrf_field() ?>
        <div class="modal-header efy-modal__header">
          <div>
            <span class="efy-modal__eyebrow">Student</span>
            <h5 class="modal-title efy-modal__title" id="settingsModalLabel">
              <i class="fas fa-cog" aria-hidden="true"></i>
              Settings
            </h5>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body efy-modal__body">
          <div class="student-settings-section">
            <h6>Security</h6>
            <p class="small text-muted mb-2">Manage account security options.</p>
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#studentChangePasswordModal">
              <i class="fas fa-key me-1"></i>Change Password
            </button>
          </div>

          <div class="student-settings-section">
            <h6>Notifications</h6>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="event_reminders" name="event_reminders" value="1" <?= !empty($studentSettings['event_reminders']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="event_reminders">Event reminders</label>
            </div>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="rsvp_updates" name="rsvp_updates" value="1" <?= !empty($studentSettings['rsvp_updates']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="rsvp_updates">RSVP updates</label>
            </div>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="announcement_notifications" name="announcement_notifications" value="1" <?= !empty($studentSettings['announcement_notifications']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="announcement_notifications">Announcements</label>
            </div>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="notif_channel_push" name="notif_channel_push" value="1" <?= !empty($studentSettings['notif_channel_push']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="notif_channel_push">Phone push notifications</label>
            </div>
            <button type="button" class="btn btn-outline-success btn-sm mb-2" id="studentEnablePushBtn">
              <i class="fas fa-bell me-1"></i>Enable push on this device
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm mb-2" id="studentTestPushBtn">
              <i class="fas fa-paper-plane me-1"></i>Send test notification
            </button>
            <p class="small text-muted mb-2" id="studentPushStatusHint" hidden></p>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="notif_channel_email" name="notif_channel_email" value="1" <?= !empty($studentSettings['notif_channel_email']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="notif_channel_email">Enable email channel</label>
            </div>
          </div>

          <div class="student-settings-section">
            <h6>Calendar & Display</h6>
            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label small" for="default_calendar_view">Default Calendar View</label>
                <?php $stDefaultView = (string)($studentSettings['default_calendar_view'] ?? 'dayGridMonth'); ?>
                <select class="form-select" id="default_calendar_view" name="default_calendar_view">
                  <option value="dayGridMonth" <?= $stDefaultView === 'dayGridMonth' ? 'selected' : '' ?>>Month</option>
                  <option value="timeGridWeek" <?= $stDefaultView === 'timeGridWeek' ? 'selected' : '' ?>>Week</option>
                  <option value="timeGridDay" <?= $stDefaultView === 'timeGridDay' ? 'selected' : '' ?>>Day</option>
                </select>
              </div>
              <div class="col-md-6 d-flex align-items-end">
                <div class="form-check form-switch mb-2">
                  <input class="form-check-input" type="checkbox" id="show_calendar_legend" name="show_calendar_legend" value="1" <?= !empty($studentSettings['show_calendar_legend']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="show_calendar_legend">Show event legend</label>
                </div>
              </div>
            </div>
          </div>

          <div class="student-settings-section">
            <h6>RSVP Preferences</h6>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="auto_add_rsvp_calendar" name="auto_add_rsvp_calendar" value="1" <?= !empty($studentSettings['auto_add_rsvp_calendar']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="auto_add_rsvp_calendar">Auto-add RSVP events to my calendar</label>
            </div>
            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label small" for="reminder_timing">Reminder timing</label>
                <?php $stReminderTiming = (string)($studentSettings['reminder_timing'] ?? '30_min'); ?>
                <select class="form-select" id="reminder_timing" name="reminder_timing">
                  <option value="30_min" <?= $stReminderTiming === '30_min' ? 'selected' : '' ?>>30 minutes before</option>
                  <option value="1_hour" <?= $stReminderTiming === '1_hour' ? 'selected' : '' ?>>1 hour before</option>
                  <option value="1_day" <?= $stReminderTiming === '1_day' ? 'selected' : '' ?>>1 day before</option>
                </select>
                <span class="form-text small text-muted">Bell + push before events start (open-entry audience or your RSVPs). Also notifies when the event is starting. Needs Event reminders + Phone push on.</span>
              </div>
              <div class="col-md-6 d-flex align-items-end">
                <div class="form-check form-switch mb-2">
                  <input class="form-check-input" type="checkbox" id="hide_past_rsvped" name="hide_past_rsvped" value="1" <?= !empty($studentSettings['hide_past_rsvped']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="hide_past_rsvped">Hide past RSVPed events</label>
                </div>
              </div>
            </div>
          </div>

          <div class="student-settings-section">
            <h6>Privacy</h6>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="share_profile_with_organizers" name="share_profile_with_organizers" value="1" <?= !empty($studentSettings['share_profile_with_organizers']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="share_profile_with_organizers">Show my profile info to organizers in attendee lists</label>
            </div>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="allow_photo_tagging" name="allow_photo_tagging" value="1" <?= !empty($studentSettings['allow_photo_tagging']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="allow_photo_tagging">Allow photo tagging consent</label>
            </div>
          </div>
        </div>
        <div class="modal-footer efy-modal__footer">
          <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn efy-btn-primary" id="studentSettingsUpdateBtn"><i class="fas fa-save me-1"></i>Update settings</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Change Password Modal (Student) -->
<div class="modal fade" id="studentChangePasswordModal" tabindex="-1" aria-labelledby="studentChangePasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content efy-modal efy-modal--compact">
      <form method="POST" action="<?= BASE_URL ?>/backend/auth/change_password.php">
        <?= csrf_field() ?>
        <input type="hidden" name="return_to" value="student_dashboard">
        <div class="modal-header efy-modal__header">
          <div>
            <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="studentChangePasswordModalLabel">
              <i class="fas fa-key" aria-hidden="true"></i>
              Change password
            </h5>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body efy-modal__body efy-modal__body--compact">
          <div class="mb-2">
            <label class="form-label small" for="studentCurrentPassword">Current Password</label>
            <div class="password-input-wrap">
              <input type="password" class="form-control" id="studentCurrentPassword" name="current_password" required>
              <button type="button" class="password-toggle-btn" data-target="studentCurrentPassword" aria-label="Show current password">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label small" for="studentNewPassword">New Password</label>
            <div class="password-input-wrap">
              <input type="password" class="form-control" id="studentNewPassword" name="new_password" required>
              <button type="button" class="password-toggle-btn" data-target="studentNewPassword" aria-label="Show new password">
                <i class="fas fa-eye"></i>
              </button>
            </div>
            <small class="text-muted">At least 8 chars, 1 uppercase, 1 special character.</small>
          </div>
          <div class="mb-0">
            <label class="form-label small" for="studentConfirmPassword">Confirm New Password</label>
            <div class="password-input-wrap">
              <input type="password" class="form-control" id="studentConfirmPassword" name="confirm_password" required>
              <button type="button" class="password-toggle-btn" data-target="studentConfirmPassword" aria-label="Show confirm password">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
        </div>
        <div class="modal-footer efy-modal__footer">
          <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn efy-btn-primary"><i class="fas fa-save me-1"></i>Update password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Confirm student settings update -->
<div class="modal fade" id="confirmStudentSettingsModal" tabindex="-1" aria-labelledby="confirmStudentSettingsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content efy-modal efy-modal--compact">
      <div class="modal-header efy-modal__header">
        <div>
          <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="confirmStudentSettingsModalLabel">
            <i class="fas fa-save" aria-hidden="true"></i>
            Confirm update
          </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body efy-modal__body efy-modal__body--compact">
        <p class="efy-confirm-message mb-0">Are you sure you want to update your settings?</p>
      </div>
      <div class="modal-footer efy-modal__footer">
        <button type="button" class="btn efy-btn-primary" id="confirmStudentSettingsYesBtn">Yes, update</button>
        <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">No</button>
      </div>
    </div>
  </div>
</div>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content efy-modal efy-modal--compact">
      <div class="modal-header efy-modal__header">
        <div>
          <span class="efy-modal__eyebrow">Student</span>
          <h5 class="modal-title efy-modal__title" id="helpModalLabel">
            <i class="fas fa-circle-question" aria-hidden="true"></i>
            Help
          </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body efy-modal__body efy-modal__body--compact">
        <ul class="help-list mb-0">
          <li>Use the mini calendar to jump to a date.</li>
          <li>Events shown are filtered by your department.</li>
          <li>Click Profile to update your info, course, year level, and school year.</li>
        </ul>
      </div>
      <div class="modal-footer efy-modal__footer">
        <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Scan QR for attendance -->
<?php include __DIR__ . '/partials/scan_qr_modal.php'; ?>

<?php
    $notif_clear_modal_id = 'studentClearNotifsModal';
    include __DIR__ . '/partials/notification_clear_confirm_modal.php';
    include __DIR__ . '/partials/notification_detail_modal.php';
?>

<nav class="student-app-tabbar d-md-none" aria-label="Student app navigation">
  <button type="button" class="student-app-tabbar__btn" data-student-nav="scan" data-bs-toggle="modal" data-bs-target="#scanQRModal">
    <i class="fas fa-qrcode"></i><span>Scan</span>
  </button>
  <button type="button" class="student-app-tabbar__btn" data-student-nav="myevents" data-scroll-target="#studentMyEventsHub" title="My registrations" aria-label="My registrations">
    <i class="fas fa-id-card-alt"></i><span>Joined</span>
  </button>
  <button type="button" class="student-app-tabbar__btn" data-student-nav="calendar" data-scroll-target="#studentCalendarSection">
    <i class="fas fa-calendar"></i><span>Calendar</span>
  </button>
  <button type="button" class="student-app-tabbar__btn<?= $student_attendance_panel_open ? ' is-active' : '' ?>" data-student-panel="attendance" title="Check-in history" aria-label="Check-in history">
    <i class="fas fa-clipboard-check"></i><span>History</span>
  </button>
</nav>

<!-- PWA install help (iPhone / Android manual steps) -->
<div class="modal fade" id="pwaInstallHelpModal" tabindex="-1" aria-labelledby="pwaInstallHelpModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content efy-modal pwa-install-modal">
      <div class="modal-header efy-modal__header">
        <div>
          <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="pwaInstallHelpModalLabel">
            <i class="fas fa-mobile-alt" aria-hidden="true"></i>
            Install EVENTIFY
          </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body efy-modal__body" id="pwaInstallHelpModalBody"></div>
      <div class="modal-footer efy-modal__footer">
        <button type="button" class="btn btn-success rounded-pill px-4" data-bs-dismiss="modal">Got it</button>
      </div>
    </div>
  </div>
</div>

<!-- Urgent post-event feedback prompt -->
<div class="modal fade" id="studentUrgentFeedbackModal" tabindex="-1" aria-labelledby="studentUrgentFeedbackModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content efy-modal efy-modal--compact">
      <div class="modal-header efy-modal__header">
        <div>
          <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="studentUrgentFeedbackModalLabel">
            <i class="fas fa-bullhorn" aria-hidden="true"></i>
            Evaluation needed
          </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body efy-modal__body efy-modal__body--compact" id="studentUrgentFeedbackModalBody">
        <p class="mb-0 efy-form-help">Loading…</p>
      </div>
      <div class="modal-footer efy-modal__footer flex-wrap gap-2">
        <button type="button" class="btn btn-outline-secondary rounded-pill" id="studentUrgentFeedbackSnoozeBtn">Remind me in 4 hours</button>
        <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Event Details Modal -->
<div class="modal fade" id="eventDetailsModal" tabindex="-1" aria-labelledby="eventDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content efy-modal">
      <div class="modal-header efy-modal__header">
        <div>
          <span class="efy-modal__eyebrow">Student</span>
          <h5 class="modal-title efy-modal__title" id="eventDetailsModalLabel">
            <i class="fas fa-calendar-alt" aria-hidden="true"></i>
            Event details
          </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body efy-modal__body" id="eventDetailsModalBody">
        <p class="mb-0 efy-form-help">Loading…</p>
      </div>
      <div class="modal-footer efy-modal__footer flex-wrap gap-2" id="studentEventDetailsModalFooter">
        <div id="studentEventDetailsRsvpActions" class="d-flex flex-wrap gap-2 me-auto"></div>
        <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Cancel RSVP Confirmation Modal -->
<div class="modal fade" id="cancelRsvpConfirmModal" tabindex="-1" aria-labelledby="cancelRsvpConfirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content efy-modal efy-modal--compact">
      <div class="modal-header efy-modal__header">
        <div>
          <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="cancelRsvpConfirmModalLabel">
            <i class="fas fa-user-minus" aria-hidden="true"></i>
            Cancel RSVP
          </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body efy-modal__body efy-modal__body--compact">
        <p class="efy-confirm-message mb-0">Are you sure you want to cancel your RSVP for this event?</p>
      </div>
      <div class="modal-footer efy-modal__footer">
        <button type="button" class="btn efy-btn-danger" id="cancelRsvpConfirmYesBtn">Yes, cancel RSVP</button>
        <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">No</button>
      </div>
    </div>
  </div>
</div>

<!-- Profile Changes Confirmation Modal -->
<div class="modal fade" id="confirmProfileChangesModal" tabindex="-1" aria-labelledby="confirmProfileChangesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content efy-modal efy-modal--compact">
            <div class="modal-header efy-modal__header">
                <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="confirmProfileChangesModalLabel">
                    <i class="fas fa-save" aria-hidden="true"></i>
                    Confirm changes
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body efy-modal__body efy-modal__body--compact">
                <p id="confirmProfileChangesMessage" class="efy-confirm-message mb-0"></p>
            </div>
            <div class="modal-footer efy-modal__footer">
                <button type="button" class="btn efy-btn-primary" id="confirmProfileChangesBtn">
                    <i class="fas fa-check me-1"></i> Yes, proceed
                </button>
                <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Profile Picture Full-Screen Viewer -->
<div class="profile-pic-fullscreen" id="profilePicFullscreen" style="display:none;">
    <div class="profile-pic-fullscreen-overlay" onclick="closeProfilePicFullscreen()"></div>
    <button class="profile-pic-fullscreen-close" onclick="closeProfilePicFullscreen()" aria-label="Close">
        <i class="fas fa-times"></i>
    </button>
    <div class="profile-pic-fullscreen-content">
        <img id="profilePicFullscreenImg" src="" alt="Profile picture">
    </div>
</div>

<!-- jsQR for QR code decoding -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/scan_qr.js?v=2"></script>
<!-- Dashboard Scripts -->
<script src="<?= BASE_URL ?>/assets/js/eventify_calendar_colors.js?v=10"></script>
<script src="<?= BASE_URL ?>/assets/js/event_day_sessions.js?v=10"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_notifications.js?v=7"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_dashboard_keyboard_scroll.js"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_schedule_display.js?v=1"></script>
<script src="<?= BASE_URL ?>/assets/js/dashboard_student.js?v=22"></script>
<script>window.__myTicketsBootstrap = <?= json_encode($student_tickets_bootstrap, JSON_UNESCAPED_UNICODE) ?>;</script>

<script>
// Profile picture preview function
function previewProfilePicture(input) {
    const preview = document.getElementById('profilePicturePreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            if (preview.tagName === 'IMG') {
                preview.src = e.target.result;
                preview.classList.add('profile-picture-clickable');
                preview.onclick = function() { openProfilePicFullscreen(preview.src); };
            } else {
                const img = document.createElement('img');
                img.id = 'profilePicturePreview';
                img.className = 'profile-picture-preview profile-picture-clickable';
                img.src = e.target.result;
                img.alt = 'Profile picture preview';
                img.onclick = function() { openProfilePicFullscreen(img.src); };
                preview.parentNode.replaceChild(img, preview);
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

var pendingFormSubmission = null;

function confirmProfileChanges(form) {
    var fileInput = form.querySelector('input[name="profile_picture"]');
    var hasNewPicture = fileInput && fileInput.files && fileInput.files.length > 0;
    var msg = hasNewPicture
        ? 'Are you sure you want to change your profile picture? Your current picture will be replaced.'
        : 'Are you sure you want to save your changes?';
    
    // Store the form for later submission
    pendingFormSubmission = form;
    
    // Set the message in the modal
    document.getElementById('confirmProfileChangesMessage').textContent = msg;
    
    // Show the modal using Bootstrap
    var modalEl = document.getElementById('confirmProfileChangesModal');
    var modal = new bootstrap.Modal(modalEl, {
        backdrop: true,
        keyboard: true
    });
    modal.show();
    
    // Ensure modal and backdrop have higher z-index after showing
    setTimeout(function() {
        var backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
            backdrop.style.zIndex = '1199';
        }
        modalEl.style.zIndex = '1200';
    }, 10);
}

// Handle confirm button click
document.addEventListener('DOMContentLoaded', function() {
    var confirmBtn = document.getElementById('confirmProfileChangesBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            if (pendingFormSubmission) {
                // Hide the modal first
                var modal = bootstrap.Modal.getInstance(document.getElementById('confirmProfileChangesModal'));
                if (modal) {
                    modal.hide();
                }
                // Submit the form
                pendingFormSubmission.submit();
                pendingFormSubmission = null;
            }
        });
    }
});

function openProfilePicFullscreen(src) {
    if (!src) return;
    var el = document.getElementById('profilePicFullscreen');
    var img = document.getElementById('profilePicFullscreenImg');
    if (el && img) {
        img.src = src;
        el.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeProfilePicFullscreen() {
    var el = document.getElementById('profilePicFullscreen');
    if (el) {
        el.style.display = 'none';
        document.body.style.overflow = '';
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeProfilePicFullscreen();
});

</script>
<script src="<?= BASE_URL ?>/assets/js/eventify_alert_modal.js?v=1"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_pwa.js?v=17"></script>
<script>
(function () {
    function showPushMessage(message, options) {
        if (typeof window.eventifyAlert === 'function') {
            window.eventifyAlert(message, options || {});
            return;
        }
        if (window.eventifyAlertModal && typeof window.eventifyAlertModal.show === 'function') {
            window.eventifyAlertModal.show(message, options || {});
        }
    }

    function updatePushStatusHint() {
        var hint = document.getElementById('studentPushStatusHint');
        if (!hint || !window.eventifyPwa || typeof window.eventifyPwa.fetchPushStatus !== 'function') {
            return;
        }
        window.eventifyPwa.fetchPushStatus().then(function (status) {
            if (!status || !status.ok) {
                return;
            }
            if (typeof Notification !== 'undefined' && Notification.permission === 'granted' && status.push_ready) {
                hint.textContent = 'Push is active on this device.';
                hint.hidden = false;
                return;
            }
            if (typeof Notification !== 'undefined' && Notification.permission === 'granted' && status.subscription_count === 0) {
                hint.textContent = 'Notifications are allowed, but this device is not registered yet. Tap Enable push on this device.';
                hint.hidden = false;
                return;
            }
            if (!status.configured) {
                hint.textContent = 'Server push is not configured yet.';
                hint.hidden = false;
            }
        });
    }

    document.getElementById('studentEnablePushBtn')?.addEventListener('click', function () {
        if (window.eventifyPwa && typeof window.eventifyPwa.enablePush === 'function') {
            window.eventifyPwa.enablePush().then(function () {
                updatePushStatusHint();
            });
        }
    });

    document.getElementById('studentTestPushBtn')?.addEventListener('click', function () {
        var btn = this;
        if (!window.eventifyPwa || typeof window.eventifyPwa.sendTestPush !== 'function') {
            return;
        }
        btn.disabled = true;
        window.eventifyPwa.syncPushSubscription(true).then(function () {
            return window.eventifyPwa.sendTestPush();
        }).then(function (result) {
            if (result && result.ok) {
                showPushMessage('Test notification sent. Check your phone notification tray.', {
                    title: 'Notifications',
                    type: 'success',
                    icon: 'fa-circle-check'
                });
                updatePushStatusHint();
                return;
            }
            var detail = (result && Array.isArray(result.errors) && result.errors.length)
                ? result.errors.join('; ')
                : ((result && result.error) || 'unknown error');
            showPushMessage('Test failed: ' + detail, {
                title: 'Notifications',
                type: 'error',
                icon: 'fa-circle-exclamation'
            });
        }).finally(function () {
            btn.disabled = false;
        });
    });

    updatePushStatusHint();
})();
</script>

</body>
</html>
