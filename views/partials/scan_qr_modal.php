<?php
/** Shared Scan QR modal — student dashboard and activities hub pages. */
?>
<div class="modal fade" id="scanQRModal" tabindex="-1" aria-labelledby="scanQRModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content efy-modal">
      <div class="modal-header efy-modal__header">
        <div>
          <span class="efy-modal__eyebrow">Student</span>
          <h5 class="modal-title efy-modal__title" id="scanQRModalLabel">
            <i class="fas fa-qrcode" aria-hidden="true"></i>
            Scan QR for attendance
          </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" id="scanQRModalClose"></button>
      </div>
      <div class="modal-body efy-modal__body">
        <div id="scanQRVideoContainer" class="position-relative bg-dark rounded overflow-hidden" style="min-height: 260px;">
          <video id="scanQRVideo" playsinline muted style="width:100%; height:auto; display:block;"></video>
          <canvas id="scanQRCanvas" style="display:none;"></canvas>
          <div id="scanQRPlaceholder" class="position-absolute top-0 start-0 w-100 h-100 text-white" style="display:flex; align-items:center; justify-content:center;">
            <span><i class="fas fa-camera fa-2x mb-2 d-block"></i>Starting camera…</span>
          </div>
        </div>
        <p id="scanQRStatus" class="small text-muted mt-2 mb-0">Scan an <strong>event</strong> or <strong>activity</strong> QR code. Location may be required at the venue.</p>
        <div id="scanQRFallback" class="mt-3 pt-3 border-top" style="display:none;">
          <p class="small fw-semibold mb-2">Camera blocked? Use one of these:</p>
          <label class="btn btn-outline-primary btn-sm w-100 mb-2" for="scanQRFileInput">
            <i class="fas fa-image me-1"></i>Upload QR photo
          </label>
          <input type="file" id="scanQRFileInput" accept="image/*" capture="environment" class="d-none">
          <div class="input-group input-group-sm">
            <input type="url" class="form-control" id="scanQRLinkInput" placeholder="Paste check-in link from QR" autocomplete="off" inputmode="url">
            <button type="button" class="btn btn-primary" id="scanQRLinkGo">Open</button>
          </div>
          <p class="small text-muted mt-2 mb-0">Or open your phone <strong>Camera</strong> app, scan the event QR poster, then tap the link.</p>
        </div>
      </div>
      <div class="modal-footer efy-modal__footer">
        <button type="button" class="btn efy-btn-muted btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="scanQrActivityMismatchModal" tabindex="-1" aria-labelledby="scanQrActivityMismatchModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content efy-modal efy-modal--compact">
      <div class="modal-header efy-modal__header">
        <div>
          <span class="efy-modal__eyebrow">Student</span>
          <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="scanQrActivityMismatchModalLabel">
            <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
            Different activity QR
          </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body efy-modal__body efy-modal__body--compact">
        <p class="efy-confirm-message mb-0">
          You're viewing <strong id="scanQrMismatchCurrentTitle">this activity</strong> but this QR code is for <strong id="scanQrMismatchScannedTitle">another activity</strong>. Continue to check in anyway?
        </p>
      </div>
      <div class="modal-footer efy-modal__footer">
        <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">Stay here</button>
        <button type="button" class="btn efy-btn-primary" id="scanQrMismatchContinue">Continue to check-in</button>
      </div>
    </div>
  </div>
</div>
