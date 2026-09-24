<?php
/**
 * Optional photo button for one student check-in record.
 *
 * @var string $kind event|activity
 * @var int $recordId
 * @var string|null $photoPath
 * @var string $returnTo dashboard|history
 * @var string $filter all|events|activities
 */
if (!function_exists('eventify_attendance_proof_public_url')) {
    return;
}

$kind = ($kind ?? '') === 'activity' ? 'activity' : 'event';
$recordId = (int) ($recordId ?? 0);
$photoPath = (string) ($photoPath ?? '');
$returnTo = ($returnTo ?? '') === 'history' ? 'history' : 'dashboard';
$filter = (string) ($filter ?? 'all');
if (!in_array($filter, ['all', 'events', 'activities'], true)) {
    $filter = 'all';
}
if ($recordId < 1 || !function_exists('csrf_field')) {
    return;
}

$photoUrl = eventify_attendance_proof_public_url($photoPath);
$uploadAction = (defined('BASE_URL') ? rtrim((string) BASE_URL, '/') : '') . '/backend/auth/upload_attendance_proof.php';
$hasPhoto = $photoUrl !== '';
$inputId = 'attProofFile_' . $kind . '_' . $recordId;
?>
<div class="att-proof<?= $hasPhoto ? ' att-proof--has-photo' : '' ?>">
    <?php if ($hasPhoto): ?>
        <button type="button" class="att-proof__preview" data-attendance-proof-view="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="View your attendance photo">
            <img src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Your attendance photo">
        </button>
    <?php endif; ?>
    <form class="att-proof__form" method="post" action="<?= htmlspecialchars($uploadAction, ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="kind" value="<?= htmlspecialchars($kind, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="record_id" value="<?= $recordId ?>">
        <input type="hidden" name="return" value="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>">
        <label class="att-proof__label" for="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>">
            <i class="fas fa-image" aria-hidden="true"></i>
            <?= $hasPhoto ? 'Change photo' : 'Add photo' ?>
        </label>
        <input type="file" name="photo" id="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>" class="att-proof__input" required>
        <button type="submit" class="att-proof__submit">Upload</button>
    </form>
</div>
