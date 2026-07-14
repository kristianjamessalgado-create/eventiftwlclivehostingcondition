<?php
/** @var array<int, array<string, mixed>> $activities_hub_events */
$activities_hub_events = $activities_hub_events ?? [];
$activities_hub_count = count($activities_hub_events);
$activities_hub_current_event_id = (int) ($activities_hub_current_event_id ?? 0);
?>
<div class="modal fade" id="activitiesHubPickModal" tabindex="-1" aria-labelledby="activitiesHubPickModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="activitiesHubPickModalLabel">
          <i class="fas fa-th-large me-2"></i>Activities hub
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-3">Choose an event to browse its day activities, schedules, and check-ins.</p>
        <?php if ($activities_hub_count > 0): ?>
          <div class="list-group">
            <?php foreach ($activities_hub_events as $ev): ?>
              <?php
                $eid = (int) ($ev['id'] ?? 0);
                $st = strtolower((string) ($ev['status'] ?? ''));
                $actCount = (int) ($ev['activity_count'] ?? 0);
                $hubHref = BASE_URL . '/event_activities.php?id=' . $eid . '#eah-sp-all';
              ?>
              <a class="list-group-item list-group-item-action text-decoration-none text-reset<?= $eid === $activities_hub_current_event_id ? ' active' : '' ?>" href="<?= htmlspecialchars($hubHref) ?>"<?= $eid === $activities_hub_current_event_id ? ' aria-current="true"' : '' ?>>
                <div class="d-flex w-100 justify-content-between align-items-start gap-2">
                  <div>
                    <h6 class="mb-1">
                      <i class="fas fa-calendar-day me-1 text-primary"></i>
                      <?= htmlspecialchars($ev['title'] ?? 'Untitled') ?>
                    </h6>
                    <div class="small text-muted">
                      <?php if (!empty($ev['date'])): ?>
                        <?= date('M j, Y', strtotime((string) $ev['date'])) ?>
                      <?php endif; ?>
                      <?php if (!empty($ev['location'])): ?>
                        <?php if (!empty($ev['date'])): ?> · <?php endif; ?>
                        <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars((string) $ev['location']) ?>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="text-end flex-shrink-0">
                    <?php if ($actCount > 0): ?>
                      <span class="badge bg-primary"><?= $actCount ?> activit<?= $actCount === 1 ? 'y' : 'ies' ?></span>
                    <?php endif; ?>
                    <?php if ($st !== ''): ?>
                      <div class="small text-muted mt-1 text-capitalize"><?= htmlspecialchars($st) ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="text-muted mb-0">No events with activities are available for your account yet.</p>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
