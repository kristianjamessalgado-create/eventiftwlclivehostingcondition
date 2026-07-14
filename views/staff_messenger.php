<?php
/** @var string $pageTitle */
/** @var string $dashboardHref */
/** @var int $uid */
/** @var string $role */
/** @var string $myName */
/** @var array $peersList */
/** @var int $initialWith */
/** @var string|null $messaging_error */

$pageTitle = $pageTitle ?? 'Messages';
$dashboardHref = $dashboardHref ?? BASE_URL . '/';
$myName = $myName ?? '';
$peersList = $peersList ?? [];
$initialWith = isset($initialWith) ? (int) $initialWith : 0;
$messaging_error = $messaging_error ?? null;
$uid = isset($uid) ? (int) $uid : (int) ($_SESSION['user_id'] ?? 0);
$role = $role ?? (string) ($_SESSION['role'] ?? '');
$peerLabel = ($role === 'admin') ? 'Organizers' : 'Admins';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle) ?> — EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL) ?>/assets/css/eventify_modal.css?v=1">
    <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL) ?>/assets/css/staff_messenger.css?v=8">
</head>
<body class="msgr-body">
<?php
$msgr_embedded = false;
include __DIR__ . '/partials/staff_messenger_embed.php';
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.BASE_URL = <?= json_encode(BASE_URL) ?>;
window.csrfToken = <?= json_encode(function_exists('csrf_token') ? csrf_token() : '') ?>;
window.__staffMessengerSelfId = <?= (int) $uid ?>;
window.__staffMessengerPeers = <?= json_encode($peersList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.__staffMessengerInitialWith = <?= (int) $initialWith ?>;
window.__staffMessengerPeerLabel = <?= json_encode($peerLabel) ?>;
window.__staffMessengerError = <?= json_encode($messaging_error) ?>;
</script>
<script src="<?= htmlspecialchars(BASE_URL) ?>/assets/js/eventify_toast.js?v=1"></script>
<script src="<?= htmlspecialchars(BASE_URL) ?>/assets/js/staff_messenger.js?v=6"></script>
</body>
</html>
