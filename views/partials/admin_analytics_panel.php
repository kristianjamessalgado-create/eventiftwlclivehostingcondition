<?php
/** @var bool $admin_analytics_panel_open */
/** @var array<string, mixed> $eventStats */
/** @var array<string, mixed> $feedbackStats */

$admin_analytics_panel_open = !empty($admin_analytics_panel_open);
$eventStats = is_array($eventStats ?? null) ? $eventStats : [];
$feedbackStats = is_array($feedbackStats ?? null) ? $feedbackStats : [];
$panelEnterClass = $admin_analytics_panel_open ? ' adm-dash-panel--enter' : '';
?>

<section
    class="adm-dash-panel adm-analytics-panel<?= $panelEnterClass ?><?= $admin_analytics_panel_open ? '' : ' d-none' ?>"
    id="adminAnalyticsPanel"
    aria-label="Analytics and insights"
    <?= $admin_analytics_panel_open ? '' : ' hidden' ?>
>
    <div class="adm-dash-panel__shell">
        <div class="adm-dash-panel__toolbar">
            <button type="button" class="adm-dash-panel__back" data-admin-panel="home">
                <span class="adm-dash-panel__back-icon" aria-hidden="true"><i class="fas fa-arrow-left"></i></span>
                <span class="adm-dash-panel__back-label">Back to calendar</span>
            </button>
        </div>

        <header class="adm-dash-panel__hero">
            <div class="adm-dash-panel__hero-icon" aria-hidden="true"><i class="fas fa-chart-pie"></i></div>
            <div class="adm-dash-panel__hero-text">
                <h2 class="adm-dash-panel__title">Analytics &amp; insights</h2>
                <p class="adm-dash-panel__subtitle mb-0">
                    System-wide overview of events and student feedback ratings.
                </p>
            </div>
        </header>

        <div class="adm-stats adm-stats--modal mb-3">
            <div class="adm-stat-card">
                <div class="adm-stat-label">Pending approval</div>
                <div class="adm-stat-value"><?= (int) ($eventStats['pending'] ?? 0) ?></div>
            </div>
            <div class="adm-stat-card">
                <div class="adm-stat-label">Active events</div>
                <div class="adm-stat-value"><?= (int) ($eventStats['active'] ?? 0) ?></div>
            </div>
            <div class="adm-stat-card">
                <div class="adm-stat-label">Total events</div>
                <div class="adm-stat-value"><?= (int) ($eventStats['total'] ?? 0) ?></div>
            </div>
            <div class="adm-stat-card">
                <div class="adm-stat-label">Feedback avg</div>
                <div class="adm-stat-value"><?= number_format((float) ($feedbackStats['avg_rating'] ?? 0), 2) ?></div>
            </div>
        </div>

        <div class="adm-charts adm-charts--modal">
            <div class="adm-chart-card">
                <h6 class="mb-0">Events by department</h6>
                <div class="adm-chart-wrap" id="adminChartDeptWrap">
                    <canvas id="adminChartDept" aria-label="Bar chart of events by department"></canvas>
                </div>
            </div>
            <div class="adm-chart-card">
                <h6 class="mb-0">Events by status</h6>
                <div class="adm-chart-wrap" id="adminChartStatusWrap">
                    <canvas id="adminChartStatus" aria-label="Doughnut chart of events by status"></canvas>
                </div>
            </div>
            <div class="adm-chart-card">
                <h6 class="mb-0">Feedback ratings distribution</h6>
                <div class="adm-chart-wrap" id="adminChartFeedbackWrap">
                    <canvas id="adminChartFeedback" aria-label="Bar chart of feedback ratings"></canvas>
                </div>
            </div>
        </div>
    </div>
</section>
