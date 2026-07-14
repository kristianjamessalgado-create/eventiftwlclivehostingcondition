<?php
/** @var bool $mm_events_panel_open */
/** @var list<array<string, mixed>> $events */

$mm_events_panel_open = !empty($mm_events_panel_open);
$events = is_array($events ?? null) ? $events : [];
$mm_events_count = isset($mm_events_count) ? (int) $mm_events_count : count($events);
$panelEnterClass = $mm_events_panel_open ? ' mm-dash-panel--enter' : '';

require_once __DIR__ . '/../../backend/lib/event_ticketing.php';
?>

<section
    class="mm-dash-panel mm-all-events-panel<?= $panelEnterClass ?><?= $mm_events_panel_open ? '' : ' d-none' ?>"
    id="mmAllEventsPanel"
    aria-label="All events"
    <?= $mm_events_panel_open ? '' : ' hidden' ?>
>
    <div class="mm-dash-panel__shell">
        <div class="mm-dash-panel__toolbar">
            <button type="button" class="mm-dash-panel__back" data-mm-panel="home">
                <span class="mm-dash-panel__back-icon" aria-hidden="true"><i class="fas fa-arrow-left"></i></span>
                <span class="mm-dash-panel__back-label">Back to calendar</span>
            </button>
            <?php if ($mm_events_count > 0): ?>
                <span class="mm-dash-panel__count-pill">
                    <i class="fas fa-images" aria-hidden="true"></i>
                    <?= $mm_events_count ?> event<?= $mm_events_count === 1 ? '' : 's' ?>
                </span>
            <?php endif; ?>
        </div>

        <header class="mm-dash-panel__hero">
            <div class="mm-dash-panel__hero-icon" aria-hidden="true"><i class="fas fa-images"></i></div>
            <div class="mm-dash-panel__hero-text">
                <h2 class="mm-dash-panel__title">All events</h2>
                <p class="mm-dash-panel__subtitle mb-0">
                    Approved events only — upload and manage photo galleries for active and past events.
                </p>
            </div>
        </header>

        <?php if (!$photoStatusEnabled): ?>
            <div class="alert alert-warning mm-migration-hint d-flex align-items-start gap-2 mb-3" role="status">
                <i class="fas fa-database mt-1 flex-shrink-0"></i>
                <div class="small">
                    <strong>Photo moderation is unavailable until the database is updated.</strong>
                    Run <code>migrations/event_photos_publish_columns.sql</code>, then refresh this page.
                </div>
            </div>
        <?php elseif (!$is_multimedia_moderator): ?>
            <div class="alert alert-info mm-migration-hint d-flex align-items-start gap-2 mb-3" role="status">
                <i class="fas fa-hourglass-half mt-1 flex-shrink-0"></i>
                <div class="small">
                    Uploads stay <strong>pending</strong> until your multimedia moderator approves them.
                </div>
            </div>
        <?php endif; ?>

        <div class="mm-panel-search-row">
            <div class="search-wrap">
                <i class="fas fa-search"></i>
                <input id="eventSearchInput" type="search" class="search-input" placeholder="Search events by title or location…" autocomplete="off">
            </div>
        </div>

        <?php
            $mmStatusChips = [
                'active' => 'Active',
                'closed' => 'Past / closed',
            ];
        ?>
        <div class="mm-status-toolbar">
            <div class="mm-status-filter" id="mmStatusFilter">
                <div class="mm-status-filter__head">
                    <span class="mm-status-filter__label">Show status</span>
                    <span class="mm-status-filter__hint">Tap to show or hide</span>
                </div>
                <div class="mm-status-filter__chips" role="group" aria-label="Filter events by status">
                    <?php foreach ($mmStatusChips as $st => $label): ?>
                        <?php $chipOn = in_array($st, ['active', 'closed'], true); ?>
                        <button type="button"
                                class="mm-status-chip mm-status-chip--<?= htmlspecialchars($st) ?><?= $chipOn ? ' is-selected' : '' ?>"
                                data-status-filter="<?= htmlspecialchars($st) ?>"
                                aria-pressed="<?= $chipOn ? 'true' : 'false' ?>">
                            <?= htmlspecialchars($label) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="mm-status-filter__quick">
                    <button type="button" class="mm-status-filter__quick-btn" data-mm-filter-all>Select all</button>
                    <span class="mm-status-filter__quick-sep" aria-hidden="true">·</span>
                    <button type="button" class="mm-status-filter__quick-btn" data-mm-filter-none>Clear all</button>
                </div>
            </div>
            <div class="mm-status-toolbar__divider" aria-hidden="true"></div>
            <div class="mm-coverage-filter" id="mmCoverageFilter">
                <div class="mm-status-filter__head">
                    <span class="mm-status-filter__label">My coverage</span>
                    <span class="mm-status-filter__hint">Filter by your uploads</span>
                </div>
                <div class="mm-status-filter__chips" role="group" aria-label="Filter events by coverage">
                    <button type="button" class="mm-coverage-chip is-selected" data-coverage-filter="all" aria-pressed="true">All events</button>
                    <button type="button" class="mm-coverage-chip" data-coverage-filter="my-uploads" aria-pressed="false">My uploads</button>
                    <button type="button" class="mm-coverage-chip" data-coverage-filter="pending" aria-pressed="false">
                        <?= $is_multimedia_moderator ? 'Team pending' : 'My pending' ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="mm-events-list-host eventify-filter-loading-host" id="mmEventsListHost">
            <div class="eventify-filter-loading" id="mmEventsFilterLoading" hidden aria-hidden="true">
                <div class="eventify-spinner" role="status" aria-live="polite">
                    <span class="eventify-spinner__sr">Updating filters</span>
                </div>
                <p class="eventify-filter-loading__text">Updating filters…</p>
            </div>
        <div class="events-list" id="eventsList">
            <?php if (empty($events)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <p>No events yet. Events will appear here when organizers create them.</p>
                </div>
            <?php else: ?>
                <?php foreach ($events as $ev): ?>
                    <?php
                        $eid = (int)($ev['id'] ?? 0);
                        $title = (string)($ev['title'] ?? '');
                        $location = (string)($ev['location'] ?? '');
                        $myCount = (int)($ev['my_photo_count'] ?? 0);
                        $myDraftCount = (int)($ev['my_draft_count'] ?? 0);
                        $pendingDraftCount = (int)($ev['pending_draft_count'] ?? 0);
                        $publishedCount = (int)($ev['photo_count'] ?? 0);
                        $totalPhotoCount = (int)($ev['total_photo_count'] ?? $publishedCount);
                        $hasPublished = $publishedCount > 0;
                        $galleryReadyTitle = $hasPublished
                            ? 'Open student gallery preview (published photos only)'
                            : 'Publish photos before sharing the gallery QR or preview';
                        $canModeratePublish = $photoStatusEnabled && $is_multimedia_moderator && $pendingDraftCount > 0;
                        $showPendingNotice = $photoStatusEnabled && !$is_multimedia_moderator && $myDraftCount > 0;
                        if (!$photoStatusEnabled) {
                            $publishHelpTitle = 'Photo moderation needs the status column. Run migrations/event_photos_publish_columns.sql on your database, then refresh this page.';
                        } elseif ($is_multimedia_moderator && $pendingDraftCount <= 0) {
                            $publishHelpTitle = 'No pending photos for this event.';
                        } elseif ($is_multimedia_moderator) {
                            $publishHelpTitle = 'Approve all pending photos for this event';
                        } elseif ($myDraftCount <= 0) {
                            $publishHelpTitle = 'No pending photos from you for this event.';
                        } else {
                            $publishHelpTitle = 'Your photos are waiting for moderator approval';
                        }
                        $previewPath = null;
                        if (!empty($photosByEvent) && isset($photosByEvent[$eid]) && !empty($photosByEvent[$eid][0]['file_path'])) {
                            $previewPath = $photosByEvent[$eid][0]['file_path'];
                        }
                        $eventActivities = $eventActivitiesByEvent[$eid] ?? [];
                        $sessionPhotoStats = $eventSessionPhotoStats[$eid] ?? [];
                    ?>
                    <div class="event-card<?= $eventActivities !== [] ? ' event-card--has-activities' : '' ?>"
                         id="mm-event-<?= $eid ?>"
                         data-event-id="<?= $eid ?>"
                         data-title="<?= htmlspecialchars($title) ?>"
                         data-location="<?= htmlspecialchars($location) ?>"
                         data-status="<?= htmlspecialchars(strtolower(trim((string)($ev['status'] ?? '')))) ?>"
                         data-my-photos="<?= $myCount ?>"
                         data-my-pending="<?= $myDraftCount ?>"
                         data-team-pending="<?= $pendingDraftCount ?>"
                         data-published="<?= $publishedCount ?>"
                         data-activity-count="<?= count($eventActivities) ?>"
                         <?php if ($eventActivities !== []): ?>
                         role="button"
                         tabindex="0"
                         aria-label="Open <?= htmlspecialchars($title) ?> — choose activity to upload photos"
                         <?php endif; ?>>
                        <div class="event-media">
                            <?php if ($previewPath): ?>
                                <img
                                    src="<?= BASE_URL ?>/<?= htmlspecialchars($previewPath) ?>"
                                    alt="Preview photo for <?= htmlspecialchars($title) ?>"
                                    class="event-preview"
                                    loading="lazy"
                                    decoding="async"
                                >
                            <?php else: ?>
                                <div class="event-preview-placeholder" aria-hidden="true">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="event-info">
                            <div class="event-title-row">
                                <h3 class="event-title"><?= htmlspecialchars($title) ?></h3>
                                <?php if (!empty($ev['department'])): ?>
                                    <span class="dept-pill"><?= htmlspecialchars($ev['department']) ?></span>
                                <?php endif; ?>
                            </div>

                            <p class="event-meta">
                                <span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($ev['date'])) ?></span>
                                <span class="dot">·</span>
                                <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($location) ?></span>
                            </p>

                            <div class="event-badges">
                                <?php
                                $regUi = eventify_registration_mode_ui($ev);
                                include __DIR__ . '/registration_mode_badge.php';
                                ?>
                                <span class="photo-badge photo-badge--published" title="Photos visible to students">
                                    <i class="fas fa-check-circle"></i> <?= $publishedCount ?> published
                                </span>
                                <?php if ($photoStatusEnabled && $is_multimedia_moderator && $pendingDraftCount > 0): ?>
                                    <span class="photo-badge photo-badge--pending" title="Pending photos awaiting approval">
                                        <i class="fas fa-user-shield"></i> <?= $pendingDraftCount ?> pending
                                    </span>
                                <?php elseif ($photoStatusEnabled && $myDraftCount > 0): ?>
                                    <span class="photo-badge photo-badge--pending" title="Your photos waiting for moderator approval">
                                        <i class="fas fa-hourglass-half"></i> <?= $myDraftCount ?> pending
                                    </span>
                                <?php elseif (!$photoStatusEnabled && $publishedCount > 0): ?>
                                    <span class="photo-badge" title="Total uploaded photos">
                                        <i class="fas fa-images"></i> <?= $publishedCount ?> photo<?= $publishedCount === 1 ? '' : 's' ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($myCount > 0): ?>
                                    <span class="photo-badge photo-badge--mine" title="Photos you uploaded for this event">
                                        <i class="fas fa-camera"></i> <?= $myCount ?> mine
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($eventActivities !== []): ?>
                                <p class="mm-event-activity-hint">
                                    <i class="fas fa-layer-group" aria-hidden="true"></i>
                                    <?= count($eventActivities) ?> activit<?= count($eventActivities) === 1 ? 'y' : 'ies' ?> — tap to choose where to upload
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="event-actions" onclick="event.stopPropagation();">
                            <button type="button" class="btn btn-upload" onclick="openUploadModal(this); return false;"
                                    data-event-id="<?= $eid ?>"
                                    data-event-title="<?= htmlspecialchars($title) ?>"
                                    data-session-id="0"
                                    title="Upload photos for the main event">
                                <i class="fas fa-cloud-upload-alt"></i> Upload
                            </button>
                            <?php if ($canModeratePublish): ?>
                                <button type="button"
                                        class="btn btn-outline-success mm-publish-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#publishPhotosModal"
                                        data-event-id="<?= $eid ?>"
                                        data-event-title="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
                                        data-draft-count="<?= (int)$pendingDraftCount ?>"
                                        title="<?= htmlspecialchars($publishHelpTitle) ?>">
                                    <i class="fas fa-check-double"></i> Approve all
                                </button>
                            <?php elseif ($showPendingNotice): ?>
                                <span class="btn btn-outline-secondary mm-pending-label disabled" title="<?= htmlspecialchars($publishHelpTitle) ?>">
                                    <i class="fas fa-hourglass-half"></i> Pending
                                </span>
                            <?php endif; ?>
                            <button type="button"
                                    class="btn btn-outline-secondary btn-gallery <?= $totalPhotoCount <= 0 ? 'disabled' : '' ?>"
                                    <?= $totalPhotoCount <= 0 ? 'disabled aria-disabled="true"' : '' ?>
                                    data-bs-toggle="modal"
                                    data-bs-target="#galleryModal"
                                    data-event-id="<?= $eid ?>"
                                    data-event-title="<?= htmlspecialchars($title) ?>"
                                    title="View all uploaded photos (including pending)">
                                <i class="fas fa-folder-open"></i> View
                            </button>
                            <?php if ($hasPublished): ?>
                                <a href="<?= BASE_URL ?>/photo_gallery.php?event_id=<?= $eid ?>&staff_preview=1"
                                   class="btn btn-outline-secondary btn-preview"
                                   target="_blank"
                                   rel="noopener"
                                   title="<?= htmlspecialchars($galleryReadyTitle) ?>">
                                    <i class="fas fa-eye"></i> Preview
                                </a>
                                <a href="<?= BASE_URL ?>/event_photos_qr.php?id=<?= $eid ?>"
                                   class="btn btn-outline-primary btn-gallery-qr"
                                   target="_blank"
                                   rel="noopener"
                                   title="Print gallery QR for students">
                                    <i class="fas fa-qrcode"></i> QR
                                </a>
                            <?php else: ?>
                                <span class="btn btn-outline-secondary btn-preview disabled"
                                      title="<?= htmlspecialchars($galleryReadyTitle) ?>"
                                      tabindex="-1" aria-disabled="true">
                                    <i class="fas fa-eye"></i> Preview
                                </span>
                                <span class="btn btn-outline-primary btn-gallery-qr disabled"
                                      title="<?= htmlspecialchars($galleryReadyTitle) ?>"
                                      tabindex="-1" aria-disabled="true">
                                    <i class="fas fa-qrcode"></i> QR
                                </span>
                            <?php endif; ?>
                            <button type="button"
                                    class="btn btn-outline-danger btn-delete-photos <?= $myCount <= 0 ? 'disabled' : '' ?>"
                                    <?= $myCount <= 0 ? 'disabled aria-disabled="true"' : '' ?>
                                    data-bs-toggle="modal"
                                    data-bs-target="#deleteEventPhotosModal"
                                    data-event-id="<?= $eid ?>"
                                    data-event-title="<?= htmlspecialchars($title) ?>"
                                    data-my-count="<?= (int)$myCount ?>">
                                <i class="fas fa-trash-alt"></i> Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="empty-state" id="mmNoEventResults" style="display:none;">
            <i class="fas fa-filter"></i>
            <p>No events match your search or filter.</p>
        </div>
        </div><!-- #mmEventsListHost -->
    </div>
</section>
