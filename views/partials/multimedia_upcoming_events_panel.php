<?php
/** @var bool $mm_upcoming_panel_open */
/** @var list<array<string, mixed>> $upcomingEvents */

$mm_upcoming_panel_open = !empty($mm_upcoming_panel_open);
$upcomingEvents = is_array($upcomingEvents ?? null) ? $upcomingEvents : [];
$mm_upcoming_count = isset($mm_upcoming_count) ? (int) $mm_upcoming_count : count($upcomingEvents);
$panelEnterClass = $mm_upcoming_panel_open ? ' mm-dash-panel--enter' : '';
?>

<section
    class="mm-dash-panel mm-upcoming-events-panel<?= $panelEnterClass ?><?= $mm_upcoming_panel_open ? '' : ' d-none' ?>"
    id="mmUpcomingEventsPanel"
    aria-label="Upcoming events"
    <?= $mm_upcoming_panel_open ? '' : ' hidden' ?>
>
    <div class="mm-dash-panel__shell">
        <div class="mm-dash-panel__toolbar">
            <button type="button" class="mm-dash-panel__back" data-mm-panel="home">
                <span class="mm-dash-panel__back-icon" aria-hidden="true"><i class="fas fa-arrow-left"></i></span>
                <span class="mm-dash-panel__back-label">Back to calendar</span>
            </button>
            <?php if ($mm_upcoming_count > 0): ?>
                <span class="mm-dash-panel__count-pill">
                    <i class="fas fa-calendar-check" aria-hidden="true"></i>
                    <?= $mm_upcoming_count ?> upcoming
                </span>
            <?php endif; ?>
        </div>

        <header class="mm-dash-panel__hero">
            <div class="mm-dash-panel__hero-icon" aria-hidden="true"><i class="fas fa-calendar-check"></i></div>
            <div class="mm-dash-panel__hero-text">
                <h2 class="mm-dash-panel__title">Upcoming events</h2>
                <p class="mm-dash-panel__subtitle mb-0">
                    Active events from today onward for your department. Tap an event to open it in All events.
                </p>
            </div>
        </header>

        <?php if (empty($upcomingEvents)): ?>
            <div class="mm-upcoming-empty">
                <i class="fas fa-calendar-times"></i>
                <div>
                    <div class="fw-semibold">No upcoming events</div>
                    <div class="text-muted small">Once organizers create active events, they will appear here.</div>
                </div>
            </div>
        <?php else: ?>
            <div class="mm-upcoming-list">
                <?php foreach ($upcomingEvents as $ev): ?>
                    <?php $eid = (int) ($ev['id'] ?? 0); ?>
                    <button
                        type="button"
                        class="mm-upcoming-item mm-upcoming-event-link w-100 text-start"
                        data-event-id="<?= $eid ?>"
                        data-event-date="<?= htmlspecialchars($ev['date'] ?? '') ?>"
                    >
                        <div class="mm-upcoming-date">
                            <div class="m"><?= date('M', strtotime($ev['date'])) ?></div>
                            <div class="d"><?= date('d', strtotime($ev['date'])) ?></div>
                        </div>
                        <div class="mm-upcoming-info flex-grow-1 min-w-0">
                            <div class="mm-upcoming-title"><?= htmlspecialchars($ev['title'] ?? 'Untitled') ?></div>
                            <div class="mm-upcoming-meta">
                                <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($ev['location'] ?? 'TBA') ?></span>
                                <span class="dot">·</span>
                                <span class="pill"><?= htmlspecialchars(eventify_format_department_label((string) ($ev['department'] ?? 'ALL'))) ?></span>
                            </div>
                            <?php if (!empty($ev['description'])): ?>
                                <div class="mm-upcoming-desc">
                                    <?= htmlspecialchars(mb_strimwidth((string) $ev['description'], 0, 140, '…')) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <i class="fas fa-chevron-right mm-upcoming-chevron" aria-hidden="true"></i>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="mm-dash-panel__footer-actions mt-3">
            <a href="<?= BASE_URL ?>/upcoming_events.php" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                <i class="fas fa-up-right-from-square me-1"></i> Open full page
            </a>
        </div>
    </div>
</section>
