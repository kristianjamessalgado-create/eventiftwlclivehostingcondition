<?php
if (!defined('EVENTIFY_ADMIN_DASHBOARD_LOADED')) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/views/login.php');
    exit;
}
$admin_name    = $admin_name    ?? 'Admin';
$admin_email   = $admin_email   ?? '';
$admin_user    = is_array($admin_user ?? null) ? $admin_user : [
    'name' => $admin_name,
    'email' => $admin_email,
    'profile_picture' => null,
];
$events        = $events        ?? [];
$pendingCount  = $pendingCount  ?? 0;
$pendingEvents = $pendingEvents ?? [];
$eventStats    = $eventStats    ?? ['total' => 0, 'pending' => 0, 'active' => 0, 'rejected' => 0, 'closed' => 0];
$auditLogs     = $auditLogs     ?? [];
$chartDeptLabels = $chartDeptLabels ?? [];
$chartDeptCounts = $chartDeptCounts ?? [];
$chartStatusLabels = $chartStatusLabels ?? ['Pending', 'Active', 'Rejected', 'Closed'];
$chartStatusCounts = $chartStatusCounts ?? [0, 0, 0, 0];
$upcomingAdminEvents = $upcomingAdminEvents ?? [];
$upcomingAdminCount  = isset($upcomingAdminCount) ? (int) $upcomingAdminCount : count($upcomingAdminEvents);
$admin_notifications = $admin_notifications ?? [];
$admin_unread_count = isset($admin_unread_count) ? (int) $admin_unread_count : 0;
$feedbackStats = $feedbackStats ?? ['total_feedback' => 0, 'avg_rating' => 0, 'rating_labels' => ['1â˜…','2â˜…','3â˜…','4â˜…','5â˜…'], 'rating_counts' => [0,0,0,0,0]];
$admin_feedback_list = $admin_feedback_list ?? [];
$admin_evaluation_averages = $admin_evaluation_averages ?? [];
$success       = $_GET['success'] ?? '';
$error         = $_GET['error'] ?? '';
$openModal     = strtolower((string)($_GET['open_modal'] ?? ''));
$admin_panel = (string) ($_GET['panel'] ?? '');
if ($admin_panel === '' && $openModal === 'events') {
    $admin_panel = 'events';
}
if ($admin_panel === '' && $openModal === 'accounts') {
    $admin_panel = 'users';
}
if ($admin_panel === '' && $openModal === 'pending') {
    $admin_panel = 'pending';
}
if ($admin_panel === '' && in_array($openModal, ['charts', 'analytics'], true)) {
    $admin_panel = 'analytics';
}
$admin_events_panel_open = !empty($admin_events_panel_open) || ($admin_panel === 'events');
$admin_users_panel_open = !empty($admin_users_panel_open) || ($admin_panel === 'users');
$admin_messages_panel_open = !empty($admin_messages_panel_open) || ($admin_panel === 'messages');
$admin_pending_panel_open = !empty($admin_pending_panel_open) || ($admin_panel === 'pending');
$admin_feedback_panel_open = !empty($admin_feedback_panel_open) || ($admin_panel === 'feedback');
$admin_audit_panel_open = !empty($admin_audit_panel_open) || ($admin_panel === 'audit');
$admin_revenue_panel_open = !empty($admin_revenue_panel_open) || ($admin_panel === 'revenue');
$admin_analytics_panel_open = !empty($admin_analytics_panel_open) || ($admin_panel === 'analytics');
$admin_upcoming_panel_open = !empty($admin_upcoming_panel_open) || ($admin_panel === 'upcoming');
$admin_dashboard_panel_open = !empty($admin_dashboard_panel_open)
    || $admin_events_panel_open
    || $admin_users_panel_open
    || $admin_messages_panel_open
    || $admin_pending_panel_open
    || $admin_feedback_panel_open
    || $admin_audit_panel_open
    || $admin_revenue_panel_open
    || $admin_analytics_panel_open
    || $admin_upcoming_panel_open;
$admin_events_count = isset($admin_events_count) ? (int) $admin_events_count : count($events);
$admin_users_count = isset($admin_users_count) ? (int) $admin_users_count : count($allUsers);
$staff_messaging_unread = isset($staff_messaging_unread) ? (int) $staff_messaging_unread : 0;
$peersList = is_array($peersList ?? null) ? $peersList : [];
$initialWith = isset($initialWith) ? (int) $initialWith : 0;
$messaging_error = $messaging_error ?? null;
$dashboardHref = $dashboardHref ?? BASE_URL . '/backend/admin/dashboard.php';
$otpTableReady = $otpTableReady ?? false;
$usersHasOtpContactColumns = $usersHasOtpContactColumns ?? false;
$assignableOrganizers = $assignableOrganizers ?? [];
$stalePendingEvents = $stalePendingEvents ?? [];
$stalePendingCount = isset($stalePendingCount) ? (int) $stalePendingCount : count($stalePendingEvents);
$allUsers = $allUsers ?? [];
$pendingAccountCount = isset($pendingAccountCount) ? (int) $pendingAccountCount : 0;
$revenueOverview = $revenueOverview ?? [
    'total_revenue' => 0.0, 'tickets_sold' => 0, 'orders_paid' => 0,
    'pending_amount' => 0.0, 'pending_orders' => 0, 'demo_revenue' => 0.0,
    'demo_orders' => 0, 'by_method' => [], 'by_event' => [],
];
$rsvpCountByEvent = $rsvpCountByEvent ?? [];
$checkinCountByEvent = $checkinCountByEvent ?? [];
$adminDepartmentChoices = function_exists('eventify_allowed_departments') ? eventify_allowed_departments() : ['ALL'];
$showPendingReminder = ((int) ($adminSettings['notify_pending_reminder'] ?? 1) === 1) && $stalePendingCount > 0;
$adminSettings = $adminSettings ?? [
    'notify_email_new_event' => 1,
    'notify_pending_reminder' => 1,
    'notification_retention_days' => 30,
    'otp_required_sensitive_actions' => 1,
    'otp_expiry_minutes' => 10,
    'otp_max_attempts' => 5,
    'event_lead_days' => 3,
    'auto_complete_past_events' => 1,
    'max_event_photos' => 10,
    'max_upload_size_mb' => 10,
    'session_timeout_minutes' => 30,
    'force_relogin_sensitive_actions' => 1,
    'default_dashboard_view' => 'calendar',
    'calendar_legend_visible' => 1,
    'table_page_size' => 10,
];
$peerLabel = 'Organizers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Admin Dashboard - EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/eventify_modal.css?v=2">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/dashboard_student.css?v=8">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/calendar_legend.css?v=7">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/dashboard_calendar_shell.css?v=9">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/notifications.css?v=5">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/calendar_scroll_fix.css?v=9">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/dashboard_admin.css?v=26">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/staff_messenger.css?v=8">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/organizer_profile_modal.css?v=6">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/eventify_dashboard_brand.css?v=2">
</head>
<body class="admin-dashboard" data-eventify-keyboard-scroll data-eventify-sidebar="adminSidebar" data-eventify-calendar="calendar" data-eventify-main=".admin-dashboard .main-content">

<nav class="adm-navbar">
    <div class="d-flex align-items-center">
        <button type="button" class="nav-btn sidebar-toggle-mobile me-2" id="adminSidebarToggle" aria-label="Toggle sidebar" title="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <a href="<?= BASE_URL ?>/backend/admin/dashboard.php" class="adm-brand" data-admin-panel="home">
            <i class="fas fa-calendar-alt"></i>
            <span>EVENTIFY</span>
        </a>
    </div>
    <div class="d-flex align-items-center">
        <button
            type="button"
            class="nav-btn position-relative me-2<?= $admin_messages_panel_open ? ' is-active' : '' ?>"
            title="Messages (Organizers)"
            data-admin-panel="messages"
        >
            <i class="fas fa-comments"></i>
            <?php if ($staff_messaging_unread > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.55rem;">
                    <?= $staff_messaging_unread > 99 ? '99+' : $staff_messaging_unread ?>
                </span>
            <?php endif; ?>
        </button>
        <button class="nav-btn position-relative me-2" type="button" title="Notifications" data-bs-toggle="modal" data-bs-target="#adminNotificationsModal" data-eventify-notif-badge>
            <i class="fas fa-bell"></i>
            <?php if ($admin_unread_count > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.55rem;">
                    <?= $admin_unread_count > 99 ? '99+' : $admin_unread_count ?>
                </span>
            <?php endif; ?>
        </button>
        <button type="button" class="nav-btn adm-logout-mobile me-2" title="Logout" aria-label="Logout" data-bs-toggle="modal" data-bs-target="#logoutModal">
            <i class="fas fa-sign-out-alt"></i>
        </button>
        <div class="dropdown">
            <button class="adm-user-menu dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="<?= htmlspecialchars($admin_name) ?>">
                <?php if (!empty($admin_user['profile_picture'])): ?>
                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($admin_user['profile_picture']) ?>" alt="<?= htmlspecialchars($admin_name) ?>" class="adm-user-menu__avatar">
                <?php else: ?>
                    <i class="fas fa-user-shield adm-user-menu__icon"></i>
                <?php endif; ?>
                <span class="adm-user-menu__label"><?= htmlspecialchars($admin_name) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end adm-dropdown-menu">
                <li>
                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#adminProfileModal">
                        <i class="fas fa-user me-2"></i>Profile
                    </button>
                </li>
                <li>
                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#adminSettingsModal">
                        <i class="fas fa-cog me-2"></i>Settings
                    </button>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="dashboard-layout">
    <div class="sidebar-backdrop" id="adminSidebarBackdrop" aria-hidden="true"></div>

    <!-- Left Sidebar -->
    <aside class="sidebar eventify-kb-scroll-zone" id="adminSidebar" tabindex="0" aria-label="Admin sidebar â€” use arrow keys to scroll">
        <button type="button" class="sidebar-close-mobile" id="adminSidebarClose" aria-label="Close menu"><i class="fas fa-times"></i></button>

        <!-- Admin Profile Card -->
        <div class="organizer-user-card">
            <div class="organizer-user-avatar">
                <?php if (!empty($admin_user['profile_picture'])): ?>
                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($admin_user['profile_picture']) ?>" alt="<?= htmlspecialchars($admin_name) ?>" class="organizer-user-avatar-img">
                <?php else: ?>
                    <?= strtoupper(substr($admin_name, 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div class="organizer-user-name"><?= htmlspecialchars($admin_name) ?></div>
            <div class="organizer-user-role">Admin</div>
        </div>

        <!-- Mini Calendar -->
        <div class="mini-calendar-widget">
            <div class="mini-calendar-header">
                <button class="mini-cal-nav" id="miniCalPrev" type="button" aria-label="Previous month"><i class="fas fa-chevron-left"></i></button>
                <span class="mini-cal-month" id="miniCalMonth"><?= date('F Y') ?></span>
                <button class="mini-cal-nav" id="miniCalNext" type="button" aria-label="Next month"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="mini-calendar-grid" id="miniCalendar"></div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3 class="section-title">QUICK ACTIONS</h3>
            <button
                type="button"
                class="action-btn w-100 text-start border-0 bg-transparent<?= $admin_pending_panel_open ? ' is-active' : '' ?>"
                data-admin-panel="pending"
            >
                <i class="fas fa-inbox"></i>
                <span>Pending Events<?= $pendingCount > 0 ? ' (' . ($pendingCount > 99 ? '99+' : (int) $pendingCount) . ')' : '' ?></span>
            </button>
            <?php if ($showPendingReminder): ?>
            <div class="adm-pending-reminder adm-pending-reminder--sidebar" role="status">
                <i class="fas fa-clock" aria-hidden="true"></i>
                <span><?= (int) $stalePendingCount ?> pending &gt;24h</span>
            </div>
            <?php endif; ?>
            <button
                type="button"
                class="action-btn w-100 text-start border-0 bg-transparent<?= $admin_users_panel_open ? ' is-active' : '' ?>"
                data-admin-panel="users"
            >
                <i class="fas fa-users"></i>
                <span>All Users<?= $admin_users_count > 0 ? ' (' . ($admin_users_count > 99 ? '99+' : $admin_users_count) . ')' : '' ?><?= $pendingAccountCount > 0 ? ' Â· ' . ($pendingAccountCount > 99 ? '99+' : (int) $pendingAccountCount) . ' pending' : '' ?></span>
            </button>
            <button
                type="button"
                class="action-btn w-100 text-start border-0 bg-transparent<?= $admin_revenue_panel_open ? ' is-active' : '' ?>"
                data-admin-panel="revenue"
            >
                <i class="fas fa-peso-sign"></i>
                <span>Revenue</span>
            </button>
            <button
                type="button"
                class="action-btn w-100 text-start border-0 bg-transparent<?= $admin_analytics_panel_open ? ' is-active' : '' ?>"
                data-admin-panel="analytics"
            >
                <i class="fas fa-chart-pie"></i>
                <span>Analytics</span>
            </button>
            <?php
                $activities_hub_btn_class = '';
                include __DIR__ . '/../views/partials/activities_hub_quick_action.php';
            ?>
            <button
                type="button"
                class="action-btn w-100 text-start border-0 bg-transparent<?= $admin_upcoming_panel_open ? ' is-active' : '' ?>"
                data-admin-panel="upcoming"
            >
                <i class="fas fa-calendar-check"></i>
                <span>Upcoming Events<?= $upcomingAdminCount > 0 ? ' (' . ($upcomingAdminCount > 99 ? '99+' : (int) $upcomingAdminCount) . ')' : '' ?></span>
            </button>
            <button
                type="button"
                class="action-btn w-100 text-start border-0 bg-transparent<?= $admin_events_panel_open ? ' is-active' : '' ?>"
                data-admin-panel="events"
            >
                <i class="fas fa-calendar-day"></i>
                <span>Events<?= $admin_events_count > 0 ? ' (' . ($admin_events_count > 99 ? '99+' : $admin_events_count) . ')' : '' ?></span>
            </button>
            <button
                type="button"
                class="action-btn w-100 text-start border-0 bg-transparent<?= $admin_messages_panel_open ? ' is-active' : '' ?>"
                data-admin-panel="messages"
            >
                <i class="fas fa-comments"></i>
                <span>Messages<?= $staff_messaging_unread > 0 ? ' (' . ($staff_messaging_unread > 99 ? '99+' : (int) $staff_messaging_unread) . ')' : '' ?></span>
            </button>
            <button
                type="button"
                class="action-btn w-100 text-start border-0 bg-transparent<?= $admin_feedback_panel_open ? ' is-active' : '' ?>"
                data-admin-panel="feedback"
            >
                <i class="fas fa-star-half-stroke"></i>
                <span>Student feedback<?= !empty($admin_feedback_list) ? ' (' . (count($admin_feedback_list) > 99 ? '99+' : count($admin_feedback_list)) . ')' : '' ?></span>
            </button>
            <button
                type="button"
                class="action-btn w-100 text-start border-0 bg-transparent<?= $admin_audit_panel_open ? ' is-active' : '' ?>"
                data-admin-panel="audit"
            >
                <i class="fas fa-clipboard-list"></i>
                <span>Audit log</span>
            </button>
            <a href="#" class="action-btn" data-bs-toggle="modal" data-bs-target="#logoutModal">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content eventify-kb-scroll-zone" tabindex="0" aria-label="Admin dashboard â€” use arrow keys to scroll">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($showPendingReminder): ?>
            <div class="adm-pending-reminder adm-pending-reminder--banner mx-3 mt-3" role="status">
                <div class="adm-pending-reminder__content">
                    <i class="fas fa-hourglass-half" aria-hidden="true"></i>
                    <div>
                        <strong><?= (int) $stalePendingCount ?> event<?= $stalePendingCount === 1 ? '' : 's' ?> pending over 24 hours</strong>
                        <span class="adm-pending-reminder__sub d-block">Review, correct details if needed, then send OTP to the organizer.</span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-warning" data-admin-panel="pending">
                    Review pending
                </button>
            </div>
        <?php endif; ?>
        <div id="adminDashboardHome" class="<?= $admin_dashboard_panel_open ? 'd-none' : '' ?>">
        <div class="calendar-controls eventify-dashboard-cal-controls">
            <div class="controls-left">
                <button class="control-nav" id="calPrev"><i class="fas fa-chevron-left"></i></button>
                <h2 class="calendar-title" id="calendarTitle">Calendar</h2>
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
        $legendId = 'adminCalendarLegend';
        $legendClass = 'eventify-calendar-legend eventify-dashboard-cal-legend';
        $showSelectionClearNote = true;
        include __DIR__ . '/../views/partials/calendar_event_state_legend.php';
        ?>
        <div class="calendar-container eventify-dashboard-cal-container">
            <div id="calendar"></div>
        </div>
        </div><!-- #adminDashboardHome -->

        <?php include __DIR__ . '/../views/partials/admin_all_events_panel.php'; ?>
        <?php include __DIR__ . '/../views/partials/admin_all_users_panel.php'; ?>
        <?php include __DIR__ . '/../views/partials/admin_messages_panel.php'; ?>
        <?php include __DIR__ . '/../views/partials/admin_pending_events_panel.php'; ?>
        <?php include __DIR__ . '/../views/partials/admin_student_feedback_panel.php'; ?>
        <?php include __DIR__ . '/../views/partials/admin_audit_log_panel.php'; ?>
        <?php include __DIR__ . '/../views/partials/admin_revenue_panel.php'; ?>
        <?php include __DIR__ . '/../views/partials/admin_analytics_panel.php'; ?>
        <?php include __DIR__ . '/../views/partials/admin_upcoming_events_panel.php'; ?>
    </main>
</div>

<!-- Admin profile modal -->
<div class="modal fade" id="adminProfileModal" tabindex="-1" aria-labelledby="adminProfileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable op-modal-dialog">
    <div class="modal-content op-modal">
      <div class="modal-header op-modal__header">
        <div class="op-modal__header-text">
          <span class="op-modal__eyebrow">Admin</span>
          <h5 class="modal-title op-modal__title" id="adminProfileModalLabel">
            <i class="fas fa-user-circle" aria-hidden="true"></i>
            Profile
          </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="adminProfileForm" action="<?= BASE_URL ?>/backend/admin/update_admin_profile.php" method="POST" enctype="multipart/form-data" onsubmit="event.preventDefault(); confirmAdminProfileChanges(this);"
        data-initial-name="<?= htmlspecialchars($admin_user['name'] ?? $admin_name, ENT_QUOTES, 'UTF-8') ?>">
        <?= csrf_field() ?>
        <div class="modal-body op-modal__body">
          <section class="op-form-section op-form-section--photo">
            <div class="organizer-profile-picture-container">
              <?php if (!empty($admin_user['profile_picture'])): ?>
                <img src="<?= BASE_URL ?>/<?= htmlspecialchars($admin_user['profile_picture']) ?>" alt="Profile" id="adminProfilePicturePreview" class="organizer-profile-picture-preview" title="Click to view full screen">
              <?php else: ?>
                <div class="organizer-profile-picture-placeholder" id="adminProfilePicturePreview">
                  <i class="fas fa-user" aria-hidden="true"></i>
                </div>
              <?php endif; ?>
            </div>
            <div class="op-file-field">
              <label class="form-label op-form-label" for="adminProfilePictureInput">Profile picture</label>
              <input type="file" class="form-control op-form-control" id="adminProfilePictureInput" name="profile_picture" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewAdminProfilePicture(this)">
              <small class="op-form-help">JPG, PNG, GIF, or WEBP (max 5MB)</small>
            </div>
          </section>

          <section class="op-form-section">
            <p class="op-form-section__label">Account</p>
            <div class="mb-3">
              <label class="form-label op-form-label" for="adminFullName">Full name</label>
              <input type="text" class="form-control op-form-control" id="adminFullName" name="name" value="<?= htmlspecialchars($admin_user['name'] ?? $admin_name) ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label op-form-label">Role</label>
              <input type="text" class="form-control op-form-control op-form-control--readonly" value="Admin" readonly>
            </div>
            <div class="mb-0">
              <label class="form-label op-form-label" for="adminAccountEmail">Account email</label>
              <input type="email" class="form-control op-form-control op-form-control--readonly" id="adminAccountEmail" value="<?= htmlspecialchars($admin_user['email'] ?? $admin_email) ?>" readonly>
              <small class="op-form-help">This is your login email.</small>
            </div>
          </section>

          <section class="op-form-section">
            <p class="op-form-section__label">Security</p>
            <a href="<?= BASE_URL ?>/views/change_password.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-key me-1"></i>Change password</a>
          </section>
        </div>
        <div class="modal-footer op-modal__footer">
          <button type="submit" class="btn op-btn-primary"><i class="fas fa-save me-1"></i>Save changes</button>
          <button type="button" class="btn op-btn-muted" data-bs-dismiss="modal">Close</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Confirm Admin Profile Save Modal -->
<div class="modal fade" id="confirmAdminProfileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content op-modal op-modal--compact">
      <div class="modal-header op-modal__header">
        <div class="op-modal__header-text">
          <h5 class="modal-title op-modal__title op-modal__title--sm">
            <i class="fas fa-user-check" aria-hidden="true"></i>
            Save profile changes?
          </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body op-modal__body">
        <div id="confirmAdminProfileMessage" class="op-confirm-message mb-0"></div>
      </div>
      <div class="modal-footer op-modal__footer">
        <button type="button" class="btn op-btn-muted" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn op-btn-primary" id="confirmAdminProfileBtn">Save</button>
      </div>
    </div>
  </div>
</div>

<!-- Admin settings modal -->
<div class="modal fade" id="adminSettingsModal" tabindex="-1" aria-labelledby="adminSettingsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content efy-modal">
      <form method="POST" action="<?= BASE_URL ?>/backend/admin/update_settings.php" id="adminSettingsForm">
        <?= csrf_field() ?>
        <input type="hidden" name="open_modal" value="settings">
        <div class="modal-header efy-modal__header">
          <div>
            <span class="efy-modal__eyebrow">Admin</span>
            <h5 class="modal-title efy-modal__title" id="adminSettingsModalLabel"><i class="fas fa-cog" aria-hidden="true"></i> Settings</h5>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body efy-modal__body">
          <div class="settings-section">
            <h6>Security</h6>
            <div class="row g-2">
              <div class="col-md-6">
                <label for="session_timeout_minutes" class="form-label small">Session Timeout (minutes)</label>
                <input type="number" min="5" max="240" class="form-control" id="session_timeout_minutes" name="session_timeout_minutes" value="<?= (int)($adminSettings['session_timeout_minutes'] ?? 30) ?>">
              </div>
              <div class="col-md-6 d-flex align-items-end">
                <div class="form-check form-switch mt-2">
                  <input class="form-check-input" type="checkbox" role="switch" id="force_relogin_sensitive_actions" name="force_relogin_sensitive_actions" value="1" <?= !empty($adminSettings['force_relogin_sensitive_actions']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="force_relogin_sensitive_actions">Require re-auth for sensitive actions</label>
                </div>
              </div>
            </div>
          </div>

          <div class="settings-section">
            <h6>Notifications</h6>
            <div class="row g-2">
              <div class="col-md-6 d-flex align-items-center">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" role="switch" id="notify_email_new_event" name="notify_email_new_event" value="1" <?= !empty($adminSettings['notify_email_new_event']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="notify_email_new_event">Email alerts for new submissions</label>
                </div>
              </div>
              <div class="col-md-6 d-flex align-items-center">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" role="switch" id="notify_pending_reminder" name="notify_pending_reminder" value="1" <?= !empty($adminSettings['notify_pending_reminder']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="notify_pending_reminder">Pending approval reminders</label>
                </div>
              </div>
              <div class="col-md-6">
                <label for="notification_retention_days" class="form-label small">Notification Retention (days)</label>
                <input type="number" min="1" max="365" class="form-control" id="notification_retention_days" name="notification_retention_days" value="<?= (int)($adminSettings['notification_retention_days'] ?? 30) ?>">
                <div class="form-text">In-app bell alerts older than this are removed automatically (default 30 days).</div>
              </div>
            </div>
          </div>

          <div class="settings-section">
            <h6>Event Approval & Rules</h6>
            <div class="row g-2">
              <div class="col-md-6 d-flex align-items-center">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" role="switch" id="otp_required_sensitive_actions" name="otp_required_sensitive_actions" value="1" <?= !empty($adminSettings['otp_required_sensitive_actions']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="otp_required_sensitive_actions">Require OTP for approve/reject</label>
                </div>
              </div>
              <div class="col-md-6 d-flex align-items-center">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" role="switch" id="auto_complete_past_events" name="auto_complete_past_events" value="1" <?= !empty($adminSettings['auto_complete_past_events']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="auto_complete_past_events">Auto-complete past events</label>
                </div>
              </div>
              <div class="col-md-4">
                <label for="otp_expiry_minutes" class="form-label small">OTP Expiry (minutes)</label>
                <input type="number" min="3" max="30" class="form-control" id="otp_expiry_minutes" name="otp_expiry_minutes" value="<?= (int)($adminSettings['otp_expiry_minutes'] ?? 10) ?>">
              </div>
              <div class="col-md-4">
                <label for="otp_max_attempts" class="form-label small">OTP Max Attempts</label>
                <input type="number" min="3" max="10" class="form-control" id="otp_max_attempts" name="otp_max_attempts" value="<?= (int)($adminSettings['otp_max_attempts'] ?? 5) ?>">
              </div>
              <div class="col-md-4">
                <label for="event_lead_days" class="form-label small">Event Lead Time (days)</label>
                <input type="number" min="0" max="30" class="form-control" id="event_lead_days" name="event_lead_days" value="<?= (int)($adminSettings['event_lead_days'] ?? 3) ?>">
              </div>
            </div>
          </div>

          <div class="settings-section">
            <h6>Uploads & Display</h6>
            <div class="row g-2">
              <div class="col-md-4">
                <label for="max_event_photos" class="form-label small">Max Photos per Event</label>
                <input type="number" min="1" max="30" class="form-control" id="max_event_photos" name="max_event_photos" value="<?= (int)($adminSettings['max_event_photos'] ?? 10) ?>">
              </div>
              <div class="col-md-4">
                <label for="max_upload_size_mb" class="form-label small">Max Upload Size (MB)</label>
                <input type="number" min="1" max="50" class="form-control" id="max_upload_size_mb" name="max_upload_size_mb" value="<?= (int)($adminSettings['max_upload_size_mb'] ?? 10) ?>">
              </div>
              <div class="col-md-4">
                <label for="table_page_size" class="form-label small">Default Table Page Size</label>
                <input type="number" min="5" max="100" step="5" class="form-control" id="table_page_size" name="table_page_size" value="<?= (int)($adminSettings['table_page_size'] ?? 10) ?>">
              </div>
              <div class="col-md-6">
                <label for="default_dashboard_view" class="form-label small">Default Dashboard View</label>
                <select class="form-select" id="default_dashboard_view" name="default_dashboard_view">
                  <?php $defaultView = (string)($adminSettings['default_dashboard_view'] ?? 'calendar'); ?>
                  <option value="calendar" <?= $defaultView === 'calendar' ? 'selected' : '' ?>>Calendar</option>
                  <option value="charts" <?= $defaultView === 'charts' ? 'selected' : '' ?>>Charts</option>
                  <option value="pending" <?= $defaultView === 'pending' ? 'selected' : '' ?>>Pending Approvals</option>
                </select>
              </div>
              <div class="col-md-6 d-flex align-items-end">
                <div class="form-check form-switch mt-2">
                  <input class="form-check-input" type="checkbox" role="switch" id="calendar_legend_visible" name="calendar_legend_visible" value="1" <?= !empty($adminSettings['calendar_legend_visible']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="calendar_legend_visible">Show calendar color legend</label>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer efy-modal__footer">
          <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn efy-btn-primary" id="adminSettingsUpdateBtn"><i class="fas fa-save me-1"></i>Update settings</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Confirm settings update modal -->
<div class="modal fade" id="confirmAdminSettingsUpdateModal" tabindex="-1" aria-labelledby="confirmAdminSettingsUpdateLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content efy-modal efy-modal--compact">
      <div class="modal-header efy-modal__header">
        <div>
          <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="confirmAdminSettingsUpdateLabel">
            <i class="fas fa-save" aria-hidden="true"></i>
            Confirm update
          </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body efy-modal__body efy-modal__body--compact">
        <p class="efy-confirm-message mb-0">Are you sure you want to update admin settings?</p>
      </div>
      <div class="modal-footer efy-modal__footer">
        <button type="button" class="btn efy-btn-primary" id="confirmAdminSettingsUpdateYes">Yes, update</button>
        <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">No</button>
      </div>
    </div>
  </div>
</div>

<!-- Admin Notifications Modal -->
<div class="modal fade eventify-notif-modal" id="adminNotificationsModal" tabindex="-1" aria-labelledby="adminNotificationsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header eventify-notif-modal__header">
        <div>
          <h5 class="modal-title" id="adminNotificationsModalLabel"><i class="fas fa-bell me-2"></i>Notifications</h5>
          <p class="eventify-notif-modal__subtitle mb-0">
            <?php if ($admin_unread_count > 0): ?>
              <?= (int) $admin_unread_count ?> unread Â· tap to mark read
            <?php else: ?>
              You're all caught up
            <?php endif; ?>
          </p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body eventify-notif-modal__body">
        <div class="eventify-notif-scroll">
          <?php
            $notifications = $admin_notifications;
            $empty_title = 'No notifications yet';
            $empty_text = 'Event reviews and system alerts will appear here.';
            $notif_interactive = true;
            include __DIR__ . '/../views/partials/notification_cards.php';
          ?>
        </div>
      </div>
      <?php
        $notif_show_mark_all = ($admin_unread_count ?? 0) > 0;
        $notif_show_clear = !empty($admin_notifications);
        $notif_show_done = true;
        $notif_clear_modal_id = 'adminClearNotifsModal';
        $notif_context = 'modal';
        include __DIR__ . '/../views/partials/notification_footer_actions.php';
      ?>
    </div>
  </div>
</div>

<?php
    $notif_clear_modal_id = 'adminClearNotifsModal';
    include __DIR__ . '/../views/partials/notification_clear_confirm_modal.php';
    include __DIR__ . '/../views/partials/notification_detail_modal.php';
?>

<!-- Logout Confirmation Modal -->
<?php include __DIR__ . '/../views/partials/activities_hub_pick_modal.php'; ?>
<?php include __DIR__ . '/../views/partials/logout_confirm_modal.php'; ?>

<!-- OTP request confirmation modal -->
<div class="modal fade" id="otpRequestConfirmModal" tabindex="-1" aria-labelledby="otpRequestConfirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content efy-modal efy-modal--compact">
      <div class="modal-header efy-modal__header">
        <div>
          <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="otpRequestConfirmModalLabel">
            <i class="fas fa-shield-alt" aria-hidden="true"></i>
            Confirm OTP request
          </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body efy-modal__body efy-modal__body--compact">
        <p id="otpRequestConfirmText" class="efy-confirm-message mb-0">Are you sure you want to request OTP?</p>
      </div>
      <div class="modal-footer efy-modal__footer">
        <button type="button" class="btn efy-btn-primary" id="otpRequestConfirmBtn"><i class="fas fa-paper-plane me-1"></i>Yes, request OTP</button>
        <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- Reject event modal (with optional reason) -->
<div class="modal fade" id="rejectEventModal" tabindex="-1" aria-labelledby="rejectEventModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content efy-modal efy-modal--compact">
      <form method="POST" action="<?= BASE_URL ?>/backend/super_admin/update_event_status.php" id="rejectEventForm">
        <?= csrf_field() ?>
        <input type="hidden" name="event_id" id="rejectEventId" value="">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="return_to" id="rejectReturnTo" value="dashboard">
        <input type="hidden" name="return_panel" id="rejectReturnPanel" value="pending">
        <input type="hidden" name="open_modal" id="rejectOpenModal" value="pending">
        <div class="modal-header efy-modal__header">
          <div>
            <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="rejectEventModalLabel">
              <i class="fas fa-times-circle" aria-hidden="true"></i>
              Reject event
            </h5>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body efy-modal__body efy-modal__body--compact">
          <p class="efy-confirm-message mb-2" id="rejectEventTitleText">Optionally give a reason so the organizer knows what to fix.</p>
          <label for="rejectReasonInput" class="efy-form-label">Reason (optional)</label>
          <textarea class="form-control efy-form-control" id="rejectReasonInput" name="reject_reason" rows="3" placeholder="e.g. Please add a clearer description or change the date."></textarea>
        </div>
        <div class="modal-footer efy-modal__footer">
          <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn efy-btn-danger"><i class="fas fa-times me-1"></i>Reject event</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Admin: correct a student's course / program (department is derived automatically) -->
<div class="modal fade" id="adminEditStudentCourseModal" tabindex="-1" aria-labelledby="adminEditStudentCourseModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content efy-modal">
      <form method="POST" action="<?= BASE_URL ?>/backend/admin/update_student_course.php">
        <?= csrf_field() ?>
        <input type="hidden" name="return_panel" value="users">
        <input type="hidden" name="user_id" id="admEditCourseUserId" value="">
        <div class="modal-header efy-modal__header">
          <div>
            <span class="efy-modal__eyebrow">Admin correction</span>
            <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="adminEditStudentCourseModalLabel"><i class="fas fa-graduation-cap" aria-hidden="true"></i> Edit course / program</h5>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body efy-modal__body">
          <p class="text-muted small mb-3">Updating the course / program for <strong id="admEditCourseUserName">this student</strong>. The department is set automatically from the chosen program.</p>
          <div class="mb-1">
            <label class="form-label small" for="admEditCourseSelect">Course / program</label>
            <select name="student_course" id="admEditCourseSelect" class="form-select form-select-sm" required>
              <?php foreach (eventify_student_course_program_options() as $cv => $clab): ?>
                <option value="<?= htmlspecialchars((string) $cv) ?>"><?= htmlspecialchars($clab) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer efy-modal__footer">
          <button type="button" class="btn efy-btn-muted btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn efy-btn-primary btn-sm"><i class="fas fa-save me-1"></i> Save course</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Admin edit pending event -->
<div class="modal fade" id="adminEditPendingEventModal" tabindex="-1" aria-labelledby="adminEditPendingEventModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content efy-modal">
      <form method="POST" action="<?= BASE_URL ?>/backend/admin/edit_pending_event.php" id="adminEditPendingEventForm">
        <?= csrf_field() ?>
        <input type="hidden" name="event_id" id="adminEditPendingEventId" value="">
        <input type="hidden" name="return_panel" value="pending">
        <div class="modal-header efy-modal__header">
          <div>
            <span class="efy-modal__eyebrow">Admin correction</span>
            <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="adminEditPendingEventModalLabel"><i class="fas fa-pen" aria-hidden="true"></i> Edit pending event</h5>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body efy-modal__body efy-modal__body--compact">
          <p class="text-muted small mb-3">Fix typos or dates before sending OTP. The organizer is notified of changes.</p>
          <div class="mb-3">
            <label for="adminEditPendingTitle" class="efy-form-label">Title</label>
            <input type="text" class="form-control efy-form-control" id="adminEditPendingTitle" name="title" maxlength="150" required>
          </div>
          <div class="mb-3">
            <label for="adminEditPendingDate" class="efy-form-label">Date</label>
            <input type="date" class="form-control efy-form-control" id="adminEditPendingDate" name="date" required>
          </div>
          <div class="mb-3">
            <label for="adminEditPendingLocation" class="efy-form-label">Location</label>
            <input type="text" class="form-control efy-form-control" id="adminEditPendingLocation" name="location" maxlength="100" required>
          </div>
          <div class="mb-3">
            <label class="efy-form-label d-block">Target department(s)</label>
            <div class="adm-edit-dept-grid" id="adminEditPendingDeptGrid">
              <?php foreach ($adminDepartmentChoices as $deptOpt): ?>
                <?php if ($deptOpt === 'ALL') continue; ?>
                <label class="adm-edit-dept-option">
                  <input type="checkbox" name="department[]" value="<?= htmlspecialchars($deptOpt, ENT_QUOTES, 'UTF-8') ?>" class="admin-edit-dept-cb">
                  <span><?= htmlspecialchars(function_exists('eventify_format_department_label') ? eventify_format_department_label($deptOpt) : $deptOpt) ?></span>
                </label>
              <?php endforeach; ?>
              <label class="adm-edit-dept-option adm-edit-dept-option--all">
                <input type="checkbox" name="department[]" value="ALL" class="admin-edit-dept-cb admin-edit-dept-cb--all">
                <span>All departments</span>
              </label>
            </div>
          </div>
          <div class="mb-0">
            <label for="adminEditPendingDescription" class="efy-form-label">Description</label>
            <textarea class="form-control efy-form-control" id="adminEditPendingDescription" name="description" rows="4"></textarea>
          </div>
        </div>
        <div class="modal-footer efy-modal__footer">
          <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn efy-btn-primary"><i class="fas fa-save me-1"></i>Save changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Event Details Modal -->
<div class="modal fade" id="eventDetailsModal" tabindex="-1" aria-labelledby="eventDetailsLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="eventDetailsLabel">Event Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <h5 id="eventTitle" class="mb-2"></h5>
        <div class="efy-detail-row mb-2">
          <strong class="d-block mb-1">Schedule</strong>
          <div id="eventDate"></div>
        </div>
        <p class="mb-1"><strong>Location:</strong> <span id="eventLocation"></span></p>
        <p class="mb-1"><strong>Status:</strong> <span id="eventStatus" class="badge bg-success"></span></p>
        <p class="mb-1"><strong>Entry:</strong> <span id="eventRegistrationModeBadge"></span></p>
        <p class="mb-1"><strong>Target Department:</strong> <span id="eventDepartment"></span></p>
        <p class="mb-1"><strong>Organizer:</strong> <span id="eventOrganizer"></span></p>
        <div id="eventAttendanceSummaryWrap" class="adm-event-attendance-summary mb-2" style="display:none;">
          <strong class="d-block mb-1">Attendance</strong>
          <div class="adm-event-attendance-summary__grid">
            <div class="adm-event-attendance-summary__item">
              <span class="adm-event-attendance-summary__value" id="eventRsvpCount">0</span>
              <span class="adm-event-attendance-summary__label">RSVPs</span>
            </div>
            <div class="adm-event-attendance-summary__item">
              <span class="adm-event-attendance-summary__value" id="eventCheckinCount">0</span>
              <span class="adm-event-attendance-summary__label">Checked in</span>
            </div>
          </div>
        </div>
        <p class="mt-3 mb-1"><strong>Description:</strong></p>
        <p id="eventDescription" class="mb-2 text-muted"></p>
        <p class="mb-0"><small><strong>Created at:</strong> <span id="eventCreatedAt"></span></small></p>
      </div>
      <div class="modal-footer">
        <a href="#" id="eventQrLink" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener" style="display:none;"><i class="fas fa-qrcode me-1"></i> QR</a>
        <a href="#" id="eventAttendanceLink" class="btn btn-outline-info btn-sm" target="_blank" rel="noopener" style="display:none;"><i class="fas fa-clipboard-check me-1"></i> Attendance</a>
        <button type="button" class="btn btn-primary btn-sm" id="admOpenPendingBtn" title="Open pending event approvals">
          <i class="fas fa-inbox me-1"></i> Pending Approvals
        </button>
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
window.__adminChartDept = <?= json_encode(['labels' => $chartDeptLabels, 'counts' => $chartDeptCounts]) ?>;
window.__adminChartStatus = <?= json_encode(['labels' => $chartStatusLabels, 'counts' => $chartStatusCounts]) ?>;
window.__adminChartFeedback = <?= json_encode(['labels' => $feedbackStats['rating_labels'] ?? ['1â˜…','2â˜…','3â˜…','4â˜…','5â˜…'], 'counts' => $feedbackStats['rating_counts'] ?? [0,0,0,0,0]]) ?>;
</script>
<script>
window.BASE_URL = <?= json_encode(BASE_URL) ?>;
window.currentRole = 'admin';
window.__adminFocusPendingEventId = <?= json_encode((int) ($admin_focus_pending_event_id ?? 0)) ?>;
window.__adminOpenModal = <?= json_encode($openModal) ?>;
window.__adminSettings = <?= json_encode($adminSettings ?? []) ?>;
window.__adminCsrfToken = <?= json_encode(function_exists('csrf_token') ? csrf_token() : '') ?>;
window.csrfToken = window.__adminCsrfToken;
window.__eventifyMessengerHref = <?= json_encode(BASE_URL . '/backend/admin/dashboard.php?panel=messages') ?>;
window.__staffMessengerSelfId = <?= (int) ($_SESSION['user_id'] ?? 0) ?>;
window.__staffMessengerPeers = <?= json_encode($peersList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.__staffMessengerInitialWith = <?= (int) $initialWith ?>;
window.__staffMessengerPeerLabel = <?= json_encode($peerLabel) ?>;
window.__staffMessengerError = <?= json_encode($messaging_error) ?>;
window.eventsData = <?= json_encode(eventify_events_to_fullcalendar_list($events, function ($e) use ($rsvpCountByEvent, $checkinCountByEvent) {
    $eid = (int) ($e['id'] ?? 0);
    return [
        'description'         => $e['description'] ?? '',
        'location'            => $e['location'] ?? '',
        'created_at'          => $e['created_at'] ?? '',
        'status'              => $e['status'] ?? 'pending',
        'reject_reason'       => $e['reject_reason'] ?? null,
        'start_time'          => $e['start_time'] ?? null,
        'end_time'            => $e['end_time'] ?? null,
        'end_time_na'         => !empty($e['end_time_na']),
        'department'          => $e['department'] ?? 'ALL',
        'department_display'  => function_exists('eventify_format_department_label')
            ? eventify_format_department_label((string) ($e['department'] ?? 'ALL'))
            : (string) ($e['department'] ?? 'ALL'),
        'organizer'           => $e['organizer_name'] ?? 'Organizer',
        'registration_mode'   => function_exists('eventify_event_registration_mode') ? eventify_event_registration_mode($e) : (string) ($e['registration_mode'] ?? 'rsvp'),
        'event_is_live'       => function_exists('eventify_event_is_live') ? eventify_event_is_live($e) : (($e['status'] ?? '') === 'active'),
        'rsvp_count'          => (int) ($rsvpCountByEvent[$eid] ?? 0),
        'checkin_count'       => (int) ($checkinCountByEvent[$eid] ?? 0),
    ];
}), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/logout_confirm.js"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_calendar_colors.js?v=10"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_schedule_display.js?v=1"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_registration_mode.js?v=1"></script>
<script src="<?= BASE_URL ?>/assets/js/dashboardorganizer.js?v=21"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_notifications.js?v=9"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_dashboard_keyboard_scroll.js"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_toast.js?v=1"></script>
<script src="<?= BASE_URL ?>/assets/js/staff_messenger.js?v=6"></script>
<script src="<?= BASE_URL ?>/assets/js/dashboard_admin.js?v=20"></script>
</body>
</html>
