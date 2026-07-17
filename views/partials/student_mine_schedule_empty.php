<?php
/**
 * Empty "My schedule" state for students.
 *
 * Vars: $event, $eventId, $hubUrl, $allSessions, $mainEventRegMode
 */
$mineEmptyEvent = is_array($event ?? null) ? $event : [];
$mineEmptyEventId = (int) ($eventId ?? 0);
$mineEmptyHubUrl = (string) ($hubUrl ?? BASE_URL . '/event_activities.php?id=' . $mineEmptyEventId);
$mineEmptyHasDayActivities = is_array($allSessions ?? null) && count($allSessions) > 0;
$mineEmptyRegMode = (string) ($mainEventRegMode ?? 'rsvp');
$mineEmptyIsOpen = ($mineEmptyRegMode === 'open');
$mineEmptySchedule = function_exists('eventify_format_event_date_range')
    ? eventify_format_event_date_range($mineEmptyEvent, true)
    : '';
$mineEmptyLocation = trim((string) ($mineEmptyEvent['location'] ?? ''));

if (!function_exists('student_mine_empty_h')) {
    function student_mine_empty_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
?>
<div class="eah-student-hub-empty<?= $mineEmptyHasDayActivities ? '' : ' eah-student-hub-empty--mine-main' ?>">
    <div class="eah-student-hub-empty__icon" aria-hidden="true"><i class="fas fa-bookmark"></i></div>
    <?php if (!$mineEmptyHasDayActivities): ?>
        <h3 class="eah-student-hub-empty__title">No day activities to RSVP</h3>
        <p class="eah-student-hub-empty__text">
            <strong>My schedule</strong> is only for day activities you RSVP to (workshops, sessions, and similar).
            This event uses the <strong>main meeting time only</strong> — follow that on <strong>This event</strong>.
        </p>
        <?php if ($mineEmptySchedule !== '' || $mineEmptyLocation !== ''): ?>
        <div class="eah-mine-main-summary">
            <?php if ($mineEmptySchedule !== ''): ?>
            <p class="eah-mine-main-summary__row"><i class="fas fa-clock" aria-hidden="true"></i> <?= student_mine_empty_h($mineEmptySchedule) ?></p>
            <?php endif; ?>
            <?php if ($mineEmptyLocation !== ''): ?>
            <p class="eah-mine-main-summary__row"><i class="fas fa-map-marker-alt" aria-hidden="true"></i> <?= student_mine_empty_h(mb_strimwidth($mineEmptyLocation, 0, 120, '…')) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($mineEmptyIsOpen): ?>
        <p class="eah-student-hub-empty__note"><i class="fas fa-door-open" aria-hidden="true"></i> <strong>Open entry</strong> — no RSVP needed. Use <strong>Scan QR</strong> at the venue when you arrive.</p>
        <?php endif; ?>
        <div class="eah-student-hub-empty__actions">
            <a class="eah-student-hub-empty__cta" href="<?= student_mine_empty_h($mineEmptyHubUrl) ?>">
                <i class="fas fa-calendar-day" aria-hidden="true"></i> Back to this event
            </a>
            <?php if ($mineEmptyIsOpen): ?>
            <button type="button" class="eah-student-hub-empty__cta eah-student-hub-empty__cta--ghost" data-bs-toggle="modal" data-bs-target="#scanQRModal">
                <i class="fas fa-qrcode" aria-hidden="true"></i> Scan QR
            </button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <h3 class="eah-student-hub-empty__title">Nothing on your personal schedule yet</h3>
        <p class="eah-student-hub-empty__text">
            <strong>My schedule</strong> only lists day activities <strong>you RSVP to</strong>.
            Open-entry activities stay on <strong>This event</strong> — browse them there, then scan QR at the venue.
        </p>
        <div class="eah-student-hub-empty__actions">
            <a class="eah-student-hub-empty__cta" href="<?= student_mine_empty_h($mineEmptyHubUrl) ?>">
                <i class="fas fa-layer-group" aria-hidden="true"></i> Browse activities
            </a>
        </div>
    <?php endif; ?>
</div>
