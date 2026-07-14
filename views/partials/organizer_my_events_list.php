<?php
/** @var list<array<string, mixed>> $events */

$eventsSorted = is_array($events ?? null) ? $events : [];
if ($eventsSorted !== []) {
    usort($eventsSorted, static function ($a, $b) {
        return strtotime($a['date'] ?? '') <=> strtotime($b['date'] ?? '');
    });
}
?>

<?php if ($eventsSorted === []): ?>
    <div class="org-dash-panel__empty">
        <div class="org-dash-panel__empty-icon" aria-hidden="true"><i class="fas fa-calendar-plus"></i></div>
        <h3 class="org-dash-panel__empty-title">No events yet</h3>
        <p class="org-dash-panel__empty-text mb-0">Tap <strong>Create event</strong> below or use the <strong>+</strong> button in the header.</p>
    </div>
<?php else: ?>
    <div class="events-list org-my-events-list">
        <?php foreach ($eventsSorted as $i => $event): ?>
            <?php $staggerStyle = '--panel-stagger: ' . min($i, 8) * 0.035 . 's'; ?>
            <div class="event-item org-my-event-card" style="<?= htmlspecialchars($staggerStyle) ?>">
                <div class="event-date-badge">
                    <span class="event-month"><?= htmlspecialchars(date('M', strtotime($event['date'] ?? 'now'))) ?></span>
                    <span class="event-day"><?= htmlspecialchars(date('d', strtotime($event['date'] ?? 'now'))) ?></span>
                </div>
                <div class="event-details">
                    <h4 class="event-title"><?= htmlspecialchars($event['title'] ?? 'Untitled') ?></h4>
                    <p class="event-meta">
                        <i class="fas fa-map-marker-alt"></i>
                        <?= htmlspecialchars($event['location'] ?? 'TBA') ?>
                    </p>
                    <p class="event-meta">
                        <i class="fas fa-users"></i>
                        <?= htmlspecialchars(eventify_format_department_label((string) ($event['department'] ?? 'ALL'))) ?>
                    </p>
                    <?php
                    $evStatus = $event['status'] ?? '';
                    $evRejectReason = trim($event['reject_reason'] ?? '');
                    $evStatusLower = strtolower((string) $evStatus);
                    $evStatusUi = function_exists('eventify_event_status_ui') ? eventify_event_status_ui($event) : ['label' => ucfirst($evStatusLower ?: 'Unknown'), 'badge' => 'secondary', 'is_live' => ($evStatusLower === 'active')];
                    $evIsLive = !empty($evStatusUi['is_live']);
                    $evRegModeUi = function_exists('eventify_registration_mode_ui') ? eventify_registration_mode_ui($event) : ['label' => 'Free RSVP', 'badge_class' => 'bg-primary'];
                    ?>
                    <p class="event-meta mb-2">
                        <span class="badge bg-<?= htmlspecialchars($evStatusUi['badge']) ?>"><?= htmlspecialchars($evStatusUi['label']) ?></span>
                        <?php $regUi = $evRegModeUi; include __DIR__ . '/registration_mode_badge.php'; ?>
                        <?php if (!$evIsLive && in_array($evStatusLower, ['closed', 'completed', 'active'], true)): ?>
                            <span class="small text-muted ms-1">— ticket sales and QR check-in are off</span>
                        <?php endif; ?>
                    </p>
                    <?php if ($evStatus === 'rejected' && $evRejectReason !== ''): ?>
                        <p class="event-meta text-danger small mb-1"><i class="fas fa-info-circle"></i> <strong>Rejection reason:</strong> <?= htmlspecialchars($evRejectReason) ?></p>
                    <?php endif; ?>
                    <div class="event-actions d-flex gap-1 flex-wrap">
                        <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/backend/auth/edit_event.php?id=<?= urlencode($event['id']) ?>">Edit</a>
                        <?php if ($evIsLive): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/event_qr.php?id=<?= urlencode($event['id']) ?>" target="_blank" rel="noopener" title="Show QR for check-in"><i class="fas fa-qrcode"></i> QR</a>
                        <?php else: ?>
                            <span class="btn btn-sm btn-outline-secondary disabled" title="Event ended — check-in QR disabled"><i class="fas fa-qrcode"></i> QR</span>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline-info" href="<?= BASE_URL ?>/event_attendance.php?id=<?= urlencode($event['id']) ?>" target="_blank" rel="noopener" title="View who attended"><i class="fas fa-clipboard-check"></i> Attendance</a>
                        <?php if ($evIsLive): ?>
                            <a class="btn btn-sm btn-outline-success" href="<?= BASE_URL ?>/manage_event_tickets.php?event_id=<?= urlencode($event['id']) ?>" title="Ticket sales"><i class="fas fa-ticket-alt"></i> Tickets</a>
                        <?php else: ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/manage_event_tickets.php?event_id=<?= urlencode($event['id']) ?>" title="View ticket history"><i class="fas fa-ticket-alt"></i> Tickets</a>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/event_rsvp.php?id=<?= urlencode($event['id']) ?>" target="_blank" rel="noopener" title="RSVP list"><i class="fas fa-user-check"></i> RSVP</a>
                        <?php if ($evStatusLower === 'pending'): ?>
                            <?php $evHasActiveOtp = !empty($event['has_active_otp']); ?>
                            <?php if ($evHasActiveOtp): ?>
                            <form method="POST" action="<?= BASE_URL ?>/backend/auth/verify_event_approval_otp.php" class="d-inline-flex gap-1 align-items-center flex-wrap w-100 mt-1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                                <input type="hidden" name="return_modal" value="events">
                                <input type="text" name="otp_code" class="form-control form-control-sm" style="width: 110px;" maxlength="6" placeholder="Enter OTP" required pattern="\d{6}" inputmode="numeric" autocomplete="one-time-code" aria-label="Event verification OTP">
                                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-key me-1"></i>Verify OTP</button>
                                <span class="small text-muted w-100">Use the <strong>latest</strong> OTP from email or notifications (expires in 10 minutes).</span>
                            </form>
                            <?php else: ?>
                            <p class="small text-muted mb-0 w-100"><i class="fas fa-hourglass-half me-1"></i>Waiting for admin to review and send an OTP.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($evStatusLower === 'pending'): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary js-organizer-event-status-btn" data-eventify-action="cancel" data-eventify-event-id="<?= (int) $event['id'] ?>"><i class="fas fa-undo"></i> Withdraw</button>
                        <?php elseif ($evStatusLower === 'active'): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary js-organizer-event-status-btn" data-eventify-action="close" data-eventify-event-id="<?= (int) $event['id'] ?>"><i class="fas fa-flag-checkered"></i> End entire event</button>
                        <?php elseif (in_array($evStatusLower, ['closed', 'completed'], true) && function_exists('eventify_event_can_organizer_reopen') && eventify_event_can_organizer_reopen($event)): ?>
                            <button type="button" class="btn btn-sm btn-outline-success js-organizer-event-status-btn" data-eventify-action="reopen" data-eventify-event-id="<?= (int) $event['id'] ?>"><i class="fas fa-redo"></i> Reopen event</button>
                            <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/event_activities.php?id=<?= urlencode($event['id']) ?>"><i class="fas fa-th-large"></i> Activities hub</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
