<?php
/**
 * Create event modal (organizer). Requires:
 * @var bool $eventsHasGeo
 * @var bool $eventsHasEndDate
 * @var array<string,string> $organizer_department_choices
 * @var string $createEventRedirectTo optional post-submit redirect key
 */
$createEventRedirectTo = $createEventRedirectTo ?? '';
?>
<div class="modal fade" id="createEventModal" tabindex="-1" aria-labelledby="createEventModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-md-down ce-modal-dialog">
    <div class="modal-content ce-modal">
      <div class="modal-header ce-modal__header">
        <div class="ce-modal__header-text">
          <span class="ce-modal__eyebrow">Organizer</span>
          <h5 class="modal-title ce-modal__title" id="createEventModalLabel">
            <i class="fas fa-calendar-plus" aria-hidden="true"></i>
            Create new event
          </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>/backend/auth/createevent.php" id="createEventModalForm" data-require-geo="<?= !empty($eventsHasGeo) ? '1' : '0' ?>">
        <?= csrf_field() ?>
        <?php if ($createEventRedirectTo !== ''): ?>
          <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($createEventRedirectTo, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <div class="modal-body ce-modal__body">
          <section class="ce-form-section">
            <p class="ce-form-section__label">Event details</p>
            <div class="mb-3">
              <label for="ceTitle" class="form-label ce-form-label">Event title <span class="ce-required">*</span></label>
              <input type="text" name="title" id="ceTitle" class="form-control ce-form-control" maxlength="150" required placeholder="e.g. MS Intrams 2026">
            </div>
            <div class="mb-0">
              <label for="ceDescription" class="form-label ce-form-label">Description</label>
              <textarea name="description" id="ceDescription" class="form-control ce-form-control" rows="3" maxlength="1000" placeholder="What is this event about?"></textarea>
            </div>
          </section>

          <section class="ce-form-section ce-form-section--schedule">
            <?php
            $scheduleModeValue = 'single';
            $postedScheduleDates = [];
            $postedStartDate = '';
            $postedEndDate = '';
            $postedEndTimeOption = 'none';
            $postedEndTime = '';
            $postedDayEndTimes = [];
            $postedDayStartTimes = [];
            if (!empty($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['date'])) {
                $postedStartDate = (string) $_GET['date'];
            }
            $idPrefix = 'ce';
            include __DIR__ . '/event_schedule_fields.php';
            ?>
            <div class="row g-3 mt-1" id="ceStartTimeRow">
              <div class="col-md-6">
                <label for="ceStartTime" class="form-label ce-form-label">Event start time <span class="ce-required">*</span></label>
                <p class="ce-form-help mb-1">For multi-day events, set start time on each day in the schedule above.</p>
                <input type="time" name="start_time" id="ceStartTime" class="form-control ce-form-control" required>
              </div>
            </div>
          </section>

          <section class="ce-form-section">
            <p class="ce-form-section__label">Venue</p>
            <label class="form-label ce-form-label">Map location <span class="ce-required">*</span></label>
            <p class="ce-form-help mb-2">Search, tap the map, or use your device location.</p>
            <input type="hidden" name="event_latitude" id="ceEventLatitude" value="">
            <input type="hidden" name="event_longitude" id="ceEventLongitude" value="">
            <div class="ce-loc-toolbar">
              <input type="search" id="ceLocSearch" class="form-control ce-form-control ce-loc-toolbar__search" placeholder="Search place or address" autocomplete="off">
              <button type="button" class="btn ce-btn-outline btn-sm" id="ceLocSearchBtn">Search</button>
              <button type="button" class="btn ce-btn-gold btn-sm" id="ceLocUseGps" title="Use GPS"><i class="fas fa-location-crosshairs"></i></button>
            </div>
            <div id="ceLocResults" class="list-group mb-2 organizer-loc-results ce-loc-results" style="display:none;"></div>
            <div id="ceLocationMap" class="event-location-map ce-location-map mb-2"></div>
            <label for="ceLocation" class="form-label ce-form-label">Venue name / address <span class="ce-required">*</span></label>
            <input type="text" name="location" id="ceLocation" class="form-control ce-form-control" maxlength="255" required placeholder="Shown to attendees">
            <?php if (!empty($eventsHasGeo)): ?>
            <small class="ce-form-help">Coordinates are saved from the map pin.</small>
            <?php endif; ?>
          </section>

          <?php
            $regModeFieldIdPrefix = 'ce';
            $postedRegistrationMode = $_POST['registration_mode'] ?? 'rsvp';
            include __DIR__ . '/event_registration_mode_fields.php';
          ?>

          <section class="ce-form-section ce-dept-audience">
            <p class="ce-form-section__label">Department / audience</p>
            <p class="ce-form-help mb-2">Choose <strong>All departments</strong> for a school-wide event, or pick specific colleges.</p>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" name="department[]" value="ALL" id="ceDeptAll" checked>
              <label class="form-check-label fw-semibold" for="ceDeptAll">All departments</label>
            </div>
            <div class="ce-dept-checkbox-list">
              <?php foreach ($organizer_department_choices as $deptVal => $deptLabel): ?>
                <?php if ($deptVal === 'ALL') {
                    continue;
                } ?>
                <?php $ceCbId = 'ce_dept_' . substr(md5($deptVal), 0, 14); ?>
                <div class="form-check">
                  <input class="form-check-input ce-dept-specific" type="checkbox" name="department[]" value="<?= htmlspecialchars($deptVal) ?>" id="<?= htmlspecialchars($ceCbId) ?>">
                  <label class="form-check-label" for="<?= htmlspecialchars($ceCbId) ?>"><?= htmlspecialchars($deptLabel) ?></label>
                </div>
              <?php endforeach; ?>
            </div>
          </section>
        </div>
        <div class="modal-footer ce-modal__footer">
          <button type="submit" class="btn ce-btn-primary">
            <i class="fas fa-check me-1"></i>Submit for approval
          </button>
          <button type="button" class="btn ce-btn-muted" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>
