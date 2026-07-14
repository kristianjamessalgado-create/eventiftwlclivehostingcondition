<?php
/** Shared logout confirmation — include once per page (id="logoutModal"). */
$logoutUrl = BASE_URL . '/backend/auth/logout.php';
?>
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content efy-modal efy-modal--compact">
            <div class="modal-header efy-modal__header">
                <div>
                    <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="logoutModalLabel">
                        <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                        Confirm log out
                    </h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body efy-modal__body efy-modal__body--compact">
                <p class="efy-confirm-message mb-0">Are you sure you want to log out?</p>
            </div>
            <div class="modal-footer efy-modal__footer">
                <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">Cancel</button>
                <a href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn efy-btn-danger js-logout-confirm">Yes, log out</a>
            </div>
        </div>
    </div>
</div>
