<?php
/** @var bool $admin_revenue_panel_open */
/** @var array<string, mixed> $revenueOverview */

$admin_revenue_panel_open = !empty($admin_revenue_panel_open);
$rev = is_array($revenueOverview ?? null) ? $revenueOverview : [];
$fmtPeso = static function ($n): string {
    return '₱' . number_format((float) $n, 2);
};
$panelEnterClass = $admin_revenue_panel_open ? ' adm-dash-panel--enter' : '';
?>

<section
    class="adm-dash-panel adm-revenue-panel<?= $panelEnterClass ?><?= $admin_revenue_panel_open ? '' : ' d-none' ?>"
    id="adminRevenuePanel"
    aria-label="Ticket revenue"
    <?= $admin_revenue_panel_open ? '' : ' hidden' ?>
>
    <div class="adm-dash-panel__shell">
        <div class="adm-dash-panel__toolbar">
            <button type="button" class="adm-dash-panel__back" data-admin-panel="home">
                <span class="adm-dash-panel__back-icon" aria-hidden="true"><i class="fas fa-arrow-left"></i></span>
                <span class="adm-dash-panel__back-label">Back to calendar</span>
            </button>
        </div>

        <header class="adm-dash-panel__hero">
            <div class="adm-dash-panel__hero-icon" aria-hidden="true"><i class="fas fa-peso-sign"></i></div>
            <div class="adm-dash-panel__hero-text">
                <h2 class="adm-dash-panel__title">Ticket revenue</h2>
                <p class="adm-dash-panel__subtitle mb-0">
                    Actual money collected from paid events. Demo / test payments are excluded from totals.
                </p>
            </div>
        </header>

        <div class="row g-2 mb-3">
            <div class="col-6 col-md-3">
                <div class="border rounded p-3 h-100 bg-light">
                    <div class="text-muted small">Total revenue</div>
                    <div class="h4 mb-0 text-success fw-bold"><?= htmlspecialchars($fmtPeso($rev['total_revenue'] ?? 0)) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="border rounded p-3 h-100 bg-light">
                    <div class="text-muted small">Tickets sold</div>
                    <div class="h4 mb-0"><?= (int) ($rev['tickets_sold'] ?? 0) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="border rounded p-3 h-100 bg-light">
                    <div class="text-muted small">Paid orders</div>
                    <div class="h4 mb-0"><?= (int) ($rev['orders_paid'] ?? 0) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="border rounded p-3 h-100 bg-light">
                    <div class="text-muted small">Pending payment</div>
                    <div class="h6 mb-0"><?= htmlspecialchars($fmtPeso($rev['pending_amount'] ?? 0)) ?> <span class="text-muted">(<?= (int) ($rev['pending_orders'] ?? 0) ?>)</span></div>
                </div>
            </div>
        </div>

        <?php if ((float) ($rev['demo_revenue'] ?? 0) > 0 || (int) ($rev['demo_orders'] ?? 0) > 0): ?>
            <div class="alert alert-warning py-2 small mb-3">
                <i class="fas fa-flask me-1"></i> Demo / test payments (excluded from revenue): <strong><?= htmlspecialchars($fmtPeso($rev['demo_revenue'] ?? 0)) ?></strong> across <?= (int) ($rev['demo_orders'] ?? 0) ?> order(s).
            </div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-12 col-lg-5">
                <h6 class="small text-uppercase text-muted">By payment method</h6>
                <?php
                $realMethods = array_filter($rev['by_method'] ?? [], static function ($m) {
                    return ($m['method'] ?? '') !== 'simulate';
                });
                ?>
                <?php if (!empty($realMethods)): ?>
                    <div class="table-responsive border rounded">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Method</th><th class="text-end">Orders</th><th class="text-end">Revenue</th></tr></thead>
                            <tbody>
                                <?php foreach ($realMethods as $m): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) ($m['label'] ?? '')) ?></td>
                                        <td class="text-end"><?= (int) ($m['orders'] ?? 0) ?></td>
                                        <td class="text-end"><?= htmlspecialchars($fmtPeso($m['revenue'] ?? 0)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">No real payments yet.</p>
                <?php endif; ?>
            </div>

            <div class="col-12 col-lg-7">
                <h6 class="small text-uppercase text-muted">By event</h6>
                <?php if (!empty($rev['by_event'])): ?>
                    <div class="table-responsive border rounded">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead><tr><th>Event</th><th>Organizer</th><th class="text-end">Orders</th><th class="text-end">Revenue</th></tr></thead>
                            <tbody>
                                <?php foreach ($rev['by_event'] as $ev): ?>
                                    <?php
                                    $evDate = trim((string) ($ev['date'] ?? ''));
                                    $evDateLabel = $evDate !== '' && ($ts = strtotime($evDate)) ? date('M j, Y', $ts) : '';
                                    ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars((string) ($ev['title'] ?? '')) ?>
                                            <?php if ($evDateLabel !== ''): ?><div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($evDateLabel) ?></div><?php endif; ?>
                                        </td>
                                        <td class="small"><?= htmlspecialchars((string) ($ev['organizer_name'] ?? '—')) ?></td>
                                        <td class="text-end"><?= (int) ($ev['orders'] ?? 0) ?></td>
                                        <td class="text-end fw-semibold"><?= htmlspecialchars($fmtPeso($ev['revenue'] ?? 0)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">No paid-event revenue yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
