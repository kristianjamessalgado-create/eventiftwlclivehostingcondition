<?php
/** @var bool $admin_audit_panel_open */
/** @var list<array<string, mixed>> $auditLogs */

$admin_audit_panel_open = !empty($admin_audit_panel_open);
$auditLogs = is_array($auditLogs ?? null) ? $auditLogs : [];
$logCount = count($auditLogs);
$panelEnterClass = $admin_audit_panel_open ? ' adm-dash-panel--enter' : '';
?>

<section
    class="adm-dash-panel adm-audit-log-panel<?= $panelEnterClass ?><?= $admin_audit_panel_open ? '' : ' d-none' ?>"
    id="adminAuditLogPanel"
    aria-label="Audit log"
    <?= $admin_audit_panel_open ? '' : ' hidden' ?>
>
    <div class="adm-dash-panel__shell">
        <div class="adm-dash-panel__toolbar">
            <button type="button" class="adm-dash-panel__back" data-admin-panel="home">
                <span class="adm-dash-panel__back-icon" aria-hidden="true"><i class="fas fa-arrow-left"></i></span>
                <span class="adm-dash-panel__back-label">Back to calendar</span>
            </button>
            <?php if ($logCount > 0): ?>
                <span class="adm-dash-panel__count-pill">
                    <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                    <?= $logCount ?> entries
                </span>
            <?php endif; ?>
        </div>

        <header class="adm-dash-panel__hero">
            <div class="adm-dash-panel__hero-icon" aria-hidden="true"><i class="fas fa-clipboard-list"></i></div>
            <div class="adm-dash-panel__hero-text">
                <h2 class="adm-dash-panel__title">Audit log</h2>
                <p class="adm-dash-panel__subtitle mb-0">
                    Latest <?= $logCount ?> entries (newest first). Use search to filter this list.
                </p>
            </div>
        </header>

        <div class="mb-3">
            <label for="auditLogSearch" class="form-label small mb-1">Search</label>
            <input type="search" id="auditLogSearch" class="form-control form-control-sm" placeholder="Filter by date, user, action, details…" autocomplete="off">
        </div>
        <div class="table-responsive border rounded adm-audit-log-table-wrap">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Actor</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody id="auditLogTableBody">
                    <?php if (empty($auditLogs)): ?>
                        <tr><td colspan="6" class="text-muted text-center py-4">No log entries yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($auditLogs as $row): ?>
                            <tr class="audit-log-row">
                                <td class="text-nowrap small"><?= htmlspecialchars($row['created_at'] ?? '') ?></td>
                                <td class="small"><?= htmlspecialchars($row['actor_name'] ?? '—') ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($row['actor_role'] ?? '—') ?></span></td>
                                <td><span class="badge bg-info text-dark"><?= htmlspecialchars($row['action'] ?? '') ?></span></td>
                                <td class="small text-muted">
                                    <?php if (!empty($row['target_type'])): ?>
                                        <?= htmlspecialchars($row['target_type']) ?>
                                        <?php if ($row['target_id'] !== null && $row['target_id'] !== ''): ?>
                                            #<?= (int) $row['target_id'] ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= htmlspecialchars($row['details'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
