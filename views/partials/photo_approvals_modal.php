<?php
/** @var list<array<string, mixed>> $pending_photos_queue */
$pending_photos_queue = is_array($pending_photos_queue ?? null) ? $pending_photos_queue : [];
$pending_photo_count = (int) ($pending_photo_count ?? count($pending_photos_queue));
?>
<div class="modal fade" id="photoApprovalsModal" tabindex="-1" aria-labelledby="photoApprovalsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content efy-modal">
      <div class="modal-header efy-modal__header">
        <div>
          <span class="efy-modal__eyebrow">Multimedia</span>
          <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="photoApprovalsModalLabel">
            <i class="fas fa-user-shield" aria-hidden="true"></i>
            Photo approvals
          </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body efy-modal__body">
        <p class="efy-form-help mb-3">Review uploads from your multimedia team. Approved photos appear for students; rejected photos stay hidden.</p>
        <?php if ($pending_photos_queue === []): ?>
          <p class="text-muted mb-0">No photos waiting for approval right now.</p>
        <?php else: ?>
          <div class="list-group">
            <?php foreach ($pending_photos_queue as $ph): ?>
              <?php
                $phId = (int) ($ph['id'] ?? 0);
                $phEventId = (int) ($ph['event_id'] ?? 0);
                $phSessionId = (int) ($ph['session_id'] ?? 0);
                $phPath = (string) ($ph['file_path'] ?? '');
                $phEventTitle = (string) ($ph['event_title'] ?? 'Event');
                $phSessionTitle = trim((string) ($ph['session_title'] ?? ''));
                $phUploader = (string) ($ph['uploader_name'] ?? 'Multimedia');
                $phCreated = !empty($ph['created_at']) ? date('M j, Y g:i A', strtotime((string) $ph['created_at'])) : '';
                $phImgUrl = BASE_URL . '/' . ltrim($phPath, '/');
                $activityHref = $phSessionId > 0
                    ? BASE_URL . '/event_activities.php?id=' . $phEventId . '&activity=' . $phSessionId
                    : '';
                $phLabel = $phSessionTitle !== ''
                    ? $phEventTitle . ' · ' . $phSessionTitle
                    : $phEventTitle;
              ?>
              <div class="list-group-item">
                <div class="d-flex w-100 justify-content-between align-items-start gap-3">
                  <div class="d-flex gap-3 flex-grow-1 min-w-0">
                    <a href="<?= htmlspecialchars($phImgUrl) ?>" target="_blank" rel="noopener" class="mm-approval-list__thumb flex-shrink-0" title="Open photo">
                      <img src="<?= htmlspecialchars($phImgUrl) ?>" alt="Pending photo" loading="lazy" decoding="async">
                    </a>
                    <div class="min-w-0">
                      <h6 class="mb-1">
                        <i class="fas fa-image me-1 text-primary"></i>
                        <?= htmlspecialchars($phEventTitle) ?>
                      </h6>
                      <div class="small text-muted">
                        <?php if ($phSessionTitle !== ''): ?>
                          <i class="fas fa-bolt me-1"></i><?= htmlspecialchars($phSessionTitle) ?>
                          ·
                        <?php endif; ?>
                        <i class="fas fa-user me-1"></i><?= htmlspecialchars($phUploader) ?>
                        <?php if ($phCreated !== ''): ?>
                          · <?= htmlspecialchars($phCreated) ?>
                        <?php endif; ?>
                      </div>
                      <?php if ($activityHref !== ''): ?>
                        <a href="<?= htmlspecialchars($activityHref) ?>" class="small text-decoration-none">View activity</a>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="text-end flex-shrink-0">
                    <span class="badge bg-warning text-dark mb-2">Pending</span>
                    <div class="d-flex flex-wrap gap-1 justify-content-end">
                      <form method="POST" action="<?= BASE_URL ?>/backend/auth/moderate_event_photo.php" class="d-inline js-photo-moderate-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="photo_id" value="<?= $phId ?>">
                        <input type="hidden" name="event_id" value="<?= $phEventId ?>">
                        <input type="hidden" name="session_id" value="<?= $phSessionId ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="button" class="btn btn-success btn-sm js-photo-moderate-trigger" data-action="approve" data-photo-label="<?= htmlspecialchars($phLabel, ENT_QUOTES, 'UTF-8') ?>">
                          <i class="fas fa-check me-1"></i>Approve
                        </button>
                      </form>
                      <form method="POST" action="<?= BASE_URL ?>/backend/auth/moderate_event_photo.php" class="d-inline js-photo-moderate-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="photo_id" value="<?= $phId ?>">
                        <input type="hidden" name="event_id" value="<?= $phEventId ?>">
                        <input type="hidden" name="session_id" value="<?= $phSessionId ?>">
                        <input type="hidden" name="action" value="reject">
                        <button type="button" class="btn btn-outline-danger btn-sm js-photo-moderate-trigger" data-action="reject" data-photo-label="<?= htmlspecialchars($phLabel, ENT_QUOTES, 'UTF-8') ?>">
                          <i class="fas fa-times me-1"></i>Reject
                        </button>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer efy-modal__footer">
        <button type="button" class="btn efy-btn-muted btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/photo_moderation_confirm_modal.php'; ?>
