<?php
/** @var bool $eventsHasEndDate */
/** @var string $idPrefix e.g. ce or standalone */
/** @var string $scheduleModeValue single|range|specific */
/** @var array<int,string> $postedScheduleDates */
/** @var string $postedStartDate Y-m-d */
/** @var string $postedEndDate Y-m-d */
/** @var string $postedEndTimeOption none|time|na */
/** @var string $postedEndTime H:i */
/** @var array<string, array{mode?: string, time?: string}> $postedDayEndTimes */
/** @var array<string, string> $postedDayStartTimes */
$idPrefix = $idPrefix ?? 'ce';
$scheduleModeValue = $scheduleModeValue ?? 'single';
$postedScheduleDates = $postedScheduleDates ?? [];
$postedStartDate = $postedStartDate ?? '';
$postedEndDate = $postedEndDate ?? '';
$postedEndTimeOption = $postedEndTimeOption ?? 'none';
$postedEndTime = $postedEndTime ?? '';
$postedDayEndTimes = $postedDayEndTimes ?? [];
$postedDayStartTimes = $postedDayStartTimes ?? [];
$endTimePanelId = $idPrefix . 'EndTimePanel';
$hiddenWrapId = $idPrefix . 'ScheduleDatesHidden';
$calendarId = $idPrefix . 'ScheduleClickCalendar';
$calendarWrapId = $idPrefix . 'ScheduleCalendarWrap';
$hintId = $idPrefix . 'ScheduleModeHint';
$minDate = date('Y-m-d');
$scheduleCss = (defined('BASE_URL') ? BASE_URL : '') . '/assets/css/event_schedule_picker.css';
$startDateInputId = $idPrefix === 'ce' ? 'ceDate' : ($idPrefix === 'standalone' ? 'date' : $idPrefix . 'Date');
?>
<link rel="stylesheet" href="<?= htmlspecialchars($scheduleCss) ?>">
<div class="event-schedule-block mb-3" data-schedule-prefix="<?= htmlspecialchars($idPrefix) ?>">
  <label class="form-label fw-semibold">When is this event? <span class="text-danger">*</span></label>
  <p class="text-muted small mb-2">Pick a schedule type, then click dates on the calendar. Selected days turn <strong class="text-secondary">gray</strong>. Double-click a gray day to clear it.</p>

  <div class="mb-2">
    <div class="form-check">
      <input class="form-check-input js-schedule-mode" type="radio" name="schedule_mode" id="<?= htmlspecialchars($idPrefix) ?>ModeSingle" value="single" <?= $scheduleModeValue === 'single' ? 'checked' : '' ?>>
      <label class="form-check-label" for="<?= htmlspecialchars($idPrefix) ?>ModeSingle"><strong>Single day</strong> — click one day</label>
    </div>
    <?php if (!empty($eventsHasEndDate)): ?>
    <div class="form-check">
      <input class="form-check-input js-schedule-mode" type="radio" name="schedule_mode" id="<?= htmlspecialchars($idPrefix) ?>ModeRange" value="range" <?= $scheduleModeValue === 'range' ? 'checked' : '' ?>>
      <label class="form-check-label" for="<?= htmlspecialchars($idPrefix) ?>ModeRange"><strong>Date range</strong> — click start day, then end day; set end time for each day in the range</label>
    </div>
    <?php endif; ?>
    <div class="form-check">
      <input class="form-check-input js-schedule-mode" type="radio" name="schedule_mode" id="<?= htmlspecialchars($idPrefix) ?>ModeSpecific" value="specific" <?= $scheduleModeValue === 'specific' ? 'checked' : '' ?>>
      <label class="form-check-label" for="<?= htmlspecialchars($idPrefix) ?>ModeSpecific"><strong>Specific days</strong> — click each day (skip weekends, etc.)</label>
    </div>
  </div>

  <p id="<?= htmlspecialchars($hintId) ?>" class="text-muted small mb-2 esc-mode-hint"></p>

  <div id="<?= htmlspecialchars($calendarWrapId) ?>" class="schedule-calendar-wrap">
    <div id="<?= htmlspecialchars($calendarId) ?>" class="event-schedule-click-calendar" role="application" aria-label="Pick event days"></div>
  </div>

  <input type="hidden" name="date" id="<?= htmlspecialchars($startDateInputId) ?>" value="<?= htmlspecialchars($postedStartDate) ?>" required>
  <?php if (!empty($eventsHasEndDate)): ?>
  <input type="hidden" name="end_date" id="<?= htmlspecialchars($idPrefix) ?>EndDate" value="<?= htmlspecialchars($postedEndDate) ?>">
  <?php endif; ?>
  <div id="<?= htmlspecialchars($hiddenWrapId) ?>"></div>

  <div id="<?= htmlspecialchars($endTimePanelId) ?>" class="event-end-time-panel mt-3" aria-live="polite"></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof eventifyInitSchedulePicker === 'function') {
    eventifyInitSchedulePicker('<?= htmlspecialchars($idPrefix, ENT_QUOTES) ?>', {
      dates: <?= json_encode(array_values($postedScheduleDates), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      startDate: <?= json_encode($postedStartDate, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      endDate: <?= json_encode($postedEndDate, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      endTimeOption: <?= json_encode($postedEndTimeOption, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      endTime: <?= json_encode($postedEndTime, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      dayEndTimes: <?= json_encode($postedDayEndTimes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      dayStartTimes: <?= json_encode($postedDayStartTimes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
    });
  }
});
</script>
