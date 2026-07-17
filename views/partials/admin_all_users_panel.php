<?php
/** @var bool $admin_users_panel_open */
/** @var list<array<string, mixed>> $allUsers */
/** @var int $pendingAccountCount */

$admin_users_panel_open = !empty($admin_users_panel_open);
$allUsers = is_array($allUsers ?? null) ? $allUsers : [];
$pendingAccountCount = (int) ($pendingAccountCount ?? 0);
$userCount = count($allUsers);
$panelEnterClass = $admin_users_panel_open ? ' adm-dash-panel--enter' : '';
?>

<section
    class="adm-dash-panel adm-all-users-panel<?= $panelEnterClass ?><?= $admin_users_panel_open ? '' : ' d-none' ?>"
    id="adminAllUsersPanel"
    aria-label="All users"
    <?= $admin_users_panel_open ? '' : ' hidden' ?>
>
    <div class="adm-dash-panel__shell">
        <div class="adm-dash-panel__toolbar">
            <button type="button" class="adm-dash-panel__back" data-admin-panel="home">
                <span class="adm-dash-panel__back-icon" aria-hidden="true"><i class="fas fa-arrow-left"></i></span>
                <span class="adm-dash-panel__back-label">Back to calendar</span>
            </button>
            <?php if ($userCount > 0): ?>
                <span class="adm-dash-panel__count-pill">
                    <i class="fas fa-users" aria-hidden="true"></i>
                    <?= $userCount ?> user<?= $userCount === 1 ? '' : 's' ?>
                </span>
            <?php endif; ?>
        </div>

        <header class="adm-dash-panel__hero">
            <div class="adm-dash-panel__hero-icon" aria-hidden="true"><i class="fas fa-users"></i></div>
            <div class="adm-dash-panel__hero-text">
                <h2 class="adm-dash-panel__title">All users</h2>
                <p class="adm-dash-panel__subtitle mb-0">
                    Approve or reject email-verified registrations. Assign class sections for section-only events. Super Admin accounts are not shown.
                </p>
            </div>
        </header>

        <?php
          $adminClassSections = is_array($adminClassSections ?? null) ? $adminClassSections : [];
        ?>
        <div class="border rounded p-3 mb-3 bg-white">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <strong class="small mb-0"><i class="fas fa-layer-group me-1" aria-hidden="true"></i> Class sections</strong>
                <span class="text-muted" style="font-size:.72rem;">Labels used for section-exclusive events and announcements</span>
            </div>
            <p class="text-muted small mb-2">
                Adding or removing a label does not change a student's assigned section.
                Use the <strong>Section</strong> button on each student card to reassign them.
            </p>
            <form method="POST" action="<?= BASE_URL ?>/backend/admin/manage_class_sections.php" class="row g-2 align-items-end mb-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="col-sm">
                    <label class="form-label small mb-1" for="admNewSectionLabel">Add section</label>
                    <input type="text" class="form-control form-control-sm" name="label" id="admNewSectionLabel" maxlength="80" placeholder="e.g. BSIT 4102" required>
                </div>
                <div class="col-sm-auto">
                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-plus me-1"></i>Add</button>
                </div>
            </form>
            <?php if ($adminClassSections !== []): ?>
                <ul class="list-unstyled mb-0 d-flex flex-wrap gap-2" id="admClassSectionsList">
                    <?php foreach ($adminClassSections as $sec): ?>
                        <li class="d-inline-flex align-items-center gap-1 border rounded-pill px-2 py-1" style="font-size:.78rem;" data-section-label="<?= htmlspecialchars((string) ($sec['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <span><?= htmlspecialchars((string) ($sec['label'] ?? '')) ?></span>
                            <form method="POST" action="<?= BASE_URL ?>/backend/admin/manage_class_sections.php" class="d-inline mb-0" onsubmit="return confirm('Remove this section from the list? Students keep their assigned label until you change it.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="section_id" value="<?= (int) ($sec['id'] ?? 0) ?>">
                                <button type="submit" class="btn btn-link btn-sm text-danger p-0" title="Remove from list" aria-label="Remove section"><i class="fas fa-times"></i></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-muted small mb-0">No sections yet. Add labels like BSIT 3102, then assign them to students.</p>
            <?php endif; ?>
        </div>

        <?php if ($pendingAccountCount > 0): ?>
            <div class="adm-all-events-stats adm-all-users-stats">
                <span class="adm-all-events-stats__pill adm-all-events-stats__pill--warn">
                    <i class="fas fa-hourglass-half" aria-hidden="true"></i>
                    <?= $pendingAccountCount ?> pending approval
                </span>
            </div>
        <?php endif; ?>

        <?php if (!empty($allUsers)): ?>
            <div class="adm-all-users-filters mb-3">
                <input type="search" id="admUserSearch" class="form-control form-control-sm" placeholder="Search name or email" autocomplete="off">
                <select id="admUserRoleFilter" class="form-select form-select-sm">
                    <option value="">All Roles</option>
                    <option value="admin">Admin</option>
                    <option value="organizer">Organizer</option>
                    <option value="multimedia">Multimedia</option>
                    <option value="student">Student</option>
                </select>
                <select id="admUserStatusFilter" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="active">Active</option>
                    <option value="locked">Locked</option>
                </select>
            </div>
            <div class="table-responsive border rounded adm-users-table-wrap">
                <table class="table table-sm table-hover align-middle mb-0" id="admUsersTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Registered</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allUsers as $acc): ?>
                            <?php
                                $accId = (int) ($acc['id'] ?? 0);
                                $accRole = (string) ($acc['role'] ?? 'user');
                                $accStatus = (string) ($acc['status'] ?? '');
                                $accFailed = (int) ($acc['failed_attempts'] ?? 0);
                                $isPending = ($accStatus === 'inactive' && $accFailed === 0);
                                $isLocked = ($accStatus === 'inactive' && $accFailed > 0);
                                $adminManageable = ($accRole !== 'admin');
                                $statusKey = $isPending ? 'pending' : ($isLocked ? 'locked' : ($accStatus === 'active' ? 'active' : $accStatus));
                                $accDept = trim((string) ($acc['department'] ?? ''));
                                $accDeptLabel = $accDept === ''
                                    ? '—'
                                    : (function_exists('eventify_format_department_label')
                                        ? eventify_format_department_label($accDept)
                                        : $accDept);
                                $accCreated = trim((string) ($acc['created_at'] ?? ''));
                                $accCreatedLabel = $accCreated !== '' && ($ts = strtotime($accCreated)) ? date('M j, Y g:i A', $ts) : '—';
                            ?>
                            <tr class="adm-user-row"
                                data-search="<?= htmlspecialchars(strtolower(trim(($acc['name'] ?? '') . ' ' . ($acc['email'] ?? '')))) ?>"
                                data-role="<?= htmlspecialchars($accRole) ?>"
                                data-status="<?= htmlspecialchars($statusKey) ?>">
                                <td data-label="Name"><?= htmlspecialchars((string) ($acc['name'] ?? '—')) ?></td>
                                <td class="small adm-user-email" data-label="Email"><?= htmlspecialchars((string) ($acc['email'] ?? '—')) ?></td>
                                <td data-label="Role"><span class="badge bg-secondary"><?= htmlspecialchars(ucfirst($accRole)) ?></span></td>
                                <td class="small" data-label="Department">
                                    <?= htmlspecialchars($accDeptLabel) ?>
                                    <?php if ($accRole === 'student'): ?>
                                        <?php
                                            $accCourse = trim((string) ($acc['student_course'] ?? ''));
                                            $accYear = trim((string) ($acc['student_year_level'] ?? ''));
                                            $accSection = trim((string) ($acc['student_section'] ?? ''));
                                        ?>
                                        <div class="text-muted" style="font-size: .72rem; line-height: 1.2;">
                                            <?= $accCourse !== '' ? htmlspecialchars($accCourse) : '<em>No course set</em>' ?><?= $accYear !== '' ? ' &middot; ' . htmlspecialchars($accYear) : '' ?>
                                            <?php if ($accSection !== ''): ?>
                                                <div><i class="fas fa-users me-1" aria-hidden="true"></i><?= htmlspecialchars($accSection) ?></div>
                                            <?php else: ?>
                                                <div><em>No section</em></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap small text-muted" data-label="Registered"><?= htmlspecialchars($accCreatedLabel) ?></td>
                                <td data-label="Status">
                                    <?php if ($isPending): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php elseif ($isLocked): ?>
                                        <span class="badge bg-danger">Locked</span>
                                    <?php elseif ($accStatus === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars(ucfirst($accStatus)) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end adm-user-actions" data-label="Actions">
                                    <div class="d-inline-flex flex-wrap gap-1 justify-content-end adm-user-actions__btns">
                                        <?php if ($isPending && $adminManageable): ?>
                                            <form method="POST" action="<?= BASE_URL ?>/backend/admin/activate_user.php" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $accId ?>">
                                                <input type="hidden" name="return_panel" value="users">
                                                <button type="button" class="btn btn-sm btn-success js-confirm-submit"
                                                        data-confirm-message="Approve and activate this account?">
                                                    <i class="fas fa-user-check me-1"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" action="<?= BASE_URL ?>/backend/admin/reject_user.php" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $accId ?>">
                                                <input type="hidden" name="return_panel" value="users">
                                                <button type="button" class="btn btn-sm btn-outline-danger js-confirm-submit"
                                                        data-confirm-message="Reject this pending registration? This permanently removes the unverified account.">
                                                    <i class="fas fa-user-xmark me-1"></i> Reject
                                                </button>
                                            </form>
                                        <?php elseif ($isPending || $isLocked): ?>
                                            <span class="text-muted small align-self-center">Super Admin only</span>
                                        <?php endif; ?>

                                        <?php if ($accRole === 'student'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary js-edit-student-course"
                                                    data-user-id="<?= $accId ?>"
                                                    data-user-name="<?= htmlspecialchars((string) ($acc['name'] ?? 'this student'), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-current-course="<?= htmlspecialchars((string) ($acc['student_course'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="fas fa-graduation-cap me-1"></i> Course
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary js-edit-student-section"
                                                    data-user-id="<?= $accId ?>"
                                                    data-user-name="<?= htmlspecialchars((string) ($acc['name'] ?? 'this student'), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-current-section="<?= htmlspecialchars((string) ($acc['student_section'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="fas fa-layer-group me-1"></i> Section
                                            </button>
                                        <?php elseif (!$isPending && !$isLocked): ?>
                                            <span class="text-muted small align-self-center">—</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p id="admUsersNoResults" class="text-muted small mt-2 mb-0" style="display:none;">No users match your search or filter.</p>
        <?php else: ?>
            <div class="adm-dash-panel__empty">
                <div class="adm-dash-panel__empty-icon" aria-hidden="true"><i class="fas fa-users"></i></div>
                <h3 class="adm-dash-panel__empty-title">No user accounts</h3>
                <p class="adm-dash-panel__empty-text mb-0">Registered users will appear here.</p>
            </div>
        <?php endif; ?>
    </div>
</section>
