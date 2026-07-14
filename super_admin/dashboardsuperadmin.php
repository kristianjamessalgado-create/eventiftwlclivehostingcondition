<?php
if (!defined('EVENTIFY_SUPERADMIN_DASHBOARD_LOADED')) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/views/login.php');
    exit;
}
$superadmin_name = $superadmin_name ?? 'Super Admin';
$users = $users ?? [];
$logs = $logs ?? [];
$pendingEvents = $pendingEvents ?? [];
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/dashboardsuperadmin.css?v=3">
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
        <a href="#" class="sa-logout" data-bs-toggle="modal" data-bs-target="#logoutModal"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<div class="sa-sidebar-backdrop" id="saSidebarBackdrop" aria-hidden="true"></div>

<main class="sa-main">
    <div class="sa-layout">
        <!-- Sidebar -->
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
                    data-bs-target="#pendingEventsModal"
                >
                    <span><i class="fas fa-calendar-check me-2"></i>Pending Events</span>
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
                <button
                    type="button"
                    class="sa-nav-btn mb-1"
                    data-bs-toggle="modal"
                    data-bs-target="#revenueModal"
                >
                    <span><i class="fas fa-peso-sign me-2"></i>Revenue</span>
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
                <div>Signed in as <strong><?= htmlspecialchars($superadmin_name) ?></strong></div>
                <div class="mt-1">Use the menu to manage users, approvals, and view logs.</div>
            </div>
        </aside>

        <!-- Main content -->
        <section class="sa-content">
            <div class="sa-card">
                <div class="sa-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h1 class="mb-0"><i class="fas fa-gauge-high"></i> Super Admin Dashboard</h1>
                    <span class="text-muted small">
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
                            <i class="fas fa-user-tie"></i><span>Admins &amp; Organizers</span>
                        </div>
                        <div class="sa-stat-value">
                            <?= (int)(($userStats['admin'] ?? 0) + ($userStats['organizer'] ?? 0)) ?>
                        </div>
                        <div class="sa-stat-sub">
                            Admin: <?= (int)($userStats['admin'] ?? 0) ?> · Organizer: <?= (int)($userStats['organizer'] ?? 0) ?>
                        </div>
                    </div>
                    <div class="sa-stat-card">
                        <div class="sa-stat-label">
                            <i class="fas fa-calendar-alt"></i><span>Events</span>
                        </div>
                        <div class="sa-stat-value"><?= (int)($eventStats['total'] ?? 0) ?></div>
                        <div class="sa-stat-sub">
                            Pending: <?= (int)($eventStats['pending'] ?? 0) ?> · Active: <?= (int)($eventStats['active'] ?? 0) ?>
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
                </div>
            </div>
        </section>
    </div>
</main>

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
                <div class="sa-table-wrap sa-modal-table-panel">
                    <?php if (empty($users)): ?>
                        <div class="sa-empty">
                            <i class="fas fa-users-slash"></i>
                            <p class="mb-0">No users found.</p>
                        </div>
                    <?php else: ?>
                        <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
                            <input
                                type="text"
                                id="userSearch"
                                class="form-control form-control-sm"
                                placeholder="Search name or email"
                                style="max-width: 220px;"
                            >
                            <select id="roleFilter" class="form-select form-select-sm" style="max-width: 180px;">
                                <option value="">All Roles</option>
                                <option value="super_admin">Super Admin</option>
                                <option value="admin">Admin</option>
                                <option value="organizer">Organizer</option>
                                <option value="multimedia">Multimedia</option>
                                <option value="student">Student</option>
                            </select>
                            <select id="statusFilter" class="form-select form-select-sm" style="max-width: 160px;">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
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
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">
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
                                <a class="btn btn-outline-secondary <?= $usersPage <= 1 ? 'disabled' : '' ?>" href="<?= BASE_URL ?>/backend/super_admin/dashboardsuperadmin.php?users_page=<?= $prevUsersPage ?>&events_page=<?= (int)$eventsPage ?>&open_modal=users">Prev</a>
                                <a class="btn btn-outline-secondary <?= $usersPage >= $usersTotalPages ? 'disabled' : '' ?>" href="<?= BASE_URL ?>/backend/super_admin/dashboardsuperadmin.php?users_page=<?= $nextUsersPage ?>&events_page=<?= (int)$eventsPage ?>&open_modal=users">Next</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Revenue Modal -->
<div class="modal fade" id="revenueModal" tabindex="-1" aria-labelledby="revenueModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="revenueModalLabel">
                    <i class="fas fa-peso-sign me-2"></i>Ticket Revenue
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php
                  $rev = $revenueOverview ?? [
                      'total_revenue' => 0.0, 'tickets_sold' => 0, 'orders_paid' => 0,
                      'pending_amount' => 0.0, 'pending_orders' => 0, 'demo_revenue' => 0.0,
                      'demo_orders' => 0, 'by_method' => [], 'by_event' => [],
                  ];
                  $fmtPeso = static function ($n): string { return '₱' . number_format((float) $n, 2); };
                ?>
                <p class="text-muted small mb-3">Actual money collected from paid events. Demo / test payments are excluded from the totals and shown separately.</p>

                <div class="row g-2 mb-3">
                  <div class="col-6 col-md-3">
                    <div class="border rounded p-3 h-100">
                      <div class="text-muted small">Total revenue</div>
                      <div class="h4 mb-0 fw-bold text-success"><?= htmlspecialchars($fmtPeso($rev['total_revenue'])) ?></div>
                    </div>
                  </div>
                  <div class="col-6 col-md-3">
                    <div class="border rounded p-3 h-100">
                      <div class="text-muted small">Tickets sold</div>
                      <div class="h4 mb-0"><?= (int) $rev['tickets_sold'] ?></div>
                    </div>
                  </div>
                  <div class="col-6 col-md-3">
                    <div class="border rounded p-3 h-100">
                      <div class="text-muted small">Paid orders</div>
                      <div class="h4 mb-0"><?= (int) $rev['orders_paid'] ?></div>
                    </div>
                  </div>
                  <div class="col-6 col-md-3">
                    <div class="border rounded p-3 h-100">
                      <div class="text-muted small">Pending payment</div>
                      <div class="h6 mb-0"><?= htmlspecialchars($fmtPeso($rev['pending_amount'])) ?> <span class="text-muted">(<?= (int) $rev['pending_orders'] ?>)</span></div>
                    </div>
                  </div>
                </div>

                <?php if ((float) $rev['demo_revenue'] > 0 || (int) $rev['demo_orders'] > 0): ?>
                  <div class="alert alert-warning py-2 small mb-3">
                    <i class="fas fa-flask me-1"></i> Demo / test payments (excluded from revenue): <strong><?= htmlspecialchars($fmtPeso($rev['demo_revenue'])) ?></strong> across <?= (int) $rev['demo_orders'] ?> order(s).
                  </div>
                <?php endif; ?>

                <div class="row g-3">
                  <div class="col-12 col-lg-5">
                    <h6 class="small text-uppercase text-muted">By payment method</h6>
                    <?php $realMethods = array_filter($rev['by_method'], static function ($m) { return ($m['method'] ?? '') !== 'simulate'; }); ?>
                    <?php if (!empty($realMethods)): ?>
                      <div class="sa-table-wrap">
                        <table class="sa-table">
                          <thead><tr><th>Method</th><th>Orders</th><th>Revenue</th></tr></thead>
                          <tbody>
                            <?php foreach ($realMethods as $m): ?>
                              <tr>
                                <td><?= htmlspecialchars((string) $m['label']) ?></td>
                                <td><?= (int) $m['orders'] ?></td>
                                <td><?= htmlspecialchars($fmtPeso($m['revenue'])) ?></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php else: ?>
                      <p class="text-muted small mb-0">No real payments yet.</p>
                    <?php endif; ?>
                  </div>

                  <div class="col-12 col-lg-7">
                    <h6 class="small text-uppercase text-muted">By event</h6>
                    <?php if (!empty($rev['by_event'])): ?>
                      <div class="sa-table-wrap">
                        <table class="sa-table">
                          <thead><tr><th>Event</th><th>Organizer</th><th>Orders</th><th>Revenue</th></tr></thead>
                          <tbody>
                            <?php foreach ($rev['by_event'] as $ev): ?>
                              <?php
                                $evDate = trim((string) ($ev['date'] ?? ''));
                                $evDateLabel = $evDate !== '' && ($ts = strtotime($evDate)) ? date('M j, Y', $ts) : '';
                              ?>
                              <tr>
                                <td>
                                  <strong><?= htmlspecialchars((string) $ev['title']) ?></strong>
                                  <?php if ($evDateLabel !== ''): ?><div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($evDateLabel) ?></div><?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars((string) ($ev['organizer_name'] ?? '—')) ?></td>
                                <td><?= (int) $ev['orders'] ?></td>
                                <td><?= htmlspecialchars($fmtPeso($ev['revenue'])) ?></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php else: ?>
                      <p class="text-muted small mb-0">No paid-event revenue yet.</p>
                    <?php endif; ?>
                  </div>
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

<!-- Pending Events Modal -->
<div class="modal fade" id="pendingEventsModal" tabindex="-1" aria-labelledby="pendingEventsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pendingEventsModalLabel">
                    <i class="fas fa-calendar-check me-2"></i>Pending Event Approvals
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="sa-table-wrap sa-modal-table-panel">
                    <?php if (empty($pendingEvents)): ?>
                        <div class="sa-empty">
                            <i class="fas fa-calendar-times"></i>
                            <p class="mb-0">No events are currently waiting for approval.</p>
                        </div>
                    <?php else: ?>
                        <div class="sa-modal-table-scroll" tabindex="0" aria-label="Scroll pending events table horizontally">
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
                                                <a href="<?= BASE_URL ?>/event_attendance.php?id=<?= (int)$event['id'] ?>" class="sa-btn sa-btn-outline btn btn-sm" target="_blank" rel="noopener" title="View who attended"><i class="fas fa-clipboard-check me-1"></i>Attendance</a>
                                                <form method="POST" action="<?= BASE_URL ?>/backend/super_admin/update_event_status.php" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="return_to" value="dashboard">
                                                    <input type="hidden" name="open_modal" value="pending">
                                                    <button type="submit" class="sa-btn-approve">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                </form>
                                                <button type="button" class="sa-btn-reject" data-bs-toggle="modal" data-bs-target="#rejectEventModal" data-event-id="<?= (int)$event['id'] ?>" data-event-title="<?= htmlspecialchars($event['title'] ?? '') ?>" data-return-to="dashboard" data-open-modal="pending">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </div>
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
                <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
                    <input type="text" id="eventSearch" class="form-control form-control-sm" placeholder="Search title or location" style="max-width: 220px;">
                    <select id="allEventsStatusFilter" class="form-select form-select-sm" style="max-width: 160px;">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="active">Active</option>
                        <option value="rejected">Rejected</option>
                        <option value="closed">Closed</option>
                        <option value="completed">Completed</option>
                    </select>
                    <select id="allEventsDeptFilter" class="form-select form-select-sm" style="max-width: 200px;">
                        <option value="">All Departments</option>
                        <option value="ALL">All Departments</option>
                        <option value="BSIT">BSIT</option>
                        <option value="BSHM">BSHM</option>
                        <option value="CONAHS">CONAHS</option>
                        <option value="Senior High">Senior High</option>
                        <option value="High school department">High School Department</option>
                        <option value="College of Communication, Information and Technology">CCIT</option>
                        <option value="College of Accountancy and Business">CAB</option>
                        <option value="School of Law and Political Science">SLPS</option>
                        <option value="College of Education">Education</option>
                        <option value="College of Nursing and Allied health sciences">CONAHS</option>
                        <option value="College of Hospitality Management">CHM</option>
                    </select>
                </div>
                <div class="sa-table-wrap sa-modal-table-panel">
                    <?php if (empty($allEvents)): ?>
                        <div class="sa-empty">
                            <i class="fas fa-calendar-times"></i>
                            <p class="mb-0">No events found.</p>
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
                                                    <form method="POST" action="<?= BASE_URL ?>/backend/super_admin/update_event_status.php" class="d-inline">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="event_id" value="<?= $eid ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="return_to" value="dashboard">
                                                        <input type="hidden" name="open_modal" value="events">
                                                        <button type="submit" class="sa-btn-approve btn btn-sm"><i class="fas fa-check"></i> Approve</button>
                                                    </form>
                                                    <button type="button" class="sa-btn-reject btn btn-sm" data-bs-toggle="modal" data-bs-target="#rejectEventModal" data-event-id="<?= $eid ?>" data-event-title="<?= htmlspecialchars($ev['title'] ?? '') ?>" data-return-to="dashboard" data-open-modal="events">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                <?php elseif ($estatus === 'active'): ?>
                                                    <form method="POST" action="<?= BASE_URL ?>/backend/super_admin/update_event_status.php" class="d-inline">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="event_id" value="<?= $eid ?>">
                                                        <input type="hidden" name="action" value="close">
                                                        <input type="hidden" name="return_to" value="dashboard">
                                                        <input type="hidden" name="open_modal" value="events">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary js-confirm-submit" data-confirm-message="Close this event?"><i class="fas fa-archive"></i> Close</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted small">—</span>
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
                                <a class="btn btn-outline-secondary <?= $eventsPage <= 1 ? 'disabled' : '' ?>" href="<?= BASE_URL ?>/backend/super_admin/dashboardsuperadmin.php?events_page=<?= $prevEventsPage ?>&users_page=<?= (int)$usersPage ?>&open_modal=events">Prev</a>
                                <a class="btn btn-outline-secondary <?= $eventsPage >= $eventsTotalPages ? 'disabled' : '' ?>" href="<?= BASE_URL ?>/backend/super_admin/dashboardsuperadmin.php?events_page=<?= $nextEventsPage ?>&users_page=<?= (int)$usersPage ?>&open_modal=events">Next</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reject event modal (with optional reason) -->
<div class="modal fade" id="rejectEventModal" tabindex="-1" aria-labelledby="rejectEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= BASE_URL ?>/backend/super_admin/update_event_status.php" id="rejectEventForm">
                <?= csrf_field() ?>
                <input type="hidden" name="event_id" id="rejectEventId" value="">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="return_to" id="rejectReturnTo" value="dashboard">
                <input type="hidden" name="open_modal" id="rejectOpenModal" value="events">
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
                <a href="<?= BASE_URL ?>/backend/auth/logout.php" class="btn btn-danger">Yes, Log out</a>
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
<script src="<?= BASE_URL ?>/assets/js/logout_confirm.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_calendar_colors.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
window.BASE_URL = <?= json_encode(BASE_URL ?? '') ?>;
window.saUserRoles = <?= json_encode(['labels' => $saUserRoleLabels, 'counts' => $saUserRoleCounts]) ?>;
window.saEventStatus = <?= json_encode(['labels' => $saEventStatusLabels, 'counts' => $saEventStatusCounts]) ?>;
window.saAllEventsJson = <?= json_encode($saCalendarEvents ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/dashboardsuperadmin.js?v=2"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof eventifyInitSuperAdminDashboard === 'function') {
        eventifyInitSuperAdminDashboard();
    }
});
</script>
</body>
</html>
