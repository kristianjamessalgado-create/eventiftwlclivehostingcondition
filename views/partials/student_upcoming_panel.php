<?php
/** @var bool $student_upcoming_panel_open */
/** @var list<array<string, mixed>> $upcoming_events */

$student_upcoming_panel_open = !empty($student_upcoming_panel_open);
$upcoming_events = is_array($upcoming_events ?? null) ? $upcoming_events : [];
$upcomingCount = count($upcoming_events);
$panelEnterClass = $student_upcoming_panel_open ? ' student-dash-panel--enter' : '';
?>

<section
    class="student-dash-panel student-upcoming-panel<?= $panelEnterClass ?><?= $student_upcoming_panel_open ? '' : ' d-none' ?>"
    id="studentUpcomingPanel"
    aria-label="Upcoming events"
    <?= $student_upcoming_panel_open ? '' : ' hidden' ?>
>
    <div class="student-dash-panel__shell">
        <div class="student-dash-panel__toolbar">
            <button type="button" class="student-dash-panel__back" data-student-panel="home">
                <span class="student-dash-panel__back-icon" aria-hidden="true"><i class="fas fa-arrow-left"></i></span>
                <span class="student-dash-panel__back-label">Back to calendar</span>
            </button>
            <?php if ($upcomingCount > 0): ?>
                <span class="student-dash-panel__count-pill">
                    <i class="fas fa-calendar-check" aria-hidden="true"></i>
                    <?= $upcomingCount ?> upcoming
                </span>
            <?php endif; ?>
        </div>

        <header class="student-dash-panel__hero">
            <div class="student-dash-panel__hero-icon" aria-hidden="true"><i class="fas fa-calendar-check"></i></div>
            <div class="student-dash-panel__hero-text">
                <h2 class="student-dash-panel__title">Upcoming events</h2>
                <p class="student-dash-panel__subtitle mb-0">
                    Tap an event to view details and RSVP.
                </p>
            </div>
        </header>

        <?php if ($upcomingCount === 0): ?>
            <div class="student-dash-panel__empty">
                <div class="student-dash-panel__empty-icon" aria-hidden="true"><i class="fas fa-calendar"></i></div>
                <h3 class="student-dash-panel__empty-title">No upcoming events</h3>
                <p class="student-dash-panel__empty-text mb-0">
                    Check back later for events open to your department.
                </p>
                <button type="button" class="student-dash-panel__empty-cta" data-student-panel="home">
                    <i class="fas fa-calendar me-1"></i> Browse calendar
                </button>
            </div>
        <?php else: ?>
            <div class="student-upcoming-list">
                <?php foreach ($upcoming_events as $i => $event): ?>
                    <?php
                        $eventId = (int) ($event['id'] ?? 0);
                        $staggerStyle = '--panel-stagger: ' . min($i, 8) * 0.035 . 's';
                    ?>
                    <article
                        class="student-upcoming-card student-event-link"
                        style="<?= htmlspecialchars($staggerStyle) ?>"
                        data-event-id="<?= $eventId ?>"
                        role="button"
                        tabindex="0"
                    >
                        <div class="student-upcoming-card__icon" aria-hidden="true"><i class="fas fa-calendar-day"></i></div>
                        <div class="student-upcoming-card__body">
                            <div class="student-upcoming-card__top">
                                <h3 class="student-upcoming-card__title"><?= htmlspecialchars((string) ($event['title'] ?? 'Untitled')) ?></h3>
                                <?php if (!empty($event['date'])): ?>
                                    <time class="student-upcoming-card__date" datetime="<?= htmlspecialchars(substr((string) $event['date'], 0, 10)) ?>">
                                        <?= htmlspecialchars(date('M j, Y', strtotime((string) $event['date']))) ?>
                                    </time>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($event['location']) || !empty($event['department'])): ?>
                                <p class="student-upcoming-card__meta mb-0">
                                    <?php if (!empty($event['location'])): ?>
                                        <span><i class="fas fa-map-marker-alt" aria-hidden="true"></i> <?= htmlspecialchars((string) $event['location']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($event['department'])): ?>
                                        <span class="student-upcoming-card__dept"><?= htmlspecialchars(eventify_format_department_label((string) $event['department'])) ?></span>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($event['description'])): ?>
                                <p class="student-upcoming-card__desc"><?= htmlspecialchars(mb_strimwidth((string) $event['description'], 0, 120, '…')) ?></p>
                            <?php endif; ?>
                        </div>
                        <i class="fas fa-chevron-right student-upcoming-card__chevron" aria-hidden="true"></i>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
