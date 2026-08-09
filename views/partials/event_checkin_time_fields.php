<?php
/** @var string $idPrefix e.g. standalone, ce, edit */
/** @var string $postedCheckinStart H:i or empty */
/** @var string $postedCheckinEnd H:i or empty */
$idPrefix = $idPrefix ?? 'standalone';
$postedCheckinStart = $postedCheckinStart ?? '';
$postedCheckinEnd = $postedCheckinEnd ?? '';
$startInputId = $idPrefix === 'standalone' ? 'checkin_start_time' : ($idPrefix . 'CheckinStartTime');
$endInputId = $idPrefix === 'standalone' ? 'checkin_end_time' : ($idPrefix . 'CheckinEndTime');
$rowId = $idPrefix . 'CheckinTimeRow';
?>
<div class="form-group event-checkin-time-row" id="<?= htmlspecialchars($rowId) ?>">
    <span class="d-block mb-2 fw-semibold" style="font-size: 14px;">Attendance check-in window</span>
    <p class="text-muted small mb-2">Optional. Set when students can scan the event QR for attendance. Leave blank to use the event start and end times.</p>
    <div class="row g-3">
        <div class="col-md-6">
            <label for="<?= htmlspecialchars($startInputId) ?>">Attendance opens</label>
            <div class="input-icon">
                <i class="fas fa-user-check"></i>
                <input type="time" id="<?= htmlspecialchars($startInputId) ?>" name="checkin_start_time" class="form-control"
                       value="<?= htmlspecialchars($postedCheckinStart) ?>">
            </div>
            <small class="text-muted d-block mt-1">Example: 9:30 PM when the event starts at 10:00 PM, or 7:30 AM for an 8:00 AM start.</small>
        </div>
        <div class="col-md-6">
            <label for="<?= htmlspecialchars($endInputId) ?>">Attendance closes</label>
            <div class="input-icon">
                <i class="fas fa-user-clock"></i>
                <input type="time" id="<?= htmlspecialchars($endInputId) ?>" name="checkin_end_time" class="form-control"
                       value="<?= htmlspecialchars($postedCheckinEnd) ?>">
            </div>
            <small class="text-muted d-block mt-1">Example: 11:30 AM when the event ends at 12:00 PM.</small>
        </div>
    </div>
</div>
