<?php
/** @var bool $daySessionsHaveGeo */
$daySessionsHaveGeo = !empty($daySessionsHaveGeo);
?>
<div class="modal fade" id="eventDaySessionsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable eds-modal-dialog">
    <div class="modal-content eds-modal">
      <div class="modal-header eds-modal__header">
        <div class="eds-modal__header-text">
          <span class="eds-modal__eyebrow">Day schedule</span>
          <h5 class="modal-title eds-modal__title">
            <i class="fas fa-layer-group" aria-hidden="true"></i>
            <span id="eventDaySessionsDateLabel"></span>
          </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body eds-modal__body">
        <div id="edsScheduleLockNotice" class="eds-schedule-lock-notice" style="display:none;" role="status"></div>
        <section class="eds-modal__list-section" aria-label="Activities on this day">
          <h6 class="eds-modal__section-title"><i class="fas fa-list-ul" aria-hidden="true"></i> On this day</h6>
          <div id="eventDaySessionsList" class="eds-modal__list"></div>
        </section>

        <section class="eds-form-panel" aria-labelledby="eventDaySessionFormTitle">
          <div class="eds-form-panel__head">
            <span class="eds-form-panel__icon" aria-hidden="true"><i class="fas fa-plus"></i></span>
            <div>
              <h6 class="eds-form-panel__title" id="eventDaySessionFormTitle">Add activity</h6>
              <p class="eds-form-panel__hint">Sub-activities like Badminton, Volleyball, or workshops.</p>
            </div>
          </div>
          <form id="eventDaySessionForm" class="eds-form-panel__form">
            <input type="hidden" id="edsSessionId" value="">

            <div class="eds-form-section">
              <p class="eds-form-section__label">Basics</p>
              <div class="row g-2">
                <div class="col-md-6" id="edsScheduleDateWrap" style="display:none;">
                  <label class="form-label eds-form-label" for="edsScheduleDate">Activity date <span class="eds-required">*</span></label>
                  <select class="form-select eds-form-control" id="edsScheduleDate"></select>
                  <small class="eds-form-help">Which day of the event this activity runs on.</small>
                </div>
                <div class="col-md-6">
                  <label class="form-label eds-form-label" for="edsTitle">Activity name <span class="eds-required">*</span></label>
                  <input type="text" class="form-control eds-form-control" id="edsTitle" maxlength="150" required placeholder="e.g. Badminton">
                </div>
                <div class="col-md-6">
                  <label class="form-label eds-form-label" for="edsCategory">Category</label>
                  <select class="form-select eds-form-control" id="edsCategory">
                    <option value="">— Select —</option>
                    <?php foreach (eventify_day_session_category_options() as $catOpt): ?>
                      <option value="<?= htmlspecialchars($catOpt) ?>"><?= htmlspecialchars($catOpt) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label eds-form-label" for="edsStatus">Status</label>
                  <select class="form-select eds-form-control" id="edsStatus">
                    <option value="scheduled">Scheduled</option>
                    <option value="delayed">Delayed</option>
                    <option value="cancelled">Cancelled</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label eds-form-label" for="edsMaxCapacity">Max capacity <span class="eds-optional">(optional)</span></label>
                  <input type="number" class="form-control eds-form-control" id="edsMaxCapacity" placeholder="No limit">
                </div>
                <div class="col-md-4">
                  <label class="form-label eds-form-label" for="edsSortOrder">Sort order</label>
                  <input type="number" class="form-control eds-form-control" id="edsSortOrder" min="0" value="0">
                </div>
                <div class="col-md-6">
                  <label class="form-label eds-form-label" for="edsAccessMode">Student access</label>
                  <select class="form-select eds-form-control" id="edsAccessMode">
                    <option value="open" selected>Open — scan QR at venue (no RSVP)</option>
                    <option value="free">Free — RSVP only (e.g. sports)</option>
                    <option value="ticket_required">Paid — ticket required (e.g. Mr &amp; Ms)</option>
                  </select>
                </div>
                <div class="col-md-6" id="edsTicketTypeWrap" style="display:none;">
                  <label class="form-label eds-form-label">Accepted ticket types</label>
                  <div class="eds-tt-checks" id="edsTicketTypeChecks" role="group" aria-label="Accepted ticket types">
                    <p class="eds-tt-checks__empty" id="edsTicketTypeChecksEmpty">No ticket types yet — create one below.</p>
                  </div>
                  <div class="eds-tt-help">
                    <small class="eds-form-help">Tick every tier that gets in. Students buy any one of them.</small>
                    <button type="button" class="eds-tt-add-toggle" id="edsTicketTypeAddToggle">
                      <i class="fas fa-plus" aria-hidden="true"></i> New type
                    </button>
                  </div>
                  <div class="eds-tt-inline" id="edsTicketTypeInline" style="display:none;">
                    <div class="row g-2">
                      <div class="col-12">
                        <input type="text" class="form-control eds-form-control" id="edsNewTypeName" maxlength="100" placeholder="Type name (e.g. VIP)">
                      </div>
                      <div class="col-6">
                        <input type="number" class="form-control eds-form-control" id="edsNewTypePrice" min="0" step="0.01" placeholder="Price ₱">
                      </div>
                      <div class="col-6">
                        <input type="number" class="form-control eds-form-control" id="edsNewTypeQty" min="0" placeholder="Qty cap (optional)">
                      </div>
                    </div>
                    <div class="eds-tt-inline__error" id="edsNewTypeError" style="display:none;"></div>
                    <div class="eds-tt-inline__actions">
                      <button type="button" class="btn eds-btn-primary btn-sm" id="edsNewTypeSave"><i class="fas fa-check me-1"></i>Add type</button>
                      <button type="button" class="btn eds-btn-muted btn-sm" id="edsNewTypeCancel">Cancel</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="eds-form-section">
              <p class="eds-form-section__label">Venue</p>
              <div class="row g-2">
                <div class="col-12">
                  <?php if ($daySessionsHaveGeo): ?>
                  <label class="form-label eds-form-label">Map location <span class="eds-required">*</span></label>
                  <p class="eds-form-help mb-2">Search, tap the map, or use your device location.</p>
                  <input type="hidden" id="edsLatitude" value="">
                  <input type="hidden" id="edsLongitude" value="">
                  <div class="eds-loc-toolbar">
                    <input type="search" id="edsLocSearch" class="form-control eds-form-control eds-loc-toolbar__search" placeholder="Search place or address" autocomplete="off">
                    <button type="button" class="btn eds-btn-outline btn-sm" id="edsLocSearchBtn">Search</button>
                    <button type="button" class="btn eds-btn-gold btn-sm" id="edsLocUseGps" title="Use GPS"><i class="fas fa-location-crosshairs"></i></button>
                  </div>
                  <div id="edsLocResults" class="list-group mb-2 organizer-loc-results eds-loc-results" style="display:none;"></div>
                  <div id="edsLocationMap" class="event-location-map eds-location-map mb-2"></div>
                  <?php else: ?>
                  <input type="hidden" id="edsLatitude" value="">
                  <input type="hidden" id="edsLongitude" value="">
                  <?php endif; ?>
                  <label class="form-label eds-form-label" for="edsLocation">Venue name / address <span class="eds-required">*</span></label>
                  <input type="text" class="form-control eds-form-control" id="edsLocation" maxlength="255" required placeholder="Shown to attendees">
                </div>
              </div>
            </div>

            <div class="eds-form-section">
              <p class="eds-form-section__label">Time &amp; contact</p>
              <div class="row g-2">
                <div class="col-md-4">
                  <label class="form-label eds-form-label" for="edsStartTime">Start time</label>
                  <input type="time" class="form-control eds-form-control" id="edsStartTime">
                </div>
                <div class="col-md-4">
                  <label class="form-label eds-form-label" for="edsEndTime">End time</label>
                  <input type="time" class="form-control eds-form-control" id="edsEndTime">
                </div>
                <div class="col-md-6">
                  <label class="form-label eds-form-label" for="edsContactName">Contact person <span class="eds-optional">(optional)</span></label>
                  <input type="text" class="form-control eds-form-control" id="edsContactName" maxlength="100" placeholder="On-site contact name">
                </div>
                <div class="col-md-6">
                  <label class="form-label eds-form-label" for="edsContactPhone">Contact number <span class="eds-optional">(optional)</span></label>
                  <input type="text" class="form-control eds-form-control" id="edsContactPhone" maxlength="50" placeholder="Phone or email">
                </div>
                <div class="col-12">
                  <label class="form-label eds-form-label" for="edsNotes">Notes / instructions</label>
                  <textarea class="form-control eds-form-control" id="edsNotes" rows="2" maxlength="2000" placeholder="Venue details, what to bring, etc."></textarea>
                </div>
              </div>
            </div>

            <div class="eds-form-actions">
              <button type="submit" class="btn eds-btn-primary"><i class="fas fa-save me-1"></i>Save activity</button>
              <button type="button" class="btn eds-btn-outline" id="edsCancelEditBtn" style="display:none;">Cancel edit</button>
            </div>
          </form>
        </section>
      </div>
      <div class="modal-footer eds-modal__footer">
        <a href="#" class="btn eds-btn-outline btn-sm" id="edsActivitiesHubBtn" target="_blank" rel="noopener" style="display:none;"><i class="fas fa-th-large me-1"></i>Activities hub</a>
        <a href="#" class="btn eds-btn-outline btn-sm" id="edsPrintScheduleBtn" target="_blank" rel="noopener" style="display:none;"><i class="fas fa-print me-1"></i>Print schedule</a>
        <button type="button" class="btn eds-btn-muted" data-bs-dismiss="modal">Done</button>
      </div>
    </div>
  </div>
</div>
