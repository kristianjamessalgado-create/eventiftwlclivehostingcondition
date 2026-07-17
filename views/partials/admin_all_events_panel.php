<?php
/** @var bool $admin_events_panel_open */
/** @var list<array<string, mixed>> $events */
/** @var array<string, int> $eventStats */

$admin_events_panel_open = !empty($admin_events_panel_open);
$events = is_array($events ?? null) ? $events : [];
$eventStats = is_array($eventStats ?? null) ? $eventStats : [];
$eventCount = count($events);
$panelEnterClass = $admin_events_panel_open ? ' adm-dash-panel--enter' : '';
$pendingCount = (int) ($eventStats['pending'] ?? 0);
$activeCount = (int) ($eventStats['active'] ?? 0);
?>

<section
    class="adm-dash-panel adm-all-events-panel<?= $panelEnterClass ?><?= $admin_events_panel_open ? '' : ' d-none' ?>"
    id="adminAllEventsPanel"
    aria-label="All events"
    <?= $admin_events_panel_open ? '' : ' hidden' ?>
>
    <div class="adm-dash-panel__shell">
        <div class="adm-dash-panel__toolbar">
            <button type="button" class="adm-dash-panel__back" data-admin-panel="home">
                <span class="adm-dash-panel__back-icon" aria-hidden="true"><i class="fas fa-arrow-left"></i></span>
                <span class="adm-dash-panel__back-label">Back to calendar</span>
            </button>
            <?php if ($eventCount > 0): ?>
                <span class="adm-dash-panel__count-pill">
                    <i class="fas fa-calendar-day" aria-hidden="true"></i>
                    <?= $eventCount ?> event<?= $eventCount === 1 ? '' : 's' ?>
                </span>
            <?php endif; ?>
        </div>

        <header class="adm-dash-panel__hero">
            <div class="adm-dash-panel__hero-icon" aria-hidden="true"><i class="fas fa-calendar-day"></i></div>
            <div class="adm-dash-panel__hero-text">
                <h2 class="adm-dash-panel__title">Events</h2>
                <p class="adm-dash-panel__subtitle mb-0">
                    All events in the system. Tap an event to open it on the calendar.
                </p>
            </div>
            <button
                type="button"
                class="btn adm-pending-btn adm-pending-btn--primary"
                data-bs-toggle="modal"
                data-bs-target="#createEventModal"
            >
                <i class="fas fa-plus me-1" aria-hidden="true"></i> Create event
            </button>
        </header>

        <?php if ($eventCount > 0): ?>
            <div class="adm-all-events-stats">
                <span class="adm-all-events-stats__pill"><i class="fas fa-check-circle" aria-hidden="true"></i> <?= $activeCount ?> active</span>
                <span class="adm-all-events-stats__pill adm-all-events-stats__pill--warn"><i class="fas fa-hourglass-half" aria-hidden="true"></i> <?= $pendingCount ?> pending</span>
            </div>
        <?php endif; ?>

        <?php include __DIR__ . '/admin_all_events_list.php'; ?>
    </div>
</section>
