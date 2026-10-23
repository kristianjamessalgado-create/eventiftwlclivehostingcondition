<?php
/** @var bool $admin_analytics_panel_open */
/** @var array<string, mixed> $eventStats */
/** @var array<string, mixed> $feedbackStats */
/** @var array<string, mixed> $analyticsParticipation */
/** @var array<string, array{avg: float|null, count: int}> $admin_evaluation_averages */

$admin_analytics_panel_open = !empty($admin_analytics_panel_open);
$eventStats = is_array($eventStats ?? null) ? $eventStats : [];
$feedbackStats = is_array($feedbackStats ?? null) ? $feedbackStats : [];
$analyticsParticipation = is_array($analyticsParticipation ?? null) ? $analyticsParticipation : [];
$admin_evaluation_averages = is_array($admin_evaluation_averages ?? null) ? $admin_evaluation_averages : [];
$panelEnterClass = $admin_analytics_panel_open ? ' adm-dash-panel--enter' : '';

$fbCount = (int) ($feedbackStats['total_feedback'] ?? 0);
$fbAvgLabel = $fbCount > 0 ? number_format((float) ($feedbackStats['avg_rating'] ?? 0), 2) : '—';
$fbHint = $fbCount === 1 ? '1 response' : $fbCount . ' responses';

$regTotal = (int) ($analyticsParticipation['registrations'] ?? 0);
$checkinTotal = (int) ($analyticsParticipation['checkins'] ?? 0);
$showUpRate = $analyticsParticipation['show_up_rate'] ?? null;
if ($showUpRate === null || $regTotal < 1) {
    $showUpLabel = '—';
    $showUpHint = 'No registrations yet';
} else {
    $showUpNum = (float) $showUpRate;
    $showUpLabel = (fmod($showUpNum, 1.0) === 0.0 ? (string) (int) $showUpNum : number_format($showUpNum, 1)) . '%';
    $showUpHint = 'Checked in of registrations';
}

$evalSections = [];
$evalSectionTitles = [
    'event' => 'About the event',
    'system' => 'About EVENTIFY',
];
if (function_exists('eventify_evaluation_sections')) {
    foreach (eventify_evaluation_sections() as $sectionId => $section) {
        $weighted = 0.0;
        $weight = 0;
        $questions = [];
        foreach (($section['questions'] ?? []) as $q) {
            $key = (string) ($q['key'] ?? '');
            $stat = $admin_evaluation_averages[$key] ?? ['avg' => null, 'count' => 0];
            $cnt = (int) ($stat['count'] ?? 0);
            $avg = $cnt > 0 ? (float) ($stat['avg'] ?? 0) : null;
            if ($avg !== null) {
                $weighted += $avg * $cnt;
                $weight += $cnt;
            }
            $questions[] = [
                'text' => (string) ($q['text'] ?? $key),
                'avg' => $avg,
                'count' => $cnt,
            ];
        }
        $evalSections[] = [
            'label' => $evalSectionTitles[$sectionId] ?? (string) ($section['label'] ?? 'Evaluation'),
            'avg' => $weight > 0 ? round($weighted / $weight, 2) : null,
            'questions' => $questions,
        ];
    }
}
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
                    Events, attendance, and student feedback across the system.
                </p>
            </div>
        </header>

        <h3 class="adm-analytics-heading">Events</h3>
        <div class="adm-stats adm-analytics-stats">
            <div class="adm-stat-card">
                <div class="adm-stat-label">Pending approval</div>
                <div class="adm-stat-value"><?= (int) ($eventStats['pending'] ?? 0) ?></div>
            </div>
            <div class="adm-stat-card">
                <div class="adm-stat-label">Active events</div>
                <div class="adm-stat-value"><?= (int) ($eventStats['active'] ?? 0) ?></div>
            </div>
            <div class="adm-stat-card">
                <div class="adm-stat-label">Closed events</div>
                <div class="adm-stat-value"><?= (int) ($eventStats['closed'] ?? 0) ?></div>
            </div>
            <div class="adm-stat-card">
                <div class="adm-stat-label">Total events</div>
                <div class="adm-stat-value"><?= (int) ($eventStats['total'] ?? 0) ?></div>
            </div>
        </div>

        <h3 class="adm-analytics-heading">Attendance &amp; feedback</h3>
        <div class="adm-stats adm-analytics-stats">
            <div class="adm-stat-card">
                <div class="adm-stat-label">Registrations</div>
                <div class="adm-stat-value"><?= $regTotal ?></div>
                <div class="adm-stat-hint">Students who signed up</div>
            </div>
            <div class="adm-stat-card">
                <div class="adm-stat-label">Check-ins</div>
                <div class="adm-stat-value"><?= $checkinTotal ?></div>
                <div class="adm-stat-hint">Marked present at the event</div>
            </div>
            <div class="adm-stat-card">
                <div class="adm-stat-label">Show-up rate</div>
                <div class="adm-stat-value"><?= htmlspecialchars($showUpLabel) ?></div>
                <div class="adm-stat-hint"><?= htmlspecialchars($showUpHint) ?></div>
            </div>
            <div class="adm-stat-card">
                <div class="adm-stat-label">Feedback avg</div>
                <div class="adm-stat-value"><?= htmlspecialchars($fbAvgLabel) ?></div>
                <div class="adm-stat-hint"><?= htmlspecialchars($fbHint) ?></div>
            </div>
        </div>

        <div class="adm-charts adm-charts--analytics">
            <div class="adm-chart-card adm-chart-card--wide">
                <h6 class="mb-0">Events by department</h6>
                <div class="adm-chart-wrap adm-chart-wrap--dept" id="adminChartDeptWrap">
                    <canvas id="adminChartDept" aria-label="Bar chart of events by department"></canvas>
                </div>
            </div>
            <div class="adm-charts__split">
                <div class="adm-chart-card">
                    <h6 class="mb-0">Events by status</h6>
                    <div class="adm-chart-wrap" id="adminChartStatusWrap">
                        <canvas id="adminChartStatus" aria-label="Doughnut chart of events by status"></canvas>
                    </div>
                </div>
                <div class="adm-chart-card">
                    <h6 class="mb-0">Feedback ratings</h6>
                    <p class="adm-chart-card__note"><?= htmlspecialchars($fbHint) ?></p>
                    <div class="adm-chart-wrap" id="adminChartFeedbackWrap">
                        <canvas id="adminChartFeedback" aria-label="Bar chart of feedback ratings"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($evalSections !== []): ?>
            <h3 class="adm-analytics-heading">Evaluation scores</h3>
            <div class="adm-eval-grid">
                <?php foreach ($evalSections as $section): ?>
                    <?php
                    $sectionAvg = $section['avg'];
                    $sectionAvgLabel = $sectionAvg === null ? '—' : number_format((float) $sectionAvg, 2);
                    ?>
                    <article class="adm-eval-card">
                        <header class="adm-eval-card__head">
                            <h3 class="adm-eval-card__title"><?= htmlspecialchars((string) $section['label']) ?></h3>
                            <div class="adm-eval-card__score"><?= htmlspecialchars($sectionAvgLabel) ?><span>/5</span></div>
                        </header>
                        <ul class="adm-eval-list">
                            <?php foreach ($section['questions'] as $q): ?>
                                <?php
                                $qAvg = $q['avg'];
                                $qCount = (int) $q['count'];
                                $barWidth = $qAvg === null ? 0 : max(0, min(100, ((float) $qAvg / 5) * 100));
                                $qAvgLabel = $qAvg === null ? '—' : number_format((float) $qAvg, 2);
                                $qHint = $qCount === 1 ? '1 response' : $qCount . ' responses';
                                ?>
                                <li class="adm-eval-row">
                                    <div class="adm-eval-row__top">
                                        <span><?= htmlspecialchars((string) $q['text']) ?></span>
                                        <strong><?= htmlspecialchars($qAvgLabel) ?></strong>
                                    </div>
                                    <div class="adm-eval-bar" aria-hidden="true"><span style="width: <?= htmlspecialchars((string) $barWidth) ?>%"></span></div>
                                    <div class="adm-eval-row__count"><?= htmlspecialchars($qHint) ?></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
