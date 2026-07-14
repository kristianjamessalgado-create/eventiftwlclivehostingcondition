<?php
/**
 * Student main hub hero — event_activities.php (view=hub).
 *
 * Vars: $studentEventHubTitle, $studentEventHubHubUrl,
 *       $studentEventHubActivityCount, $studentEventHubDayCount,
 *       $studentEventHubLiveCount (optional int)
 */
$studentEventHubTitle = (string) ($studentEventHubTitle ?? 'Event');
$studentEventHubHubUrl = (string) ($studentEventHubHubUrl ?? BASE_URL . '/activities_hub.php');
$studentEventHubActivityCount = (int) ($studentEventHubActivityCount ?? 0);
$studentEventHubDayCount = (int) ($studentEventHubDayCount ?? 0);
$studentEventHubLiveCount = (int) ($studentEventHubLiveCount ?? 0);

if (!function_exists('student_event_hub_h')) {
    function student_event_hub_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
?>
<header class="eah-student-hub-hero eah-student-hub-hero--event">
    <nav class="eah-student-hub-hero__crumb" aria-label="Breadcrumb">
        <a class="eah-student-hub-hero__crumb-link" href="<?= student_event_hub_h($studentEventHubHubUrl) ?>">
            <i class="fas fa-th-large" aria-hidden="true"></i> My Activities and Events
        </a>
        <span class="eah-student-hub-hero__crumb-sep" aria-hidden="true">/</span>
        <span class="eah-student-hub-hero__crumb-current"><?= student_event_hub_h($studentEventHubTitle) ?></span>
    </nav>
    <div class="eah-student-hub-hero__main">
        <div class="eah-student-hub-hero__icon" aria-hidden="true"><i class="fas fa-calendar-day"></i></div>
        <div class="eah-student-hub-hero__copy">
            <p class="eah-student-hub-hero__eyebrow">Main hub</p>
            <h1 class="eah-student-hub-hero__title"><?= student_event_hub_h($studentEventHubTitle) ?></h1>
            <p class="eah-student-hub-hero__subtitle">Day activities for this event only — RSVP, save your schedule, and check in with QR. To switch events, open <strong>My Activities and Events</strong> in the menu.</p>
        </div>
    </div>
    <div class="eah-student-hub-hero__stats">
        <?php if ($studentEventHubActivityCount > 0): ?>
        <span class="eah-student-hub-stat">
            <i class="fas fa-layer-group" aria-hidden="true"></i>
            <?= $studentEventHubActivityCount ?> activit<?= $studentEventHubActivityCount === 1 ? 'y' : 'ies' ?>
        </span>
        <span class="eah-student-hub-stat">
            <i class="fas fa-calendar-week" aria-hidden="true"></i>
            <?= $studentEventHubDayCount ?> day<?= $studentEventHubDayCount === 1 ? '' : 's' ?>
        </span>
        <?php else: ?>
        <span class="eah-student-hub-stat eah-student-hub-stat--main">
            <i class="fas fa-calendar-check" aria-hidden="true"></i>
            Main event schedule
        </span>
        <?php endif; ?>
        <?php if ($studentEventHubLiveCount > 0): ?>
        <span class="eah-student-hub-stat eah-student-hub-stat--live">
            <i class="fas fa-circle" aria-hidden="true"></i>
            <?= $studentEventHubLiveCount ?> live
        </span>
        <?php endif; ?>
    </div>
</header>
