<?php
/** @var bool $admin_upcoming_panel_open */
/** @var list<array<string, mixed>> $upcomingAdminEvents */
/** @var int $upcomingAdminCount */

$admin_upcoming_panel_open = !empty($admin_upcoming_panel_open);
$upcomingAdminEvents = is_array($upcomingAdminEvents ?? null) ? $upcomingAdminEvents : [];
$upcomingAdminCount = isset($upcomingAdminCount) ? (int) $upcomingAdminCount : count($upcomingAdminEvents);
$panelEnterClass = $admin_upcoming_panel_open ? ' adm-dash-panel--enter' : '';
?>

<section
    class="adm-dash-panel adm-upcoming-events-panel<?= $panelEnterClass ?><?= $admin_upcoming_panel_open ? '' : ' d-none' ?>"
    id="adminUpcomingEventsPanel"
    aria-label="Upcoming events"
    <?= $admin_upcoming_panel_open ? '' : ' hidden' ?>
>
    <div class="adm-dash-panel__shell">
        <div class="adm-dash-panel__toolbar">
            <button type="button" class="adm-dash-panel__back" data-admin-panel="home">
                <span class="adm-dash-panel__back-icon" aria-hidden="true"><i class="fas fa-arrow-left"></i></span>
                <span class="adm-dash-panel__back-label">Back to calendar</span>
            </button>
            <?php if ($upcomingAdminCount > 0): ?>
                <span class="adm-dash-panel__count-pill">
                    <i class="fas fa-calendar-check" aria-hidden="true"></i>
                    <?= $upcomingAdminCount ?> upcoming
                </span>
            <?php endif; ?>
        </div>

        <header class="adm-dash-panel__hero">
            <div class="adm-dash-panel__hero-icon" aria-hidden="true"><i class="fas fa-calendar-check"></i></div>
            <div class="adm-dash-panel__hero-text">
                <h2 class="adm-dash-panel__title">Upcoming events</h2>
                <p class="adm-dash-panel__subtitle mb-0">
                    Active and pending events from today onward (system-wide). Tap an event to see full details on the calendar.
                </p>
            </div>
        </header>

        <?php if (!empty($upcomingAdminEvents)): ?>
            <div class="list-group adm-upcoming-events-list">
                <?php foreach ($upcomingAdminEvents as $ev): ?>
                    <?php
                    $eid = (int) ($ev['id'] ?? 0);
                    $st = strtolower((string) ($ev['status'] ?? ''));
                    ?>
                    <button
                        type="button"
                        class="list-group-item list-group-item-action text-start admin-upcoming-event-link"
                        data-event-id="<?= $eid ?>"
                        data-event-date="<?= htmlspecialchars($ev['date'] ?? '') ?>"
                    >
                        <div class="d-flex w-100 justify-content-between align-items-start gap-2">
                            <div>
                                <h6 class="mb-1">
                                    <i class="fas fa-calendar-day me-1 text-primary"></i>
                                    <?= htmlspecialchars($ev['title'] ?? 'Untitled') ?>
                                </h6>
                                <div class="small text-muted">
                                    <?php if (!empty($ev['date'])): ?>
                                        <?= date('M j, Y', strtotime($ev['date'])) ?>
                                        <?php if (!empty($ev['start_time'])): ?>
                                            · <?= htmlspecialchars(substr($ev['start_time'], 0, 5)) ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($ev['location'])): ?>
                                        <br><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($ev['location']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                                <span class="badge <?= $st === 'active' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                    <?= $st === 'active' ? 'Active' : 'Pending' ?>
                                </span>
                                <?php
                                require_once __DIR__ . '/../../backend/lib/event_ticketing.php';
                                $regUi = eventify_registration_mode_ui($ev);
                                include __DIR__ . '/registration_mode_badge.php';
                                ?>
                            </div>
                        </div>
                        <?php if (!empty($ev['description'])): ?>
                            <p class="mb-0 small mt-2 text-muted"><?= htmlspecialchars(mb_strimwidth((string) $ev['description'], 0, 160, '…')) ?></p>
                        <?php endif; ?>
                        <div class="small text-muted mt-1"><i class="fas fa-user me-1"></i><?= htmlspecialchars($ev['organizer_name'] ?? '') ?></div>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">No upcoming active or pending events.</p>
        <?php endif; ?>

        <div class="adm-dash-panel__footer-actions mt-3">
            <a href="<?= BASE_URL ?>/upcoming_events.php" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
                <i class="fas fa-external-link-alt me-1"></i> Open full page
            </a>
        </div>
    </div>
</section>
