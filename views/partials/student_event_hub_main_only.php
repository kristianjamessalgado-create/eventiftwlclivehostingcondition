<?php
/**
 * Main hub when the event has no day activities — the calendar event IS the schedule.
 *
 * Vars: $event, $mainEventRegMode, $activitiesHubListUrl, $studentDashboardUrl (optional)
 */
$mainOnlyEvent = is_array($event ?? null) ? $event : [];
$mainOnlyRegMode = (string) ($mainEventRegMode ?? 'rsvp');
$mainOnlyHubUrl = (string) ($activitiesHubListUrl ?? BASE_URL . '/activities_hub.php');
$mainOnlyDashboardUrl = (string) ($studentDashboardUrl ?? BASE_URL . '/backend/auth/dashboard_student.php');
$mainOnlySchedule = function_exists('eventify_format_event_date_range')
    ? eventify_format_event_date_range($mainOnlyEvent, true)
    : '';
$mainOnlyLocation = trim((string) ($mainOnlyEvent['location'] ?? ''));
$mainOnlyDepartment = function_exists('eventify_format_department_label')
    ? eventify_format_department_label((string) ($mainOnlyEvent['department'] ?? 'ALL'))
    : trim((string) ($mainOnlyEvent['department'] ?? ''));
$mainOnlyDescription = trim(strip_tags((string) ($mainOnlyEvent['description'] ?? '')));
$mainOnlyIsOpen = ($mainOnlyRegMode === 'open');
$mainOnlyIsPaid = ($mainOnlyRegMode === 'paid_ticket');
$mainOnlyIsLive = function_exists('eventify_event_is_live') && eventify_event_is_live($mainOnlyEvent);
$mainOnlyEnded = function_exists('eventify_event_ended_for_feedback') && eventify_event_ended_for_feedback($mainOnlyEvent);
$mainOnlyShowScan = $mainOnlyIsOpen && $mainOnlyIsLive && !$mainOnlyEnded;

if (!function_exists('student_main_only_h')) {
    function student_main_only_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
?>
<section class="eah-main-event-card" aria-labelledby="eahMainEventCardHeading">
    <div class="eah-main-event-card__head">
        <div class="eah-main-event-card__icon" aria-hidden="true"><i class="fas fa-calendar-check"></i></div>
        <div class="eah-main-event-card__head-copy">
            <h2 class="eah-main-event-card__title" id="eahMainEventCardHeading">You're viewing the full event</h2>
            <p class="eah-main-event-card__lead">
                This meeting does not use separate day activities. The schedule below is what you follow — no need to wait for another publish step.
            </p>
        </div>
    </div>

    <dl class="eah-main-event-card__facts">
        <?php if ($mainOnlySchedule !== ''): ?>
        <div class="eah-main-event-card__fact">
            <dt><i class="fas fa-clock" aria-hidden="true"></i> When</dt>
            <dd><?= student_main_only_h($mainOnlySchedule) ?></dd>
        </div>
        <?php endif; ?>
        <?php if ($mainOnlyLocation !== ''): ?>
        <div class="eah-main-event-card__fact">
            <dt><i class="fas fa-map-marker-alt" aria-hidden="true"></i> Where</dt>
            <dd><?= student_main_only_h($mainOnlyLocation) ?></dd>
        </div>
        <?php endif; ?>
        <?php if ($mainOnlyDepartment !== '' && $mainOnlyDepartment !== 'All Departments'): ?>
        <div class="eah-main-event-card__fact">
            <dt><i class="fas fa-building" aria-hidden="true"></i> For</dt>
            <dd><?= student_main_only_h($mainOnlyDepartment) ?></dd>
        </div>
        <?php endif; ?>
        <div class="eah-main-event-card__fact">
            <dt><i class="fas fa-door-open" aria-hidden="true"></i> Entry</dt>
            <dd>
                <?php if ($mainOnlyIsOpen): ?>
                    <strong>Open entry</strong> — walk in; no RSVP needed for this event.
                <?php elseif ($mainOnlyIsPaid): ?>
                    <strong>Ticket required</strong> — purchase a ticket before check-in.
                <?php else: ?>
                    <strong>RSVP</strong> — register on your dashboard if you have not yet.
                <?php endif; ?>
            </dd>
        </div>
    </dl>

    <?php if ($mainOnlyDescription !== ''): ?>
    <div class="eah-main-event-card__desc">
        <p class="eah-main-event-card__desc-label">About this event</p>
        <p class="eah-main-event-card__desc-text"><?= student_main_only_h($mainOnlyDescription) ?></p>
    </div>
    <?php endif; ?>

    <?php if ($mainOnlyShowScan): ?>
    <p class="eah-main-event-card__checkin">
        <i class="fas fa-qrcode" aria-hidden="true"></i>
        At the venue, tap <strong>Scan QR</strong> below and scan the organizer's check-in code when you arrive.
    </p>
    <?php elseif ($mainOnlyIsOpen && $mainOnlyEnded): ?>
    <p class="eah-main-event-card__checkin eah-main-event-card__checkin--muted">
        <i class="fas fa-info-circle" aria-hidden="true"></i>
        <strong>Event ended</strong> — Scan QR is not available.
    </p>
    <?php elseif ($mainOnlyIsOpen): ?>
    <p class="eah-main-event-card__checkin eah-main-event-card__checkin--muted">
        <i class="fas fa-info-circle" aria-hidden="true"></i>
        Check-in opens when the event starts. Use <strong>Scan QR</strong> at the venue.
    </p>
    <?php endif; ?>

    <div class="eah-main-event-card__actions">
        <?php if ($mainOnlyShowScan): ?>
        <button type="button" class="eah-main-event-card__cta" data-bs-toggle="modal" data-bs-target="#scanQRModal">
            <i class="fas fa-qrcode" aria-hidden="true"></i> Scan QR at venue
        </button>
        <?php endif; ?>
        <a class="eah-main-event-card__cta eah-main-event-card__cta--ghost" href="<?= student_main_only_h($mainOnlyDashboardUrl) ?>">
            <i class="fas fa-calendar-alt" aria-hidden="true"></i> Open dashboard calendar
        </a>
        <a class="eah-main-event-card__cta eah-main-event-card__cta--ghost" href="<?= student_main_only_h($mainOnlyHubUrl) ?>">
            <i class="fas fa-th-large" aria-hidden="true"></i> My registrations
        </a>
    </div>

    <p class="eah-main-event-card__footnote">
        If the organizer later adds workshops or sessions inside this event, they will appear here automatically.
    </p>
</section>
