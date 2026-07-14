<?php
/** @var list<array<string, mixed>> $multimedia_photo_activity_logs */
$multimedia_photo_activity_logs = is_array($multimedia_photo_activity_logs ?? null)
    ? $multimedia_photo_activity_logs
    : [];
?>
<div class="modal fade" id="photoActivityLogModal" tabindex="-1" aria-labelledby="photoActivityLogModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="photoActivityLogModalLabel">
          <i class="fas fa-clipboard-list me-2"></i>Photo activity log
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-3">Uploads, approvals, and rejections from your multimedia team.</p>
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
                    $actorLabel = $actorName !== '' ? $actorName : 'System';
                    if ($actorUserId !== '') {
                        $actorLabel .= ' · ' . $actorUserId;
                    }
                    $actionClass = 'mm-activity-log-badge';
                    if ($action === 'photo_uploaded') {
                        $actionClass .= ' mm-activity-log-badge--upload';
                    } elseif ($action === 'photo_approved' || $action === 'photo_bulk_approved') {
                        $actionClass .= ' mm-activity-log-badge--approve';
                    } elseif ($action === 'photo_rejected') {
                        $actionClass .= ' mm-activity-log-badge--reject';
                    } elseif ($action === 'photo_deleted') {
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
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
