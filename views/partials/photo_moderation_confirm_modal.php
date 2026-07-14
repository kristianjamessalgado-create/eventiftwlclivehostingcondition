<div class="modal fade" id="photoModerationConfirmModal" tabindex="-1" aria-labelledby="photoModerationConfirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content efy-modal efy-modal--compact">
      <div class="modal-header efy-modal__header">
        <div>
          <span class="efy-modal__eyebrow">Multimedia</span>
          <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="photoModerationConfirmModalLabel"></h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body efy-modal__body efy-modal__body--compact">
        <p class="efy-confirm-message mb-0" id="photoModerationConfirmMessage"></p>
        <div id="photoModerationReasonWrap" class="efy-form-section mt-3 mb-0" hidden>
          <label class="efy-form-label" for="photoModerationReasonInput">Reason for rejection <span class="text-danger">*</span></label>
          <textarea class="form-control" id="photoModerationReasonInput" rows="3" maxlength="500" placeholder="e.g. Blurry image, wrong event, duplicate photo…"></textarea>
          <span class="efy-form-help">The uploader will see this in their notification.</span>
        </div>
      </div>
      <div class="modal-footer efy-modal__footer">
        <button type="button" class="btn efy-btn-muted btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn efy-btn-primary btn-sm" id="photoModerationConfirmBtn"></button>
      </div>
    </div>
  </div>
</div>
