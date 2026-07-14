<?php
/**
 * Multimedia photo status for Activities hub (drawer sidebar or main hub card).
 *
 * @var array{my_pending: int, my_published: int, my_rejected: int, team_pending: int} $mmPhotoSummary
 * @var string $mm_photo_status_context drawer|hub
 * @var bool $isMultimediaModerator
 * @var bool $photoStatusEnabled
 */
$mmPhotoSummary = $mmPhotoSummary ?? [
    'my_pending' => 0,
    'my_published' => 0,
    'my_rejected' => 0,
    'team_pending' => 0,
];
$mm_photo_status_context = $mm_photo_status_context ?? 'hub';
$isMultimediaModerator = !empty($isMultimediaModerator);
$photoStatusEnabled = !empty($photoStatusEnabled);

if (!$photoStatusEnabled) {
    return;
}

$myPending = (int) ($mmPhotoSummary['my_pending'] ?? 0);
$myPublished = (int) ($mmPhotoSummary['my_published'] ?? 0);
$myRejected = (int) ($mmPhotoSummary['my_rejected'] ?? 0);
$teamPending = (int) ($mmPhotoSummary['team_pending'] ?? 0);
$isDrawer = $mm_photo_status_context === 'drawer';
$panelClass = $isDrawer ? 'eah-mm-photo-status eah-mm-photo-status--drawer' : 'eah-mm-photo-status eah-mm-photo-status--hub';
?>
<div class="<?= htmlspecialchars($panelClass) ?>">
    <div class="eah-mm-photo-status__head">
        <i class="fas fa-camera" aria-hidden="true"></i>
        <span><?= $isMultimediaModerator ? 'Photo status' : 'Your photos' ?></span>
    </div>

    <div class="eah-mm-photo-status__grid">
        <div class="eah-mm-photo-status__stat<?= $myPending > 0 ? ' is-highlight' : '' ?>">
            <span class="eah-mm-photo-status__value"><?= $myPending ?></span>
            <span class="eah-mm-photo-status__label">Pending</span>
        </div>
        <div class="eah-mm-photo-status__stat">
            <span class="eah-mm-photo-status__value"><?= $myPublished ?></span>
            <span class="eah-mm-photo-status__label">Approved</span>
        </div>
        <div class="eah-mm-photo-status__stat<?= $myRejected > 0 ? ' is-warn' : '' ?>">
            <span class="eah-mm-photo-status__value"><?= $myRejected ?></span>
            <span class="eah-mm-photo-status__label">Rejected</span>
        </div>
    </div>

    <?php if ($isMultimediaModerator && $teamPending > 0): ?>
        <a class="eah-mm-photo-status__action" href="<?= htmlspecialchars(BASE_URL . '/backend/auth/dashboard_multimedia.php?panel=photo_approvals') ?>">
            <i class="fas fa-user-shield me-1"></i>
            Review <?= $teamPending ?> team photo<?= $teamPending === 1 ? '' : 's' ?>
        </a>
    <?php endif; ?>
    <?php if ($isMultimediaModerator): ?>
        <a class="eah-mm-photo-status__action eah-mm-photo-status__action--secondary" href="<?= htmlspecialchars(BASE_URL . '/backend/auth/dashboard_multimedia.php?panel=photo_activity') ?>">
            <i class="fas fa-clipboard-list me-1"></i>
            Photo activity log
        </a>
    <?php endif; ?>

    <p class="eah-mm-photo-status__note">
        <?php if ($isMultimediaModerator): ?>
            Open an activity to upload or review photos. Students only see approved photos.
        <?php elseif ($myPending > 0): ?>
            <?= $myPending ?> photo<?= $myPending === 1 ? '' : 's' ?> waiting for your moderator. Activities with pending uploads are marked in the list.
        <?php else: ?>
            Upload inside each activity. Your moderator approves before students can see them.
        <?php endif; ?>
    </p>
</div>
