<?php
/**
 * "Add a photo proof" prompt shown right after a successful QR check-in.
 * Opens the in-app camera (assets/js/attendance_camera.js) and saves the stamped
 * photo to this check-in via backend/auth/upload_attendance_proof.php.
 *
 * @var string      $proofKind      event|activity
 * @var int         $proofRecordId  registrations.id or event_day_session_attendance.id
 * @var string|null $proofPhotoPath existing proof photo (if any)
 * @var string      $proofTitle     event / activity name for the stamp
 */
$proofKind = ($proofKind ?? '') === 'activity' ? 'activity' : 'event';
$proofRecordId = (int) ($proofRecordId ?? 0);
$proofTitle = trim((string) ($proofTitle ?? ''));
if ($proofRecordId < 1 || !function_exists('csrf_field')) {
    return;
}
$proofPhotoUrl = function_exists('eventify_attendance_proof_public_url')
    ? eventify_attendance_proof_public_url((string) ($proofPhotoPath ?? ''))
    : '';
$proofHasPhoto = $proofPhotoUrl !== '';
$proofAction = rtrim((string) BASE_URL, '/') . '/backend/auth/upload_attendance_proof.php';
?>
<section class="checkin-photo-prompt<?= $proofHasPhoto ? ' checkin-photo-prompt--done' : '' ?>" id="checkinPhotoPrompt" aria-label="Attendance photo proof">
    <?php if ($proofHasPhoto): ?>
        <div class="checkin-photo-prompt__head">
            <img class="checkin-photo-prompt__thumb" src="<?= htmlspecialchars($proofPhotoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Your attendance photo">
            <div>
                <p class="checkin-photo-prompt__title"><i class="fas fa-circle-check" aria-hidden="true"></i> Photo proof added</p>
                <p class="checkin-photo-prompt__text">You can retake it if you need to.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="checkin-photo-prompt__head">
            <span class="checkin-photo-prompt__icon" aria-hidden="true"><i class="fas fa-camera"></i></span>
            <div>
                <p class="checkin-photo-prompt__title">Add a photo proof</p>
                <p class="checkin-photo-prompt__text">Take a quick photo at the venue. It gets the EVENTIFY time &amp; location stamp and is saved to this check-in.</p>
            </div>
        </div>
    <?php endif; ?>
    <form class="att-proof__form checkin-photo-prompt__form" method="post" action="<?= htmlspecialchars($proofAction, ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" data-proof-title="<?= htmlspecialchars($proofTitle, ENT_QUOTES, 'UTF-8') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="kind" value="<?= htmlspecialchars($proofKind, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="record_id" value="<?= $proofRecordId ?>">
        <input type="hidden" name="return" value="history">
        <input type="hidden" name="filter" value="all">
        <button type="button" class="checkin-photo-prompt__take" data-att-proof-camera>
            <i class="fas fa-camera" aria-hidden="true"></i>
            <?= $proofHasPhoto ? 'Retake photo' : 'Take photo now' ?>
        </button>
        <?php if (!$proofHasPhoto): ?>
            <button type="button" class="checkin-photo-prompt__skip" data-checkin-photo-skip>Skip for now</button>
        <?php endif; ?>
    </form>
    <?php if (!$proofHasPhoto): ?>
        <p class="checkin-photo-prompt__later">You can also add it later in <strong>Check-in history</strong>.</p>
    <?php endif; ?>
</section>
<script>
(function () {
    var prompt = document.getElementById('checkinPhotoPrompt');
    var skip = prompt ? prompt.querySelector('[data-checkin-photo-skip]') : null;
    if (skip) {
        skip.addEventListener('click', function () {
            prompt.hidden = true;
        });
    }
})();
</script>
