<?php
/** @var list<array<string, mixed>> $pendingEvents */
/** @var bool $otpTableReady */
/** @var bool $usersHasOtpContactColumns */
/** @var list<array<string, mixed>> $assignableOrganizers */

$pendingEvents = is_array($pendingEvents ?? null) ? $pendingEvents : [];
$assignableOrganizers = is_array($assignableOrganizers ?? null) ? $assignableOrganizers : [];
?>
<?php if (empty($otpTableReady) || empty($usersHasOtpContactColumns)): ?>
    <div class="alert alert-warning adm-pending-alert py-2 mb-3">
        <i class="fas fa-exclamation-triangle me-1"></i>
        OTP approval requires database migration: <code>school_events_event_approval_otp.sql</code>.
    </div>
<?php endif; ?>
<?php if (empty($pendingEvents)): ?>
    <div class="adm-pending-empty">
        <div class="adm-pending-empty__icon" aria-hidden="true"><i class="fas fa-check-circle"></i></div>
        <h6 class="adm-pending-empty__title">All caught up</h6>
        <p class="adm-pending-empty__text mb-0">No events are waiting for approval right now.</p>
    </div>
<?php else: ?>
    <form method="POST" action="<?= BASE_URL ?>/backend/super_admin/update_event_status_bulk.php" id="bulkEventStatusForm">
        <?= csrf_field() ?>
        <input type="hidden" name="reject_reason" id="bulkRejectReasonInput" value="">
        <input type="hidden" name="return_panel" value="pending">
        <div class="adm-pending-toolbar">
            <label class="adm-pending-toolbar__select">
                <input type="checkbox" id="pendingHeadCheck" aria-label="Select all pending events">
                <span>Select all</span>
            </label>
            <div class="adm-pending-toolbar__actions">
                <button type="button" class="adm-pending-btn adm-pending-btn--ghost" id="bulkSelectAllPending" title="Toggle selection">
                    <i class="fas fa-check-double" aria-hidden="true"></i><span class="d-none d-sm-inline">Toggle</span>
                </button>
                <button type="button" class="adm-pending-btn adm-pending-btn--danger" id="bulkRejectBtn">
                    <i class="fas fa-times" aria-hidden="true"></i><span>Reject selected</span>
                </button>
            </div>
            <p class="adm-pending-toolbar__hint mb-0"><i class="fas fa-key me-1" aria-hidden="true"></i>Assign the correct organizer if needed, then send an OTP for them to verify and publish.</p>
        </div>
        <div class="adm-pending-list" id="admPendingList">
            <?php foreach ($pendingEvents as $ev): ?>
                <?php
                $evId = (int) ($ev['id'] ?? 0);
                $evTitle = (string) ($ev['title'] ?? 'Untitled');
                $evDesc = trim((string) ($ev['description'] ?? ''));
                $evDateRaw = trim((string) ($ev['date'] ?? ''));
                $evDateLabel = $evDateRaw;
                if ($evDateRaw !== '') {
                    $ts = strtotime($evDateRaw);
                    $evDateLabel = $ts ? date('M j, Y', $ts) : $evDateRaw;
                }
                $evLocation = trim((string) ($ev['location'] ?? ''));
                $evLocationShort = $evLocation !== '' ? mb_strimwidth($evLocation, 0, 72, '…') : '—';
                $deptLabel = ($ev['department'] ?? 'ALL') === 'ALL' ? 'All' : (string) ($ev['department'] ?? 'All');
                $evCreatedAt = trim((string) ($ev['created_at'] ?? ''));
                $isStalePending = false;
                if ($evCreatedAt !== '') {
                    $createdTs = strtotime($evCreatedAt);
                    $isStalePending = $createdTs && $createdTs <= (time() - 86400);
                }
                $deptStored = (string) ($ev['department'] ?? 'ALL');
                $orgEmail = trim((string) ($ev['organizer_contact_email'] ?? ''));
                if ($orgEmail === '') {
                    $orgEmail = trim((string) ($ev['organizer_email'] ?? ''));
                }
                $orgPhone = trim((string) ($ev['organizer_phone'] ?? ''));
                $prefMethod = (string) ($ev['organizer_contact_method'] ?? 'email');
                $canSendOtp = !empty($otpTableReady) && !empty($usersHasOtpContactColumns) && (($prefMethod === 'phone' && $orgPhone !== '') || $orgEmail !== '');
                $otpMethod = ($prefMethod === 'phone' && $orgPhone !== '') ? 'phone' : 'email';
                if ($otpMethod === 'phone') {
                    $digits = preg_replace('/\D+/', '', $orgPhone);
                    $maskedTarget = strlen($digits) >= 4
                        ? str_repeat('*', max(0, strlen($digits) - 4)) . substr($digits, -4)
                        : '***';
                } else {
                    $parts = explode('@', (string) $orgEmail, 2);
                    if (count($parts) === 2) {
                        $local = $parts[0];
                        $domain = $parts[1];
                        $maskedLocal = strlen($local) <= 2
                            ? substr($local, 0, 1) . '*'
                            : substr($local, 0, 2) . str_repeat('*', max(1, strlen($local) - 2));
                        $maskedTarget = $maskedLocal . '@' . $domain;
                    } else {
                        $maskedTarget = '***';
                    }
                }
                $otpConfirmMsg = "Send OTP to the organizer's {$otpMethod}: {$maskedTarget}?";
                $evOrganizerId = (int) ($ev['organizer_id'] ?? 0);
                ?>
                <article class="adm-pending-card" data-pending-card data-event-id="<?= $evId ?>">
                    <label class="adm-pending-card__check">
                        <input type="checkbox" class="pending-event-checkbox" name="event_ids[]" value="<?= $evId ?>" aria-label="Select <?= htmlspecialchars($evTitle) ?>">
                    </label>
                    <div class="adm-pending-card__body">
                        <div class="adm-pending-card__top">
                            <span class="adm-pending-card__id">#<?= $evId ?></span>
                            <span class="adm-pending-card__badge adm-pending-card__badge--pending">Pending</span>
                            <?php if ($isStalePending): ?>
                                <span class="adm-pending-card__badge adm-pending-card__badge--stale" title="Waiting more than 24 hours">&gt;24h</span>
                            <?php endif; ?>
                            <span class="adm-pending-card__badge adm-pending-card__badge--dept"><?= htmlspecialchars($deptLabel) ?></span>
                            <?php
                            require_once __DIR__ . '/../../backend/lib/event_ticketing.php';
                            $regUi = eventify_registration_mode_ui($ev);
                            $regBadgeExtraClass = 'adm-pending-card__badge';
                            include __DIR__ . '/registration_mode_badge.php';
                            unset($regBadgeExtraClass);
                            ?>
                        </div>
                        <h6 class="adm-pending-card__title"><?= htmlspecialchars($evTitle) ?></h6>
                        <?php if ($evDesc !== ''): ?>
                            <p class="adm-pending-card__desc"><?= htmlspecialchars(mb_strimwidth($evDesc, 0, 140, '…')) ?></p>
                        <?php endif; ?>
                        <ul class="adm-pending-card__meta">
                            <li><i class="fas fa-calendar-day" aria-hidden="true"></i><span><?= htmlspecialchars($evDateLabel) ?></span></li>
                            <li title="<?= htmlspecialchars($evLocation) ?>"><i class="fas fa-map-marker-alt" aria-hidden="true"></i><span><?= htmlspecialchars($evLocationShort) ?></span></li>
                            <li><i class="fas fa-user" aria-hidden="true"></i><span><?= htmlspecialchars($ev['organizer_name'] ?? '—') ?></span></li>
                        </ul>
                    </div>
                    <div class="adm-pending-card__actions">
                        <?php if (!empty($assignableOrganizers)): ?>
                        <form
                            method="POST"
                            action="<?= BASE_URL ?>/backend/admin/assign_event_organizer.php"
                            class="adm-pending-assign-form js-assign-organizer-form"
                            data-event-title="<?= htmlspecialchars($evTitle, ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <?= csrf_field() ?>
                            <input type="hidden" name="event_id" value="<?= $evId ?>">
                            <input type="hidden" name="return_to" value="dashboard">
                            <input type="hidden" name="return_panel" value="pending">
                            <label class="adm-pending-assign-form__label" for="assignOrg<?= $evId ?>">Assign organizer</label>
                            <div class="adm-pending-assign-form__row">
                                <select name="organizer_id" id="assignOrg<?= $evId ?>" class="form-select form-select-sm adm-pending-assign-form__select" required aria-label="Assign organizer for <?= htmlspecialchars($evTitle) ?>">
                                    <?php foreach ($assignableOrganizers as $orgOpt): ?>
                                        <option value="<?= (int) ($orgOpt['id'] ?? 0) ?>" <?= $evOrganizerId === (int) ($orgOpt['id'] ?? 0) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string) ($orgOpt['name'] ?? 'Organizer')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="adm-pending-btn adm-pending-btn--ghost adm-pending-btn--compact" title="Save organizer assignment">
                                    <i class="fas fa-user-check" aria-hidden="true"></i><span>Assign</span>
                                </button>
                            </div>
                            <p class="adm-pending-assign-form__hint mb-0">Clears any old OTP — send a new one after reassigning.</p>
                        </form>
                        <?php endif; ?>
                        <div class="adm-pending-card__otp-form">
                            <button
                                type="button"
                                class="adm-pending-btn adm-pending-btn--primary adm-pending-btn--block js-confirm-otp-request"
                                data-otp-action="<?= BASE_URL ?>/backend/super_admin/update_event_status.php"
                                data-event-id="<?= $evId ?>"
                                data-return-to="dashboard"
                                data-return-panel="pending"
                                data-confirm-message="<?= htmlspecialchars($otpConfirmMsg, ENT_QUOTES, 'UTF-8') ?>"
                                <?= $canSendOtp ? '' : 'disabled title="Organizer contact missing"' ?>
                            >
                                <i class="fas fa-paper-plane" aria-hidden="true"></i><span>Request OTP</span>
                            </button>
                        </div>
                        <div class="adm-pending-card__quick">
                            <button
                                type="button"
                                class="adm-pending-icon-btn js-edit-pending-event"
                                title="Edit event details"
                                data-bs-toggle="modal"
                                data-bs-target="#adminEditPendingEventModal"
                                data-event-id="<?= $evId ?>"
                                data-event-title="<?= htmlspecialchars($evTitle, ENT_QUOTES, 'UTF-8') ?>"
                                data-event-description="<?= htmlspecialchars($evDesc, ENT_QUOTES, 'UTF-8') ?>"
                                data-event-date="<?= htmlspecialchars($evDateRaw, ENT_QUOTES, 'UTF-8') ?>"
                                data-event-location="<?= htmlspecialchars($evLocation, ENT_QUOTES, 'UTF-8') ?>"
                                data-event-department="<?= htmlspecialchars($deptStored, ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <i class="fas fa-pen" aria-hidden="true"></i><span class="visually-hidden">Edit</span>
                            </button>
                            <a href="<?= BASE_URL ?>/event_qr.php?id=<?= $evId ?>" class="adm-pending-icon-btn" target="_blank" rel="noopener" title="Show QR for check-in">
                                <i class="fas fa-qrcode" aria-hidden="true"></i><span class="visually-hidden">QR</span>
                            </a>
                            <a href="<?= BASE_URL ?>/event_attendance.php?id=<?= $evId ?>" class="adm-pending-icon-btn" target="_blank" rel="noopener" title="View attendance">
                                <i class="fas fa-clipboard-check" aria-hidden="true"></i><span class="visually-hidden">Attendance</span>
                            </a>
                            <button type="button" class="adm-pending-icon-btn adm-pending-icon-btn--danger" data-bs-toggle="modal" data-bs-target="#rejectEventModal" data-event-id="<?= $evId ?>" data-return-to="dashboard" data-return-panel="pending" data-event-title="<?= htmlspecialchars($evTitle) ?>" title="Reject event">
                                <i class="fas fa-times" aria-hidden="true"></i><span class="visually-hidden">Reject</span>
                            </button>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </form>
    <div class="adm-dash-panel__footer-actions mt-3">
        <a href="<?= BASE_URL ?>/backend/super_admin/manage_events.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-external-link-alt me-1"></i> Open full list</a>
    </div>
<?php endif; ?>
