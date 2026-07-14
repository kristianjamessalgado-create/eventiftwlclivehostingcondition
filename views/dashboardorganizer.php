<?php
$user = $user ?? ['name' => $user_name ?? 'Organizer', 'profile_picture' => null];
$msg = $msg ?? '';
$error = $error ?? '';
$organizer_flash_type = '';
$organizer_flash_message = '';
if ($msg !== '') {
    $msgLower = strtolower($msg);
    $organizer_flash_is_success = strpos($msgLower, 'success') !== false
        || strpos($msgLower, 'updated') !== false
        || strpos($msgLower, 'verified') !== false
        || strpos($msgLower, 'approved') !== false
        || strpos($msgLower, 'submitted') !== false;
    $organizer_flash_type = $organizer_flash_is_success ? 'success' : 'warning';
    $organizer_flash_message = $msg;
} elseif ($error !== '') {
    $organizer_flash_type = 'error';
    $organizer_flash_message = $error;
}
$organizer_settings = $organizer_settings ?? [];
$organizer_department_choices = $organizer_department_choices ?? [];
$staff_messaging_unread = isset($staff_messaging_unread) ? (int) $staff_messaging_unread : 0;
$messengerHref = BASE_URL . '/backend/messaging/staff_messenger.php';
$fb = $feedbackStats ?? ['total_feedback' => 0, 'avg_rating' => 0, 'five_star' => 0];
$organizer_feedback_list = $organizer_feedback_list ?? [];
$organizer_evaluation_averages = $organizer_evaluation_averages ?? [];
$organizer_events_panel_open = !empty($organizer_events_panel_open);
$organizer_feedback_panel_open = !empty($organizer_feedback_panel_open);
$organizer_dashboard_panel_open = !empty($organizer_dashboard_panel_open);
$organizer_events_count = (int) ($organizer_events_count ?? count($events ?? []));
$events = $events ?? [];
$eventsHasGeo = !empty($eventsHasGeo);
$eventsHasEndDate = !empty($eventsHasEndDate);
$daySessionsHaveGeo = !empty($daySessionsHaveGeo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizer Dashboard - EVENTIFY</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- FullCalendar CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Inter font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/eventify_modal.css?v=2">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/create_event_modal.css?v=2">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/organizer_profile_modal.css?v=6">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/calendar_legend.css?v=7">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/dashboard_calendar_shell.css?v=9">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/event_day_sessions.css?v=6">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/notifications.css?v=4">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/calendar_scroll_fix.css?v=9">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/dashboardorganizer.css?v=8">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/eventify_dashboard_brand.css?v=1">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
</head>
<body class="organizer-dashboard" data-eventify-keyboard-scroll data-eventify-sidebar="organizerSidebar" data-eventify-calendar="calendar" data-eventify-main=".organizer-dashboard .main-content">

<!-- Top Navigation Bar -->
<nav class="top-navbar">
    <div class="navbar-left">
        <button type="button" class="nav-btn sidebar-toggle-mobile" id="organizerSidebarToggle" aria-label="Toggle menu" title="Menu">
            <i class="fas fa-bars"></i>
        </button>
        <button type="button" class="brand-logo border-0 bg-transparent p-0" data-organizer-panel="home" title="Back to calendar">
            <i class="fas fa-calendar-alt"></i>
            <span>EVENTIFY</span>
        </button>
    </div>
    <div class="navbar-right">
        <button
            type="button"
            class="nav-btn create-btn"
            title="Create Event"
            data-bs-toggle="modal"
            data-bs-target="#createEventModal"
        >
            <i class="fas fa-plus"></i>
        </button>
        <button class="nav-btn" type="button" title="Calendar">
            <i class="fas fa-calendar"></i>
        </button>
        <a class="nav-btn position-relative" title="Messages (Admin)" href="<?= htmlspecialchars($messengerHref) ?>" target="_blank" rel="noopener noreferrer">
            <i class="fas fa-comments"></i>
            <?php if ($staff_messaging_unread > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.55rem;"><?= $staff_messaging_unread > 99 ? '99+' : $staff_messaging_unread ?></span>
            <?php endif; ?>
        </a>
        <?php $org_notifications = $organizer_notifications ?? []; ?>
        <div class="dropdown">
            <button class="nav-btn position-relative dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" data-eventify-notif-badge aria-expanded="false" title="Notifications">
                <i class="fas fa-bell"></i>
                <?php if (count($org_notifications) > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.55rem;"><?= count($org_notifications) ?></span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end eventify-notif-dropdown">
                <li class="eventify-notif-dropdown__header">
                    <i class="fas fa-bell me-2"></i>Notifications
                    <?php if (count($org_notifications) > 0): ?>
                        <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;"><?= count($org_notifications) ?> new</span>
                    <?php endif; ?>
                </li>
                <li class="eventify-notif-dropdown__scroll">
                    <div class="eventify-notif-scroll eventify-notif-scroll--dropdown">
                        <?php
                            $notifications = $org_notifications;
                            $empty_title = 'All caught up';
                            $empty_text = 'No new notifications right now.';
                            $notif_interactive = true;
                            include __DIR__ . '/partials/notification_cards.php';
                        ?>
                    </div>
                </li>
                <?php if (!empty($org_notifications)): ?>
                    <li class="eventify-notif-dropdown__footer">
                        <?php
                            $notif_show_mark_all = true;
                            $notif_show_clear = true;
                            $notif_clear_modal_id = 'organizerClearNotifsModal';
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
                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#organizerProfileModal">
                        <i class="fas fa-user me-2"></i> Profile
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#" data-organizer-panel="events">
                        <i class="fas fa-list me-2"></i> My Events
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

<!-- Main Layout -->
<div class="dashboard-layout">
    <div class="sidebar-backdrop" id="organizerSidebarBackdrop" aria-hidden="true"></div>
    <!-- Left Sidebar -->
    <aside class="sidebar eventify-kb-scroll-zone" id="organizerSidebar" tabindex="0" aria-label="Organizer sidebar — use arrow keys to scroll">
        <button type="button" class="sidebar-close-mobile" id="organizerSidebarClose" aria-label="Close menu"><i class="fas fa-times"></i></button>
        <!-- Organizer Profile Card -->
        <div class="organizer-user-card">
            <div class="organizer-user-avatar">
                <?php if (!empty($user['profile_picture'])): ?>
                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($user['profile_picture']) ?>" alt="<?= htmlspecialchars($user_name) ?>" class="organizer-user-avatar-img">
                <?php else: ?>
                    <?= strtoupper(substr((string)$user_name, 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div class="organizer-user-name"><?= htmlspecialchars($user_name) ?></div>
            <div class="organizer-user-role">Organizer</div>
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

        <!-- Calendars/Departments List -->
        <div class="calendars-section">
            <h3 class="calendars-title">DEPARTMENTS</h3>
            <div class="calendars-search">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search" id="calendarSearch">
            </div>
            <div class="calendars-list" id="calendarsList">
                <?php $orgDept = (string)($organizer_settings['default_department_filter'] ?? 'ALL'); ?>
                <div class="calendar-item<?= $orgDept === 'ALL' ? ' active' : '' ?>" data-dept="ALL">
                    <div class="calendar-avatar" style="background: #1b4a1b;">A</div>
                    <span class="calendar-name">All Departments</span>
                    <i class="fas fa-check"></i>
                </div>
                <div class="calendar-item<?= $orgDept === 'High school department' ? ' active' : '' ?>" data-dept="High school department">
                    <div class="calendar-avatar" style="background: #3d8a35;">H</div>
                    <span class="calendar-name">High School Department</span>
                </div>
                <div class="calendar-item<?= $orgDept === 'College of Communication, Information and Technology' ? ' active' : '' ?>" data-dept="College of Communication, Information and Technology">
                    <div class="calendar-avatar" style="background: #2f6626;">C</div>
                    <span class="calendar-name">College of Communication, Information and Technology</span>
                </div>
                <div class="calendar-item<?= $orgDept === 'College of Accountancy and Business' ? ' active' : '' ?>" data-dept="College of Accountancy and Business">
                    <div class="calendar-avatar" style="background: #e6c54a; color: #1b4a1b;">A</div>
                    <span class="calendar-name">College of Accountancy and Business</span>
                </div>
                <div class="calendar-item<?= $orgDept === 'School of Law and Political Science' ? ' active' : '' ?>" data-dept="School of Law and Political Science">
                    <div class="calendar-avatar" style="background: #153313;">L</div>
                    <span class="calendar-name">School of Law and Political Science</span>
                </div>
                <div class="calendar-item<?= $orgDept === 'College of Education' ? ' active' : '' ?>" data-dept="College of Education">
                    <div class="calendar-avatar" style="background: #3f6a2a;">E</div>
                    <span class="calendar-name">College of Education</span>
                </div>
                <div class="calendar-item<?= $orgDept === 'College of Nursing and Allied health sciences' ? ' active' : '' ?>" data-dept="College of Nursing and Allied health sciences">
                    <div class="calendar-avatar" style="background: #b7be77; color: #153313;">N</div>
                    <span class="calendar-name">College of Nursing and Allied health sciences</span>
                </div>
                <div class="calendar-item<?= $orgDept === 'College of Hospitality Management' ? ' active' : '' ?>" data-dept="College of Hospitality Management">
                    <div class="calendar-avatar" style="background: #b88f2a;">H</div>
                    <span class="calendar-name">College of Hospitality Management</span>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3 class="section-title">QUICK ACTIONS</h3>
            <button
                type="button"
                class="action-btn w-100 text-start border-0 bg-transparent<?= $organizer_events_panel_open ? ' is-active' : '' ?>"
                data-organizer-panel="events"
            >
                <i class="fas fa-list"></i>
                <span>My Events</span>
                <?php if ($organizer_events_count > 0): ?>
                    <span class="badge bg-success ms-1"><?= $organizer_events_count ?></span>
                <?php endif; ?>
            </button>
            <?php
                $activities_hub_btn_class = '';
                include __DIR__ . '/partials/activities_hub_quick_action.php';
            ?>
            <button
                type="button"
                class="action-btn w-100 text-start border-0 bg-transparent<?= $organizer_feedback_panel_open ? ' is-active' : '' ?>"
                data-organizer-panel="feedback"
            >
                <i class="fas fa-star-half-stroke"></i>
                <span>Feedback insights</span>
                <?php if ((int) ($fb['total_feedback'] ?? 0) > 0): ?>
                    <span class="badge bg-success ms-1"><?= (int) $fb['total_feedback'] ?></span>
                <?php endif; ?>
            </button>
            <a class="action-btn text-decoration-none text-reset" href="<?= htmlspecialchars($messengerHref) ?>" target="_blank" rel="noopener noreferrer">
                <i class="fas fa-comments"></i>
                <span>Messages<?= $staff_messaging_unread > 0 ? ' (' . $staff_messaging_unread . ')' : '' ?></span>
            </a>
            <a href="#" class="action-btn" data-bs-toggle="modal" data-bs-target="#logoutModal">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content eventify-kb-scroll-zone" tabindex="0" aria-label="Organizer calendar — use arrow keys to scroll">

        <div id="organizerDashboardHome" class="<?= $organizer_dashboard_panel_open ? 'd-none' : '' ?>">

        <!-- Calendar Controls (center calendar on dashboard again) -->
        <div class="calendar-controls eventify-dashboard-cal-controls">
            <div class="controls-left">
                <button class="control-nav" id="calPrev"><i class="fas fa-chevron-left"></i></button>
                <h2 class="calendar-title" id="calendarTitle">My Events Calendar</h2>
                <button class="control-nav" id="calNext"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="controls-right">
                <?php $orgCalView = (string)($organizer_settings['default_calendar_view'] ?? 'dayGridMonth'); ?>
                <button class="view-btn<?= $orgCalView === 'dayGridMonth' ? ' active' : '' ?>" data-view="dayGridMonth">Month</button>
                <button class="view-btn<?= $orgCalView === 'timeGridWeek' ? ' active' : '' ?>" data-view="timeGridWeek">Week</button>
                <button class="view-btn<?= $orgCalView === 'timeGridDay' ? ' active' : '' ?>" data-view="timeGridDay">Day</button>
                <button class="view-btn" data-view="today">Today</button>
            </div>
        </div>

        <?php
        $legendId = 'organizerCalendarLegend';
        $legendClass = 'eventify-calendar-legend eventify-dashboard-cal-legend';
        $showSelectionClearNote = true;
        include __DIR__ . '/partials/calendar_event_state_legend.php';
        ?>

        <!-- FullCalendar Container -->
        <div class="calendar-container eventify-dashboard-cal-container">
            <div id="calendar"></div>
        </div>

        <!-- (Stats and lists removed; calendar is main focus) -->
        </div><!-- #organizerDashboardHome -->

        <?php include __DIR__ . '/partials/organizer_my_events_panel.php'; ?>
        <?php include __DIR__ . '/partials/organizer_feedback_panel.php'; ?>
    </main>
</div>

<!-- Organizer Profile Modal -->
<div class="modal fade" id="organizerProfileModal" tabindex="-1" aria-labelledby="organizerProfileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable op-modal-dialog">
    <div class="modal-content op-modal">
      <div class="modal-header op-modal__header">
        <div class="op-modal__header-text">
          <span class="op-modal__eyebrow">Organizer</span>
          <h5 class="modal-title op-modal__title" id="organizerProfileModalLabel">
            <i class="fas fa-user-circle" aria-hidden="true"></i>
            Profile
          </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="organizerProfileForm" action="<?= BASE_URL ?>/backend/auth/update_organizer_profile.php" method="POST" enctype="multipart/form-data" onsubmit="event.preventDefault(); confirmOrganizerProfileChanges(this);"
        data-initial-name="<?= htmlspecialchars($user['name'] ?? $user_name, ENT_QUOTES, 'UTF-8') ?>"
        data-initial-contact-method="<?= htmlspecialchars($user['organizer_contact_method'] ?? 'email', ENT_QUOTES, 'UTF-8') ?>"
        data-initial-contact-email="<?= htmlspecialchars($user['organizer_contact_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
        data-initial-phone="<?= htmlspecialchars($user['organizer_phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <?= csrf_field() ?>
        <div class="modal-body op-modal__body">
          <section class="op-form-section op-form-section--photo">
            <div class="organizer-profile-picture-container">
              <?php if (!empty($user['profile_picture'])): ?>
                <img src="<?= BASE_URL ?>/<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile" id="organizerProfilePicturePreview" class="organizer-profile-picture-preview" title="Click to view full screen">
              <?php else: ?>
                <div class="organizer-profile-picture-placeholder" id="organizerProfilePicturePreview">
                  <i class="fas fa-user" aria-hidden="true"></i>
                </div>
              <?php endif; ?>
            </div>
            <div class="op-file-field">
              <label class="form-label op-form-label" for="organizerProfilePictureInput">Profile picture</label>
              <input type="file" class="form-control op-form-control" id="organizerProfilePictureInput" name="profile_picture" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewOrganizerProfilePicture(this)">
              <small class="op-form-help">JPG, PNG, GIF, or WEBP (max 5MB)</small>
            </div>
          </section>

          <section class="op-form-section">
            <p class="op-form-section__label">Account</p>
            <div class="mb-3">
              <label class="form-label op-form-label" for="organizerFullName">Full name</label>
              <input type="text" class="form-control op-form-control" id="organizerFullName" name="name" value="<?= htmlspecialchars($user['name'] ?? $user_name) ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label op-form-label">Role</label>
              <input type="text" class="form-control op-form-control op-form-control--readonly" value="Organizer" readonly>
            </div>
            <div class="mb-0">
              <label class="form-label op-form-label" for="organizerAccountEmail">Account email</label>
              <input type="email" class="form-control op-form-control op-form-control--readonly" id="organizerAccountEmail" value="<?= htmlspecialchars($user['email'] ?? '') ?>" readonly>
              <small class="op-form-help">This is your login email.</small>
            </div>
          </section>

          <section class="op-form-section">
            <p class="op-form-section__label">OTP verification</p>
            <p class="op-form-help mb-2">Where admin sends the code when your events are approved.</p>
            <div class="mb-3">
              <label class="form-label op-form-label" for="organizerContactMethod">Verification method</label>
              <select class="form-select op-form-control" id="organizerContactMethod" name="organizer_contact_method">
                <?php $selContactMethod = $user['organizer_contact_method'] ?? 'email'; ?>
                <option value="email" <?= $selContactMethod === 'email' ? 'selected' : '' ?>>Email</option>
                <option value="phone" <?= $selContactMethod === 'phone' ? 'selected' : '' ?>>Phone number</option>
              </select>
            </div>
            <div class="mb-3" id="organizerEmailFieldWrap">
              <label class="form-label op-form-label" for="organizerContactEmail">Verification email</label>
              <input type="email" class="form-control op-form-control" id="organizerContactEmail" name="organizer_contact_email" value="<?= htmlspecialchars($user['organizer_contact_email'] ?? '') ?>" placeholder="Enter email for OTP">
            </div>
            <div class="mb-0" id="organizerPhoneFieldWrap">
              <label class="form-label op-form-label" for="organizerPhone">Verification phone</label>
              <input type="text" class="form-control op-form-control" id="organizerPhone" name="organizer_phone" value="<?= htmlspecialchars($user['organizer_phone'] ?? '') ?>" maxlength="25" placeholder="e.g. 09XXXXXXXXX">
            </div>
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

<!-- Confirm Organizer Profile Save Modal -->
<div class="modal fade" id="confirmOrganizerProfileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content op-modal op-modal--compact">
      <div class="modal-header op-modal__header">
        <div class="op-modal__header-text">
          <h5 class="modal-title op-modal__title op-modal__title--sm">
            <i class="fas fa-save" aria-hidden="true"></i>
            Save profile changes?
          </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body op-modal__body op-modal__body--compact">
        <div id="confirmOrganizerProfileMessage" class="op-confirm-message mb-0"></div>
      </div>
      <div class="modal-footer op-modal__footer">
        <button type="button" class="btn op-btn-primary" id="confirmOrganizerProfileBtn">Save</button>
        <button type="button" class="btn op-btn-muted" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- Organizer Settings -->
<div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content efy-modal">
      <form id="organizerSettingsForm" method="POST" action="<?= BASE_URL ?>/backend/auth/update_organizer_settings.php">
        <?= csrf_field() ?>
        <div class="modal-header efy-modal__header">
          <div>
            <span class="efy-modal__eyebrow">Organizer</span>
            <h5 class="modal-title efy-modal__title" id="settingsModalLabel">
              <i class="fas fa-cog" aria-hidden="true"></i>
              Settings
            </h5>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body efy-modal__body">
          <div class="organizer-settings-section">
            <h6>Security</h6>
            <p class="small text-muted mb-2">Change your account password.</p>
            <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>/views/change_password.php?from=organizer&amp;next=<?= urlencode(BASE_URL . '/backend/auth/dashboardorganizer.php') ?>">
              <i class="fas fa-key me-1"></i>Change Password
            </a>
          </div>

          <div class="organizer-settings-section">
            <h6>Calendar</h6>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label small" for="org_default_calendar_view">Default view</label>
                <?php $ocv = (string)($organizer_settings['default_calendar_view'] ?? 'dayGridMonth'); ?>
                <select class="form-select" id="org_default_calendar_view" name="default_calendar_view">
                  <option value="dayGridMonth" <?= $ocv === 'dayGridMonth' ? 'selected' : '' ?>>Month</option>
                  <option value="timeGridWeek" <?= $ocv === 'timeGridWeek' ? 'selected' : '' ?>>Week</option>
                  <option value="timeGridDay" <?= $ocv === 'timeGridDay' ? 'selected' : '' ?>>Day</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label small" for="org_default_department_filter">Default department filter</label>
                <select class="form-select" id="org_default_department_filter" name="default_department_filter">
                  <?php foreach ($organizer_department_choices as $val => $label): ?>
                    <option value="<?= htmlspecialchars($val) ?>" <?= ((string)($organizer_settings['default_department_filter'] ?? 'ALL') === $val) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="form-check form-switch mt-3">
              <input class="form-check-input" type="checkbox" id="org_show_weekends" name="show_weekends" value="1" <?= !empty($organizer_settings['show_weekends']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="org_show_weekends">Show weekends on calendar</label>
            </div>
            <div class="mt-2">
              <label class="form-label small" for="org_week_starts_on">Week starts on</label>
              <?php $wso = (int)($organizer_settings['week_starts_on'] ?? 0); ?>
              <select class="form-select" id="org_week_starts_on" name="week_starts_on">
                <option value="0" <?= $wso === 0 ? 'selected' : '' ?>>Sunday</option>
                <option value="1" <?= $wso === 1 ? 'selected' : '' ?>>Monday</option>
              </select>
            </div>
          </div>

          <div class="organizer-settings-section">
            <h6>Notifications (email)</h6>
            <p class="small text-muted mb-2">Preferences for future email notifications. In-app notifications are unchanged.</p>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="org_notify_email_event_status" name="notify_email_event_status" value="1" <?= !empty($organizer_settings['notify_email_event_status']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="org_notify_email_event_status">Event status updates (approved, rejected, etc.)</label>
            </div>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="org_notify_email_feedback" name="notify_email_feedback" value="1" <?= !empty($organizer_settings['notify_email_feedback']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="org_notify_email_feedback">New feedback on my events</label>
            </div>
          </div>
        </div>
        <div class="modal-footer efy-modal__footer">
          <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn efy-btn-primary" id="organizerSettingsUpdateBtn"><i class="fas fa-save me-1"></i>Save settings</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="confirmOrganizerSettingsModal" tabindex="-1" aria-labelledby="confirmOrganizerSettingsLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content efy-modal efy-modal--compact">
      <div class="modal-header efy-modal__header">
        <div>
          <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="confirmOrganizerSettingsLabel">
            <i class="fas fa-save" aria-hidden="true"></i>
            Save settings?
          </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body efy-modal__body efy-modal__body--compact">
        <p class="efy-confirm-message mb-0">Your calendar defaults and notification preferences will be updated.</p>
      </div>
      <div class="modal-footer efy-modal__footer">
        <button type="button" class="btn efy-btn-primary" id="confirmOrganizerSettingsYes">Yes, save</button>
        <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">Cancel</button>
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
          <span class="efy-modal__eyebrow">Organizer</span>
          <h5 class="modal-title efy-modal__title" id="helpModalLabel">
            <i class="fas fa-circle-question" aria-hidden="true"></i>
            Help
          </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body efy-modal__body efy-modal__body--compact">
        <ul class="help-list mb-0">
          <li>Click a date to create an event.</li>
          <li>Click an event to view details.</li>
          <li>Use “My Events” to view/edit all events.</li>
        </ul>
      </div>
      <div class="modal-footer efy-modal__footer">
        <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php
    $notif_clear_modal_id = 'organizerClearNotifsModal';
    include __DIR__ . '/partials/notification_clear_confirm_modal.php';
    include __DIR__ . '/partials/notification_detail_modal.php';
?>

<!-- Logout Modal -->
<?php include __DIR__ . '/partials/activities_hub_pick_modal.php'; ?>

<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content efy-modal efy-modal--compact">
      <div class="modal-header efy-modal__header">
        <div>
          <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="logoutModalLabel">
            <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
            Confirm log out
          </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body efy-modal__body efy-modal__body--compact">
        <p class="efy-confirm-message mb-0">Are you sure you want to log out?</p>
      </div>
      <div class="modal-footer efy-modal__footer">
        <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">Cancel</button>
        <a href="<?= BASE_URL ?>/backend/auth/logout.php" class="btn efy-btn-danger js-logout-confirm">Yes, log out</a>
      </div>
    </div>
  </div>
</div>

<!-- Create Event Modal -->
<?php
$createEventRedirectTo = '';
include __DIR__ . '/partials/create_event_modal.php';
?>

<!-- Pass PHP events to JS -->
<script>
window.BASE_URL = <?= json_encode(BASE_URL) ?>;
window.csrfToken = <?= json_encode(function_exists('csrf_token') ? csrf_token() : '') ?>;
window.__organizerFlash = <?= json_encode([
    'type' => $organizer_flash_type,
    'message' => $organizer_flash_message,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.eventsData = <?= json_encode(eventify_events_to_fullcalendar_list($events, function ($e) use ($user_name) {
    return [
        'description'   => $e['description'],
        'location'      => $e['location'],
        'created_at'    => $e['created_at'],
        'status'        => $e['status'],
        'reject_reason' => $e['reject_reason'] ?? null,
        'start_time'    => $e['start_time'] ?? null,
            'end_time'      => $e['end_time'] ?? null,
            'end_time_na'   => !empty($e['end_time_na']),
        'editUrl'       => 'edit_event.php?id=' . $e['id'],
        'organizer'     => $user_name,
        'department'    => $e['department'] ?? 'ALL',
        'department_display' => eventify_format_department_label((string)($e['department'] ?? 'ALL')),
        'event_is_live'   => function_exists('eventify_event_is_live') ? eventify_event_is_live($e) : (($e['status'] ?? '') === 'active'),
        'registration_mode' => function_exists('eventify_event_registration_mode') ? eventify_event_registration_mode($e) : (string) ($e['registration_mode'] ?? 'rsvp'),
        'has_active_otp' => !empty($e['has_active_otp']),
    ];
}), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;

window.currentUser = {
    name: <?= json_encode($user_name) ?>,
    id: <?= json_encode($_SESSION['user_id'] ?? 0) ?>
};
window.currentRole = 'organizer';
window.__organizerSettings = <?= json_encode($organizer_settings, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
</script>

<!-- Event Details Modal -->
<div class="modal fade" id="eventDetailsModal" tabindex="-1" aria-labelledby="eventDetailsLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content efy-modal">
      <div class="modal-header efy-modal__header">
        <div>
          <span class="efy-modal__eyebrow">Organizer</span>
          <h5 class="modal-title efy-modal__title" id="eventDetailsLabel">
            <i class="fas fa-calendar-day" aria-hidden="true"></i>
            Event details
          </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body efy-modal__body">
        <div class="efy-form-section mb-3">
          <h5 id="eventTitle" class="mb-2" style="color:var(--efy-forest);font-weight:800;"></h5>
          <div class="efy-detail-row mb-2">
            <strong class="d-block mb-1">Schedule</strong>
            <div id="eventDate"></div>
          </div>
          <p class="efy-detail-row mb-1"><strong>Location:</strong> <span id="eventLocation"></span></p>
          <p class="efy-detail-row mb-1"><strong>Status:</strong> <span id="eventStatus" class="badge bg-success"></span></p>
          <p class="efy-detail-row mb-1"><strong>Entry:</strong> <span id="eventRegistrationModeBadge"></span> <span class="efy-form-help d-inline" id="eventRegistrationHint">— use <strong>Ticket sales</strong> below to switch to paid tickets</span></p>
          <p class="efy-detail-row mb-1" id="eventRejectReasonWrap" style="display:none;"><strong>Rejection reason:</strong> <span id="eventRejectReason" class="text-danger"></span></p>
          <p class="efy-detail-row mb-1"><strong>Target department:</strong> <span id="eventDepartment"></span></p>
          <p class="efy-detail-row mb-1"><strong>Created by:</strong> <span id="eventOrganizer"></span></p>
          <p class="efy-detail-row mt-2 mb-1"><strong>Description</strong></p>
          <p id="eventDescription" class="efy-form-help mb-0"></p>
        </div>

        <div id="eventDaySessionsPanel" class="event-day-sessions-panel mt-3" style="display:none;">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <div>
              <strong class="small text-uppercase text-muted">Activities on this day</strong>
              <div class="small fw-semibold" id="eventDaySessionsDayLabel"></div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" id="eventDaySessionsManageBtn" style="display:none;">
              <i class="fas fa-plus me-1"></i>Manage activities
            </button>
          </div>
          <div id="eventDaySessionsPreview"></div>
          <p class="text-muted small mb-0 mt-2">
            Add activities, venues, or sessions for this day.
            <a href="#" id="eventDaySessionsHubLink" class="ms-1" target="_blank" rel="noopener" style="display:none;">Open activities hub</a>
          </p>
        </div>

        <div id="eventOtpVerifyWrap" class="mt-3" style="display:none;">
          <div class="small text-muted mb-2" id="eventOtpVerifyHint">This event is pending. Enter the OTP sent by admin to verify and activate it.</div>
          <div class="small text-warning mb-2" id="eventOtpWaitingHint" style="display:none;"><i class="fas fa-hourglass-half me-1"></i>Admin has not sent an OTP for this event yet. Wait for admin review, then use the newest code from email or the bell icon.</div>
          <form method="POST" action="<?= BASE_URL ?>/backend/auth/verify_event_approval_otp.php" class="d-flex gap-2 align-items-center flex-wrap" id="eventOtpVerifyForm">
            <?= csrf_field() ?>
            <input type="hidden" name="event_id" id="eventOtpEventId" value="">
            <input type="hidden" name="return_modal" value="eventDetails">
            <input
              type="text"
              name="otp_code"
              id="eventOtpCodeInput"
              class="form-control form-control-sm"
              style="width: 130px;"
              maxlength="6"
              placeholder="Enter OTP"
              required
              pattern="\d{6}"
              inputmode="numeric"
              autocomplete="one-time-code"
            >
            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-key me-1"></i>Verify OTP</button>
          </form>
        </div>
        <p class="efy-form-help mb-0"><strong>Created at:</strong> <span id="eventCreatedAt"></span></p>
      </div>
      <div class="modal-footer efy-modal__footer flex-wrap">
        <a href="#" id="eventActivitiesHubLink" class="btn btn-outline-success" target="_blank" rel="noopener" style="display:none;"><i class="fas fa-th-large me-1"></i> Activities hub</a>
        <a href="#" id="eventEditLink" class="btn btn-primary">Edit Event</a>
        <a href="#" id="eventTicketsLink" class="btn btn-outline-success" target="_blank" rel="noopener" style="display:none;" title="Enable paid tickets, add ticket types, confirm payments"><i class="fas fa-ticket-alt me-1"></i> Ticket sales</a>
        <a href="#" id="eventQrLink" class="btn btn-outline-secondary" target="_blank" rel="noopener" style="display:none;"><i class="fas fa-qrcode me-1"></i> Show QR</a>
        <a href="#" id="eventAttendanceLink" class="btn btn-outline-info" target="_blank" rel="noopener" style="display:none;"><i class="fas fa-clipboard-check me-1"></i> Attendance</a>
        <button type="button" class="btn btn-outline-secondary" id="organizerMarkEndedBtn" style="display:none;" data-eventify-event-id=""><i class="fas fa-flag-checkered me-1"></i>End event</button>
        <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Manage day activities -->
<?php include __DIR__ . '/partials/event_day_sessions_modal.php'; ?>

<?php if (!empty($promptActivitiesEventId)): ?>
<div class="modal fade" id="promptActivitiesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content efy-modal efy-modal--compact">
      <div class="modal-header efy-modal__header">
        <div>
          <h5 class="modal-title efy-modal__title efy-modal__title--sm">
            <i class="fas fa-layer-group" aria-hidden="true"></i>
            Add day activities?
          </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body efy-modal__body efy-modal__body--compact">
        <p class="efy-confirm-message mb-0">Your multi-day event was created. Click a day on the calendar, open <strong>Event Details</strong>, then use <strong>Manage activities</strong> to add activities, venues, and times for each day.</p>
      </div>
      <div class="modal-footer efy-modal__footer">
        <button type="button" class="btn efy-btn-primary" data-bs-dismiss="modal">Got it</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/logout_confirm.js"></script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="<?= BASE_URL ?>/assets/js/event_location_picker.js"></script>
<script src="<?= BASE_URL ?>/assets/js/event_schedule_picker.js"></script>
<script src="<?= BASE_URL ?>/assets/js/create_event_modal.js?v=2"></script>

<!-- Dashboard Scripts -->
<script src="<?= BASE_URL ?>/assets/js/eventify_calendar_colors.js?v=10"></script>
<script src="<?= BASE_URL ?>/assets/js/event_day_sessions.js?v=14"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_notifications.js?v=7"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_dashboard_keyboard_scroll.js"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_schedule_display.js?v=1"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_toast.js?v=1"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_registration_mode.js?v=1"></script>
<script src="<?= BASE_URL ?>/assets/js/dashboardorganizer.js?v=24"></script>

<script>
window.EVENTIFY_GEOCODE_URL = <?= json_encode(BASE_URL . '/backend/auth/geocode_proxy.php') ?>;
window.EVENTIFY_SESSIONS_HAVE_GEO = <?= $daySessionsHaveGeo ? 'true' : 'false' ?>;
<?php if (!empty($promptActivitiesEventId)): ?>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('promptActivitiesModal');
    if (el && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(el).show();
    }
});
<?php endif; ?>
</script>

<!-- Event Details Modal -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  var methodEl = document.getElementById('organizerContactMethod');
  var emailWrap = document.getElementById('organizerEmailFieldWrap');
  var phoneWrap = document.getElementById('organizerPhoneFieldWrap');
  var emailInput = document.getElementById('organizerContactEmail');
  var phoneInput = document.getElementById('organizerPhone');
  function syncOrganizerOtpContactFields() {
    if (!methodEl || !emailWrap || !phoneWrap || !emailInput || !phoneInput) return;
    var method = methodEl.value === 'phone' ? 'phone' : 'email';
    if (method === 'email') {
      emailWrap.style.display = '';
      phoneWrap.style.display = 'none';
      emailInput.required = true;
      phoneInput.required = false;
    } else {
      emailWrap.style.display = 'none';
      phoneWrap.style.display = '';
      emailInput.required = false;
      phoneInput.required = true;
    }
  }
  if (methodEl) {
    methodEl.addEventListener('change', syncOrganizerOtpContactFields);
    syncOrganizerOtpContactFields();
  }
});
</script>

<!-- Last in DOM so it stacks above other modals (My Events, event details). Confirm footer stays clickable. -->
<div class="modal fade" id="organizerEventStatusConfirmModal" tabindex="-1" aria-labelledby="organizerEventStatusConfirmLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content efy-modal efy-modal--compact">
      <div class="modal-header efy-modal__header">
        <div>
          <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="organizerEventStatusConfirmLabel">
            <i class="fas fa-flag-checkered" aria-hidden="true"></i>
            <span id="organizerEventStatusConfirmTitle">Confirm</span>
          </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body efy-modal__body efy-modal__body--compact">
        <p class="efy-confirm-message mb-0" id="organizerEventStatusConfirmBody"></p>
      </div>
      <div class="modal-footer efy-modal__footer">
        <button type="button" class="btn efy-btn-primary" id="organizerEventStatusConfirmYes"><i class="fas fa-check me-1"></i>Confirm</button>
        <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>
<form id="organizerEventStatusHiddenForm" method="POST" action="#" style="display:none" aria-hidden="true">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(function_exists('csrf_token') ? csrf_token() : '') ?>">
  <input type="hidden" name="event_id" id="organizerEventStatusHiddenEventId" value="">
  <input type="hidden" name="action" id="organizerEventStatusHiddenAction" value="">
</form>

</body>
</html>
