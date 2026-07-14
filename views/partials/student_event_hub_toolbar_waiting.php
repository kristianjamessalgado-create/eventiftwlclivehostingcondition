<?php
/**
 * Short note above main-event card when there are no day activities.
 */
$studentHubWaitingRegMode = (string) ($mainEventRegMode ?? 'rsvp');
$studentHubWaitingIsOpen = ($studentHubWaitingRegMode === 'open');

if (!function_exists('student_hub_wait_h')) {
    function student_hub_wait_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
?>
<div class="eah-toolbar-waiting eah-toolbar-waiting--main-only" role="note">
    <div class="eah-toolbar-waiting__icon" aria-hidden="true"><i class="fas fa-info-circle"></i></div>
    <div class="eah-toolbar-waiting__copy">
        <p class="eah-toolbar-waiting__title">No separate day activities</p>
        <p class="eah-toolbar-waiting__text">
            This event uses the <strong>main schedule only</strong> — the date, time, and place below are what you need.
            Browse and filter tools appear only if the organizer adds optional sessions later.
        </p>
    </div>
</div>
