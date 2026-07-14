<?php
/** @var bool $admin_messages_panel_open */
/** @var string $dashboardHref */
/** @var int $uid */
/** @var string $role */
/** @var string $myName */
/** @var array $peersList */
/** @var int $initialWith */
/** @var string|null $messaging_error */

$admin_messages_panel_open = !empty($admin_messages_panel_open);
$dashboardHref = $dashboardHref ?? BASE_URL . '/backend/admin/dashboard.php';
$myName = $myName ?? '';
$peersList = is_array($peersList ?? null) ? $peersList : [];
$initialWith = isset($initialWith) ? (int) $initialWith : 0;
$messaging_error = $messaging_error ?? null;
$uid = isset($uid) ? (int) $uid : (int) ($_SESSION['user_id'] ?? 0);
$role = $role ?? 'admin';
$panelEnterClass = $admin_messages_panel_open ? ' adm-dash-panel--enter' : '';
?>

<section
    class="adm-dash-panel adm-messages-panel<?= $panelEnterClass ?><?= $admin_messages_panel_open ? '' : ' d-none' ?>"
    id="adminMessagesPanel"
    aria-label="Staff messages"
    <?= $admin_messages_panel_open ? '' : ' hidden' ?>
>
    <div class="adm-dash-panel__shell adm-dash-panel__shell--flush">
        <?php
        $msgr_embedded = true;
        include __DIR__ . '/staff_messenger_embed.php';
        ?>
    </div>
</section>
