<?php
$pendingEvents = $pendingEvents ?? [];
$assignableOrganizers = $assignableOrganizers ?? [];
$success = $success ?? '';
$error   = $error ?? '';
$backUrl   = $backUrl   ?? (BASE_URL . '/backend/super_admin/dashboardsuperadmin.php');
$backLabel = $backLabel ?? 'Back';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #16213e 100%);
            min-height: 100vh;
            color: #1e293b;
        }
        .sa-navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 2rem;
            background: rgba(10, 10, 10, 0.85);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        }
        .sa-brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 800;
            font-size: 1.35rem;
            color: #e7e7e7;
            letter-spacing: -0.02em;
            text-decoration: none;
        }
        .sa-brand i { color: #00bfff; }
        .sa-main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        .sa-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.12);
            overflow: hidden;
        }
        .sa-card-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
        }
        .sa-card-header h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1e293b;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .sa-card-header h1 i { color: #00bfff; }
        .sa-alert {
            margin: 0 2rem 1.5rem;
            border-radius: 12px;
            padding: 1rem 1.25rem;
        }
        .sa-table-wrap { padding: 0 1.5rem 1.5rem; overflow-x: auto; }
        .sa-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .sa-table thead tr {
            background: #f1f5f9;
            border-bottom: 2px solid #e2e8f0;
        }
        .sa-table th {
            padding: 1rem 1.25rem;
            text-align: left;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.75rem;
        }
        .sa-table td {
            padding: 0.9rem 1.25rem;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            vertical-align: top;
        }
        .sa-table tbody tr:hover { background: #f8fafc; }
        .sa-table tbody tr:last-child td { border-bottom: none; }
        .sa-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .sa-badge-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .sa-badge-dept {
            background: #eff6ff;
            color: #1d4ed8;
        }
        .sa-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
        }
        .sa-btn-outline {
            display: inline-block;
            padding: 0.4rem 0.75rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            color: #475569;
            border: 1px solid #cbd5e1;
            background: #fff;
            margin-right: 0.35rem;
        }
        .sa-btn-outline:hover { background: #f1f5f9; color: #334155; }
        .sa-btn-approve,
        .sa-btn-reject {
            padding: 0.35rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 600;
            border-radius: 999px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .sa-btn-approve {
            background: #10b981;
            color: #fff;
        }
        .sa-btn-approve:hover { background: #059669; }
        .sa-btn-reject {
            background: #fee2e2;
            color: #b91c1c;
        }
        .sa-btn-reject:hover { background: #fecaca; }
        .sa-empty {
            text-align: center;
            padding: 3rem 2rem;
            color: #64748b;
        }
        .sa-empty i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
        @media (max-width: 768px) {
            .sa-navbar { padding: 0.875rem 1rem; }
            .sa-main { padding: 1rem; }
            .sa-card-header, .sa-table th, .sa-table td { padding: 0.75rem 1rem; }
            .sa-table { font-size: 0.85rem; }
        }
    </style>
</head>
<body>

<nav class="sa-navbar">
    <a href="<?= htmlspecialchars($backUrl) ?>" class="sa-brand">
        <i class="fas fa-shield-alt"></i>
        <span>EVENTIFY</span>
    </a>
    <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-outline-light btn-sm">
        <i class="fas fa-arrow-left me-1"></i> <?= htmlspecialchars($backLabel) ?>
    </a>
</nav>

<main class="sa-main">
    <div class="sa-card">
        <div class="sa-card-header">
            <h1><i class="fas fa-calendar-check"></i> Pending Event Approvals</h1>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show sa-alert" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show sa-alert" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="sa-table-wrap">
            <?php if (empty($pendingEvents)): ?>
                <div class="sa-empty">
                    <i class="fas fa-calendar-times"></i>
                    <p class="mb-0">No events are currently waiting for approval.</p>
                </div>
            <?php else: ?>
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Event</th>
                            <th>Date &amp; Location</th>
                            <th>Organizer</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingEvents as $event): ?>
                            <tr>
                                <td><span class="text-muted">#<?= (int)$event['id'] ?></span></td>
                                <td>
                                    <strong><?= htmlspecialchars($event['title'] ?? 'Untitled') ?></strong>
                                    <?php if (!empty($event['description'])): ?>
                                        <div class="text-muted small mt-1">
                                            <?= nl2br(htmlspecialchars(mb_strimwidth($event['description'], 0, 120, '...'))) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small">
                                        <i class="fas fa-calendar-day me-1 text-primary"></i>
                                        <?= htmlspecialchars($event['date'] ?? 'TBA') ?>
                                    </div>
                                    <div class="small mt-1">
                                        <i class="fas fa-location-dot me-1 text-secondary"></i>
                                        <?= htmlspecialchars($event['location'] ?? 'TBA') ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="small fw-semibold">
                                        <i class="fas fa-user me-1 text-secondary"></i>
                                        <?= htmlspecialchars($event['organizer_name'] ?? 'Unknown') ?>
                                    </div>
                                    <?php if (!empty($event['organizer_email'])): ?>
                                        <div class="small text-muted">
                                            <?= htmlspecialchars($event['organizer_email']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($assignableOrganizers)): ?>
                                        <?php $evOrganizerId = (int) ($event['organizer_id'] ?? 0); ?>
                                        <form method="POST" action="<?= BASE_URL ?>/backend/admin/assign_event_organizer.php" class="sa-assign-org-form mt-2 js-assign-organizer-form" data-event-title="<?= htmlspecialchars((string) ($event['title'] ?? 'Event'), ENT_QUOTES, 'UTF-8') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                                            <input type="hidden" name="return_to" value="manage_events">
                                            <select name="organizer_id" class="form-select form-select-sm mb-1" required aria-label="Assign organizer">
                                                <?php foreach ($assignableOrganizers as $orgOpt): ?>
                                                    <option value="<?= (int) ($orgOpt['id'] ?? 0) ?>" <?= $evOrganizerId === (int) ($orgOpt['id'] ?? 0) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars((string) ($orgOpt['name'] ?? 'Organizer')) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-outline-secondary w-100"><i class="fas fa-user-check me-1"></i>Assign</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="sa-badge sa-badge-dept">
                                        <?= htmlspecialchars(($event['department'] ?? 'ALL') === 'ALL' ? 'All Departments' : ($event['department'] ?? 'ALL')) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="sa-badge sa-badge-pending">
                                        <i class="fas fa-hourglass-half me-1"></i> Pending
                                    </span>
                                </td>
                                <td>
                                    <div class="sa-actions">
                                        <a href="<?= BASE_URL ?>/event_qr.php?id=<?= (int)$event['id'] ?>" class="sa-btn sa-btn-outline" target="_blank" rel="noopener" title="Show QR for event check-in"><i class="fas fa-qrcode me-1"></i>QR</a>
                                        <a href="<?= BASE_URL ?>/event_attendance.php?id=<?= (int)$event['id'] ?>" class="sa-btn sa-btn-outline" target="_blank" rel="noopener" title="View who attended"><i class="fas fa-clipboard-check me-1"></i>Attendance</a>
                                        <form method="POST" action="<?= BASE_URL ?>/backend/super_admin/update_event_status.php" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="sa-btn-approve">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <button type="button" class="sa-btn-reject" data-bs-toggle="modal" data-bs-target="#rejectEventModal" data-event-id="<?= (int)$event['id'] ?>" data-event-title="<?= htmlspecialchars($event['title'] ?? '') ?>">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Reject event modal (with optional reason) -->
<div class="modal fade" id="rejectEventModal" tabindex="-1" aria-labelledby="rejectEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= BASE_URL ?>/backend/super_admin/update_event_status.php" id="rejectEventForm">
                <?= csrf_field() ?>
                <input type="hidden" name="event_id" id="rejectEventId" value="">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="return_to" id="rejectReturnTo" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectEventModalLabel"><i class="fas fa-times-circle me-2 text-danger"></i>Reject event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2" id="rejectEventTitleText">Optionally give a reason so the organizer knows what to fix.</p>
                    <label for="rejectReasonInput" class="form-label small text-muted">Reason (optional)</label>
                    <textarea class="form-control" id="rejectReasonInput" name="reject_reason" rows="3" placeholder="e.g. Please add a clearer description or change the date."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times me-1"></i>Reject event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var rejectModal = document.getElementById('rejectEventModal');
    if (rejectModal) {
        rejectModal.addEventListener('show.bs.modal', function(e) {
            var btn = e.relatedTarget;
            if (btn && btn.getAttribute('data-event-id')) {
                document.getElementById('rejectEventId').value = btn.getAttribute('data-event-id') || '';
                document.getElementById('rejectReturnTo').value = btn.getAttribute('data-return-to') || '';
                var title = btn.getAttribute('data-event-title') || 'this event';
                document.getElementById('rejectEventTitleText').textContent = 'Reject "' + title + '"? Optionally give a reason so the organizer knows what to fix.';
                document.getElementById('rejectReasonInput').value = '';
            }
        });
    }
    document.querySelectorAll('.js-assign-organizer-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var sel = form.querySelector('select[name="organizer_id"]');
            if (!sel) return;
            var eventTitle = form.getAttribute('data-event-title') || 'this event';
            var orgName = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text.trim() : 'organizer';
            if (!window.confirm('Assign "' + eventTitle + '" to ' + orgName + '?\n\nAny pending OTP will be cleared.')) {
                e.preventDefault();
            }
        });
    });
});
</script>
</body>
</html>

