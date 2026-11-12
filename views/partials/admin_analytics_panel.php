<?php
/** @var bool $admin_analytics_panel_open */
/** @var array<string, mixed> $eventStats */
/** @var array<string, mixed> $feedbackStats */
/** @var array<string, mixed> $analyticsParticipation */
/** @var array<string, mixed> $adminAnalytics  from backend/lib/admin_analytics.php */
/** @var array<string, array{avg: float|null, count: int}> $admin_evaluation_averages */

$admin_analytics_panel_open = !empty($admin_analytics_panel_open);
$eventStats = is_array($eventStats ?? null) ? $eventStats : [];
$feedbackStats = is_array($feedbackStats ?? null) ? $feedbackStats : [];
$adminAnalytics = is_array($adminAnalytics ?? null) ? $adminAnalytics : [];
$admin_evaluation_averages = is_array($admin_evaluation_averages ?? null) ? $admin_evaluation_averages : [];
$panelEnterClass = $admin_analytics_panel_open ? ' adm-dash-panel--enter' : '';

$aP = (array) ($adminAnalytics['participation'] ?? []);
$aR = (array) ($adminAnalytics['reach'] ?? []);
$aAct = (array) ($adminAnalytics['activities'] ?? []);
$aFb = (array) ($adminAnalytics['feedback'] ?? []);
$aInsights = (array) ($adminAnalytics['insights'] ?? []);
$aDept = (array) ($adminAnalytics['dept_participation'] ?? []);
$aYear = (array) ($adminAnalytics['year_participation'] ?? []);
$aTop = (array) ($adminAnalytics['top_events'] ?? []);
$aOrgs = (array) ($adminAnalytics['organizers'] ?? []);

/** Format a number or percentage; null → em dash. */
$fmtPct = static function ($v): string {
    if ($v === null) {
        return '—';
    }
    $n = (float) $v;
    return (fmod($n, 1.0) === 0.0 ? (string) (int) $n : number_format($n, 1)) . '%';
};
$fmtNum = static function ($v, int $dec = 1): string {
    if ($v === null) {
        return '—';
    }
    $n = (float) $v;
    return fmod($n, 1.0) === 0.0 ? number_format($n) : number_format($n, $dec);
};
$fmtRating = static function ($v): string {
    return $v === null ? '—' : number_format((float) $v, 2);
};
$plural = static function (int $n, string $one, string $many): string {
    return $n === 1 ? "1 {$one}" : number_format($n) . " {$many}";
};

$fbCount = (int) ($feedbackStats['total_feedback'] ?? 0);
$fbAvgLabel = $fbCount > 0 ? number_format((float) ($feedbackStats['avg_rating'] ?? 0), 2) : '—';
$fbHint = $plural($fbCount, 'response', 'responses');

$walkins = (int) ($aP['walkins'] ?? 0);
$endedRsvps = (int) ($aP['ended_rsvps'] ?? 0);

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
                    Events, attendance, student engagement, and feedback across the system.
                </p>
            </div>
        </header>

        <?php if ($aInsights !== []): ?>
            <h3 class="adm-analytics-heading">Key insights</h3>
            <ul class="adm-insights">
                <?php foreach ($aInsights as $tip): ?>
                    <li class="adm-insight adm-insight--<?= htmlspecialchars((string) ($tip['tone'] ?? 'info')) ?>">
                        <i class="fas <?= htmlspecialchars((string) ($tip['icon'] ?? 'fa-circle-info')) ?>" aria-hidden="true"></i>
                        <span><?= htmlspecialchars((string) ($tip['text'] ?? '')) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h3 class="adm-analytics-heading">Events</h3>
        <div class="adm-stats adm-analytics-stats adm-analytics-stats--auto">
            <div class="adm-stat-card">
                <div class="adm-stat-label">Total events</div>
                <div class="adm-stat-value"><?= (int) ($eventStats['total'] ?? 0) ?></div>
                <div class="adm-stat-hint">All statuses</div>
            </div>
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
                <div class="adm-stat-hint">Finished or completed</div>
            </div>
            <div class="adm-stat-card">
                <div class="adm-stat-label">Rejected</div>
                <div class="adm-stat-value"><?= (int) ($eventStats['rejected'] ?? 0) ?></div>
            </div>
            <div class="adm-stat-card">
                <div class="adm-stat-label">Events held</div>
                <div class="adm-stat-value"><?= (int) ($aP['held_events'] ?? 0) ?></div>
                <div class="adm-stat-hint">Approved events that already ended</div>
            </div>
        </div>

        <h3 class="adm-analytics-heading">Attendance</h3>
        <div class="adm-stats adm-analytics-stats adm-analytics-stats--auto">
            <div class="adm-stat-card">
                <div class="adm-stat-label">RSVPs</div>
                <div class="adm-stat-value"><?= number_format((int) ($aP['registrations'] ?? 0)) ?></div>
                <div class="adm-stat-hint">Students who signed up in advance</div>
            </div>
            <div class="adm-stat-card">
                <div class="adm-stat-label">Check-ins</div>
                <div class="adm-stat-value"><?= number_format((int) ($aP['checkins'] ?? 0)) ?></div>
                <div class="adm-stat-hint">Marked present<?= $walkins > 0 ? ' · incl. ' . $plural($walkins, 'walk-in', 'walk-ins') : '' ?></div>
            </div>
            <div class="adm-stat-card">
                <div class="adm-stat-label">Show-up rate</div>
                <div class="adm-stat-value"><?= htmlspecialchars($fmtPct($aP['show_up_rate'] ?? null)) ?></div>
                <div class="adm-stat-hint"><?= $endedRsvps > 0 ? 'RSVPs who attended, finished events only' : 'No finished events with RSVPs yet' ?></div>
            </div>
            <div class="adm-stat-card">
                <div class="adm-stat-label">No-shows</div>
                <div class="adm-stat-value"><?= number_format((int) ($aP['no_shows'] ?? 0)) ?></div>
                <div class="adm-stat-hint">RSVP'd but did not attend</div>
            </div>
            <div class="adm-stat-card">
                <div class="adm-stat-label">Avg. attendance</div>
                <div class="adm-stat-value"><?= htmlspecialchars($fmtNum($aP['avg_attendance'] ?? null)) ?></div>
                <div class="adm-stat-hint">Check-ins per finished event</div>
            </div>
            <div class="adm-stat-card">
                <div class="adm-stat-label">Unique attendees</div>
                <div class="adm-stat-value"><?= number_format((int) ($aP['unique_attendees'] ?? 0)) ?></div>
                <div class="adm-stat-hint">Different people who checked in</div>
            </div>
        </div>

        <h3 class="adm-analytics-heading">Student reach</h3>
        <div class="adm-stats adm-analytics-stats adm-analytics-stats--auto">
            <div class="adm-stat-card">
                <div class="adm-stat-label">Active students</div>
                <div class="adm-stat-value"><?= number_format((int) ($aR['students'] ?? 0)) ?></div>
                <div class="adm-stat-hint">+<?= (int) ($aR['new_30d'] ?? 0) ?> joined in the last 30 days</div>
            </div>
            <div class="adm-stat-card">
                <div class="adm-stat-label">Participation rate</div>
                <div class="adm-stat-value"><?= htmlspecialchars($fmtPct($aR['participation_rate'] ?? null)) ?></div>
                <div class="adm-stat-hint"><?= number_format((int) ($aR['participating'] ?? 0)) ?> attended at least one event</div>
            </div>
            <div class="adm-stat-card">
                <div class="adm-stat-label">Never attended</div>
                <div class="adm-stat-value"><?= number_format((int) ($aR['never_attended'] ?? 0)) ?></div>
                <div class="adm-stat-hint">Students with zero check-ins</div>
            </div>
            <div class="adm-stat-card">
                <div class="adm-stat-label">Active last 30 days</div>
                <div class="adm-stat-value"><?= number_format((int) ($aR['active_30d'] ?? 0)) ?></div>
                <div class="adm-stat-hint">Students who checked in recently</div>
            </div>
            <div class="adm-stat-card">
                <div class="adm-stat-label">Events per student</div>
                <div class="adm-stat-value"><?= htmlspecialchars($fmtNum($aR['avg_events_per_participant'] ?? null)) ?></div>
                <div class="adm-stat-hint">Average, among students who attend</div>
            </div>
        </div>

        <div class="adm-charts adm-charts--analytics">
            <div class="adm-chart-card adm-chart-card--wide">
                <h6 class="mb-0">Last 12 months</h6>
                <p class="adm-chart-card__note">Events held (by start date) and check-ins (by check-in date) per month.</p>
                <div class="adm-chart-wrap adm-chart-wrap--trend" id="adminChartTrendWrap">
                    <canvas id="adminChartTrend" aria-label="Monthly events and check-ins"></canvas>
                </div>
            </div>
            <div class="adm-chart-card adm-chart-card--wide">
                <h6 class="mb-0">Events by department</h6>
                <p class="adm-chart-card__note">Approved events. An event open to several departments counts once for each.</p>
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
                    <p class="adm-chart-card__note"><?= htmlspecialchars($fbHint) ?> · average <?= htmlspecialchars($fbAvgLabel) ?>/5</p>
                    <div class="adm-chart-wrap" id="adminChartFeedbackWrap">
                        <canvas id="adminChartFeedback" aria-label="Bar chart of feedback ratings"></canvas>
                    </div>
                </div>
            </div>
            <div class="adm-charts__split">
                <div class="adm-chart-card">
                    <h6 class="mb-0">Check-ins by day of week</h6>
                    <div class="adm-chart-wrap" id="adminChartWeekdayWrap">
                        <canvas id="adminChartWeekday" aria-label="Check-ins by day of week"></canvas>
                    </div>
                </div>
                <div class="adm-chart-card">
                    <h6 class="mb-0">Check-ins by time of day</h6>
                    <div class="adm-chart-wrap" id="adminChartHoursWrap">
                        <canvas id="adminChartHours" aria-label="Check-ins by hour"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <h3 class="adm-analytics-heading mt-4">Participation by department</h3>
        <div class="adm-chart-card mb-3">
            <?php if ($aDept === []): ?>
                <div class="adm-chart-empty">No students yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 adm-analytics-table">
                        <thead><tr><th>Department</th><th class="text-end">Students</th><th class="text-end">Attended</th><th class="text-end">Check-ins</th><th style="min-width:160px">Participation</th></tr></thead>
                        <tbody>
                        <?php foreach ($aDept as $d): $rate = $d['rate']; ?>
                            <tr>
                                <td><?= htmlspecialchars((string) $d['dept']) ?></td>
                                <td class="text-end"><?= number_format((int) $d['students']) ?></td>
                                <td class="text-end"><?= number_format((int) $d['participants']) ?></td>
                                <td class="text-end"><?= number_format((int) $d['checkins']) ?></td>
                                <td>
                                    <div class="adm-rate">
                                        <div class="adm-eval-bar"><span style="width: <?= $rate === null ? 0 : min(100, (float) $rate) ?>%"></span></div>
                                        <strong><?= htmlspecialchars($fmtPct($rate)) ?></strong>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($aYear !== []): ?>
            <h3 class="adm-analytics-heading">Participation by year level</h3>
            <div class="adm-chart-card mb-3">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 adm-analytics-table">
                        <thead><tr><th>Year level</th><th class="text-end">Students</th><th class="text-end">Attended</th><th style="min-width:160px">Participation</th></tr></thead>
                        <tbody>
                        <?php foreach ($aYear as $y): $rate = $y['rate']; ?>
                            <tr>
                                <td><?= htmlspecialchars((string) $y['year']) ?></td>
                                <td class="text-end"><?= number_format((int) $y['students']) ?></td>
                                <td class="text-end"><?= number_format((int) $y['participants']) ?></td>
                                <td>
                                    <div class="adm-rate">
                                        <div class="adm-eval-bar"><span style="width: <?= $rate === null ? 0 : min(100, (float) $rate) ?>%"></span></div>
                                        <strong><?= htmlspecialchars($fmtPct($rate)) ?></strong>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <h3 class="adm-analytics-heading">Top events by attendance</h3>
        <div class="adm-chart-card mb-3">
            <?php if ($aTop === []): ?>
                <div class="adm-chart-empty">No attendance recorded yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 adm-analytics-table">
                        <thead><tr><th>Event</th><th>Date</th><th class="text-end">RSVPs</th><th class="text-end">Attended</th><th class="text-end">Show-up</th><th class="text-end">Capacity used</th><th class="text-end">Rating</th></tr></thead>
                        <tbody>
                        <?php foreach ($aTop as $e): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) $e['title']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars((string) $e['organizer']) ?><?= (int) $e['walkins'] > 0 ? ' · ' . (int) $e['walkins'] . ' walk-in' . ((int) $e['walkins'] === 1 ? '' : 's') : '' ?></div>
                                </td>
                                <td class="text-nowrap"><?= $e['date'] ? htmlspecialchars(date('M j, Y', strtotime((string) $e['date']))) : '—' ?></td>
                                <td class="text-end"><?= number_format((int) $e['rsvps']) ?></td>
                                <td class="text-end fw-semibold"><?= number_format((int) $e['attended']) ?></td>
                                <td class="text-end"><?= $e['is_ended'] ? htmlspecialchars($fmtPct($e['show_up'])) : '<span class="text-muted small">Upcoming</span>' ?></td>
                                <td class="text-end"><?= $e['capacity'] ? htmlspecialchars($fmtPct($e['fill_rate'])) . ' <span class="text-muted small">of ' . (int) $e['capacity'] . '</span>' : '<span class="text-muted small">No cap</span>' ?></td>
                                <td class="text-end"><?= $e['avg_rating'] !== null ? htmlspecialchars($fmtRating($e['avg_rating'])) . ' <span class="text-muted small">(' . (int) $e['fb_count'] . ')</span>' : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <h3 class="adm-analytics-heading">Organizer performance</h3>
        <div class="adm-chart-card mb-3">
            <?php if ($aOrgs === []): ?>
                <div class="adm-chart-empty">No approved events yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 adm-analytics-table">
                        <thead><tr><th>Organizer</th><th class="text-end">Events</th><th class="text-end">Total attended</th><th class="text-end">Show-up rate</th><th class="text-end">Avg rating</th></tr></thead>
                        <tbody>
                        <?php foreach ($aOrgs as $o): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) $o['name']) ?></td>
                                <td class="text-end"><?= (int) $o['events'] ?></td>
                                <td class="text-end"><?= number_format((int) $o['attended']) ?></td>
                                <td class="text-end"><?= htmlspecialchars($fmtPct($o['show_up'])) ?></td>
                                <td class="text-end"><?= $o['avg_rating'] !== null ? htmlspecialchars($fmtRating($o['avg_rating'])) . ' <span class="text-muted small">(' . (int) $o['fb_count'] . ')</span>' : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <h3 class="adm-analytics-heading">Activities &amp; feedback</h3>
        <div class="adm-stats adm-analytics-stats adm-analytics-stats--auto">
            <?php if ($aAct !== []): ?>
                <div class="adm-stat-card">
                    <div class="adm-stat-label">Activities</div>
                    <div class="adm-stat-value"><?= number_format((int) ($aAct['activities'] ?? 0)) ?></div>
                    <div class="adm-stat-hint">Sub-events inside approved events</div>
                </div>
                <div class="adm-stat-card">
                    <div class="adm-stat-label">Activity RSVPs</div>
                    <div class="adm-stat-value"><?= number_format((int) ($aAct['rsvps'] ?? 0)) ?></div>
                </div>
                <div class="adm-stat-card">
                    <div class="adm-stat-label">Activity check-ins</div>
                    <div class="adm-stat-value"><?= number_format((int) ($aAct['checkins'] ?? 0)) ?></div>
                    <div class="adm-stat-hint"><?= htmlspecialchars($fmtNum($aAct['avg_checkins'] ?? null)) ?> per activity on average</div>
                </div>
            <?php endif; ?>
            <?php if ($aFb !== []): ?>
                <div class="adm-stat-card">
                    <div class="adm-stat-label">Feedback response rate</div>
                    <div class="adm-stat-value"><?= htmlspecialchars($fmtPct($aFb['response_rate'] ?? null)) ?></div>
                    <div class="adm-stat-hint"><?= $plural((int) ($aFb['responses'] ?? 0), 'response', 'responses') ?> from attendees of finished events</div>
                </div>
                <div class="adm-stat-card">
                    <div class="adm-stat-label">Satisfied (4–5★)</div>
                    <div class="adm-stat-value"><?= htmlspecialchars($fmtPct($aFb['satisfied_pct'] ?? null)) ?></div>
                    <div class="adm-stat-hint">Unhappy (1–2★): <?= htmlspecialchars($fmtPct($aFb['unhappy_pct'] ?? null)) ?></div>
                </div>
            <?php endif; ?>
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
