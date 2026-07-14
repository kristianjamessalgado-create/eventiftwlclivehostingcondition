<?php
/** @var bool $organizer_events_panel_open */
/** @var list<array<string, mixed>> $events */
/** @var array<string, int> $organizerStats */

$organizer_events_panel_open = !empty($organizer_events_panel_open);
$events = is_array($events ?? null) ? $events : [];
$organizerStats = is_array($organizerStats ?? null) ? $organizerStats : [];
$eventCount = count($events);
$panelEnterClass = $organizer_events_panel_open ? ' org-dash-panel--enter' : '';
$pendingCount = (int) ($organizerStats['pending'] ?? 0);
?>

<section
    class="org-dash-panel org-my-events-panel<?= $panelEnterClass ?><?= $organizer_events_panel_open ? '' : ' d-none' ?>"
    id="organizerMyEventsPanel"
    aria-label="My events"
    <?= $organizer_events_panel_open ? '' : ' hidden' ?>
>
    <div class="org-dash-panel__shell">
        <div class="org-dash-panel__toolbar">
            <button type="button" class="org-dash-panel__back" data-organizer-panel="home">
                <span class="org-dash-panel__back-icon" aria-hidden="true"><i class="fas fa-arrow-left"></i></span>
                <span class="org-dash-panel__back-label">Back to calendar</span>
            </button>
            <?php if ($eventCount > 0): ?>
                <span class="org-dash-panel__count-pill">
                    <i class="fas fa-calendar-alt" aria-hidden="true"></i>
                    <?= $eventCount ?> event<?= $eventCount === 1 ? '' : 's' ?>
                </span>
            <?php endif; ?>
        </div>

        <header class="org-dash-panel__hero">
            <div class="org-dash-panel__hero-icon" aria-hidden="true"><i class="fas fa-list"></i></div>
            <div class="org-dash-panel__hero-text">
                <h2 class="org-dash-panel__title">My events</h2>
                <p class="org-dash-panel__subtitle mb-0">
                    Manage, verify OTP, and open tools for each event you organize.
                </p>
            </div>
        </header>

        <?php if ($pendingCount > 0): ?>
            <div class="org-dash-panel__filter-note" role="status">
                <i class="fas fa-hourglass-half" aria-hidden="true"></i>
                <?= $pendingCount ?> event<?= $pendingCount === 1 ? '' : 's' ?> awaiting admin approval or OTP verification.
            </div>
        <?php endif; ?>

        <?php include __DIR__ . '/organizer_my_events_list.php'; ?>

        <div class="org-my-events-panel__footer">
            <button type="button" class="org-dash-panel__cta" id="openCreateEventFromMyEvents">
                <i class="fas fa-plus me-1"></i> Create event
            </button>
        </div>
    </div>
</section>
