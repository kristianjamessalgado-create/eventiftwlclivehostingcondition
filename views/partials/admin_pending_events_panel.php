<?php
/** @var bool $admin_pending_panel_open */
/** @var list<array<string, mixed>> $pendingEvents */
/** @var int $pendingCount */

$admin_pending_panel_open = !empty($admin_pending_panel_open);
$pendingEvents = is_array($pendingEvents ?? null) ? $pendingEvents : [];
$pendingCount = isset($pendingCount) ? (int) $pendingCount : count($pendingEvents);
$panelEnterClass = $admin_pending_panel_open ? ' adm-dash-panel--enter' : '';
?>

<section
    class="adm-dash-panel adm-pending-events-panel<?= $panelEnterClass ?><?= $admin_pending_panel_open ? '' : ' d-none' ?>"
    id="adminPendingEventsPanel"
    aria-label="Pending event approvals"
    <?= $admin_pending_panel_open ? '' : ' hidden' ?>
>
    <div class="adm-dash-panel__shell">
        <div class="adm-dash-panel__toolbar">
            <button type="button" class="adm-dash-panel__back" data-admin-panel="home">
                <span class="adm-dash-panel__back-icon" aria-hidden="true"><i class="fas fa-arrow-left"></i></span>
                <span class="adm-dash-panel__back-label">Back to calendar</span>
            </button>
            <?php if ($pendingCount > 0): ?>
                <span class="adm-dash-panel__count-pill adm-dash-panel__count-pill--warn">
                    <i class="fas fa-inbox" aria-hidden="true"></i>
                    <?= $pendingCount ?> pending
                </span>
            <?php endif; ?>
        </div>

        <header class="adm-dash-panel__hero">
            <div class="adm-dash-panel__hero-icon" aria-hidden="true"><i class="fas fa-inbox"></i></div>
            <div class="adm-dash-panel__hero-text">
                <h2 class="adm-dash-panel__title">Pending event approvals</h2>
                <p class="adm-dash-panel__subtitle mb-0">
                    <?= $pendingCount === 0
                        ? 'No events waiting for review.'
                        : $pendingCount . ' event' . ($pendingCount === 1 ? '' : 's') . ' awaiting OTP and organizer verification.' ?>
                </p>
            </div>
        </header>

        <div class="adm-pending-panel__body">
            <?php include __DIR__ . '/admin_pending_events_content.php'; ?>
        </div>
    </div>
</section>
