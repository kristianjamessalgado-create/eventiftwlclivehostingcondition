<?php
/**
 * Single notification detail — opened when user taps a notification card.
 */
?>
<div class="modal fade eventify-notif-modal eventify-notif-detail-modal" id="eventifyNotificationDetailModal" tabindex="-1" aria-labelledby="eventifyNotifDetailTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header eventify-notif-modal__header">
        <div class="eventify-notif-detail__head">
          <div class="eventify-notif-detail__icon" id="eventifyNotifDetailIcon" aria-hidden="true">
            <i class="fas fa-bell"></i>
          </div>
          <div>
            <span class="eventify-notif-detail__type" id="eventifyNotifDetailType">Notice</span>
            <h5 class="modal-title mb-0" id="eventifyNotifDetailTitle">Notification</h5>
            <p class="eventify-notif-modal__subtitle mb-0" id="eventifyNotifDetailTime"></p>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body eventify-notif-modal__body">
        <p class="eventify-notif-detail__message mb-0" id="eventifyNotifDetailMessage"></p>
      </div>
      <div class="modal-footer eventify-notif-modal__footer eventify-notif-modal__footer--single">
        <div class="eventify-notif-modal__footer-actions w-100 justify-content-end">
          <button type="button" class="eventify-notif-footer-btn eventify-notif-footer-btn--read" id="eventifyNotifDetailAction" style="display: none;"></button>
          <button type="button" class="eventify-notif-footer-btn eventify-notif-footer-btn--done" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
</div>
