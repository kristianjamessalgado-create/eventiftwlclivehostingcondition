<?php
/**
 * Organizer main hub hero — event_activities.php (view=hub).
 *
 * Vars: $organizerEventHubTitle, $organizerEventHubListUrl,
 *       $organizerEventHubActivityCount, $organizerEventHubDayCount,
 *       $organizerEventHubLiveCount (optional int),
 *       $eventScheduleDates, $defaultActivityDay,
 *       $eventHasEditableScheduleDay, $eventCanEndEarly, $eventCanReopen,
 *       $scheduleLockMessage (optional string)
 */
$organizerEventHubTitle = (string) ($organizerEventHubTitle ?? 'Event');
$organizerEventHubListUrl = (string) ($organizerEventHubListUrl ?? BASE_URL . '/activities_hub.php');
$organizerEventHubActivityCount = (int) ($organizerEventHubActivityCount ?? 0);
$organizerEventHubDayCount = (int) ($organizerEventHubDayCount ?? 0);
$organizerEventHubLiveCount = (int) ($organizerEventHubLiveCount ?? 0);
$eventScheduleDates = is_array($eventScheduleDates ?? null) ? $eventScheduleDates : [];
$defaultActivityDay = (string) ($defaultActivityDay ?? '');
$eventHasEditableScheduleDay = !empty($eventHasEditableScheduleDay);
$eventCanEndEarly = !empty($eventCanEndEarly);
$eventCanReopen = !empty($eventCanReopen ?? false);
$scheduleLockMessage = trim((string) ($scheduleLockMessage ?? ''));

$showOrganizerStatusBanner = $eventCanReopen || ($scheduleLockMessage !== '' && !$eventHasEditableScheduleDay);
$organizerStatusBannerTitle = '';
$organizerStatusBannerText = '';
$organizerStatusBannerVariant = 'locked';
if ($eventCanReopen) {
    $organizerStatusBannerTitle = 'Event ended';
    $organizerStatusBannerText = 'Check-in, RSVP, and ticket sales are paused for all activities. The schedule is read-only until you reopen this event.';
    $organizerStatusBannerVariant = 'ended';
} elseif ($scheduleLockMessage !== '' && !$eventHasEditableScheduleDay) {
    $organizerStatusBannerTitle = 'Schedule locked';
    $organizerStatusBannerText = $scheduleLockMessage;
}

if (!function_exists('organizer_event_hub_h')) {
    function organizer_event_hub_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
?>
<header class="eah-student-hub-hero eah-student-hub-hero--event eah-student-hub-hero--organizer">
    <nav class="eah-student-hub-hero__crumb" aria-label="Breadcrumb">
        <a class="eah-student-hub-hero__crumb-link" href="<?= organizer_event_hub_h($organizerEventHubListUrl) ?>">
            <i class="fas fa-th-large" aria-hidden="true"></i> Activities hub
        </a>
        <span class="eah-student-hub-hero__crumb-sep" aria-hidden="true">/</span>
        <span class="eah-student-hub-hero__crumb-current"><?= organizer_event_hub_h($organizerEventHubTitle) ?></span>
    </nav>
    <div class="eah-student-hub-hero__main">
        <div class="eah-student-hub-hero__icon" aria-hidden="true"><i class="fas fa-calendar-day"></i></div>
        <div class="eah-student-hub-hero__copy">
            <p class="eah-student-hub-hero__eyebrow">Main hub</p>
            <h1 class="eah-student-hub-hero__title"><?= organizer_event_hub_h($organizerEventHubTitle) ?></h1>
            <p class="eah-student-hub-hero__subtitle">Manage day activities, QR check-in, and attendance for this event. Add sessions like <strong>Badminton</strong> or <strong>Basketball</strong> under each event day.</p>
        </div>
    </div>
    <div class="eah-student-hub-hero__stats">
        <?php if ($organizerEventHubActivityCount > 0): ?>
        <span class="eah-student-hub-stat">
            <i class="fas fa-layer-group" aria-hidden="true"></i>
            <?= $organizerEventHubActivityCount ?> activit<?= $organizerEventHubActivityCount === 1 ? 'y' : 'ies' ?>
        </span>
        <span class="eah-student-hub-stat">
            <i class="fas fa-calendar-week" aria-hidden="true"></i>
            <?= $organizerEventHubDayCount ?> day<?= $organizerEventHubDayCount === 1 ? '' : 's' ?>
        </span>
        <?php else: ?>
        <span class="eah-student-hub-stat eah-student-hub-stat--main">
            <i class="fas fa-calendar-plus" aria-hidden="true"></i>
            No day activities yet
        </span>
        <?php endif; ?>
        <?php if ($organizerEventHubLiveCount > 0): ?>
        <span class="eah-student-hub-stat eah-student-hub-stat--live">
            <i class="fas fa-circle" aria-hidden="true"></i>
            <?= $organizerEventHubLiveCount ?> live
        </span>
        <?php endif; ?>
    </div>
    <?php if ($showOrganizerStatusBanner): ?>
    <div class="eah-organizer-status-banner eah-organizer-status-banner--<?= organizer_event_hub_h($organizerStatusBannerVariant) ?>" role="status">
        <span class="eah-organizer-status-banner__icon" aria-hidden="true">
            <i class="fas <?= $eventCanReopen ? 'fa-flag-checkered' : 'fa-lock' ?>"></i>
        </span>
        <div class="eah-organizer-status-banner__copy">
            <strong class="eah-organizer-status-banner__title"><?= organizer_event_hub_h($organizerStatusBannerTitle) ?></strong>
            <p class="eah-organizer-status-banner__text mb-0"><?= organizer_event_hub_h($organizerStatusBannerText) ?></p>
        </div>
    </div>
    <?php endif; ?>
    <div class="eah-organizer-actions eah-organizer-actions--hero<?= $showOrganizerStatusBanner ? ' eah-organizer-actions--after-banner' : '' ?>">
        <?php if (count($eventScheduleDates) > 1): ?>
            <label class="eah-organizer-actions__day" for="eahAddActivityDay">
                <span>Day</span>
                <select class="form-select form-select-sm" id="eahAddActivityDay">
                    <?php foreach ($eventScheduleDates as $schedYmd): ?>
                        <option value="<?= organizer_event_hub_h($schedYmd) ?>"<?= $schedYmd === $defaultActivityDay ? ' selected' : '' ?>>
                            <?= organizer_event_hub_h(date('M j, Y', strtotime($schedYmd))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php else: ?>
            <input type="hidden" id="eahAddActivityDay" value="<?= organizer_event_hub_h($defaultActivityDay) ?>">
        <?php endif; ?>
        <?php if ($eventHasEditableScheduleDay): ?>
        <button type="button" class="eah-hero-create-btn" id="eahHeroAddActivity">
            <i class="fas fa-plus" aria-hidden="true"></i> Add activity
        </button>
        <?php endif; ?>
        <?php if ($eventCanEndEarly): ?>
        <button type="button" class="eah-hero-end-btn js-eah-end-event" id="eahHeroEndEvent" title="Ends the whole main event, not just one activity">
            End entire event
        </button>
        <?php endif; ?>
        <?php if ($eventCanReopen): ?>
        <button type="button" class="eah-hero-create-btn eah-hero-reopen-btn js-eah-reopen-event" id="eahHeroReopenEvent">
            <i class="fas fa-redo" aria-hidden="true"></i> Reopen event
        </button>
        <?php endif; ?>
    </div>
</header>
