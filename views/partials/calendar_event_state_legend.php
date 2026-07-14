<?php
/** Calendar color guide — matches eventDidMount lifecycle colors in dashboard JS. */
$legendId = $legendId ?? 'calendarEventLegend';
$legendClass = trim(($legendClass ?? 'eventify-calendar-legend'));
$showMultiDayNote = !isset($showMultiDayNote) || $showMultiDayNote;
$showSelectionClearNote = !empty($showSelectionClearNote);
?>
<div class="<?= htmlspecialchars($legendClass) ?>" id="<?= htmlspecialchars($legendId) ?>" role="note" aria-label="Calendar color guide">
    <span class="eventify-calendar-legend__title">Color guide</span>
    <ul class="eventify-calendar-legend__list">
        <li>
            <span class="eventify-calendar-legend__swatch eventify-calendar-legend__swatch--pending" aria-hidden="true"></span>
            <span class="eventify-calendar-legend__label"><strong>Orange</strong> — Pending approval (not live yet)</span>
        </li>
        <li>
            <span class="eventify-calendar-legend__swatch eventify-calendar-legend__swatch--active" aria-hidden="true"></span>
            <span class="eventify-calendar-legend__label"><strong>Green</strong> — Active (approved and in progress)</span>
        </li>
        <li>
            <span class="eventify-calendar-legend__swatch eventify-calendar-legend__swatch--upcoming" aria-hidden="true"></span>
            <span class="eventify-calendar-legend__label"><strong>Gold</strong> — Upcoming (approved; scheduled on a future day)</span>
        </li>
        <li>
            <span class="eventify-calendar-legend__swatch eventify-calendar-legend__swatch--closed" aria-hidden="true"></span>
            <span class="eventify-calendar-legend__label"><strong>Gray</strong> — Closed or completed</span>
        </li>
        <li>
            <span class="eventify-calendar-legend__swatch eventify-calendar-legend__swatch--rejected" aria-hidden="true"></span>
            <span class="eventify-calendar-legend__label"><strong>Red</strong> — Rejected</span>
        </li>
    </ul>
    <?php if ($showMultiDayNote): ?>
    <p class="eventify-calendar-legend__note">Pending events stay orange on every day until admin approves. Active multi-day bars use gray for past days, <strong>green on today</strong>, and gold on future days.</p>
    <?php endif; ?>
    <?php if ($showSelectionClearNote): ?>
    <p class="eventify-calendar-legend__note">Double-click a highlighted day on the calendar to clear the selection and return it to white.</p>
    <?php endif; ?>
</div>
