<?php
/** @var bool $student_tickets_panel_open */
/** @var list<array<string, mixed>> $student_my_tickets */
/** @var int $student_tickets_event_filter */
/** @var int $student_tickets_order_filter */

$student_tickets_panel_open = !empty($student_tickets_panel_open);
$student_my_tickets = is_array($student_my_tickets ?? null) ? $student_my_tickets : [];
$student_tickets_event_filter = (int) ($student_tickets_event_filter ?? 0);
$student_tickets_order_filter = (int) ($student_tickets_order_filter ?? 0);
$ticketCount = count($student_my_tickets);
$dashboardUrl = BASE_URL . '/backend/auth/dashboard_student.php';
$ticketsPanelUrl = $dashboardUrl . '?panel=tickets';
$panelEnterClass = $student_tickets_panel_open ? ' student-dash-panel--enter' : '';
?>

<section
    class="student-dash-panel student-tickets-panel<?= $panelEnterClass ?><?= $student_tickets_panel_open ? '' : ' d-none' ?>"
    id="studentMyTicketsPanel"
    aria-label="My tickets"
    data-rendered-event-id="<?= (int) $student_tickets_event_filter ?>"
    data-rendered-order-id="<?= (int) $student_tickets_order_filter ?>"
    <?= $student_tickets_panel_open ? '' : ' hidden' ?>
>
    <div class="student-dash-panel__shell">
        <div class="student-dash-panel__toolbar">
            <button type="button" class="student-dash-panel__back" data-student-panel="home">
                <span class="student-dash-panel__back-icon" aria-hidden="true"><i class="fas fa-arrow-left"></i></span>
                <span class="student-dash-panel__back-label">Back to calendar</span>
            </button>
            <?php if ($ticketCount > 0): ?>
                <span class="student-dash-panel__count-pill">
                    <i class="fas fa-ticket-alt" aria-hidden="true"></i>
                    <?= $ticketCount ?> <?= $ticketCount === 1 ? 'pass' : 'passes' ?>
                </span>
            <?php endif; ?>
        </div>

        <header class="student-dash-panel__hero">
            <div class="student-dash-panel__hero-icon" aria-hidden="true"><i class="fas fa-ticket-alt"></i></div>
            <div class="student-dash-panel__hero-text">
                <h2 class="student-dash-panel__title">My tickets</h2>
                <p class="student-dash-panel__subtitle mb-0">
                    <?php if ($ticketCount > 0): ?>
                        Tap <strong>View pass</strong> at the venue for QR check-in.
                    <?php else: ?>
                        Your purchased tickets and digital passes appear here.
                    <?php endif; ?>
                </p>
            </div>
        </header>

        <?php if ($student_tickets_order_filter > 0 && $ticketCount > 0): ?>
            <div class="student-dash-panel__filter-note" role="status">
                <i class="fas fa-receipt" aria-hidden="true"></i>
                Showing tickets from your latest purchase.
                <a href="<?= htmlspecialchars($ticketsPanelUrl) ?>" data-student-panel="tickets">View all tickets</a>
            </div>
        <?php elseif ($student_tickets_event_filter > 0): ?>
            <div class="student-dash-panel__filter-note" role="status">
                <i class="fas fa-filter" aria-hidden="true"></i>
                Filtered to one event.
                <a href="<?= htmlspecialchars($ticketsPanelUrl) ?>" data-student-panel="tickets">Show all events</a>
            </div>
        <?php endif; ?>

        <div id="pwaOfflineTicketsNotice" class="pwa-offline-notice mb-3" hidden></div>

        <?php if ($student_my_tickets === []): ?>
            <div class="student-dash-panel__empty">
                <div class="student-dash-panel__empty-icon" aria-hidden="true"><i class="fas fa-ticket-alt"></i></div>
                <h3 class="student-dash-panel__empty-title">No tickets yet</h3>
                <p class="student-dash-panel__empty-text mb-0">
                    When you buy tickets for a paid event, your digital passes will show up here for quick check-in.
                </p>
                <button type="button" class="student-dash-panel__empty-cta" data-student-panel="home">
                    <i class="fas fa-calendar me-1"></i> Browse events on calendar
                </button>
            </div>
        <?php else: ?>
            <div class="student-tickets-list" id="studentMyTicketsList">
                <?php foreach ($student_my_tickets as $i => $t): ?>
                    <?php
                        $code = (string) ($t['ticket_code'] ?? '');
                        $passUrl = BASE_URL . '/ticket_pass.php?code=' . urlencode($code);
                        $eventDate = substr((string) ($t['event_date'] ?? ''), 0, 10);
                        $dateLabel = $eventDate !== '' ? date('M j, Y', strtotime($eventDate)) : '';
                        $paidAt = trim((string) ($t['paid_at'] ?? ''));
                        $paidLabel = $paidAt !== '' ? date('M j, Y', strtotime($paidAt)) : '';
                        $staggerStyle = '--panel-stagger: ' . min($i, 6) * 0.035 . 's';
                    ?>
                    <article class="student-ticket-card" style="<?= htmlspecialchars($staggerStyle) ?>">
                        <div class="student-ticket-card__ribbon" aria-hidden="true"></div>
                        <div class="student-ticket-card__inner">
                            <div class="student-ticket-card__header">
                                <span class="student-ticket-card__type"><?= htmlspecialchars((string) ($t['type_name'] ?? 'Ticket')) ?></span>
                                <span class="student-ticket-card__status">
                                    <i class="fas fa-check-circle" aria-hidden="true"></i> Valid
                                </span>
                            </div>
                            <h3 class="student-ticket-card__event"><?= htmlspecialchars((string) ($t['event_title'] ?? 'Event')) ?></h3>
                            <div class="student-ticket-card__meta">
                                <?php if ($dateLabel !== ''): ?>
                                    <span class="student-ticket-card__meta-item">
                                        <i class="fas fa-calendar-day" aria-hidden="true"></i>
                                        <?= htmlspecialchars($dateLabel) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($t['event_location'])): ?>
                                    <span class="student-ticket-card__meta-item">
                                        <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                                        <?= htmlspecialchars(mb_strimwidth((string) $t['event_location'], 0, 48, '…')) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="student-ticket-card__footer">
                                <div class="student-ticket-card__code">
                                    <span class="student-ticket-card__code-label">Ticket code</span>
                                    <code><?= htmlspecialchars($code) ?></code>
                                </div>
                                <?php if ($paidLabel !== ''): ?>
                                    <span class="student-ticket-card__paid">Purchased <?= htmlspecialchars($paidLabel) ?></span>
                                <?php endif; ?>
                            </div>
                            <a class="student-ticket-card__pass-btn" href="<?= htmlspecialchars($passUrl) ?>">
                                <i class="fas fa-qrcode" aria-hidden="true"></i>
                                <span>View pass</span>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
