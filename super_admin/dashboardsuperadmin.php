<?php
if (!defined('EVENTIFY_SUPERADMIN_DASHBOARD_LOADED')) {
    $saController = dirname(__DIR__) . '/backend/super_admin/dashboardsuperadmin.php';
    $saSelf = realpath(__FILE__);
    $saCtrlReal = is_file($saController) ? realpath($saController) : false;
    if ($saCtrlReal && $saSelf && $saCtrlReal !== $saSelf) {
        require $saController;
        exit;
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
        if (!defined('BASE_URL')) {
            require_once dirname(__DIR__) . '/config/config.php';
        }
        header('Location: ' . BASE_URL . '/index.php?auth_modal=login');
        exit;
    }
    define('EVENTIFY_SUPERADMIN_DASHBOARD_LOADED', true);
}
$superadmin_name = $superadmin_name ?? 'Super Admin';
$users = $users ?? [];
$logs = $logs ?? [];
$saLockedAccounts = $saLockedAccounts ?? [];
$saPendingAccounts = $saPendingAccounts ?? [];
$saAtRiskAccounts = $saAtRiskAccounts ?? [];
$saSecurityLogs = $saSecurityLogs ?? [];
$saLoginTrend = $saLoginTrend ?? ['labels' => [], 'counts' => []];
$saAttentionCount = count($saLockedAccounts) + count($saPendingAccounts);
$allEvents = $allEvents ?? [];
$saUserRoleLabels = $saUserRoleLabels ?? ['Super Admin', 'Admin', 'Organizer', 'Multimedia', 'Student'];
$saUserRoleCounts = $saUserRoleCounts ?? [0,0,0,0,0];
$saEventStatusLabels = $saEventStatusLabels ?? ['Pending', 'Active', 'Rejected', 'Closed'];
$saEventStatusCounts = $saEventStatusCounts ?? [0,0,0,0];
$usersPage = isset($usersPage) ? (int) $usersPage : 1;
$usersTotalPages = isset($usersTotalPages) ? (int) $usersTotalPages : 1;
$eventsPage = isset($eventsPage) ? (int) $eventsPage : 1;
$eventsTotalPages = isset($eventsTotalPages) ? (int) $eventsTotalPages : 1;
$success = $success ?? '';
$error = $error ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Super Admin Dashboard - EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/eventify_modal.css?v=10">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/dashboardsuperadmin.css?v=8">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/calendar_legend.css">
</head>
<body class="sa-dashboard">

<nav class="sa-navbar">
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="sa-sidebar-toggle" id="saSidebarToggle" aria-label="Open menu" aria-expanded="false" aria-controls="saSidebar">
            <i class="fas fa-bars" aria-hidden="true"></i>
        </button>
        <div class="sa-brand">
            <i class="fas fa-shield-alt"></i>
            <span>EVENTIFY</span>
        </div>
    </div>
    <div class="d-flex align-items-center">
        <span class="sa-user"><i class="fas fa-user-shield me-1"></i> <?= htmlspecialchars($superadmin_name) ?></span>
        <a href="<?= BASE_URL ?>/backend/auth/logout.php" class="sa-logout" data-logout-confirm aria-label="Log out">
            <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
            <span>Log out</span>
        </a>
    </div>
</nav>

<div class="sa-sidebar-backdrop" id="saSidebarBackdrop" aria-hidden="true"></div>

<div class="sa-layout">
        <aside class="sa-sidebar" id="saSidebar" aria-label="Super Admin menu">
            <button type="button" class="sa-sidebar-close" id="saSidebarClose" aria-label="Close menu">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
            <div class="sa-sidebar-header">
                <div class="sa-sidebar-avatar">
                    <?= strtoupper(substr($superadmin_name, 0, 1)) ?>
                </div>
                <div>
                    <div class="sa-sidebar-name"><?= htmlspecialchars($superadmin_name) ?></div>
                    <div class="sa-sidebar-role">Super Admin</div>
                </div>
            </div>

            <div>
                <div class="sa-nav-group-label">Management</div>
                <button
                    type="button"
                    class="sa-nav-btn primary mb-1"
                    data-bs-toggle="modal"
                    data-bs-target="#usersModal"
                >
                    <span><i class="fas fa-users me-2"></i>All Users</span>
                    <i class="fas fa-chevron-right"></i>
                </button>
                <button
                    type="button"
                    class="sa-nav-btn mb-1"
                    data-bs-toggle="modal"
                    data-bs-target="#activityModal"
                >
                    <span><i class="fas fa-clipboard-list me-2"></i>Recent Activity</span>
                    <i class="fas fa-chevron-right"></i>
                </button>
                <button
                    type="button"
                    class="sa-nav-btn mb-1"
                    data-bs-toggle="modal"
                    data-bs-target="#securityModal"
                >
                    <span>
                        <i class="fas fa-user-lock me-2"></i>Account Security
                        <?php if ((int) ($saAttentionCount ?? 0) > 0): ?>
                            <span class="badge rounded-pill bg-danger ms-1"><?= (int) $saAttentionCount ?></span>
                        <?php endif; ?>
                    </span>
                    <i class="fas fa-chevron-right"></i>
                </button>
                <button
                    type="button"
                    class="sa-nav-btn mb-1"
                    data-bs-toggle="modal"
                    data-bs-target="#allEventsModal"
                >
                    <span><i class="fas fa-calendar-day me-2"></i>All Events</span>
                    <i class="fas fa-chevron-right"></i>
                </button>
                <button
                    type="button"
                    class="sa-nav-btn mb-1"
                    data-bs-toggle="modal"
                    data-bs-target="#calendarModal"
                >
                    <span><i class="fas fa-calendar-alt me-2"></i>Calendar</span>
                    <i class="fas fa-chevron-right"></i>
                </button>
                <?php
                    $activities_hub_btn_class = 'sa-nav-btn mb-1';
                    $activities_hub_show_chevron = true;
                    $activities_hub_sa_style = true;
                    include __DIR__ . '/../views/partials/activities_hub_quick_action.php';
                ?>
            </div>

            <div class="sa-nav-footer">
                <div class="sa-nav-footer-note">Signed in as <strong><?= htmlspecialchars($superadmin_name) ?></strong></div>
                <a href="<?= BASE_URL ?>/backend/auth/logout.php" class="sa-logout sa-logout--sidebar" data-logout-confirm>
                    <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                    <span>Log out</span>
                </a>
            </div>
        </aside>

        <main class="sa-main">
        <section class="sa-content">
            <div class="sa-card">
                <div class="sa-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h1 class="mb-0"><i class="fas fa-gauge-high"></i> Super Admin Dashboard</h1>
                    <span class="text-muted small sa-logged-in-label">
                        <i class="fas fa-user-shield me-1"></i>Logged in as <strong><?= htmlspecialchars($superadmin_name) ?></strong>
                    </span>
                </div>
                <?php if ($success): ?>
                    <div class="sa-table-wrap">
                        <div class="alert alert-success alert-dismissible fade show sa-alert mt-3" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="sa-table-wrap">
                        <div class="alert alert-danger alert-dismissible fade show sa-alert mt-3" role="alert">
                            <i class="fas fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="sa-stats-grid">
                    <div class="sa-stat-card">
                        <div class="sa-stat-label">
                            <i class="fas fa-users"></i><span>Total Users</span>
                        </div>
                        <div class="sa-stat-value"><?= (int)($userStats['total'] ?? 0) ?></div>
                        <div class="sa-stat-sub">
                            Active: <?= (int)($userStats['active'] ?? 0) ?> · Inactive: <?= (int)($userStats['inactive'] ?? 0) ?>
                        </div>
                    </div>
                    <div class="sa-stat-card">
                        <div class="sa-stat-label">
                            <i class="fas fa-user-tie"></i><span>Staff Accounts</span>
                        </div>
                        <div class="sa-stat-value">
                            <?= (int)(($userStats['admin'] ?? 0) + ($userStats['organizer'] ?? 0) + ($userStats['multimedia'] ?? 0)) ?>
                        </div>
                        <div class="sa-stat-sub">
                            Admin: <?= (int)($userStats['admin'] ?? 0) ?> · Organizer: <?= (int)($userStats['organizer'] ?? 0) ?> · Multimedia: <?= (int)($userStats['multimedia'] ?? 0) ?>
                        </div>
                    </div>
                    <button type="button" class="sa-stat-card sa-stat-card--action text-start<?= (int) ($saAttentionCount ?? 0) > 0 ? ' sa-stat-card--alert' : '' ?>" data-bs-toggle="modal" data-bs-target="#securityModal">
                        <div class="sa-stat-label">
                            <i class="fas fa-user-lock"></i><span>Needs Attention</span>
                        </div>
                        <div class="sa-stat-value"><?= (int) ($saAttentionCount ?? 0) ?></div>
                        <div class="sa-stat-sub">
                            Locked: <?= count($saLockedAccounts ?? []) ?> · Pending: <?= count($saPendingAccounts ?? []) ?> · Wrong-password warnings: <?= count($saAtRiskAccounts ?? []) ?>
                        </div>
                    </button>
                    <div class="sa-stat-card">
                        <div class="sa-stat-label">
                            <i class="fas fa-calendar-alt"></i><span>Events</span>
                        </div>
                        <div class="sa-stat-value"><?= (int)($eventStats['total'] ?? 0) ?></div>
                        <div class="sa-stat-sub">
                            Active: <?= (int)($eventStats['active'] ?? 0) ?> · Pending: <?= (int)($eventStats['pending'] ?? 0) ?> · Closed: <?= (int)($eventStats['closed'] ?? 0) ?>
                        </div>
                    </div>
                    <div class="sa-stat-card">
                        <div class="sa-stat-label">
                            <i class="fas fa-right-to-bracket"></i><span>Logins Today</span>
                        </div>
                        <div class="sa-stat-value"><?= (int)($loginTodayCount ?? 0) ?></div>
                        <div class="sa-stat-sub">
                            Successful sign-ins recorded for <?= htmlspecialchars(date('Y-m-d')) ?>
                        </div>
                    </div>
                </div>
                <div class="sa-charts-grid">
                    <div class="sa-chart-card">
                        <div class="sa-chart-title">Users by role</div>
                        <div class="sa-chart-wrap"><canvas id="saUsersChart"></canvas></div>
                    </div>
                    <div class="sa-chart-card">
                        <div class="sa-chart-title">Events by status</div>
                        <div class="sa-chart-wrap"><canvas id="saEventsChart"></canvas></div>
                    </div>
                    <div class="sa-chart-card">
                        <div class="sa-chart-title">Sign-ins · last 7 days</div>
                        <div class="sa-chart-wrap"><canvas id="saLoginsChart"></canvas></div>
                    </div>
                </div>
            </div>
        </section>
        </main>
</div>

<!-- All Users Modal -->
<div class="modal fade" id="usersModal" tabindex="-1" aria-labelledby="usersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="usersModalLabel">
                    <i class="fas fa-users me-2"></i>All Users
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php
                $saF = $saFilters ?? ['u_q' => '', 'u_role' => '', 'u_status' => '', 'e_q' => '', 'e_status' => '', 'e_dept' => ''];
                $saUserFilterOn = ($saF['u_q'] . $saF['u_role'] . $saF['u_status']) !== '';
                ?>
                <form method="get" action="<?= BASE_URL ?>/backend/super_admin/dashboardsuperadmin.php" class="mb-3 d-flex flex-wrap gap-2 align-items-center js-sa-filter-form">
                    <input type="hidden" name="open_modal" value="users">
                    <input type="hidden" name="events_page" value="<?= (int) $eventsPage ?>">
                    <?php foreach (['e_q', 'e_status', 'e_dept'] as $keep): if ($saF[$keep] !== ''): ?>
                        <input type="hidden" name="<?= $keep ?>" value="<?= htmlspecialchars($saF[$keep]) ?>">
                    <?php endif; endforeach; ?>
                    <input type="search" id="userSearch" name="u_q" value="<?= htmlspecialchars($saF['u_q']) ?>"
                           class="form-control form-control-sm" placeholder="Search name or email" style="max-width: 220px;">
                    <select id="roleFilter" name="u_role" class="form-select form-select-sm" style="max-width: 180px;">
                        <?php foreach (['' => 'All Roles', 'super_admin' => 'Super Admin', 'admin' => 'Admin', 'organizer' => 'Organizer', 'multimedia' => 'Multimedia', 'student' => 'Student'] as $v => $l): ?>
                            <option value="<?= $v ?>"<?= $saF['u_role'] === $v ? ' selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="statusFilter" name="u_status" class="form-select form-select-sm" style="max-width: 180px;">
                        <?php foreach (['' => 'All Status', 'active' => 'Active', 'pending' => 'Pending approval', 'locked' => 'Locked', 'inactive' => 'Inactive (pending + locked)'] as $v => $l): ?>
                            <option value="<?= $v ?>"<?= $saF['u_status'] === $v ? ' selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-search me-1"></i>Search</button>
                    <?php if ($saUserFilterOn): ?>
                        <a class="btn btn-sm btn-link" href="<?= htmlspecialchars(eventify_sa_url(['u_q' => '', 'u_role' => '', 'u_status' => '', 'users_page' => 1, 'open_modal' => 'users'])) ?>">Clear</a>
                    <?php endif; ?>
                    <small class="text-muted ms-auto"><?= (int) $usersTotal ?> user<?= (int) $usersTotal === 1 ? '' : 's' ?><?= $saUserFilterOn ? ' match' : '' ?></small>
                </form>
                <div class="sa-table-wrap sa-modal-table-panel">
                    <?php if (empty($users)): ?>
                        <div class="sa-empty">
                            <i class="fas fa-users-slash"></i>
                            <p class="mb-0"><?= $saUserFilterOn ? 'No users match your search or filters.' : 'No users found.' ?></p>
                        </div>
                    <?php else: ?>
                        <div class="sa-modal-table-scroll" tabindex="0" aria-label="Scroll users table horizontally">
                        <table class="sa-table" id="usersTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Photo mod</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <?php
                                        $uid   = (int)$user['id'];
                                        $role  = $user['role'] ?? '';
                                        $status = $user['status'] ?? '';
                                        $failedAttempts = (int)($user['failed_attempts'] ?? 0);
                                        $isLockedAccount = $failedAttempts > 0;
                                        $isMmModerator = (int)($user['is_multimedia_moderator'] ?? 0) === 1;
                                        $eventCount = (int)($user['event_count'] ?? 0);
                                        $canDeleteAccount = $role !== 'super_admin' && $uid !== (int)($_SESSION['user_id'] ?? 0);
                                    ?>
                                    <tr
                                        data-role="<?= htmlspecialchars($role) ?>"
                                        data-status="<?= htmlspecialchars($status) ?>"
                                    >
                                        <td><span class="text-muted">#<?= $uid ?></span></td>
                                        <td><strong><?= htmlspecialchars($user['name']) ?></strong></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td>
                                            <form method="POST" action="<?= BASE_URL ?>/backend/super_admin/update_user_role.php" class="d-flex gap-1 align-items-center">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                                <input type="hidden" name="open_modal" value="users">
                                                <select name="new_role" class="form-select form-select-sm" style="min-width: 140px;">
                                                    <?php
                                                        $allRoles = ['super_admin','admin','organizer','multimedia','student'];
                                                        foreach ($allRoles as $r):
                                                    ?>
                                                        <option value="<?= $r ?>" <?= $role === $r ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $r))) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="button" class="btn btn-sm btn-outline-secondary js-confirm-submit" title="Change role"
                                                        data-confirm-message="Change the role of <?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?>? Their access changes the next time they load a page.">
                                                    <i class="fas fa-rotate"></i>
                                                </button>
                                            </form>
                                        </td>
                                        <td>
                                            <?php if ($role === 'multimedia'): ?>
                                                <form method="POST" action="<?= BASE_URL ?>/backend/super_admin/update_multimedia_moderator.php" class="d-flex gap-1 align-items-center">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="user_id" value="<?= $uid ?>">
                                                    <input type="hidden" name="open_modal" value="users">
                                                    <input type="hidden" name="is_moderator" value="<?= $isMmModerator ? '0' : '1' ?>">
                                                    <?php if ($isMmModerator): ?>
                                                        <span class="sa-badge sa-badge-active me-1">Moderator</span>
                                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="Remove photo moderator">
                                                            <i class="fas fa-user-minus"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Make photo moderator">
                                                            <i class="fas fa-user-shield me-1"></i> Set moderator
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted small">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($status === 'active'): ?>
                                                <span class="sa-badge sa-badge-active">Active</span>
                                            <?php elseif ($isLockedAccount): ?>
                                                <span class="sa-badge sa-badge-locked">Locked</span>
                                            <?php else: ?>
                                                <span class="sa-badge sa-badge-inactive"><?= ucfirst($status) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php if ($status === 'inactive'): ?>
                                                    <?php if ($isLockedAccount): ?>
                                                        <small class="text-muted d-block mb-1">Locked account: send OTP first via Reactivate.</small>
                                                        <button type="button"
                                                                class="sa-btn-react"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#reactivateConfirmModal"
                                                                data-user-id="<?= $uid ?>"
                                                                data-open-modal="users">
                                                            <i class="fas fa-paper-plane me-1"></i> Send Reactivation OTP
                                                        </button>
                                                    <?php else: ?>
                                                        <small class="text-muted d-block mb-1">New/pending account: use Activate.</small>
                                                        <form method="POST" action="<?= BASE_URL ?>/backend/super_admin/activate_user.php" class="d-inline">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="id" value="<?= $uid ?>">
                                                            <input type="hidden" name="open_modal" value="users">
                                                            <button type="button" class="sa-btn-react js-confirm-submit" data-confirm-message="Activate this pending account?">
                                                                <i class="fas fa-user-check me-1"></i> Activate
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?php if ($role !== 'super_admin'): ?>
                                                        <form method="POST" action="<?= BASE_URL ?>/backend/super_admin/deactivate_user.php" class="d-inline">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="id" value="<?= $uid ?>">
                                                            <input type="hidden" name="open_modal" value="users">
                                                            <button type="button" class="btn btn-sm btn-outline-danger js-confirm-submit" data-confirm-message="Deactivate this user account?">
                                                                <i class="fas fa-user-slash"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <?php if ($canDeleteAccount): ?>
                                                    <button type="button"
                                                            class="btn btn-sm btn-danger"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#saDeleteUserModal"
                                                            data-user-id="<?= $uid ?>"
                                                            data-user-name="<?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-user-email="<?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-user-role="<?= htmlspecialchars(ucwords(str_replace('_', ' ', $role)), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-event-count="<?= $eventCount ?>"
                                                            title="Delete account">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <small class="text-muted">Page <?= (int)$usersPage ?> of <?= (int)$usersTotalPages ?></small>
                            <div class="btn-group btn-group-sm">
                                <?php $prevUsersPage = max(1, $usersPage - 1); $nextUsersPage = min($usersTotalPages, $usersPage + 1); ?>
                                <a class="btn btn-outline-secondary <?= $usersPage <= 1 ? 'disabled' : '' ?>" href="<?= htmlspecialchars(eventify_sa_url(['users_page' => $prevUsersPage, 'open_modal' => 'users'])) ?>">Prev</a>
                                <a class="btn btn-outline-secondary <?= $usersPage >= $usersTotalPages ? 'disabled' : '' ?>" href="<?= htmlspecialchars(eventify_sa_url(['users_page' => $nextUsersPage, 'open_modal' => 'users'])) ?>">Next</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Account Security Modal -->
<div class="modal fade" id="securityModal" tabindex="-1" aria-labelledby="securityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="securityModalLabel">
                    <i class="fas fa-user-lock me-2"></i>Account Security
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php
                $saRoleLabel = static function ($r) {
                    return ucwords(str_replace('_', ' ', (string) $r));
                };
                ?>
                <h6 class="fw-bold mb-2"><i class="fas fa-lock me-2 text-danger"></i>Locked accounts <span class="text-muted fw-normal small">(5 wrong passwords — needs OTP reactivation)</span></h6>
                <div class="sa-table-wrap sa-modal-table-panel mb-4">
                    <?php if (empty($saLockedAccounts)): ?>
                        <div class="sa-empty"><i class="fas fa-shield-halved"></i><p class="mb-0">No locked accounts.</p></div>
                    <?php else: ?>
                        <div class="sa-modal-table-scroll" tabindex="0">
                        <table class="sa-table">
                            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Failed attempts</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($saLockedAccounts as $acc): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars((string) $acc['name']) ?></strong></td>
                                    <td><?= htmlspecialchars((string) $acc['email']) ?></td>
                                    <td><?= htmlspecialchars($saRoleLabel($acc['role'] ?? '')) ?></td>
                                    <td><span class="sa-badge sa-badge-locked"><?= (int) $acc['failed_attempts'] ?></span></td>
                                    <td>
                                        <button type="button" class="sa-btn-react"
                                                data-bs-toggle="modal"
                                                data-bs-target="#reactivateConfirmModal"
                                                data-user-id="<?= (int) $acc['id'] ?>"
                                                data-open-modal="security">
                                            <i class="fas fa-paper-plane me-1"></i> Send Reactivation OTP
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php endif; ?>
                </div>

                <h6 class="fw-bold mb-2"><i class="fas fa-user-clock me-2 text-warning"></i>Pending account activations</h6>
                <div class="sa-table-wrap sa-modal-table-panel mb-4">
                    <?php if (empty($saPendingAccounts)): ?>
                        <div class="sa-empty"><i class="fas fa-user-check"></i><p class="mb-0">No accounts waiting for activation.</p></div>
                    <?php else: ?>
                        <div class="sa-modal-table-scroll" tabindex="0">
                        <table class="sa-table">
                            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($saPendingAccounts as $acc): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars((string) $acc['name']) ?></strong></td>
                                    <td><?= htmlspecialchars((string) $acc['email']) ?></td>
                                    <td><?= htmlspecialchars($saRoleLabel($acc['role'] ?? '')) ?></td>
                                    <td>
                                        <form method="POST" action="<?= BASE_URL ?>/backend/super_admin/activate_user.php" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $acc['id'] ?>">
                                            <input type="hidden" name="open_modal" value="security">
                                            <button type="button" class="sa-btn-react js-confirm-submit" data-confirm-message="Activate the account of <?= htmlspecialchars((string) $acc['name'], ENT_QUOTES, 'UTF-8') ?>?">
                                                <i class="fas fa-user-check me-1"></i> Activate
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php endif; ?>
                </div>

                <h6 class="fw-bold mb-2"><i class="fas fa-triangle-exclamation me-2 text-warning"></i>Wrong-password warnings <span class="text-muted fw-normal small">(active accounts with recent failed sign-ins)</span></h6>
                <div class="sa-table-wrap sa-modal-table-panel mb-4">
                    <?php if (empty($saAtRiskAccounts)): ?>
                        <div class="sa-empty"><i class="fas fa-circle-check"></i><p class="mb-0">No active account has failed sign-in attempts.</p></div>
                    <?php else: ?>
                        <div class="sa-modal-table-scroll" tabindex="0">
                        <table class="sa-table">
                            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Failed attempts</th></tr></thead>
                            <tbody>
                            <?php foreach ($saAtRiskAccounts as $acc): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars((string) $acc['name']) ?></strong></td>
                                    <td><?= htmlspecialchars((string) $acc['email']) ?></td>
                                    <td><?= htmlspecialchars($saRoleLabel($acc['role'] ?? '')) ?></td>
                                    <td><?= (int) $acc['failed_attempts'] ?> of 5</td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php endif; ?>
                </div>

                <h6 class="fw-bold mb-2"><i class="fas fa-fingerprint me-2"></i>Recent security events</h6>
                <div class="sa-table-wrap sa-modal-table-panel">
                    <?php if (empty($saSecurityLogs)): ?>
                        <div class="sa-empty"><i class="fas fa-clipboard-check"></i><p class="mb-0">No security events recorded yet.</p></div>
                    <?php else: ?>
                        <div class="sa-modal-table-scroll" tabindex="0">
                        <table class="sa-table">
                            <thead><tr><th>When</th><th>Actor</th><th>Event</th><th>Details</th></tr></thead>
                            <tbody>
                            <?php foreach ($saSecurityLogs as $log): ?>
                                <tr>
                                    <td><span class="text-muted small"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($log['created_at'] ?? 'now'))) ?></span></td>
                                    <td>
                                        <strong><?= htmlspecialchars($log['actor_name'] ?? 'System') ?></strong>
                                        <?php if (!empty($log['actor_role'])): ?>
                                            <div class="small text-muted"><?= htmlspecialchars($saRoleLabel($log['actor_role'])) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars(str_replace('_', ' ', $log['action'] ?? '')) ?></span></td>
                                    <td><span class="small"><?= htmlspecialchars($log['details'] ?? '') ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity Modal -->
<div class="modal fade" id="activityModal" tabindex="-1" aria-labelledby="activityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="activityModalLabel">
                    <i class="fas fa-clipboard-list me-2"></i>Recent Activity
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="sa-table-wrap sa-modal-table-panel">
                    <?php if (empty($logs)): ?>
                        <div class="sa-empty">
                            <i class="fas fa-clipboard-check"></i>
                            <p class="mb-0">No recent activity recorded.</p>
                        </div>
                    <?php else: ?>
                        <div class="sa-modal-table-scroll" tabindex="0" aria-label="Scroll activity table horizontally">
                        <table class="sa-table">
                            <thead>
                                <tr>
                                    <th>When</th>
                                    <th>Actor</th>
                                    <th>Action</th>
                                    <th>Target</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td>
                                            <span class="text-muted small">
                                                <?= htmlspecialchars(date('Y-m-d H:i', strtotime($log['created_at'] ?? 'now'))) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($log['actor_name'] ?? 'System') ?></strong>
                                            <?php if (!empty($log['actor_role'])): ?>
                                                <div class="small text-muted"><?= htmlspecialchars($log['actor_role']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= htmlspecialchars(str_replace('_', ' ', $log['action'] ?? '')) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($log['target_type'])): ?>
                                                <span class="small text-muted">
                                                    <?= htmlspecialchars(ucfirst($log['target_type'])) ?>
                                                    <?php if (!empty($log['target_id'])): ?>
                                                        #<?= (int)$log['target_id'] ?>
                                                    <?php endif; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="small">
                                                <?= htmlspecialchars($log['details'] ?? '') ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- All Events Modal (Event Management) -->
<div class="modal fade" id="allEventsModal" tabindex="-1" aria-labelledby="allEventsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="allEventsModalLabel">
                    <i class="fas fa-calendar-day me-2"></i>All Events
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php
                $saF = $saFilters ?? ['u_q' => '', 'u_role' => '', 'u_status' => '', 'e_q' => '', 'e_status' => '', 'e_dept' => ''];
                $saEventFilterOn = ($saF['e_q'] . $saF['e_status'] . $saF['e_dept']) !== '';
                $saDeptOptions = [
                    '' => 'Any department',
                    'ALL' => 'School-wide events only',
                    'High school department' => 'High School Department',
                    'College of Communication, Information and Technology' => 'CCIT',
                    'College of Accountancy and Business' => 'CAB',
                    'School of Law and Political Science' => 'SLPS',
                    'College of Education' => 'Education',
                    'College of Nursing and Allied health sciences' => 'CONAHS',
                    'College of Hospitality Management' => 'CHM',
                    'BSIT' => 'BSIT (old)',
                    'BSHM' => 'BSHM (old)',
                    'CONAHS' => 'CONAHS (old)',
                    'Senior High' => 'Senior High (old)',
                ];
                ?>
                <form method="get" action="<?= BASE_URL ?>/backend/super_admin/dashboardsuperadmin.php" class="mb-3 d-flex flex-wrap gap-2 align-items-center js-sa-filter-form">
                    <input type="hidden" name="open_modal" value="events">
                    <input type="hidden" name="users_page" value="<?= (int) $usersPage ?>">
                    <?php foreach (['u_q', 'u_role', 'u_status'] as $keep): if ($saF[$keep] !== ''): ?>
                        <input type="hidden" name="<?= $keep ?>" value="<?= htmlspecialchars($saF[$keep]) ?>">
                    <?php endif; endforeach; ?>
                    <input type="search" id="eventSearch" name="e_q" value="<?= htmlspecialchars($saF['e_q']) ?>"
                           class="form-control form-control-sm" placeholder="Search title, location, organizer" style="max-width: 240px;">
                    <select id="allEventsStatusFilter" name="e_status" class="form-select form-select-sm" style="max-width: 170px;">
                        <?php foreach (['' => 'All Status', 'pending' => 'Pending', 'active' => 'Active', 'rejected' => 'Rejected', 'closed' => 'Closed / completed'] as $v => $l): ?>
                            <option value="<?= $v ?>"<?= $saF['e_status'] === $v ? ' selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="allEventsDeptFilter" name="e_dept" class="form-select form-select-sm" style="max-width: 220px;">
                        <?php foreach ($saDeptOptions as $v => $l): ?>
                            <option value="<?= htmlspecialchars($v) ?>"<?= $saF['e_dept'] === $v ? ' selected' : '' ?>><?= htmlspecialchars($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-search me-1"></i>Search</button>
                    <?php if ($saEventFilterOn): ?>
                        <a class="btn btn-sm btn-link" href="<?= htmlspecialchars(eventify_sa_url(['e_q' => '', 'e_status' => '', 'e_dept' => '', 'events_page' => 1, 'open_modal' => 'events'])) ?>">Clear</a>
                    <?php endif; ?>
                    <small class="text-muted ms-auto"><?= (int) $eventsTotal ?> event<?= (int) $eventsTotal === 1 ? '' : 's' ?><?= $saEventFilterOn ? ' match' : '' ?></small>
                </form>
                <div class="sa-table-wrap sa-modal-table-panel">
                    <?php if (empty($allEvents)): ?>
                        <div class="sa-empty">
                            <i class="fas fa-calendar-times"></i>
                            <p class="mb-0"><?= !empty($saEventFilterOn) ? 'No events match your search or filters.' : 'No events found.' ?></p>
                        </div>
                    <?php else: ?>
                        <div class="sa-modal-table-scroll" tabindex="0" aria-label="Scroll events table horizontally">
                        <table class="sa-table" id="allEventsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Event</th>
                                    <th>Date &amp; Location</th>
                                    <th>Organizer</th>
                                    <th>Dept</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allEvents as $ev): ?>
                                    <?php
                                    $eid = (int)$ev['id'];
                                    $estatus = $ev['status'] ?? '';
                                    $edept = $ev['department'] ?? 'ALL';
                                    ?>
                                    <tr data-status="<?= htmlspecialchars($estatus) ?>" data-dept="<?= htmlspecialchars($edept) ?>">
                                        <td><span class="text-muted">#<?= $eid ?></span></td>
                                        <td>
                                            <strong><?= htmlspecialchars($ev['title'] ?? 'Untitled') ?></strong>
                                            <?php if (!empty($ev['description'])): ?>
                                                <div class="text-muted small mt-1"><?= nl2br(htmlspecialchars(mb_strimwidth($ev['description'], 0, 100, '...'))) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="small"><?= htmlspecialchars($ev['date'] ?? 'TBA') ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($ev['location'] ?? '') ?></div>
                                        </td>
                                        <td>
                                            <div class="small"><?= htmlspecialchars($ev['organizer_name'] ?? '') ?></div>
                                        </td>
                                        <td>
                                            <span class="sa-badge sa-badge-dept"><?= htmlspecialchars($edept === 'ALL' ? 'All' : $edept) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($estatus === 'pending'): ?>
                                                <span class="sa-badge sa-badge-pending">Pending</span>
                                            <?php elseif ($estatus === 'active'): ?>
                                                <span class="sa-badge sa-badge-active">Active</span>
                                            <?php elseif ($estatus === 'rejected'): ?>
                                                <span class="sa-badge sa-badge-rejected">Rejected</span>
                                            <?php elseif ($estatus === 'completed'): ?>
                                                <span class="sa-badge sa-badge-closed">Completed</span>
                                            <?php else: ?>
                                                <span class="sa-badge sa-badge-closed">Closed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="sa-actions">
                                                <a href="<?= BASE_URL ?>/event_attendance.php?id=<?= $eid ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" title="View who attended"><i class="fas fa-clipboard-check me-1"></i>Attendance</a>
                                                <?php if ($estatus === 'pending'): ?>
                                                    <span class="small text-muted align-self-center"><i class="fas fa-hourglass-half me-1"></i>Awaiting admin review</span>
                                                <?php elseif ($estatus === 'active'): ?>
                                                    <form method="POST" action="<?= BASE_URL ?>/backend/super_admin/update_event_status.php" class="d-inline">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="event_id" value="<?= $eid ?>">
                                                        <input type="hidden" name="action" value="close">
                                                        <input type="hidden" name="return_to" value="dashboard">
                                                        <input type="hidden" name="open_modal" value="events">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary js-confirm-submit" data-confirm-message="Close this event?"><i class="fas fa-archive"></i> Close</button>
                                                    </form>
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-danger"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#saDeleteEventModal"
                                                        data-event-id="<?= $eid ?>"
                                                        data-event-title="<?= htmlspecialchars((string) ($ev['title'] ?? 'Event'), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-is-paid="<?= strtolower((string) ($ev['registration_mode'] ?? '')) === 'paid_ticket' ? '1' : '0' ?>"
                                                    ><i class="fas fa-trash-alt"></i> Delete</button>
                                                <?php else: ?>
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-danger"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#saDeleteEventModal"
                                                        data-event-id="<?= $eid ?>"
                                                        data-event-title="<?= htmlspecialchars((string) ($ev['title'] ?? 'Event'), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-is-paid="<?= strtolower((string) ($ev['registration_mode'] ?? '')) === 'paid_ticket' ? '1' : '0' ?>"
                                                    ><i class="fas fa-trash-alt"></i> Delete</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <small class="text-muted">Page <?= (int)$eventsPage ?> of <?= (int)$eventsTotalPages ?></small>
                            <div class="btn-group btn-group-sm">
                                <?php $prevEventsPage = max(1, $eventsPage - 1); $nextEventsPage = min($eventsTotalPages, $eventsPage + 1); ?>
                                <a class="btn btn-outline-secondary <?= $eventsPage <= 1 ? 'disabled' : '' ?>" href="<?= htmlspecialchars(eventify_sa_url(['events_page' => $prevEventsPage, 'open_modal' => 'events'])) ?>">Prev</a>
                                <a class="btn btn-outline-secondary <?= $eventsPage >= $eventsTotalPages ? 'disabled' : '' ?>" href="<?= htmlspecialchars(eventify_sa_url(['events_page' => $nextEventsPage, 'open_modal' => 'events'])) ?>">Next</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Calendar Modal -->
<div class="modal fade" id="calendarModal" tabindex="-1" aria-labelledby="calendarModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="calendarModalLabel">
                    <i class="fas fa-calendar-alt me-2"></i>Events Calendar
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div id="saCalendar" style="min-height: 420px; width: 100%;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Super Admin permanent delete -->
<div class="modal fade" id="saDeleteEventModal" tabindex="-1" aria-labelledby="saDeleteEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= BASE_URL ?>/backend/admin/delete_event.php" id="saDeleteEventForm">
                <?= csrf_field() ?>
                <input type="hidden" name="event_id" id="saDeleteEventId" value="">
                <input type="hidden" name="return_to" value="dashboard">
                <div class="modal-header">
                    <h5 class="modal-title" id="saDeleteEventModalLabel"><i class="fas fa-trash-alt me-2 text-danger"></i>Delete event?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2" id="saDeleteEventMessage">This permanently removes the event. Prefer Close if you only want it off the live calendar.</p>
                    <label class="form-label small" for="saDeleteConfirmInput">Type <strong>DELETE</strong> to confirm</label>
                    <input type="text" class="form-control" name="confirm_delete" id="saDeleteConfirmInput" autocomplete="off" placeholder="DELETE" required>
                    <div class="form-check mt-3" id="saDeleteForceWrap" style="display:none;">
                        <input class="form-check-input" type="checkbox" value="1" name="force_revenue_delete" id="saDeleteForceRevenue">
                        <label class="form-check-label small" for="saDeleteForceRevenue">I understand this also removes paid ticket order records</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="saDeleteEventSubmit" disabled>Permanently delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Logout Confirmation Modal -->
<?php include __DIR__ . '/../views/partials/activities_hub_pick_modal.php'; ?>

<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logoutModalLabel"><i class="fas fa-sign-out-alt me-2"></i>Confirm Logout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to log out?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="<?= BASE_URL ?>/backend/auth/logout.php" class="btn btn-danger js-logout-confirm">Yes, log out</a>
            </div>
        </div>
    </div>
</div>

<!-- Reactivate Account Confirmation Modal -->
<div class="modal fade" id="reactivateConfirmModal" tabindex="-1" aria-labelledby="reactivateConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reactivateConfirmModalLabel"><i class="fas fa-envelope-open-text me-2"></i>Send Reactivation OTP</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Send a verification code to this locked account? The user must verify the OTP and change password before normal access.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="<?= BASE_URL ?>/backend/super_admin/reactivate_user.php" class="d-inline" id="reactivateConfirmForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" id="reactivateUserId" value="">
                    <input type="hidden" name="open_modal" id="reactivateOpenModal" value="users">
                    <button type="submit" class="btn btn-success" id="reactivateConfirmBtn"><i class="fas fa-paper-plane me-1"></i> Send OTP</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Super Admin permanent delete account -->
<div class="modal fade" id="saDeleteUserModal" tabindex="-1" aria-labelledby="saDeleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= BASE_URL ?>/backend/super_admin/delete_user.php" id="saDeleteUserForm">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="saDeleteUserId" value="">
                <input type="hidden" name="open_modal" value="users">
                <div class="modal-header">
                    <h5 class="modal-title" id="saDeleteUserModalLabel"><i class="fas fa-user-times me-2 text-danger"></i>Delete account?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2" id="saDeleteUserMessage">This permanently removes the account and cannot be undone.</p>
                    <ul class="small text-muted mb-3" id="saDeleteUserEffects">
                        <li>The person can no longer sign in</li>
                        <li>RSVPs, attendance, and settings for this account are removed</li>
                    </ul>
                    <p class="small text-danger mb-2" id="saDeleteUserBlock" hidden></p>
                    <div id="saDeleteUserConfirmWrap">
                        <label class="form-label small" for="saDeleteUserConfirmInput">Type <strong>DELETE</strong> to confirm</label>
                        <input type="text" class="form-control" name="confirm_delete" id="saDeleteUserConfirmInput" autocomplete="off" placeholder="DELETE">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="saDeleteUserSubmit" disabled>Permanently delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Generic Action Confirmation Modal -->
<div class="modal fade" id="actionConfirmModal" tabindex="-1" aria-labelledby="actionConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="actionConfirmModalLabel"><i class="fas fa-circle-question me-2"></i>Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="actionConfirmText">Are you sure you want to continue?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="actionConfirmProceedBtn">Yes, Continue</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/logout_confirm.js?v=4"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_calendar_colors.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
window.BASE_URL = <?= json_encode(BASE_URL ?? '') ?>;
window.saUserRoles = <?= json_encode(['labels' => $saUserRoleLabels, 'counts' => $saUserRoleCounts]) ?>;
window.saEventStatus = <?= json_encode(['labels' => $saEventStatusLabels, 'counts' => $saEventStatusCounts]) ?>;
window.saLoginTrend = <?= json_encode($saLoginTrend) ?>;
window.saAllEventsJson = <?= json_encode($saCalendarEvents ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/eventify_alert_modal.js?v=4"></script>
<script src="<?= BASE_URL ?>/assets/js/dashboardsuperadmin.js?v=9"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof eventifyInitSuperAdminDashboard === 'function') {
        eventifyInitSuperAdminDashboard();
    }

    var saModal = document.getElementById('saDeleteEventModal');
    var saId = document.getElementById('saDeleteEventId');
    var saMsg = document.getElementById('saDeleteEventMessage');
    var saInput = document.getElementById('saDeleteConfirmInput');
    var saSubmit = document.getElementById('saDeleteEventSubmit');
    var saForceWrap = document.getElementById('saDeleteForceWrap');
    var saForce = document.getElementById('saDeleteForceRevenue');
    if (saModal) {
        saModal.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            if (!btn || !saId) return;
            saId.value = btn.getAttribute('data-event-id') || '';
            var title = btn.getAttribute('data-event-title') || 'this event';
            if (saMsg) {
                saMsg.textContent = 'Permanently delete "' + title + '"? Prefer Close if you only want it off the live calendar.';
            }
            if (saInput) saInput.value = '';
            var isPaid = btn.getAttribute('data-is-paid') === '1';
            if (saForceWrap) saForceWrap.style.display = isPaid ? 'block' : 'none';
            if (saForce) saForce.checked = false;
            if (saSubmit) saSubmit.disabled = true;
        });
    }
    function refreshSaDeleteGate() {
        if (!saInput || !saSubmit) return;
        var typedOk = String(saInput.value || '').trim() === 'DELETE';
        var forceNeeded = saForceWrap && saForceWrap.style.display !== 'none';
        var forceOk = !forceNeeded || (saForce && saForce.checked);
        saSubmit.disabled = !(typedOk && forceOk);
    }
    if (saInput) saInput.addEventListener('input', refreshSaDeleteGate);
    if (saForce) saForce.addEventListener('change', refreshSaDeleteGate);
});
</script>
</body>
</html>
