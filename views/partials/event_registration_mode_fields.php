<?php
/**
 * Registration mode selector for create/edit event forms.
 * @var string $regModeFieldIdPrefix e.g. 'ce' or 'standalone'
 * @var string $postedRegistrationMode optional pre-selected value
 */
$regModeFieldIdPrefix = $regModeFieldIdPrefix ?? 'ce';
$postedRegistrationMode = strtolower(trim((string) ($postedRegistrationMode ?? 'open')));
if (!in_array($postedRegistrationMode, ['rsvp', 'paid_ticket', 'open'], true)) {
    $postedRegistrationMode = 'open';
}
$regModeSelectId = $regModeFieldIdPrefix . 'RegistrationMode';
?>
<section class="ce-form-section">
  <p class="ce-form-section__label">How students join</p>
  <label for="<?= htmlspecialchars($regModeSelectId) ?>" class="form-label ce-form-label">Registration mode</label>
  <select name="registration_mode" id="<?= htmlspecialchars($regModeSelectId) ?>" class="form-select ce-form-control">
    <option value="open" <?= $postedRegistrationMode === 'open' ? 'selected' : '' ?>>Open entry — no RSVP; students scan QR at the venue</option>
    <option value="rsvp" <?= $postedRegistrationMode === 'rsvp' ? 'selected' : '' ?>>Free RSVP — students register on the dashboard before check-in</option>
    <option value="paid_ticket" <?= $postedRegistrationMode === 'paid_ticket' ? 'selected' : '' ?>>Paid tickets — students buy tickets (set prices after approval)</option>
  </select>
  <p class="ce-form-help mb-0 mt-2">You can change this later under <strong>Tickets</strong> while the event is active.</p>
</section>
