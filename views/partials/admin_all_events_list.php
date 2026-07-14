<?php
/** @var list<array<string, mixed>> $events */

$allAdminEventsList = is_array($events ?? null) ? $events : [];
if ($allAdminEventsList !== []) {
    usort($allAdminEventsList, static function ($a, $b) {
        $da = (string) ($a['date'] ?? '');
        $db = (string) ($b['date'] ?? '');
        if ($da === $db) {
            return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
        }
        return strcmp($db, $da);
    });
}
?>

<?php if ($allAdminEventsList === []): ?>
    <div class="adm-dash-panel__empty">
        <div class="adm-dash-panel__empty-icon" aria-hidden="true"><i class="fas fa-calendar-day"></i></div>
        <h3 class="adm-dash-panel__empty-title">No events yet</h3>
        <p class="adm-dash-panel__empty-text mb-0">Events created by organizers will appear here.</p>
    </div>
<?php else: ?>
    <div class="adm-all-events-filters">
        <input type="text" id="adminAllEventsSearch" class="form-control form-control-sm" placeholder="Search title, location, or organizer" aria-label="Search events">
        <select id="adminAllEventsStatusFilter" class="form-select form-select-sm" aria-label="Filter by status">
            <option value="">All status</option>
            <option value="pending">Pending</option>
            <option value="active">Active</option>
            <option value="rejected">Rejected</option>
            <option value="closed">Closed</option>
            <option value="completed">Completed</option>
        </select>
    </div>
    <div class="adm-all-events-list" id="adminAllEventsList">
        <?php foreach ($allAdminEventsList as $i => $ev): ?>
            <?php
                $eid = (int) ($ev['id'] ?? 0);
                $st = strtolower((string) ($ev['status'] ?? ''));
                $searchBlob = strtolower(trim(
                    ($ev['title'] ?? '') . ' ' . ($ev['location'] ?? '') . ' ' . ($ev['organizer_name'] ?? '') . ' ' . ($ev['department'] ?? '')
                ));
                $badgeClass = 'bg-secondary';
                $badgeLabel = ucfirst($st ?: 'unknown');
                if ($st === 'active') {
                    $badgeClass = 'bg-success';
                    $badgeLabel = 'Active';
                } elseif ($st === 'pending') {
                    $badgeClass = 'bg-warning text-dark';
                    $badgeLabel = 'Pending';
                } elseif ($st === 'rejected') {
                    $badgeClass = 'bg-danger';
                    $badgeLabel = 'Rejected';
                } elseif ($st === 'completed') {
                    $badgeClass = 'bg-secondary';
                    $badgeLabel = 'Completed';
                } elseif ($st === 'closed') {
                    $badgeClass = 'bg-secondary';
                    $badgeLabel = 'Closed';
                }
                $displayDate = !empty($ev['date']) ? date('M j, Y', strtotime((string) $ev['date'])) : 'TBA';
                if (!empty($ev['end_date']) && ($ev['end_date'] ?? '') !== ($ev['date'] ?? '')) {
                    $displayDate .= ' – ' . date('M j, Y', strtotime((string) $ev['end_date']));
                }
                $staggerStyle = '--panel-stagger: ' . min($i, 10) * 0.03 . 's';
            ?>
            <button
                type="button"
                class="adm-all-events-card admin-upcoming-event-link admin-all-events-item"
                style="<?= htmlspecialchars($staggerStyle) ?>"
                data-event-id="<?= $eid ?>"
                data-event-date="<?= htmlspecialchars($ev['date'] ?? '') ?>"
                data-status="<?= htmlspecialchars($st) ?>"
                data-search="<?= htmlspecialchars($searchBlob) ?>"
            >
                <div class="adm-all-events-card__top">
                    <h3 class="adm-all-events-card__title">
                        <i class="fas fa-calendar-day" aria-hidden="true"></i>
                        <?= htmlspecialchars($ev['title'] ?? 'Untitled') ?>
                    </h3>
                    <div class="adm-all-events-card__badges">
                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($badgeLabel) ?></span>
                        <?php
                            if (!function_exists('eventify_registration_mode_ui')) {
                                require_once __DIR__ . '/../../backend/lib/event_ticketing.php';
                            }
                            $regUi = eventify_registration_mode_ui($ev);
                            include __DIR__ . '/registration_mode_badge.php';
                        ?>
                    </div>
                </div>
                <div class="adm-all-events-card__meta">
                    <span><i class="fas fa-clock" aria-hidden="true"></i> <?= htmlspecialchars($displayDate) ?>
                        <?php if (!empty($ev['start_time'])): ?>
                            · <?= htmlspecialchars(substr((string) $ev['start_time'], 0, 5)) ?>
                        <?php endif; ?>
                    </span>
                    <?php if (!empty($ev['location'])): ?>
                        <span><i class="fas fa-map-marker-alt" aria-hidden="true"></i> <?= htmlspecialchars(mb_strimwidth((string) $ev['location'], 0, 48, '…')) ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-user" aria-hidden="true"></i> <?= htmlspecialchars($ev['organizer_name'] ?? '') ?>
                        <?php if (!empty($ev['department']) && ($ev['department'] ?? '') !== 'ALL'): ?>
                            · <?= htmlspecialchars((string) $ev['department']) ?>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if (!empty($ev['description'])): ?>
                    <p class="adm-all-events-card__desc"><?= htmlspecialchars(mb_strimwidth((string) $ev['description'], 0, 140, '…')) ?></p>
                <?php endif; ?>
            </button>
        <?php endforeach; ?>
    </div>
    <p class="adm-all-events-empty text-muted small mb-0 mt-2 d-none" id="adminAllEventsEmpty">No events match your filters.</p>
<?php endif; ?>
