<?php
/** @var bool $mm_photo_activity_panel_open */
/** @var list<array<string, mixed>> $multimedia_photo_activity_logs */

$mm_photo_activity_panel_open = !empty($mm_photo_activity_panel_open);
$multimedia_photo_activity_logs = is_array($multimedia_photo_activity_logs ?? null)
    ? $multimedia_photo_activity_logs
    : [];
$mm_photo_activity_count = count($multimedia_photo_activity_logs);
$panelEnterClass = $mm_photo_activity_panel_open ? ' mm-dash-panel--enter' : '';
?>

<section
    class="mm-dash-panel mm-photo-activity-panel<?= $panelEnterClass ?><?= $mm_photo_activity_panel_open ? '' : ' d-none' ?>"
    id="mmPhotoActivityPanel"
    aria-label="Photo activity log"
    <?= $mm_photo_activity_panel_open ? '' : ' hidden' ?>
>
    <div class="mm-dash-panel__shell">
        <div class="mm-dash-panel__toolbar">
            <button type="button" class="mm-dash-panel__back" data-mm-panel="home">
                <span class="mm-dash-panel__back-icon" aria-hidden="true"><i class="fas fa-arrow-left"></i></span>
                <span class="mm-dash-panel__back-label">Back to calendar</span>
            </button>
            <?php if ($mm_photo_activity_count > 0): ?>
                <span class="mm-dash-panel__count-pill">
                    <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                    <?= $mm_photo_activity_count ?> entries
                </span>
            <?php endif; ?>
        </div>

        <header class="mm-dash-panel__hero">
            <div class="mm-dash-panel__hero-icon" aria-hidden="true"><i class="fas fa-clipboard-list"></i></div>
            <div class="mm-dash-panel__hero-text">
                <h2 class="mm-dash-panel__title">Photo activity log</h2>
                <p class="mm-dash-panel__subtitle mb-0">
                    Uploads, approvals, and rejections from your multimedia team.
                </p>
            </div>
        </header>

        <div class="mm-activity-log-wrap">
            <?php if ($multimedia_photo_activity_logs === []): ?>
                <div class="mm-activity-log-empty">
                    <i class="fas fa-clipboard-check"></i>
                    <p class="mb-0">No photo activity recorded yet.</p>
                </div>
            <?php else: ?>
                <table class="mm-activity-log-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Account</th>
                            <th>Action</th>
                            <th>Target</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($multimedia_photo_activity_logs as $log): ?>
                            <?php
                                $action = (string) ($log['action'] ?? '');
                                $actorName = trim((string) ($log['actor_name'] ?? ''));
                                $actorUserId = trim((string) ($log['actor_user_id'] ?? ''));
                                $actionClass = 'mm-activity-log-badge';
                                if ($action === 'photo_uploaded') {
                                    $actionClass .= ' mm-activity-log-badge--upload';
                                } elseif ($action === 'photo_approved' || $action === 'photo_bulk_approved') {
                                    $actionClass .= ' mm-activity-log-badge--approve';
                                } elseif ($action === 'photo_rejected' || $action === 'photo_deleted') {
                                    $actionClass .= ' mm-activity-log-badge--reject';
                                }
                            ?>
                            <tr>
                                <td>
                                    <span class="text-muted small">
                                        <?= htmlspecialchars(date('Y-m-d H:i', strtotime((string) ($log['created_at'] ?? 'now')))) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($actorName !== '' ? $actorName : 'System') ?></strong>
                                    <?php if ($actorUserId !== ''): ?>
                                        <div class="small text-muted"><?= htmlspecialchars($actorUserId) ?></div>
                                    <?php elseif (!empty($log['actor_role'])): ?>
                                        <div class="small text-muted"><?= htmlspecialchars((string) $log['actor_role']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="<?= htmlspecialchars($actionClass) ?>">
                                        <?= htmlspecialchars(eventify_photo_activity_label($action)) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($log['target_type'])): ?>
                                        <span class="small text-muted">
                                            <?= htmlspecialchars(ucfirst((string) $log['target_type'])) ?>
                                            <?php if (!empty($log['target_id'])): ?>
                                                #<?= (int) $log['target_id'] ?>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="small"><?= htmlspecialchars((string) ($log['details'] ?? '')) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</section>
