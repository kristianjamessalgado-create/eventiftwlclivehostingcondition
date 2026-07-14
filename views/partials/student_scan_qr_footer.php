<?php
/** Scan QR modal + scripts for student hub pages. Requires Bootstrap JS loaded first. */
include __DIR__ . '/scan_qr_modal.php';
?>
<script>window.BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/scan_qr.js?v=2"></script>
