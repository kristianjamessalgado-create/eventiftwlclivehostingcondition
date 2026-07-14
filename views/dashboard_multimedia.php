<?php
if (!isset($user_name)) $user_name = 'Multimedia';
if (!isset($events)) $events = [];
if (!isset($msg)) $msg = '';
$user = $user ?? ['name' => $user_name, 'user_id' => 'N/A', 'department' => null, 'profile_picture' => null];
$department = $user['department'] ?? null;
$upcomingEvents = $upcomingEvents ?? [];
$photoStatusEnabled = (bool) ($photoStatusEnabled ?? false);
$is_multimedia_moderator = (bool) ($is_multimedia_moderator ?? false);
$pending_photo_count = (int) ($pending_photo_count ?? 0);
$pending_photos_queue = is_array($pending_photos_queue ?? null) ? $pending_photos_queue : [];

$mm_events_panel_open = !empty($mm_events_panel_open);
$mm_upcoming_panel_open = !empty($mm_upcoming_panel_open);
$mm_photo_approvals_panel_open = !empty($mm_photo_approvals_panel_open);
$mm_photo_activity_panel_open = !empty($mm_photo_activity_panel_open);
$mm_dashboard_panel_open = !empty($mm_dashboard_panel_open);
$mm_events_count = isset($mm_events_count) ? (int) $mm_events_count : count($events);
$mm_upcoming_count = isset($mm_upcoming_count) ? (int) $mm_upcoming_count : count($upcomingEvents);

$totalEvents = is_array($events) ? count($events) : 0;
$totalPhotos = 0;
if (is_array($events)) {
    foreach ($events as $e) {
        $totalPhotos += (int)($e['photo_count'] ?? 0);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#121212">
    <title>Multimedia Dashboard - EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/calendar_scroll_fix.css?v=13">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard_calendar_shell.css?v=9">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard_multimedia.css?v=35">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/eventify_dashboard_brand.css?v=1">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/eventify_modal.css?v=2">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/calendar_legend.css?v=7">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/notifications.css?v=4">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/eventify_spinner.css?v=2">
</head>
<body class="multimedia-dashboard" data-eventify-keyboard-scroll data-eventify-sidebar="mmSidebar" data-eventify-main=".multimedia-dashboard .main-content">
<input type="hidden" id="csrf_token_value" value="<?= htmlspecialchars(csrf_token()) ?>">

<nav class="top-navbar">
    <div class="navbar-left">
        <button type="button" class="nav-btn sidebar-toggle-mobile" id="mmSidebarToggle" aria-label="Toggle sidebar" title="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <button type="button" class="brand-logo border-0 bg-transparent p-0" data-mm-panel="home" title="Back to calendar">
            <i class="fas fa-calendar-alt"></i>
            <span>EVENTIFY</span>
        </button>
    </div>
    <div class="navbar-right">
        <a class="nav-btn" href="<?= BASE_URL ?>/activities_hub.php" title="Activities hub" aria-label="Activities hub">
            <i class="fas fa-th-large"></i>
        </a>
        <button type="button" class="nav-btn" id="mmTopCalendarShortcutBtn" title="Go to today">
            <i class="fas fa-calendar"></i>
        </button>
        <?php
            $mm_notifications = $multimedia_notifications ?? [];
            $mm_unread_count = (int) ($multimedia_unread_count ?? 0);
            $mm_notif_dropdown = $multimedia_notif_dropdown ?? [];
        ?>
        <div class="dropdown me-2">
            <button class="nav-btn eventify-notif-btn position-relative dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" data-eventify-notif-badge aria-expanded="false" title="Notifications">
                <i class="fas fa-bell"></i>
                <?php if ($mm_unread_count > 0): ?>
                    <span class="eventify-nav-badge badge rounded-pill bg-danger"><?= $mm_unread_count > 99 ? '99+' : $mm_unread_count ?></span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end eventify-notif-dropdown">
                <li class="eventify-notif-dropdown__header">
                    <i class="fas fa-bell me-2"></i>Notifications
                    <?php if ($mm_unread_count > 0): ?>
                        <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;"><?= $mm_unread_count ?> new</span>
                    <?php endif; ?>
                </li>
                <li class="eventify-notif-dropdown__scroll">
                    <div class="eventify-notif-scroll eventify-notif-scroll--dropdown">
                        <?php
                            $notifications = $mm_notif_dropdown;
                            $empty_title = 'All caught up';
                            $empty_text = 'No new notifications right now.';
                            $notif_interactive = true;
                            include __DIR__ . '/partials/notification_cards.php';
                        ?>
                    </div>
                </li>
                <?php if ($mm_unread_count > 0 || !empty($mm_notifications)): ?>
                    <li class="eventify-notif-dropdown__footer">
                        <?php
                            $notif_show_mark_all = $mm_unread_count > 0;
                            $notif_show_clear = !empty($mm_notifications);
                            $notif_clear_modal_id = 'multimediaClearNotifsModal';
                            $notif_context = 'dropdown';
                            include __DIR__ . '/partials/notification_footer_actions.php';
                        ?>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
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
                    <a class="dropdown-item" href="#" onclick="openMmProfileModal(); return false;">
                        <i class="fas fa-user me-2"></i> Profile
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#" data-mm-panel="upcoming">
                        <i class="fas fa-calendar-check me-2"></i> Upcoming events
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- Main Layout -->
<div class="dashboard-layout">
    <div class="sidebar-backdrop" id="mmSidebarBackdrop" aria-hidden="true"></div>

    <!-- Left Sidebar -->
    <aside class="sidebar eventify-kb-scroll-zone" id="mmSidebar" tabindex="0" aria-label="Multimedia sidebar — use arrow keys to scroll">
        <button type="button" class="sidebar-close-mobile" id="mmSidebarClose" aria-label="Close menu"><i class="fas fa-times"></i></button>

        <!-- Multimedia Profile Card -->
        <div class="mm-user-card<?= $is_multimedia_moderator ? ' mm-user-card--moderator' : '' ?>">
            <div class="mm-user-avatar<?= $is_multimedia_moderator ? ' mm-user-avatar--moderator' : '' ?>">
                <?php if (!empty($user['profile_picture'])): ?>
                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($user['profile_picture']) ?>" alt="<?= htmlspecialchars($user_name) ?>">
                <?php else: ?>
                    <span><?= strtoupper(substr($user_name, 0, 1)) ?></span>
                <?php endif; ?>
                <?php if ($is_multimedia_moderator): ?>
                    <span class="mm-user-avatar__moderator-mark" title="Photo Moderator" aria-hidden="true">
                        <i class="fas fa-user-shield"></i>
                    </span>
                <?php endif; ?>
            </div>
            <h3 class="mm-user-name"><?= htmlspecialchars($user_name) ?></h3>
            <p class="mm-user-id">ID: <?= htmlspecialchars($user['user_id'] ?? 'N/A') ?></p>
            <?php if ($is_multimedia_moderator): ?>
                <span class="mm-user-moderator-badge" title="You can approve and reject team photo uploads">
                    <i class="fas fa-user-shield" aria-hidden="true"></i>
                    Photo Moderator
                </span>
            <?php endif; ?>
            <?php if ($department): ?>
                <span class="mm-user-dept"><?= htmlspecialchars($department) ?> Multimedia</span>
            <?php else: ?>
                <span class="mm-user-dept muted">Department not set</span>
            <?php endif; ?>
        </div>

        <!-- Your Role -->
        <div class="sidebar-role">
            <h3 class="section-title">YOUR ROLE</h3>
            <p class="role-desc">
                <?php if ($is_multimedia_moderator): ?>
                    You are the photo moderator for your multimedia team. Upload photos like other members, and approve or reject pending uploads before students can see them.
                <?php else: ?>
                    You are part of the multimedia team.
                    Upload photos for events and activities; your moderator will review them before they go live for students.
                <?php endif; ?>
            </p>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3 class="section-title">QUICK ACTIONS</h3>
            <button
                type="button"
                class="action-btn w-100 text-start border-0 bg-transparent<?= $mm_events_panel_open ? ' is-active' : '' ?>"
                data-mm-panel="events"
            >
                <i class="fas fa-images"></i>
                <span>All events<?= $mm_events_count > 0 ? ' (' . ($mm_events_count > 99 ? '99+' : $mm_events_count) . ')' : '' ?></span>
            </button>
            <button
                type="button"
                class="action-btn w-100 text-start border-0 bg-transparent<?= $mm_upcoming_panel_open ? ' is-active' : '' ?>"
                data-mm-panel="upcoming"
            >
                <i class="fas fa-calendar-check"></i>
                <span>Upcoming events<?= $mm_upcoming_count > 0 ? ' (' . ($mm_upcoming_count > 99 ? '99+' : $mm_upcoming_count) . ')' : '' ?></span>
            </button>
            <?php
                $activities_hub_btn_class = '';
                include __DIR__ . '/partials/activities_hub_quick_action.php';
            ?>
            <?php if ($is_multimedia_moderator): ?>
            <button
                type="button"
                class="action-btn w-100 text-start border-0 bg-transparent<?= $mm_photo_approvals_panel_open ? ' is-active' : '' ?>"
                data-mm-panel="photo_approvals"
            >
                <i class="fas fa-user-shield"></i>
                <span>Photo approvals</span>
                <?php if ($pending_photo_count > 0): ?>
                    <span class="badge bg-primary ms-1"><?= $pending_photo_count > 99 ? '99+' : $pending_photo_count ?></span>
                <?php endif; ?>
            </button>
            <button
                type="button"
                class="action-btn w-100 text-start border-0 bg-transparent<?= $mm_photo_activity_panel_open ? ' is-active' : '' ?>"
                data-mm-panel="photo_activity"
            >
                <i class="fas fa-clipboard-list"></i>
                <span>Photo activity log</span>
            </button>
            <?php endif; ?>
            <a href="#" class="action-btn logout-btn" data-bs-toggle="modal" data-bs-target="#logoutModal">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content eventify-kb-scroll-zone" tabindex="0" aria-label="Multimedia events — use arrow keys to scroll">
        <?php if ($msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div id="mmDashboardHome" class="<?= $mm_dashboard_panel_open ? 'd-none' : '' ?>">

        <div class="calendar-controls" id="mmCalendarSection" aria-label="Events calendar">
            <div class="controls-left">
                <button type="button" class="control-nav" id="mmCalPrev" aria-label="Previous"><i class="fas fa-chevron-left"></i></button>
                <h2 class="calendar-title" id="mmCalTitle">Calendar</h2>
                <button type="button" class="control-nav" id="mmCalNext" aria-label="Next"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="controls-right">
                <button type="button" class="view-btn active" data-view="dayGridMonth">Month</button>
                <button type="button" class="view-btn" data-view="timeGridWeek">Week</button>
                <button type="button" class="view-btn" data-view="timeGridDay">Day</button>
                <button type="button" class="view-btn" data-view="today">Today</button>
            </div>
        </div>
        <?php
            $legendId = 'mmCalendarLegend';
            $legendClass = 'eventify-calendar-legend student-calendar-legend mm-calendar-legend';
            $showSelectionClearNote = false;
            include __DIR__ . '/partials/calendar_event_state_legend.php';
        ?>
        <div class="calendar-container mm-cal-container eventify-dashboard-cal-container">
            <div id="mmCalendar"></div>
        </div>
        <p class="mm-cal-hint"><i class="fas fa-circle-info"></i> Click an event to open it in All events.</p>

        </div><!-- #mmDashboardHome -->

        <?php include __DIR__ . '/partials/multimedia_all_events_panel.php'; ?>
        <?php include __DIR__ . '/partials/multimedia_upcoming_events_panel.php'; ?>
        <?php if ($is_multimedia_moderator): ?>
            <?php include __DIR__ . '/partials/multimedia_photo_approvals_panel.php'; ?>
            <?php include __DIR__ . '/partials/multimedia_photo_activity_panel.php'; ?>
        <?php endif; ?>
    </main>
</div>

<!-- Profile Modal (Multimedia) -->
<div id="mmProfileModal" class="profile-modal">
    <div class="profile-modal-content">
        <span class="profile-close" onclick="closeMmProfileModal()">&times;</span>
        <h2>My Information</h2>
        <form id="mmProfileForm" action="<?= BASE_URL ?>/backend/auth/update_multimedia_profile.php" method="POST" enctype="multipart/form-data" onsubmit="event.preventDefault(); confirmMmProfileChanges(this);">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="mmProfilePicture">Profile Picture</label>
                <div class="profile-picture-preview-container">
                    <?php if (!empty($user['profile_picture'])): ?>
                        <img src="<?= BASE_URL ?>/<?= htmlspecialchars($user['profile_picture']) ?>" alt="Current profile picture" id="mmProfilePicturePreview" class="profile-picture-preview profile-picture-clickable" onclick="openMmProfilePicFullscreen(this.src)" title="Click to view full screen">
                    <?php else: ?>
                        <div class="profile-picture-placeholder" id="mmProfilePicturePreview">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <input
                    type="file"
                    id="mmProfilePicture"
                    name="profile_picture"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    class="form-control-file"
                    onchange="previewMmProfilePicture(this)"
                >
                <small class="text-muted">JPG, PNG, GIF, or WEBP (max 5MB)</small>
            </div>

            <div class="form-group">
                <label for="mmFullName">Full Name</label>
                <input
                    type="text"
                    id="mmFullName"
                    name="name"
                    value="<?= htmlspecialchars($user['name'] ?? $user_name) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>Multimedia Club</label>
                <select name="department" id="mmDepartment">
                    <option value="" <?= empty($department) ? 'selected' : '' ?>>Select Department</option>
                    <option value="High school department" <?= ($department === 'High school department') ? 'selected' : '' ?>>High School Department</option>
                    <option value="College of Communication, Information and Technology" <?= ($department === 'College of Communication, Information and Technology') ? 'selected' : '' ?>>College of Communication, Information and Technology</option>
                    <option value="College of Accountancy and Business" <?= ($department === 'College of Accountancy and Business') ? 'selected' : '' ?>>College of Accountancy and Business</option>
                    <option value="School of Law and Political Science" <?= ($department === 'School of Law and Political Science') ? 'selected' : '' ?>>School of Law and Political Science</option>
                    <option value="College of Education" <?= ($department === 'College of Education') ? 'selected' : '' ?>>College of Education</option>
                    <option value="College of Nursing and Allied health sciences" <?= ($department === 'College of Nursing and Allied health sciences') ? 'selected' : '' ?>>College of Nursing and Allied health sciences</option>
                    <option value="College of Hospitality Management" <?= ($department === 'College of Hospitality Management') ? 'selected' : '' ?>>College of Hospitality Management</option>
                </select>
                <small class="text-muted d-block mt-1">Choose your department so your multimedia club is set correctly.</small>
            </div>

            <button type="submit" class="btn btn-primary w-100">Save Info</button>
        </form>
    </div>
</div>

<!-- Confirm Multimedia Profile Save Modal -->
<div class="modal fade" id="confirmMmProfileModal" tabindex="-1" aria-labelledby="confirmMmProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content efy-modal efy-modal--compact">
            <div class="modal-header efy-modal__header">
                <div>
                    <span class="efy-modal__eyebrow">Multimedia</span>
                    <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="confirmMmProfileModalLabel">
                        <i class="fas fa-user-edit" aria-hidden="true"></i>
                        Save profile changes?
                    </h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body efy-modal__body efy-modal__body--compact">
                <p id="confirmMmProfileMessage" class="efy-confirm-message mb-0"></p>
            </div>
            <div class="modal-footer efy-modal__footer">
                <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn efy-btn-primary" id="confirmMmProfileBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Profile picture fullscreen viewer (multimedia) -->
<div class="profile-pic-fullscreen" id="mmProfilePicFullscreen" style="display:none;">
    <div class="profile-pic-fullscreen-overlay" onclick="closeMmProfilePicFullscreen()"></div>
    <button class="profile-pic-fullscreen-close" onclick="closeMmProfilePicFullscreen()" aria-label="Close"><i class="fas fa-times"></i></button>
    <div class="profile-pic-fullscreen-content">
        <img id="mmProfilePicFullscreenImg" src="" alt="Profile picture">
    </div>
</div>

<!-- Event activities picker (tap main event card) -->
<div class="modal fade" id="mmEventActivitiesModal" tabindex="-1" aria-labelledby="mmEventActivitiesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable mm-event-activities-modal">
        <div class="modal-content efy-modal">
            <div class="modal-header efy-modal__header">
                <div>
                    <span class="efy-modal__eyebrow">Multimedia</span>
                    <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="mmEventActivitiesModalLabel">
                        <i class="fas fa-layer-group" aria-hidden="true"></i>
                        Choose upload target
                    </h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body efy-modal__body mm-event-activities-modal__body">
                <p class="mm-event-activities-modal__intro efy-form-help mb-3" id="mmEventActivitiesModalIntro"></p>
                <div class="mm-event-activities-modal__main" id="mmEventActivitiesModalMain"></div>
                <div class="mm-event-activities" id="mmEventActivitiesModalSection" hidden>
                    <div class="mm-event-activities__head">
                        <span class="mm-event-activities__label">
                            <i class="fas fa-layer-group" aria-hidden="true"></i>
                            Activities in this event
                        </span>
                        <span class="mm-event-activities__count" id="mmEventActivitiesModalCount">0</span>
                    </div>
                    <ul class="mm-event-activities__list" id="mmEventActivitiesModalList"></ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content efy-modal">
            <div class="modal-header efy-modal__header">
                <div>
                    <span class="efy-modal__eyebrow">Multimedia</span>
                    <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="uploadModalLabel">
                        <i class="fas fa-cloud-upload-alt" aria-hidden="true"></i>
                        Upload photos
                    </h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>/backend/auth/upload_event_photo.php" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="event_id" id="uploadEventId" value="">
                <div class="modal-body efy-modal__body">
                    <div class="efy-form-section mb-3">
                        <p class="efy-form-section__label">Event</p>
                        <p class="mb-0 fw-semibold" id="uploadEventTitle" style="color:var(--efy-forest);"></p>
                    </div>
                    <div class="efy-form-section mb-3" id="uploadTargetWrap">
                        <label class="efy-form-label" for="uploadSessionSelect">Upload to</label>
                        <select class="form-select" name="session_id" id="uploadSessionSelect">
                            <option value="0">Main event (general photos)</option>
                        </select>
                        <span class="efy-form-help">Choose the main event for general coverage, or pick a specific activity (e.g. Badminton).</span>
                    </div>
                    <div class="efy-form-section mb-3">
                        <label class="efy-form-label" for="photosInput">Select images (JPG, PNG, GIF, WEBP — max <?= (int)($max_upload_mb ?? 10) ?>MB each)</label>
                        <input type="file" name="photos[]" id="photosInput" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" multiple required>
                        <span class="efy-form-help">You can select multiple files.</span>
                    </div>
                    <div class="efy-form-section mb-3">
                        <label class="efy-form-label" for="uploadPhotoCaption">Caption <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" class="form-control" name="caption" id="uploadPhotoCaption" maxlength="255" placeholder="e.g. Opening ceremony, Team A vs Team B">
                    </div>
                    <div class="efy-form-section mb-0">
                        <label class="efy-form-label" for="uploadPhotoCredit">Credit line <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" class="form-control" name="credit_line" id="uploadPhotoCredit" maxlength="255" placeholder="e.g. Photo by <?= htmlspecialchars($user_name) ?> / WLC Multimedia" value="">
                        <span class="efy-form-help">Shown to students on the published gallery.</span>
                    </div>
                </div>
                <div class="modal-footer efy-modal__footer">
                    <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn efy-btn-primary"><i class="fas fa-upload me-1"></i> Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Logout modal -->
<?php include __DIR__ . '/partials/activities_hub_pick_modal.php'; ?>

<?php
    $notif_clear_modal_id = 'multimediaClearNotifsModal';
    include __DIR__ . '/partials/notification_clear_confirm_modal.php';
    include __DIR__ . '/partials/notification_detail_modal.php';
?>

<?php include __DIR__ . '/partials/logout_confirm_modal.php'; ?>

<!-- Gallery modal -->
<div class="modal fade" id="galleryModal" tabindex="-1" aria-labelledby="galleryTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content efy-modal">
            <div class="modal-header efy-modal__header">
                <div>
                    <span class="efy-modal__eyebrow">Multimedia</span>
                    <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="galleryTitle">
                        <i class="fas fa-images" aria-hidden="true"></i>
                        <span id="galleryTitleText">Event photos</span>
                    </h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body efy-modal__body">
                <div id="galleryToolbar" class="gallery-toolbar" style="display:none;">
                    <label class="gallery-selectall">
                        <input type="checkbox" id="gallerySelectAll"> Select all
                    </label>
                    <div class="gallery-toolbar-actions">
                        <button type="button" id="galleryDownloadSelected" class="btn btn-sm btn-outline-primary" disabled>
                            <i class="fas fa-download me-1"></i> Download selected (<span id="gallerySelectedCount">0</span>)
                        </button>
                        <button type="button" id="galleryDownloadAll" class="btn btn-sm btn-primary">
                            <i class="fas fa-file-zipper me-1"></i> Download all
                        </button>
                    </div>
                </div>
                <div id="galleryGrid" class="gallery-grid"><!-- Thumbnails injected by JS --></div>
                <p id="galleryEmpty" class="efy-form-help mb-0" style="display:none;">No photos uploaded yet for this event.</p>
            </div>
            <div class="modal-footer efy-modal__footer">
                <button type="button" class="btn efy-btn-muted btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Photo Confirmation Modal -->
<div class="modal fade" id="deletePhotoModal" tabindex="-1" aria-labelledby="deletePhotoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content efy-modal efy-modal--compact">
            <div class="modal-header efy-modal__header">
                <div>
                    <span class="efy-modal__eyebrow">Multimedia</span>
                    <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="deletePhotoModalLabel">
                        <i class="fas fa-trash-alt" aria-hidden="true"></i>
                        Delete photo
                    </h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body efy-modal__body efy-modal__body--compact">
                <p class="efy-confirm-message mb-0">Are you sure you want to delete this photo? This cannot be undone.</p>
                <div id="deletePhotoReasonWrap" class="efy-form-section mt-3 mb-0" style="display:none;">
                    <label for="deletePhotoReason" class="efy-form-label">
                        Reason for deletion <span class="text-danger">*</span>
                    </label>
                    <textarea id="deletePhotoReason" class="form-control" rows="3"
                              maxlength="500"
                              placeholder="e.g. Blurry photo, duplicate, or not relevant to the event."></textarea>
                    <span class="efy-form-help">The uploader will be notified with this reason.</span>
                    <div id="deletePhotoReasonError" class="text-danger small mt-1" style="display:none;">
                        Please enter a reason before deleting.
                    </div>
                </div>
            </div>
            <div class="modal-footer efy-modal__footer">
                <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn efy-btn-danger" id="deletePhotoConfirmBtn"><i class="fas fa-trash-alt me-1"></i> Yes, delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Publish / approve pending photos confirmation -->
<div class="modal fade" id="publishPhotosModal" tabindex="-1" aria-labelledby="publishPhotosModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content efy-modal efy-modal--compact">
            <div class="modal-header efy-modal__header">
                <div>
                    <span class="efy-modal__eyebrow">Multimedia</span>
                    <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="publishPhotosModalLabel">
                        <i class="fas fa-check-double" aria-hidden="true"></i>
                        Approve photos
                    </h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/backend/auth/publish_event_photos.php">
                <?= csrf_field() ?>
                <input type="hidden" name="event_id" id="publishPhotosEventId" value="">
                <div class="modal-body efy-modal__body efy-modal__body--compact">
                    <p class="efy-confirm-message mb-2" id="publishPhotosMessage"></p>
                    <span class="efy-form-help">
                        All pending photos for this event will become visible in the student photo gallery and Activities hub.
                        After approval, use <strong>Preview</strong> or <strong>QR</strong> on the event card to share photos with students.
                    </span>
                </div>
                <div class="modal-footer efy-modal__footer">
                    <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn efy-btn-primary">
                        <i class="fas fa-check me-1"></i> Yes, approve all
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Photo moderation confirm (moderator) -->
<?php if ($is_multimedia_moderator): ?>
<?php include __DIR__ . '/partials/photo_moderation_confirm_modal.php'; ?>
<?php endif; ?>

<!-- Delete Event Photos (My uploads) Confirmation Modal -->
<div class="modal fade" id="deleteEventPhotosModal" tabindex="-1" aria-labelledby="deleteEventPhotosModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content efy-modal efy-modal--compact">
            <div class="modal-header efy-modal__header">
                <div>
                    <span class="efy-modal__eyebrow">Multimedia</span>
                    <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="deleteEventPhotosModalLabel">
                        <i class="fas fa-trash-alt" aria-hidden="true"></i>
                        Delete your photos
                    </h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/backend/auth/delete_my_event_photos.php">
                <?= csrf_field() ?>
                <input type="hidden" name="event_id" id="deleteEventPhotosEventId" value="">
                <div class="modal-body efy-modal__body efy-modal__body--compact">
                    <p class="efy-confirm-message mb-2" id="deleteEventPhotosMessage"></p>
                    <span class="efy-form-help">This will delete only the photos you uploaded for this event.</span>
                </div>
                <div class="modal-footer efy-modal__footer">
                    <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn efy-btn-danger">
                        <i class="fas fa-trash-alt me-1"></i> Yes, delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Photo Viewer Lightbox (Facebook-style) -->
<div class="photo-viewer" id="photoViewer" style="display:none;">
    <div class="photo-viewer-overlay" onclick="closePhotoViewer()"></div>
    <button class="photo-viewer-close" onclick="closePhotoViewer()" aria-label="Close">
        <i class="fas fa-times"></i>
    </button>
    <button class="photo-viewer-nav photo-viewer-prev" onclick="navigatePhoto(-1)" aria-label="Previous">
        <i class="fas fa-chevron-left"></i>
    </button>
    <button class="photo-viewer-nav photo-viewer-next" onclick="navigatePhoto(1)" aria-label="Next">
        <i class="fas fa-chevron-right"></i>
    </button>
    <div class="photo-viewer-content">
        <img id="viewerImage" src="" alt="Event photo" class="photo-viewer-img">
        <div class="photo-viewer-info">
            <span id="viewerPhotoCount" class="photo-count"></span>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script>window.csrfToken = <?= json_encode(function_exists('csrf_token') ? csrf_token() : '') ?>;</script>
<script>window.BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
<script>window.eventsData = <?= json_encode(
    function_exists('eventify_events_to_fullcalendar_list')
        ? eventify_events_to_fullcalendar_list($events, function ($e) {
            return [
                'description'        => $e['description'] ?? '',
                'location'           => $e['location'] ?? '',
                'status'             => $e['status'] ?? 'active',
                'department'         => $e['department'] ?? 'ALL',
                'photo_count'        => (int) ($e['photo_count'] ?? 0),
            ];
        })
        : [],
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;</script>
<script src="<?= BASE_URL ?>/assets/js/eventify_calendar_colors.js?v=10"></script>
<script src="<?= BASE_URL ?>/assets/js/dashboard_multimedia_panels.js?v=7"></script>
<script src="<?= BASE_URL ?>/assets/js/dashboard_multimedia_calendar.js?v=8"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_notifications.js?v=9"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_dashboard_keyboard_scroll.js"></script>
<script src="<?= BASE_URL ?>/assets/js/logout_confirm.js"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_spinner.js?v=2"></script>
<script src="<?= BASE_URL ?>/assets/js/photo_moderation_confirm.js?v=3"></script>
<script>
window.mmEventActivities = <?= json_encode($eventActivitiesByEvent ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
window.mmEventSessionPhotoStats = <?= json_encode($eventSessionPhotoStats ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;

function mmEscapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
}

function mmEscapeAttr(text) {
    return String(text == null ? '' : text)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;');
}

function mmFormatActivityMeta(act) {
    var parts = [];
    if (act.schedule_date) {
        var d = new Date(act.schedule_date + 'T12:00:00');
        if (!isNaN(d.getTime())) {
            parts.push(d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }));
        }
    }
    if (act.start_time && act.end_time) {
        var fmt = function (t) {
            var raw = String(t).slice(0, 5);
            var ts = new Date('1970-01-01T' + raw + ':00');
            return isNaN(ts.getTime()) ? raw : ts.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
        };
        parts.push(fmt(act.start_time) + ' - ' + fmt(act.end_time));
    } else if (act.start_time) {
        var raw2 = String(act.start_time).slice(0, 5);
        var ts2 = new Date('1970-01-01T' + raw2 + ':00');
        parts.push(isNaN(ts2.getTime()) ? raw2 : ts2.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' }));
    }
    return parts.join(' · ');
}

function mmBuildActivityUploadBtn(eventId, eventTitle, sessionId, sessionTitle, extraClass) {
    return '<button type="button" class="btn btn-sm btn-outline-primary mm-activity-upload-btn' + (extraClass ? ' ' + extraClass : '') + '"'
        + ' data-event-id="' + eventId + '"'
        + ' data-event-title="' + mmEscapeAttr(eventTitle) + '"'
        + ' data-session-id="' + sessionId + '"'
        + ' data-session-title="' + mmEscapeAttr(sessionTitle) + '"'
        + ' onclick="openUploadModal(this); return false;">'
        + '<i class="fas fa-cloud-upload-alt"></i> Upload</button>';
}

function openMmEventActivitiesModal(card) {
    if (!card) {
        return;
    }
    var eventId = parseInt(card.dataset.eventId || '0', 10) || 0;
    var eventTitle = card.dataset.title || 'Event';
    var activities = (window.mmEventActivities && window.mmEventActivities[eventId]) || [];
    var stats = (window.mmEventSessionPhotoStats && window.mmEventSessionPhotoStats[eventId]) || {};
    var modalEl = document.getElementById('mmEventActivitiesModal');
    if (!modalEl || eventId < 1) {
        return;
    }

    var titleEl = document.getElementById('mmEventActivitiesModalLabel');
    var introEl = document.getElementById('mmEventActivitiesModalIntro');
    var mainEl = document.getElementById('mmEventActivitiesModalMain');
    var sectionEl = document.getElementById('mmEventActivitiesModalSection');
    var countEl = document.getElementById('mmEventActivitiesModalCount');
    var listEl = document.getElementById('mmEventActivitiesModalList');
    if (!titleEl || !introEl || !mainEl || !sectionEl || !countEl || !listEl) {
        return;
    }

    titleEl.textContent = eventTitle;
    introEl.textContent = activities.length
        ? 'Upload to the main event for general photos, or pick a specific activity below.'
        : 'Upload photos for this event.';

    mainEl.innerHTML = ''
        + '<div class="mm-event-activity-row mm-event-activity-row--main">'
        + '<div class="mm-event-activity-row__main">'
        + '<span class="mm-event-activity-row__title">Main event (general)</span>'
        + '<span class="mm-event-activity-row__meta">Opening, crowd shots, venue — not tied to one activity</span>'
        + '</div>'
        + mmBuildActivityUploadBtn(eventId, eventTitle, 0, '', 'mm-activity-upload-btn--main')
        + '</div>';

    if (activities.length) {
        sectionEl.hidden = false;
        countEl.textContent = String(activities.length);
        listEl.innerHTML = activities.map(function (act) {
            var sid = parseInt(act.id || '0', 10) || 0;
            var actTitle = act.title || 'Activity';
            var stat = stats[String(sid)] || stats[sid] || null;
            var published = stat ? parseInt(stat.published || '0', 10) || 0 : 0;
            var mine = stat ? parseInt(stat.my_total || '0', 10) || 0 : 0;
            var photosHtml = '';
            if (published > 0 || mine > 0) {
                photosHtml = '<span class="mm-event-activity-row__photos">'
                    + (published > 0 ? published + ' published' : '')
                    + (published > 0 && mine > 0 ? ' · ' : '')
                    + (mine > 0 ? mine + ' mine' : '')
                    + '</span>';
            }
            return '<li class="mm-event-activity-row">'
                + '<div class="mm-event-activity-row__main">'
                + '<span class="mm-event-activity-row__title">' + mmEscapeHtml(actTitle) + '</span>'
                + '<span class="mm-event-activity-row__meta">' + mmEscapeHtml(mmFormatActivityMeta(act)) + '</span>'
                + photosHtml
                + '</div>'
                + mmBuildActivityUploadBtn(eventId, eventTitle, sid, actTitle)
                + '</li>';
        }).join('');
    } else {
        sectionEl.hidden = true;
        listEl.innerHTML = '';
        countEl.textContent = '0';
    }

    modalEl.dataset.mmEventId = String(eventId);
    modalEl.dataset.mmEventTitle = eventTitle;

    if (window.bootstrap && bootstrap.Modal) {
        var inst = bootstrap.Modal.getOrCreateInstance(modalEl);
        inst.show();
    } else {
        showModalFallback(modalEl);
    }
}

function initMmEventCardPicker() {
    var list = document.getElementById('eventsList');
    if (!list) {
        return;
    }
    list.addEventListener('click', function (e) {
        if (e.target.closest('.event-actions')) {
            return;
        }
        if (e.target.closest('a, button, input, label, select, textarea')) {
            return;
        }
        var card = e.target.closest('.event-card--has-activities');
        if (!card) {
            return;
        }
        openMmEventActivitiesModal(card);
    });
    list.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') {
            return;
        }
        var card = e.target.closest('.event-card--has-activities');
        if (!card || !card.contains(e.target) || e.target.closest('.event-actions')) {
            return;
        }
        e.preventDefault();
        openMmEventActivitiesModal(card);
    });
}

document.addEventListener('DOMContentLoaded', initMmEventCardPicker);

function mmFormatActivityOptionLabel(act) {
    var parts = [];
    if (act.schedule_date) {
        var d = new Date(act.schedule_date + 'T12:00:00');
        if (!isNaN(d.getTime())) {
            parts.push(d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }));
        }
    }
    if (act.start_time) {
        var t = String(act.start_time).slice(0, 5);
        var ts = new Date('1970-01-01T' + t + ':00');
        parts.push(isNaN(ts.getTime()) ? t : ts.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' }));
    }
    return act.title + (parts.length ? ' — ' + parts.join(' · ') : '');
}

function mmPopulateUploadTargetSelect(eventId, presetSessionId) {
    var selectEl = document.getElementById('uploadSessionSelect');
    if (!selectEl) {
        return;
    }
    var activities = (window.mmEventActivities && window.mmEventActivities[eventId]) || [];
    selectEl.innerHTML = '';
    var mainOpt = document.createElement('option');
    mainOpt.value = '0';
    mainOpt.textContent = 'Main event (general photos)';
    selectEl.appendChild(mainOpt);
    activities.forEach(function (act) {
        if (!act || !act.id) {
            return;
        }
        var opt = document.createElement('option');
        opt.value = String(act.id);
        opt.textContent = mmFormatActivityOptionLabel(act);
        selectEl.appendChild(opt);
    });
    var preset = parseInt(presetSessionId || '0', 10) || 0;
    if (preset > 0 && selectEl.querySelector('option[value="' + preset + '"]')) {
        selectEl.value = String(preset);
    } else {
        selectEl.value = '0';
    }
}

function mmUpdateUploadModalTitle(eventTitle, sessionId) {
    var titleEl = document.getElementById('uploadEventTitle');
    if (!titleEl) {
        return;
    }
    var sid = parseInt(sessionId || '0', 10) || 0;
    var selectEl = document.getElementById('uploadSessionSelect');
    var activityLabel = '';
    if (sid > 0 && selectEl) {
        var opt = selectEl.options[selectEl.selectedIndex];
        activityLabel = opt ? opt.textContent : '';
    }
    if (sid > 0 && activityLabel) {
        titleEl.textContent = eventTitle + ' â†’ ' + activityLabel;
    } else {
        titleEl.textContent = eventTitle + ' (main event)';
    }
}

// Upload modal helper:
// - Uses Bootstrap modal if available
// - Falls back to a simple modal display if Bootstrap JS isn't loaded (e.g., offline)
function showModalFallback(modalEl) {
    if (!modalEl) return;
    modalEl.style.display = 'block';
    modalEl.classList.add('show');
    modalEl.removeAttribute('aria-hidden');
    modalEl.setAttribute('aria-modal', 'true');
    modalEl.setAttribute('role', 'dialog');
    document.body.style.overflow = 'hidden';
    document.body.classList.add('modal-open');

    var backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop fade show';
    backdrop.setAttribute('data-fallback', '1');
    document.body.appendChild(backdrop);
    backdrop.addEventListener('click', function() { hideModalFallback(modalEl); });

    var dismissers = modalEl.querySelectorAll('[data-bs-dismiss="modal"]');
    for (var i = 0; i < dismissers.length; i++) {
        dismissers[i].addEventListener('click', function() { hideModalFallback(modalEl); }, { once: true });
    }
}

function hideModalFallback(modalEl) {
    if (!modalEl) return;
    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    modalEl.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    document.body.classList.remove('modal-open');
    var backdrops = document.querySelectorAll('.modal-backdrop[data-fallback="1"]');
    for (var i = 0; i < backdrops.length; i++) backdrops[i].remove();
}

function openUploadModal(btn) {
    var modalEl = document.getElementById('uploadModal');
    if (!modalEl || !btn) return;

    var eid = parseInt(btn.dataset.eventId || '0', 10) || 0;
    var title = btn.dataset.eventTitle || '';
    var presetSession = parseInt(btn.dataset.sessionId || '0', 10) || 0;
    var idEl = document.getElementById('uploadEventId');
    var photosEl = document.getElementById('photosInput');
    var selectEl = document.getElementById('uploadSessionSelect');
    if (idEl) idEl.value = String(eid);
    if (photosEl) photosEl.value = '';
    mmPopulateUploadTargetSelect(eid, presetSession);
    mmUpdateUploadModalTitle(title, presetSession);
    modalEl.dataset.uploadEventTitle = title;
    if (selectEl && !selectEl.dataset.mmBound) {
        selectEl.dataset.mmBound = '1';
        selectEl.addEventListener('change', function () {
            var modal = document.getElementById('uploadModal');
            mmUpdateUploadModalTitle(
                modal ? (modal.dataset.uploadEventTitle || '') : '',
                selectEl.value
            );
        });
    }

    if (window.bootstrap && bootstrap.Modal) {
        var activitiesModal = document.getElementById('mmEventActivitiesModal');
        if (activitiesModal) {
            var actInst = bootstrap.Modal.getInstance(activitiesModal);
            if (actInst) {
                actInst.hide();
            }
        }
        new bootstrap.Modal(modalEl).show();
    } else {
        showModalFallback(modalEl);
    }
}

// Profile modal (multimedia)
function openMmProfileModal() {
    var el = document.getElementById('mmProfileModal');
    if (el) el.classList.add('show');
}
function closeMmProfileModal() {
    var el = document.getElementById('mmProfileModal');
    if (el) el.classList.remove('show');
}
function previewMmProfilePicture(input) {
    var preview = document.getElementById('mmProfilePicturePreview');
    if (!preview) return;
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            if (preview.tagName === 'IMG') {
                preview.src = e.target.result;
            } else {
                var img = document.createElement('img');
                img.id = 'mmProfilePicturePreview';
                img.className = 'profile-picture-preview profile-picture-clickable';
                img.alt = 'Preview';
                img.src = e.target.result;
                img.onclick = function() { openMmProfilePicFullscreen(img.src); };
                preview.parentNode.replaceChild(img, preview);
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}
var pendingMmProfileForm = null;
function confirmMmProfileChanges(form) {
    var nameEl = form.querySelector('input[name="name"]');
    var name = nameEl ? nameEl.value : '';
    var fileInput = form.querySelector('input[name="profile_picture"]');
    var hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
    var msg = 'Update your display name' + (name ? ' to "' + name + '"' : '') + '.';
    if (hasFile) msg += ' A new profile picture will be uploaded.';
    var messageEl = document.getElementById('confirmMmProfileMessage');
    if (messageEl) messageEl.textContent = msg;
    pendingMmProfileForm = form;
    var modalEl = document.getElementById('confirmMmProfileModal');
    if (!modalEl) { form.submit(); return; }
    var modal = new bootstrap.Modal(modalEl);
    modalEl.addEventListener('shown.bs.modal', function raiseConfirmZIndex() {
        modalEl.removeEventListener('shown.bs.modal', raiseConfirmZIndex);
        modalEl.style.zIndex = '1200';
        var backdrops = document.querySelectorAll('.modal-backdrop');
        for (var i = 0; i < backdrops.length; i++) { backdrops[i].style.zIndex = '1199'; }
    }, { once: true });
    modal.show();
    var btn = document.getElementById('confirmMmProfileBtn');
    if (btn) {
        btn.onclick = function() {
            modal.hide();
            if (pendingMmProfileForm) {
                pendingMmProfileForm.submit();
                pendingMmProfileForm = null;
            }
        };
    }
}
function openMmProfilePicFullscreen(src) {
    if (!src) return;
    var el = document.getElementById('mmProfilePicFullscreen');
    var img = document.getElementById('mmProfilePicFullscreenImg');
    if (el && img) {
        img.src = src;
        el.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}
function closeMmProfilePicFullscreen() {
    var el = document.getElementById('mmProfilePicFullscreen');
    if (el) {
        el.style.display = 'none';
        document.body.style.overflow = '';
    }
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeMmProfilePicFullscreen();
});

// Make photos data available to JS
window.photosByEvent = <?= json_encode($photosByEvent ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.currentMultimediaUserId = <?= (int)($uid ?? 0) ?>;
window.isMultimediaModerator = <?= $is_multimedia_moderator ? 'true' : 'false' ?>;
window.currentRole = 'multimedia';

document.addEventListener('DOMContentLoaded', function() {
    // Sidebar collapse toggle (desktop + tablet)
    var mmSidebarToggle = document.getElementById('mmSidebarToggle');
    var mmSidebarClose = document.getElementById('mmSidebarClose');
    var mmSidebarBackdrop = document.getElementById('mmSidebarBackdrop');
    var mainContent = document.querySelector('body.multimedia-dashboard .main-content');
    var savedMainScroll = 0;
    var isMobileView = function() { return window.matchMedia('(max-width: 768px)').matches; };
    var openMmMobileSidebar = function() {
        if (mainContent) {
            savedMainScroll = mainContent.scrollTop;
            mainContent.classList.add('mm-main-scroll-locked');
            mainContent.style.setProperty('--mm-scroll-lock-top', savedMainScroll + 'px');
        }
        document.documentElement.classList.add('mm-sidebar-open');
        document.body.classList.add('mm-sidebar-open');
        var sidebar = document.getElementById('mmSidebar');
        if (sidebar) {
            sidebar.focus({ preventScroll: true });
        }
    };
    var closeMmMobileSidebar = function() {
        document.documentElement.classList.remove('mm-sidebar-open');
        document.body.classList.remove('mm-sidebar-open');
        if (mainContent) {
            mainContent.classList.remove('mm-main-scroll-locked');
            mainContent.style.removeProperty('--mm-scroll-lock-top');
            mainContent.scrollTop = savedMainScroll;
        }
    };
    var mmTouchMoveBlock = function(e) {
        if (!document.body.classList.contains('mm-sidebar-open')) return;
        var sidebar = document.getElementById('mmSidebar');
        if (sidebar && sidebar.contains(e.target)) return;
        e.preventDefault();
    };
    document.addEventListener('touchmove', mmTouchMoveBlock, { passive: false });
    if (mmSidebarToggle) {
        mmSidebarToggle.addEventListener('click', function() {
            if (isMobileView()) {
                if (document.body.classList.contains('mm-sidebar-open')) {
                    closeMmMobileSidebar();
                } else {
                    openMmMobileSidebar();
                }
                return;
            }
            document.body.classList.toggle('mm-sidebar-collapsed');
            // Let the calendar re-fit the new content width after the transition
            [180, 360].forEach(function(ms) {
                setTimeout(function() { window.dispatchEvent(new Event('resize')); }, ms);
            });
        });
    }
    if (mmSidebarClose) mmSidebarClose.addEventListener('click', closeMmMobileSidebar);
    if (mmSidebarBackdrop) {
        mmSidebarBackdrop.addEventListener('click', closeMmMobileSidebar);
    }
    var mmSidebar = document.getElementById('mmSidebar');
    if (mmSidebar) {
        mmSidebar.addEventListener('click', function(e) {
            var target = e.target.closest('.action-btn, [data-bs-toggle="modal"], [data-mm-panel]');
            if (target && isMobileView()) closeMmMobileSidebar();
        });
    }
    window.addEventListener('resize', function() {
        if (!isMobileView()) closeMmMobileSidebar();
    });

    // Client-side filter: search + status chips + coverage
    var searchInput = document.getElementById('eventSearchInput');
    var list = document.getElementById('eventsList');
    var statusFilterWrap = document.getElementById('mmStatusFilter');
    var coverageFilterWrap = document.getElementById('mmCoverageFilter');
    var statusOptions = ['active', 'closed'];
    var selectedStatuses = ['active', 'closed'];
    var selectedCoverage = 'all';
    var mmIsModerator = <?= $is_multimedia_moderator ? 'true' : 'false' ?>;
    var mmEventsListHost = document.getElementById('mmEventsListHost');
    var mmFilterSkipLoadingOnce = true;

    // Map an event's raw status to one of the four filter buckets.
    function statusBucket(raw) {
        var s = (raw || '').toLowerCase();
        if (s === 'active' || s === 'upcoming' || s === 'ongoing' || s === 'approved') return 'active';
        return 'closed'; // closed, completed, ended, archived, etc.
    }

    function updateChipUi() {
        if (!statusFilterWrap) return;
        statusFilterWrap.querySelectorAll('.mm-status-chip').forEach(function(chip) {
            var key = chip.getAttribute('data-status-filter');
            var on = selectedStatuses.indexOf(key) >= 0;
            chip.classList.toggle('is-selected', on);
            chip.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function updateCoverageChipUi() {
        if (!coverageFilterWrap) return;
        coverageFilterWrap.querySelectorAll('.mm-coverage-chip').forEach(function(chip) {
            var key = chip.getAttribute('data-coverage-filter');
            var on = key === selectedCoverage;
            chip.classList.toggle('is-selected', on);
            chip.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function matchesCoverage(card) {
        if (selectedCoverage === 'all') return true;
        var myPhotos = parseInt(card.getAttribute('data-my-photos') || '0', 10) || 0;
        var myPending = parseInt(card.getAttribute('data-my-pending') || '0', 10) || 0;
        var teamPending = parseInt(card.getAttribute('data-team-pending') || '0', 10) || 0;
        if (selectedCoverage === 'my-uploads') return myPhotos > 0;
        if (selectedCoverage === 'pending') return mmIsModerator ? teamPending > 0 : myPending > 0;
        return true;
    }

    function applyEventFiltersCore() {
        if (!list) return;
        var q = (searchInput && searchInput.value ? searchInput.value : '').trim().toLowerCase();
        var cards = list.querySelectorAll('.event-card');
        var visible = 0;
        cards.forEach(function(card) {
            var t = (card.getAttribute('data-title') || '').toLowerCase();
            var loc = (card.getAttribute('data-location') || '').toLowerCase();
            var bucket = statusBucket(card.getAttribute('data-status'));
            var matchesSearch = !q || t.includes(q) || loc.includes(q);
            var matchesStatus = selectedStatuses.indexOf(bucket) >= 0;
            var ok = matchesSearch && matchesStatus && matchesCoverage(card);
            card.style.display = ok ? '' : 'none';
            if (ok) visible++;
        });
        var emptyEl = document.getElementById('mmNoEventResults');
        if (emptyEl) {
            emptyEl.style.display = visible === 0 ? '' : 'none';
        }
        updateChipUi();
        updateCoverageChipUi();
    }

    function applyEventFilters(showSpinner) {
        if (!showSpinner) {
            applyEventFiltersCore();
            return;
        }
        if (mmFilterSkipLoadingOnce) {
            mmFilterSkipLoadingOnce = false;
            applyEventFiltersCore();
            return;
        }
        if (window.EventifySpinner && mmEventsListHost) {
            window.EventifySpinner.run(mmEventsListHost, function (finish) {
                window.requestAnimationFrame(function () {
                    applyEventFiltersCore();
                    finish();
                });
            }, { message: 'Updating filters…', minMs: 280 });
            return;
        }
        applyEventFiltersCore();
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            applyEventFiltersCore();
        });
    }
    if (statusFilterWrap) {
        statusFilterWrap.addEventListener('click', function(e) {
            var chip = e.target.closest('.mm-status-chip');
            if (chip) {
                var key = chip.getAttribute('data-status-filter');
                if (statusOptions.indexOf(key) < 0) return;
                var idx = selectedStatuses.indexOf(key);
                if (idx >= 0) {
                    selectedStatuses.splice(idx, 1);
                } else {
                    selectedStatuses.push(key);
                }
                applyEventFilters(true);
                return;
            }
            if (e.target.closest('[data-mm-filter-all]')) {
                selectedStatuses = statusOptions.slice();
                applyEventFilters(true);
                return;
            }
            if (e.target.closest('[data-mm-filter-none]')) {
                selectedStatuses = [];
                applyEventFilters(true);
                return;
            }
        });
    }
    if (coverageFilterWrap) {
        coverageFilterWrap.addEventListener('click', function(e) {
            var chip = e.target.closest('.mm-coverage-chip');
            if (!chip) return;
            var key = chip.getAttribute('data-coverage-filter');
            if (!key) return;
            selectedCoverage = key;
            applyEventFilters(true);
        });
    }
    applyEventFilters(false);

    window.mmResetEventListFilters = function () {
        if (searchInput) {
            searchInput.value = '';
        }
        selectedStatuses = statusOptions.slice();
        selectedCoverage = 'all';
        applyEventFiltersCore();
    };

    var publishPhotosModal = document.getElementById('publishPhotosModal');
    if (publishPhotosModal) {
        publishPhotosModal.addEventListener('show.bs.modal', function(e) {
            var btn = e.relatedTarget;
            var idEl = document.getElementById('publishPhotosEventId');
            var msgEl = document.getElementById('publishPhotosMessage');
            if (!btn || !idEl || !msgEl) return;
            var eventId = btn.dataset.eventId || '';
            var title = btn.dataset.eventTitle || '';
            var drafts = parseInt(btn.dataset.draftCount || '0', 10) || 0;
            idEl.value = eventId;
            var noun = drafts === 1 ? 'pending photo' : 'pending photos';
            msgEl.textContent = 'Approve all ' + drafts + ' ' + noun + ' for "' + title + '"? Students will be able to see them in the gallery and Activities hub.';
        });
    }

    var deleteEventPhotosModal = document.getElementById('deleteEventPhotosModal');
    if (deleteEventPhotosModal) {
        deleteEventPhotosModal.addEventListener('show.bs.modal', function(e) {
            var btn = e.relatedTarget;
            var idEl = document.getElementById('deleteEventPhotosEventId');
            var msgEl = document.getElementById('deleteEventPhotosMessage');
            if (!btn || !idEl || !msgEl) return;
            var eventId = btn.dataset.eventId || '';
            var title = btn.dataset.eventTitle || '';
            var count = parseInt(btn.dataset.myCount || '0', 10) || 0;
            idEl.value = eventId;
            msgEl.textContent = 'Delete ' + count + ' of your photo(s) from "' + title + '"? This cannot be undone.';
        });
    }

    var galleryModal = document.getElementById('galleryModal');
    var currentGalleryEventId = null;

    function galleryUpdateSelection() {
        var gridEl = document.getElementById('galleryGrid');
        var countEl = document.getElementById('gallerySelectedCount');
        var dlSelectedBtn = document.getElementById('galleryDownloadSelected');
        var selectAllEl = document.getElementById('gallerySelectAll');
        if (!gridEl) return;
        var boxes = gridEl.querySelectorAll('.gallery-select-checkbox');
        var checked = gridEl.querySelectorAll('.gallery-select-checkbox:checked');
        if (countEl) countEl.textContent = checked.length;
        if (dlSelectedBtn) dlSelectedBtn.disabled = checked.length === 0;
        if (selectAllEl) {
            selectAllEl.checked = boxes.length > 0 && checked.length === boxes.length;
            selectAllEl.indeterminate = checked.length > 0 && checked.length < boxes.length;
        }
        boxes.forEach(function(cb) {
            var item = cb.closest('.gallery-item');
            if (item) item.classList.toggle('is-selected', cb.checked);
        });
    }

    function galleryDownload(ids) {
        if (!currentGalleryEventId) return;
        var url = '<?= BASE_URL ?>/backend/auth/download_event_photos.php?event_id=' + encodeURIComponent(currentGalleryEventId);
        if (ids && ids.length) {
            url += '&ids=' + encodeURIComponent(ids.join(','));
        }
        window.location.href = url;
    }

    if (galleryModal) {
        galleryModal.addEventListener('show.bs.modal', function(e) {
            var btn = e.relatedTarget;
            var titleEl = document.getElementById('galleryTitleText');
            var gridEl = document.getElementById('galleryGrid');
            var emptyEl = document.getElementById('galleryEmpty');
            var toolbarEl = document.getElementById('galleryToolbar');
            var selectAllEl = document.getElementById('gallerySelectAll');
            if (!gridEl || !emptyEl) return;

            gridEl.innerHTML = '';
            emptyEl.style.display = 'none';
            if (toolbarEl) toolbarEl.style.display = 'none';
            if (selectAllEl) { selectAllEl.checked = false; selectAllEl.indeterminate = false; }
            currentGalleryEventId = null;

            if (btn && btn.dataset.eventId) {
                var eventId = btn.dataset.eventId;
                var eventTitle = btn.dataset.eventTitle || '';
                currentGalleryEventId = eventId;
                if (titleEl) {
                    titleEl.textContent = 'Photos for: ' + eventTitle;
                }

                var photos = (window.photosByEvent && window.photosByEvent[eventId]) || [];
                if (!photos.length) {
                    emptyEl.style.display = 'block';
                    return;
                }

                if (toolbarEl) toolbarEl.style.display = 'flex';

                photos.forEach(function(photo, index) {
                    var wrapper = document.createElement('div');
                    wrapper.className = 'gallery-item';

                    var img = document.createElement('img');
                    img.src = '<?= BASE_URL ?>/' + photo.file_path;
                    img.alt = 'Event photo';
                    img.className = 'gallery-photo';
                    img.style.cursor = 'pointer';
                    img.onerror = function() {
                        this.onerror = null;
                        this.classList.add('gallery-photo--broken');
                        // Inline placeholder so the thumbnail keeps its size and the
                        // delete/select controls stay reachable for moderators.
                        this.src = 'data:image/svg+xml;charset=UTF-8,'
                            + encodeURIComponent(
                                '<svg xmlns="http://www.w3.org/2000/svg" width="110" height="110">'
                                + '<rect width="110" height="110" fill="#f1f5f9"/>'
                                + '<text x="55" y="50" font-family="sans-serif" font-size="11" fill="#94a3b8" text-anchor="middle">Image</text>'
                                + '<text x="55" y="66" font-family="sans-serif" font-size="11" fill="#94a3b8" text-anchor="middle">unavailable</text>'
                                + '</svg>'
                            );
                    };
                    img.onclick = function() {
                        openPhotoViewer(eventId, index);
                    };

                    wrapper.appendChild(img);

                    if (photo.caption || photo.credit_line) {
                        var meta = document.createElement('div');
                        meta.className = 'gallery-item__meta';
                        if (photo.caption) {
                            var cap = document.createElement('div');
                            cap.innerHTML = '<strong>Caption:</strong> ';
                            cap.appendChild(document.createTextNode(photo.caption));
                            meta.appendChild(cap);
                        }
                        if (photo.credit_line) {
                            var cred = document.createElement('div');
                            cred.innerHTML = '<strong>Credit:</strong> ';
                            cred.appendChild(document.createTextNode(photo.credit_line));
                            meta.appendChild(cred);
                        }
                        wrapper.appendChild(meta);
                    }

                    var selectLabel = document.createElement('label');
                    selectLabel.className = 'gallery-select';
                    selectLabel.title = 'Select photo';
                    selectLabel.onclick = function(ev) { ev.stopPropagation(); };
                    var selectBox = document.createElement('input');
                    selectBox.type = 'checkbox';
                    selectBox.className = 'gallery-select-checkbox';
                    selectBox.value = photo.id;
                    selectBox.onchange = galleryUpdateSelection;
                    selectLabel.appendChild(selectBox);
                    wrapper.appendChild(selectLabel);
                    var currentUid = parseInt(window.currentMultimediaUserId || '0', 10) || 0;
                    var photoOwnerId = parseInt(photo.uploaded_by || '0', 10) || 0;
                    var isOwnPhoto = currentUid > 0 && photoOwnerId === currentUid;
                    var isModerator = window.isMultimediaModerator === true;
                    var canDeletePhoto = isOwnPhoto || isModerator;
                    if (canDeletePhoto) {
                        // A moderator deleting someone else's photo must supply a reason.
                        var needsReason = isModerator && !isOwnPhoto;

                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '<?= BASE_URL ?>/backend/auth/delete_event_photo.php';
                        form.className = 'delete-photo-form';
                        form.onclick = function(ev) {
                            ev.stopPropagation();
                        };

                        var inputId = document.createElement('input');
                        inputId.type = 'hidden';
                        inputId.name = 'photo_id';
                        inputId.value = photo.id;

                        var inputEvent = document.createElement('input');
                        inputEvent.type = 'hidden';
                        inputEvent.name = 'event_id';
                        inputEvent.value = eventId;

                        var inputReason = document.createElement('input');
                        inputReason.type = 'hidden';
                        inputReason.name = 'reason';
                        inputReason.value = '';

                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'btn-delete-photo';
                        btn.innerHTML = '<i class="fas fa-trash-alt"></i>';
                        if (needsReason) {
                            btn.classList.add('btn-delete-photo--moderator');
                            btn.title = 'Delete (moderator) â€” reason required';
                        }
                        btn.onclick = function (ev) {
                            ev.stopPropagation();
                            window.pendingDeletePhotoForm = form;
                            window.pendingDeleteReasonInput = inputReason;
                            window.pendingDeleteNeedsReason = needsReason;
                            openDeletePhotoModal(needsReason);
                        };

                        var csrfEl = document.getElementById('csrf_token_value');
                        form.appendChild(inputId);
                        form.appendChild(inputEvent);
                        form.appendChild(inputReason);
                        if (csrfEl) {
                            var inputCsrf = document.createElement('input');
                            inputCsrf.type = 'hidden';
                            inputCsrf.name = 'csrf_token';
                            inputCsrf.value = csrfEl.value;
                            form.appendChild(inputCsrf);
                        }
                        form.appendChild(btn);
                        wrapper.appendChild(form);
                    }
                    gridEl.appendChild(wrapper);
                });

                galleryUpdateSelection();
            }
        });

        var selectAllEl = document.getElementById('gallerySelectAll');
        if (selectAllEl) {
            selectAllEl.addEventListener('change', function() {
                var gridEl = document.getElementById('galleryGrid');
                if (!gridEl) return;
                gridEl.querySelectorAll('.gallery-select-checkbox').forEach(function(cb) {
                    cb.checked = selectAllEl.checked;
                });
                galleryUpdateSelection();
            });
        }

        var dlAllBtn = document.getElementById('galleryDownloadAll');
        if (dlAllBtn) {
            dlAllBtn.addEventListener('click', function() {
                galleryDownload(null);
            });
        }

        var dlSelectedBtn = document.getElementById('galleryDownloadSelected');
        if (dlSelectedBtn) {
            dlSelectedBtn.addEventListener('click', function() {
                var gridEl = document.getElementById('galleryGrid');
                if (!gridEl) return;
                var ids = [];
                gridEl.querySelectorAll('.gallery-select-checkbox:checked').forEach(function(cb) {
                    ids.push(cb.value);
                });
                if (ids.length) galleryDownload(ids);
            });
        }
    }

    window.openDeletePhotoModal = function(needsReason) {
        var reasonWrap = document.getElementById('deletePhotoReasonWrap');
        var reasonInput = document.getElementById('deletePhotoReason');
        var reasonError = document.getElementById('deletePhotoReasonError');
        if (reasonError) reasonError.style.display = 'none';
        if (reasonInput) reasonInput.value = '';
        if (reasonWrap) reasonWrap.style.display = needsReason ? 'block' : 'none';
        var modalEl = document.getElementById('deletePhotoModal');
        if (!modalEl) return;
        var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.show();
        if (needsReason && reasonInput) {
            setTimeout(function() { reasonInput.focus(); }, 300);
        }
    };

    var deleteConfirmBtn = document.getElementById('deletePhotoConfirmBtn');
    if (deleteConfirmBtn) {
        deleteConfirmBtn.addEventListener('click', function() {
            if (!window.pendingDeletePhotoForm) return;
            var reasonWrap = document.getElementById('deletePhotoReasonWrap');
            var reasonInput = document.getElementById('deletePhotoReason');
            var reasonError = document.getElementById('deletePhotoReasonError');

            if (window.pendingDeleteNeedsReason) {
                var reasonVal = (reasonInput && reasonInput.value ? reasonInput.value : '').trim();
                if (reasonVal === '') {
                    if (reasonError) reasonError.style.display = 'block';
                    if (reasonInput) reasonInput.focus();
                    return;
                }
                if (window.pendingDeleteReasonInput) {
                    window.pendingDeleteReasonInput.value = reasonVal;
                }
            }

            var modal = bootstrap.Modal.getInstance(document.getElementById('deletePhotoModal'));
            if (modal) modal.hide();
            window.pendingDeletePhotoForm.submit();
            window.pendingDeletePhotoForm = null;
            window.pendingDeleteReasonInput = null;
            window.pendingDeleteNeedsReason = false;
        });
    }

    // Photo viewer (Facebook-style)
    var currentEventId = null;
    var currentPhotoIndex = 0;
    var currentPhotos = [];

    window.openPhotoViewer = function(eventId, photoIndex) {
        currentEventId = eventId;
        currentPhotoIndex = parseInt(photoIndex) || 0;
        currentPhotos = (window.photosByEvent && window.photosByEvent[eventId]) || [];
        
        if (!currentPhotos.length) return;
        
        var viewer = document.getElementById('photoViewer');
        if (viewer) {
            viewer.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            updateViewerImage();
        }
    };

    window.closePhotoViewer = function() {
        var viewer = document.getElementById('photoViewer');
        if (viewer) {
            viewer.style.display = 'none';
            document.body.style.overflow = '';
        }
    };

    window.navigatePhoto = function(direction) {
        if (!currentPhotos.length) return;
        currentPhotoIndex += direction;
        if (currentPhotoIndex < 0) currentPhotoIndex = currentPhotos.length - 1;
        if (currentPhotoIndex >= currentPhotos.length) currentPhotoIndex = 0;
        updateViewerImage();
    };

    function updateViewerImage() {
        if (!currentPhotos.length || currentPhotoIndex < 0 || currentPhotoIndex >= currentPhotos.length) return;
        var photo = currentPhotos[currentPhotoIndex];
        var img = document.getElementById('viewerImage');
        var countEl = document.getElementById('viewerPhotoCount');
        
        if (img) {
            img.src = '<?= BASE_URL ?>/' + photo.file_path;
        }
        if (countEl) {
            countEl.textContent = (currentPhotoIndex + 1) + ' / ' + currentPhotos.length;
        }
    }

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        var viewer = document.getElementById('photoViewer');
        if (viewer && viewer.style.display === 'flex') {
            if (e.key === 'Escape') {
                closePhotoViewer();
            } else if (e.key === 'ArrowLeft') {
                navigatePhoto(-1);
            } else if (e.key === 'ArrowRight') {
                navigatePhoto(1);
            }
        }
    });
});
</script>
</body>
</html>
