<?php
/**
 * Attendance mode: single check-in vs time in / time out.
 *
 * Expects (optional):
 *   $idPrefix (string) e.g. 'ce', 'edit', 'eds'
 *   $postedAttendanceMode ('single'|'time_in_out')
 *   $postedCheckoutEnd (H:i or empty)
 */
$idPrefix = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($idPrefix ?? 'att')) ?: 'att';
$postedAttendanceMode = strtolower(trim((string) ($postedAttendanceMode ?? 'single')));
if ($postedAttendanceMode !== 'time_in_out') {
    $postedAttendanceMode = 'single';
}
$postedCheckoutEnd = trim((string) ($postedCheckoutEnd ?? ''));
$modeSingleId = $idPrefix . 'AttendanceModeSingle';
$modeInOutId = $idPrefix . 'AttendanceModeInOut';
$checkoutWrapId = $idPrefix . 'CheckoutEndWrap';
$checkoutInputId = $idPrefix === 'standalone' ? 'checkout_end_time' : ($idPrefix . 'CheckoutEndTime');
$rowId = $idPrefix . 'AttendanceModeRow';
?>
<div class="form-group event-attendance-mode-row" id="<?= htmlspecialchars($rowId) ?>">
    <span class="d-block mb-2 fw-semibold" style="font-size: 14px;">Attendance type</span>
    <p class="text-muted small mb-2">Single check-in records one scan. Time in &amp; time out uses the same QR twice — first scan when arriving, second scan when leaving.</p>
    <div class="form-check mb-1">
        <input class="form-check-input js-attendance-mode-radio" type="radio" name="attendance_mode" value="single" id="<?= htmlspecialchars($modeSingleId) ?>" <?= $postedAttendanceMode === 'single' ? 'checked' : '' ?>>
        <label class="form-check-label" for="<?= htmlspecialchars($modeSingleId) ?>">Single check-in (one scan)</label>
    </div>
    <div class="form-check mb-2">
        <input class="form-check-input js-attendance-mode-radio" type="radio" name="attendance_mode" value="time_in_out" id="<?= htmlspecialchars($modeInOutId) ?>" <?= $postedAttendanceMode === 'time_in_out' ? 'checked' : '' ?>>
        <label class="form-check-label" for="<?= htmlspecialchars($modeInOutId) ?>">Time in &amp; time out (two scans)</label>
    </div>
    <div id="<?= htmlspecialchars($checkoutWrapId) ?>" class="row g-2" style="<?= $postedAttendanceMode === 'time_in_out' ? '' : 'display:none;' ?>">
        <div class="col-md-6">
            <label class="form-label small mb-1" for="<?= htmlspecialchars($checkoutInputId) ?>">Time-out closes</label>
            <input type="time" class="form-control form-control-sm" name="checkout_end_time" id="<?= htmlspecialchars($checkoutInputId) ?>" value="<?= htmlspecialchars($postedCheckoutEnd) ?>">
            <small class="text-muted">Optional. Time-out opens when the event ends. Leave blank to allow until 11:59 PM.</small>
        </div>
    </div>
</div>
<script>
(function () {
    var row = document.getElementById(<?= json_encode($rowId) ?>);
    if (!row) return;
    var wrap = document.getElementById(<?= json_encode($checkoutWrapId) ?>);
    function sync() {
        if (!wrap) return;
        var inOut = row.querySelector('input[name="attendance_mode"][value="time_in_out"]');
        wrap.style.display = inOut && inOut.checked ? '' : 'none';
    }
    row.querySelectorAll('.js-attendance-mode-radio').forEach(function (el) {
        el.addEventListener('change', sync);
    });
})();
</script>
