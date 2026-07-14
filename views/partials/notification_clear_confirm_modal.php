<?php

/** @var string $notif_clear_modal_id */

$notif_clear_modal_id = trim((string) ($notif_clear_modal_id ?? 'eventifyClearNotifsModal'));

?>

<div class="modal fade eventify-notif-modal eventify-notif-clear-modal" id="<?= htmlspecialchars($notif_clear_modal_id) ?>" tabindex="-1" aria-labelledby="<?= htmlspecialchars($notif_clear_modal_id) ?>Label" aria-hidden="true">

  <div class="modal-dialog modal-dialog-centered">

    <div class="modal-content">

      <div class="modal-header eventify-notif-modal__header py-3">

        <div>

          <h5 class="modal-title" id="<?= htmlspecialchars($notif_clear_modal_id) ?>Label">

            <i class="fas fa-trash-alt me-2"></i>Clear all notifications?

          </h5>

          <p class="eventify-notif-modal__subtitle mb-0">This cannot be undone</p>

        </div>

        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

      </div>

      <div class="modal-body eventify-notif-modal__body px-4 py-3">

        <p class="mb-0 small text-muted">Every in-app notification for your account will be removed.</p>

      </div>

      <div class="modal-footer eventify-notif-modal__footer eventify-notif-modal__footer--single">

        <form class="js-eventify-clear-notifs-form w-100" method="post" action="<?= htmlspecialchars(BASE_URL . '/backend/auth/mark_notification_read.php') ?>">

          <?= csrf_field() ?>

          <input type="hidden" name="action" value="clear_all">

          <input type="hidden" name="ajax" value="1">

          <div class="eventify-notif-modal__footer-actions w-100 justify-content-end">

            <button type="button" class="eventify-notif-footer-btn eventify-notif-footer-btn--read" data-bs-dismiss="modal">Cancel</button>

            <button type="submit" class="eventify-notif-footer-btn eventify-notif-footer-btn--clear">

              <i class="fas fa-trash-alt" aria-hidden="true"></i>

              <span>Yes, clear all</span>

            </button>

          </div>

        </form>

      </div>

    </div>

  </div>

</div>

