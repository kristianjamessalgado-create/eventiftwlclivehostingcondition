<?php
/** @var bool $mm_photo_approvals_panel_open */
/** @var list<array<string, mixed>> $pending_photos_queue */

$mm_photo_approvals_panel_open = !empty($mm_photo_approvals_panel_open);
$pending_photos_queue = is_array($pending_photos_queue ?? null) ? $pending_photos_queue : [];
$pending_photo_count = (int) ($pending_photo_count ?? count($pending_photos_queue));
$panelEnterClass = $mm_photo_approvals_panel_open ? ' mm-dash-panel--enter' : '';
?>

<section
    class="mm-dash-panel mm-photo-approvals-panel<?= $panelEnterClass ?><?= $mm_photo_approvals_panel_open ? '' : ' d-none' ?>"
    id="mmPhotoApprovalsPanel"
    aria-label="Photo approvals"
    <?= $mm_photo_approvals_panel_open ? '' : ' hidden' ?>
>
    <div class="mm-dash-panel__shell">
        <div class="mm-dash-panel__toolbar">
            <button type="button" class="mm-dash-panel__back" data-mm-panel="home">
                <span class="mm-dash-panel__back-icon" aria-hidden="true"><i class="fas fa-arrow-left"></i></span>
                <span class="mm-dash-panel__back-label">Back to calendar</span>
            </button>
            <?php if ($pending_photo_count > 0): ?>
                <span class="mm-dash-panel__count-pill">
                    <i class="fas fa-user-shield" aria-hidden="true"></i>
                    <?= $pending_photo_count ?> pending
                </span>
            <?php endif; ?>
        </div>

        <header class="mm-dash-panel__hero">
            <div class="mm-dash-panel__hero-icon" aria-hidden="true"><i class="fas fa-user-shield"></i></div>
            <div class="mm-dash-panel__hero-text">
                <h2 class="mm-dash-panel__title">Photo approvals</h2>
                <p class="mm-dash-panel__subtitle mb-0">
                    Review uploads from your multimedia team. Approved photos appear for students; rejected photos stay hidden with a reason for the uploader.
                </p>
            </div>
        </header>

        <?php if ($pending_photos_queue === []): ?>
            <div class="mm-upcoming-empty">
                <i class="fas fa-check-circle"></i>
                <div>
                    <div class="fw-semibold">No photos waiting for approval</div>
                    <div class="text-muted small">New team uploads will appear here for review.</div>
                </div>
            </div>
        <?php else: ?>
            <form id="mmPhotoBulkModerateForm" method="POST" action="<?= BASE_URL ?>/backend/auth/moderate_event_photos_bulk.php" class="d-none" aria-hidden="true">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="">
                <input type="hidden" name="reject_reason" value="">
                <div id="mmPhotoBulkIdsHost"></div>
            </form>

            <div class="mm-photo-bulk-toolbar">
                <label class="mm-photo-bulk-toolbar__select">
                    <input type="checkbox" id="mmPhotoSelectAll" aria-label="Select all pending photos">
                    <span>Select all</span>
                </label>
                <div class="mm-photo-bulk-toolbar__actions">
                    <button type="button" class="btn btn-success btn-sm js-mm-photo-bulk-btn" id="mmPhotoBulkApprove" disabled>
                        <i class="fas fa-check me-1"></i> Approve selected (<span class="js-mm-photo-bulk-count">0</span>)
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm js-mm-photo-bulk-btn" id="mmPhotoBulkReject" disabled>
                        <i class="fas fa-times me-1"></i> Reject selected (<span class="js-mm-photo-bulk-count">0</span>)
                    </button>
                </div>
            </div>

            <div class="list-group mm-photo-approvals-list">
                <?php foreach ($pending_photos_queue as $ph): ?>
                    <?php
                        $phId = (int) ($ph['id'] ?? 0);
                        $phEventId = (int) ($ph['event_id'] ?? 0);
                        $phSessionId = (int) ($ph['session_id'] ?? 0);
                        $phPath = (string) ($ph['file_path'] ?? '');
                        $phEventTitle = (string) ($ph['event_title'] ?? 'Event');
                        $phSessionTitle = trim((string) ($ph['session_title'] ?? ''));
                        $phUploader = (string) ($ph['uploader_name'] ?? 'Multimedia');
                        $phCaption = trim((string) ($ph['caption'] ?? ''));
                        $phCredit = trim((string) ($ph['credit_line'] ?? ''));
                        $phCreated = !empty($ph['created_at']) ? date('M j, Y g:i A', strtotime((string) $ph['created_at'])) : '';
                        $phImgUrl = BASE_URL . '/' . ltrim($phPath, '/');
                        $activityHref = $phSessionId > 0
                            ? BASE_URL . '/event_activities.php?id=' . $phEventId . '&activity=' . $phSessionId
                            : '';
                        $phLabel = $phSessionTitle !== ''
                            ? $phEventTitle . ' · ' . $phSessionTitle
                            : $phEventTitle;
                    ?>
                    <div class="list-group-item mm-photo-approval-item">
                        <div class="d-flex w-100 justify-content-between align-items-start gap-3">
                            <div class="mm-photo-approval-item__select">
                                <input type="checkbox" class="form-check-input js-mm-photo-select" value="<?= $phId ?>" aria-label="Select photo for <?= htmlspecialchars($phLabel) ?>">
                            </div>
                            <div class="d-flex gap-3 flex-grow-1 min-w-0">
                                <a href="<?= htmlspecialchars($phImgUrl) ?>" target="_blank" rel="noopener" class="mm-approval-list__thumb flex-shrink-0" title="Open photo">
                                    <img src="<?= htmlspecialchars($phImgUrl) ?>" alt="Pending photo" loading="lazy" decoding="async">
                                </a>
                                <div class="min-w-0">
                                    <h6 class="mb-1">
                                        <i class="fas fa-image me-1 text-primary"></i>
                                        <?= htmlspecialchars($phEventTitle) ?>
                                    </h6>
                                    <div class="small text-muted">
                                        <?php if ($phSessionTitle !== ''): ?>
                                            <i class="fas fa-bolt me-1"></i><?= htmlspecialchars($phSessionTitle) ?>
                                            ·
                                        <?php endif; ?>
                                        <i class="fas fa-user me-1"></i><?= htmlspecialchars($phUploader) ?>
                                        <?php if ($phCreated !== ''): ?>
                                            · <?= htmlspecialchars($phCreated) ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($phCaption !== ''): ?>
                                        <div class="small mt-1"><strong>Caption:</strong> <?= htmlspecialchars($phCaption) ?></div>
                                    <?php endif; ?>
                                    <?php if ($phCredit !== ''): ?>
                                        <div class="small text-muted"><strong>Credit:</strong> <?= htmlspecialchars($phCredit) ?></div>
                                    <?php endif; ?>
                                    <?php if ($activityHref !== ''): ?>
                                        <a href="<?= htmlspecialchars($activityHref) ?>" class="small text-decoration-none">View activity</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <span class="badge bg-warning text-dark mb-2">Pending</span>
                                <div class="d-flex flex-wrap gap-1 justify-content-end">
                                    <form method="POST" action="<?= BASE_URL ?>/backend/auth/moderate_event_photo.php" class="d-inline js-photo-moderate-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="photo_id" value="<?= $phId ?>">
                                        <input type="hidden" name="event_id" value="<?= $phEventId ?>">
                                        <input type="hidden" name="session_id" value="<?= $phSessionId ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="button" class="btn btn-success btn-sm js-photo-moderate-trigger" data-action="approve" data-photo-label="<?= htmlspecialchars($phLabel, ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fas fa-check me-1"></i>Approve
                                        </button>
                                    </form>
                                    <form method="POST" action="<?= BASE_URL ?>/backend/auth/moderate_event_photo.php" class="d-inline js-photo-moderate-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="photo_id" value="<?= $phId ?>">
                                        <input type="hidden" name="event_id" value="<?= $phEventId ?>">
                                        <input type="hidden" name="session_id" value="<?= $phSessionId ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="button" class="btn btn-outline-danger btn-sm js-photo-moderate-trigger" data-action="reject" data-photo-label="<?= htmlspecialchars($phLabel, ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fas fa-times me-1"></i>Reject
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
