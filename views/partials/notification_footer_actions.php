<?php
/**
 * Shared Mark read / Clear all / Done actions for notification modals and dropdowns.
 *
 * @var bool $notif_show_mark_all
 * @var bool $notif_show_clear
 * @var bool $notif_show_done
 * @var string $notif_context modal|dropdown
 * @var string $notif_mark_all_href
 * @var bool $notif_mark_all_ajax
 * @var string $notif_clear_modal_id
 */
$notif_show_mark_all = !empty($notif_show_mark_all);
$notif_show_clear = !empty($notif_show_clear);
$notif_show_done = !empty($notif_show_done);
$notif_context = ($notif_context ?? 'modal') === 'dropdown' ? 'dropdown' : 'modal';
$notif_mark_all_ajax = true;
$notif_clear_modal_id = trim((string) ($notif_clear_modal_id ?? ''));

if (!$notif_show_mark_all && !$notif_show_clear && !$notif_show_done) {
    return;
}

$footer_class = $notif_context === 'dropdown'
    ? 'eventify-notif-dropdown__footer-bar'
    : 'eventify-notif-modal__footer' . ($notif_show_done ? '' : ' eventify-notif-modal__footer--single');
?>
<div class="<?= htmlspecialchars($footer_class) ?>">
    <?php if ($notif_show_mark_all || $notif_show_clear): ?>
        <div class="eventify-notif-modal__footer-actions">
            <?php if ($notif_show_mark_all): ?>
                <button type="button" class="eventify-notif-footer-btn eventify-notif-footer-btn--read js-eventify-mark-all-notifs">
                    <i class="fas fa-check-double" aria-hidden="true"></i>
                    <span>Mark all read</span>
                </button>
            <?php endif; ?>
            <?php if ($notif_show_clear): ?>
                <?php if ($notif_clear_modal_id !== ''): ?>
                    <button type="button" class="eventify-notif-footer-btn eventify-notif-footer-btn--clear js-eventify-open-clear-notifs" data-bs-target="#<?= htmlspecialchars($notif_clear_modal_id) ?>" aria-haspopup="dialog">
                        <i class="fas fa-trash-alt" aria-hidden="true"></i>
                        <span>Clear all</span>
                    </button>
                <?php else: ?>
                    <a href="<?= htmlspecialchars(BASE_URL . '/backend/auth/mark_notification_read.php?clear_all=1') ?>" class="eventify-notif-footer-btn eventify-notif-footer-btn--clear js-notif-clear-confirm">
                        <i class="fas fa-trash-alt" aria-hidden="true"></i>
                        <span>Clear all</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($notif_show_done): ?>
        <button type="button" class="eventify-notif-footer-btn eventify-notif-footer-btn--done" data-bs-dismiss="modal">Done</button>
    <?php endif; ?>
</div>
