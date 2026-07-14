<?php
/**
 * Student main-hub toolbar — only shown when day activities exist.
 * When the schedule is empty, show a waiting notice instead (see student_event_hub_toolbar_waiting.php).
 */
$studentHubToolbarEventId = (int) ($eventId ?? 0);
$studentHubToolbarMySessions = $mySessions ?? [];
$studentHubToolbarLive = $liveSessions ?? [];
$studentHubToolbarRegMode = (string) ($mainEventRegMode ?? 'rsvp');
$studentHubToolbarHasMine = $studentHubToolbarMySessions !== [];
$studentHubToolbarHasTickets = ($studentHubToolbarRegMode === 'paid_ticket');

if (!function_exists('student_hub_tb_h')) {
    function student_hub_tb_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
?>
<div class="eah-toolbar eah-toolbar--student" role="toolbar" aria-label="Browse activities">
    <div class="eah-toolbar__filters">
        <a class="eah-tb-btn<?= $studentHubToolbarHasMine ? '' : ' eah-tb-btn--disabled' ?>"
           href="<?= student_hub_tb_h(eah_hub_link($studentHubToolbarEventId, ['view' => 'mine'])) ?>"
           title="<?= $studentHubToolbarHasMine ? 'My schedule — activities you RSVP\'d to' : 'My schedule — RSVP to activities first' ?>"
           aria-label="<?= $studentHubToolbarHasMine ? 'My schedule' : 'My schedule unavailable until you RSVP to activities' ?>"
           <?= $studentHubToolbarHasMine ? '' : ' aria-disabled="true" tabindex="-1"' ?>>
            <i class="fas fa-bookmark" aria-hidden="true"></i>
        </a>
        <?php if ($studentHubToolbarHasTickets): ?>
        <a class="eah-tb-btn"
           href="<?= student_hub_tb_h(eah_hub_link($studentHubToolbarEventId, ['view' => 'tickets'])) ?>"
           title="My tickets"
           aria-label="My tickets">
            <i class="fas fa-ticket-alt" aria-hidden="true"></i>
        </a>
        <?php endif; ?>
        <button type="button"
            class="eah-tb-btn eah-tb-btn--toggle<?= $studentHubToolbarHasMine ? '' : ' eah-tb-btn--disabled' ?>"
            id="eahMyOnlyToggle"
            title="<?= $studentHubToolbarHasMine ? 'Show my activities only' : 'RSVP to activities first — nothing on your schedule yet' ?>"
            aria-pressed="false"
            aria-label="<?= $studentHubToolbarHasMine ? 'Show my activities only' : 'Mine filter unavailable until you RSVP to activities' ?>"
            <?= $studentHubToolbarHasMine ? '' : ' disabled' ?>>
            <i class="fas fa-user-check" aria-hidden="true"></i><span class="eah-tb-btn__label">Mine</span>
        </button>
        <?php if ($studentHubToolbarLive !== []): ?>
        <a class="eah-tb-btn" href="#eah-sp-live" title="Jump to live activities" aria-label="Live activities">
            <i class="fas fa-circle" aria-hidden="true"></i>
        </a>
        <?php endif; ?>
        <a class="eah-tb-btn" href="#eah-sp-today" title="Jump to today's activities" aria-label="Today's activities">
            <i class="fas fa-sun" aria-hidden="true"></i>
        </a>
        <a class="eah-tb-btn" href="#eah-sp-days" title="Jump to schedule by day" aria-label="Activities by day">
            <i class="fas fa-calendar-week" aria-hidden="true"></i>
        </a>
        <a class="eah-tb-btn" href="#eah-sp-cats" title="Jump to categories" aria-label="Activity categories">
            <i class="fas fa-th-large" aria-hidden="true"></i>
        </a>
    </div>
    <label class="eah-toolbar__search-wrap">
        <i class="fas fa-search" aria-hidden="true"></i>
        <input type="search" class="eah-toolbar__search" id="eahHubSearch" placeholder="Search activities" autocomplete="off" aria-label="Search activities">
    </label>
</div>
<div class="eah-filter-banner" id="eahMyOnlyBanner" hidden>
    <span class="eah-filter-banner__text"><i class="fas fa-user-check" aria-hidden="true"></i> Showing your activities only</span>
    <button type="button" class="eah-filter-banner__clear" id="eahMyOnlyClear">Show all</button>
</div>
<div class="eah-filter-hint" id="eahHubFilterHint" hidden role="status">
    <span class="eah-filter-hint__text" id="eahHubFilterHintText"></span>
    <button type="button" class="eah-filter-hint__clear" id="eahHubFilterHintClear">Clear</button>
</div>
