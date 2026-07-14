<?php
/** @var bool $student_attendance_panel_open */
/** @var list<array<string, mixed>> $attendance_records */
/** @var list<array<string, mixed>> $activity_attendance_records */

$student_attendance_panel_open = !empty($student_attendance_panel_open);
$attendance_records = is_array($attendance_records ?? null) ? $attendance_records : [];
$activity_attendance_records = is_array($activity_attendance_records ?? null) ? $activity_attendance_records : [];
$eventCount = count($attendance_records);
$activityCount = count($activity_attendance_records);
$panelEnterClass = $student_attendance_panel_open ? ' student-dash-panel--enter' : '';

$thisYear = date('Y');
$thisYearAttended = 0;
foreach ($attendance_records as $rec) {
    if (!empty($rec['time_in']) && date('Y', strtotime((string) $rec['time_in'])) === $thisYear) {
        $thisYearAttended++;
    }
}
?>

<section
    class="student-dash-panel student-attendance-panel<?= $panelEnterClass ?><?= $student_attendance_panel_open ? '' : ' d-none' ?>"
    id="studentAttendancePanel"
    aria-label="My attendance"
    <?= $student_attendance_panel_open ? '' : ' hidden' ?>
>
    <div class="student-dash-panel__shell">
        <div class="student-dash-panel__toolbar">
            <button type="button" class="student-dash-panel__back" data-student-panel="home">
                <span class="student-dash-panel__back-icon" aria-hidden="true"><i class="fas fa-arrow-left"></i></span>
                <span class="student-dash-panel__back-label">Back to calendar</span>
            </button>
            <?php if ($eventCount > 0 || $activityCount > 0): ?>
                <span class="student-dash-panel__count-pill">
                    <i class="fas fa-clipboard-check" aria-hidden="true"></i>
                    <?= $eventCount + $activityCount ?> record<?= ($eventCount + $activityCount) === 1 ? '' : 's' ?>
                </span>
            <?php endif; ?>
        </div>

        <header class="student-dash-panel__hero">
            <div class="student-dash-panel__hero-icon" aria-hidden="true"><i class="fas fa-clipboard-check"></i></div>
            <div class="student-dash-panel__hero-text">
                <h2 class="student-dash-panel__title">My attendance</h2>
                <p class="student-dash-panel__subtitle mb-0">
                    Quick view on your dashboard — events and activities you checked into via QR.
                </p>
            </div>
        </header>

        <?php if ($eventCount > 0): ?>
            <div class="student-attendance-stats">
                <span class="student-attendance-stats__pill">
                    <i class="fas fa-check" aria-hidden="true"></i>
                    <?= $eventCount ?> event<?= $eventCount === 1 ? '' : 's' ?>
                </span>
                <span class="student-attendance-stats__pill student-attendance-stats__pill--muted">
                    <i class="fas fa-calendar" aria-hidden="true"></i>
                    <?= $thisYearAttended ?> this year
                </span>
            </div>
        <?php endif; ?>

        <?php if ($eventCount === 0 && $activityCount === 0): ?>
            <div class="student-dash-panel__empty">
                <div class="student-dash-panel__empty-icon" aria-hidden="true"><i class="fas fa-qrcode"></i></div>
                <h3 class="student-dash-panel__empty-title">No check-ins yet</h3>
                <p class="student-dash-panel__empty-text mb-0">
                    Scan an event or activity QR code at the venue to record your attendance here.
                </p>
                <button type="button" class="student-dash-panel__empty-cta" data-bs-toggle="modal" data-bs-target="#scanQRModal">
                    <i class="fas fa-qrcode me-1"></i> Scan QR now
                </button>
            </div>
        <?php else: ?>
            <?php if ($eventCount > 0): ?>
                <div class="student-dash-panel__section">
                    <h3 class="student-dash-panel__section-title">
                        <i class="fas fa-calendar-check" aria-hidden="true"></i> Event check-ins
                    </h3>
                    <div class="student-attendance-list">
                        <?php foreach ($attendance_records as $i => $rec): ?>
                            <?php $staggerStyle = '--panel-stagger: ' . min($i, 6) * 0.035 . 's'; ?>
                            <article class="student-attendance-card" style="<?= htmlspecialchars($staggerStyle) ?>">
                                <div class="student-attendance-card__top">
                                    <h4 class="student-attendance-card__title"><?= htmlspecialchars((string) ($rec['event_title'] ?? 'Event')) ?></h4>
                                    <span class="student-attendance-card__badge">
                                        <i class="fas fa-check-circle" aria-hidden="true"></i> Present
                                    </span>
                                </div>
                                <div class="student-attendance-card__meta">
                                    <?php if (!empty($rec['event_date'])): ?>
                                        <span><i class="fas fa-calendar-day" aria-hidden="true"></i> <?= htmlspecialchars(date('M j, Y', strtotime((string) $rec['event_date']))) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($rec['event_location'])): ?>
                                        <span><i class="fas fa-map-marker-alt" aria-hidden="true"></i> <?= htmlspecialchars(mb_strimwidth((string) $rec['event_location'], 0, 52, '…')) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($rec['time_in'])): ?>
                                    <div class="student-attendance-card__time">
                                        Checked in <?= htmlspecialchars(date('M j, Y g:i A', strtotime((string) $rec['time_in']))) ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($activityCount > 0): ?>
                <div class="student-dash-panel__section">
                    <h3 class="student-dash-panel__section-title">
                        <i class="fas fa-th-list" aria-hidden="true"></i> Activity check-ins
                    </h3>
                    <div class="student-attendance-list student-attendance-list--compact">
                        <?php foreach ($activity_attendance_records as $i => $ar): ?>
                            <?php $staggerStyle = '--panel-stagger: ' . min($i, 6) * 0.035 . 's'; ?>
                            <article class="student-attendance-card student-attendance-card--activity" style="<?= htmlspecialchars($staggerStyle) ?>">
                                <div class="student-attendance-card__top">
                                    <h4 class="student-attendance-card__title"><?= htmlspecialchars((string) ($ar['activity_title'] ?? 'Activity')) ?></h4>
                                </div>
                                <div class="student-attendance-card__meta">
                                    <span><i class="fas fa-layer-group" aria-hidden="true"></i> <?= htmlspecialchars((string) ($ar['event_title'] ?? '')) ?></span>
                                    <?php if (!empty($ar['schedule_date'])): ?>
                                        <span><i class="fas fa-calendar-day" aria-hidden="true"></i> <?= htmlspecialchars(substr((string) $ar['schedule_date'], 0, 10)) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($ar['checked_in_at'])): ?>
                                    <div class="student-attendance-card__time">
                                        <?= htmlspecialchars(date('M j, Y g:i A', strtotime((string) $ar['checked_in_at']))) ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="student-attendance-panel__footer">
                <a class="student-attendance-panel__history-link" href="<?= BASE_URL ?>/attendance_history.php">
                    <i class="fas fa-history me-1"></i> Open full attendance list
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>
