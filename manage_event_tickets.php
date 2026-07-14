<?php
/**
 * Organizer: ticket types, paid-ticket mode, confirm GCash payments.
 */
session_start();
include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
include __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/backend/lib/event_ticketing.php';
require_once __DIR__ . '/backend/lib/event_calendar.php';
require_once __DIR__ . '/backend/lib/event_status_auto.php';
require_once __DIR__ . '/backend/lib/nav_helpers.php';

eventify_run_dashboard_maintenance($conn);

$role = $_SESSION['role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($role, ['organizer', 'admin', 'super_admin'], true)) {
    header('Location: ' . BASE_URL . '/views/login.php');
    exit();
}

eventify_ticketing_ensure_schema($conn);
$userId = (int) $_SESSION['user_id'];
$eventId = (int) ($_GET['event_id'] ?? $_POST['event_id'] ?? 0);
$highlightOrderId = (int) ($_GET['highlight_order_id'] ?? 0);
$msg = '';
$error = '';

if ($eventId < 1) {
    header('Location: ' . BASE_URL . '/backend/auth/dashboardorganizer.php');
    exit();
}

$stmt = $conn->prepare('SELECT * FROM events WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $eventId);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    header('Location: ' . BASE_URL . '/backend/auth/dashboardorganizer.php?error=' . urlencode('Event not found'));
    exit();
}
if ($role === 'organizer' && (int) ($event['organizer_id'] ?? 0) !== $userId) {
    header('Location: ' . BASE_URL . '/backend/auth/dashboardorganizer.php?error=' . urlencode('Access denied'));
    exit();
}

$eventLive = eventify_event_is_live($event);
$statusUi = eventify_event_status_ui($event);
$dashNav = eventify_dashboard_nav_for_role($role);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_validate()) {
    $action = trim((string) ($_POST['action'] ?? ''));
    $needsLive = in_array($action, ['set_mode', 'add_type', 'edit_type'], true);
    if (!$eventLive && $needsLive) {
        $error = 'This event has ended. Ticket setup is closed.';
    } elseif ($action === 'toggle_type' && !$eventLive && (int) ($_POST['is_active'] ?? 1) !== 1) {
        $error = 'Cannot deactivate ticket types after the event has ended.';
    } elseif ($action === 'set_mode') {
        $mode = trim((string) ($_POST['registration_mode'] ?? 'rsvp'));
        if (!in_array($mode, ['rsvp', 'paid_ticket', 'open'], true)) {
            $mode = 'rsvp';
        }
        $u = $conn->prepare('UPDATE events SET registration_mode = ? WHERE id = ?');
        if ($u) {
            $u->bind_param('si', $mode, $eventId);
            $u->execute();
            $u->close();
            $event['registration_mode'] = $mode;
            $msg = 'Registration mode updated.';
        }
    } elseif ($action === 'add_type') {
        $qtyRaw = trim((string) ($_POST['type_quantity'] ?? ''));
        $result = eventify_add_ticket_type($conn, $eventId, [
            'name' => trim((string) ($_POST['type_name'] ?? '')),
            'description' => trim((string) ($_POST['type_description'] ?? '')),
            'price' => (float) ($_POST['type_price'] ?? 0),
            'quantity' => $qtyRaw !== '' && ctype_digit($qtyRaw) ? (int) $qtyRaw : null,
            'sort_order' => max(0, (int) ($_POST['type_sort_order'] ?? 0)),
        ]);
        $msg = $result['ok'] ? 'Ticket type added.' : '';
        $error = $result['ok'] ? '' : ($result['error'] ?? 'Could not add ticket type.');
    } elseif ($action === 'edit_type') {
        $typeId = (int) ($_POST['type_id'] ?? 0);
        $qtyRaw = trim((string) ($_POST['type_quantity'] ?? ''));
        $qty = $qtyRaw === '' ? null : (int) $qtyRaw;
        $result = eventify_update_ticket_type($conn, $typeId, $eventId, [
            'name' => trim((string) ($_POST['type_name'] ?? '')),
            'description' => trim((string) ($_POST['type_description'] ?? '')),
            'price' => (float) ($_POST['type_price'] ?? 0),
            'quantity' => $qtyRaw === '' ? null : $qty,
            'sort_order' => max(0, (int) ($_POST['type_sort_order'] ?? 0)),
        ]);
        $msg = $result['ok'] ? 'Ticket type updated.' : '';
        $error = $result['ok'] ? '' : ($result['error'] ?? 'Could not update.');
    } elseif ($action === 'toggle_type') {
        $typeId = (int) ($_POST['type_id'] ?? 0);
        $active = (int) ($_POST['is_active'] ?? 0) === 1;
        $result = eventify_set_ticket_type_active($conn, $typeId, $eventId, $active);
        $msg = $result['ok'] ? ($active ? 'Ticket type reactivated.' : 'Ticket type deactivated.') : '';
        $error = $result['ok'] ? '' : ($result['error'] ?? 'Could not update.');
    } elseif ($action === 'confirm_order') {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $result = eventify_fulfill_ticket_order_for_event($conn, $orderId, $eventId, 'gcash');
        if ($result['ok']) {
            $msg = 'Payment confirmed. ' . (int) ($result['ticket_count'] ?? 0) . ' ticket(s) issued.';
        } else {
            $error = $result['error'] ?? 'Could not confirm.';
        }
    } elseif ($action === 'reject_order') {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $result = eventify_cancel_ticket_order($conn, $orderId, $eventId);
        $msg = $result['ok'] ? 'Order rejected.' : '';
        $error = $result['ok'] ? '' : ($result['error'] ?? 'Could not reject order.');
    }
}

$types = eventify_load_ticket_types_for_event($conn, $eventId, false);
$pendingOrders = eventify_load_pending_orders_for_event($conn, $eventId);
$mode = eventify_event_registration_mode($event);
$shopUrl = BASE_URL . '/event_tickets.php?id=' . $eventId;
$salesSummary = eventify_load_ticket_sales_summary($conn, $eventId);

if (isset($_GET['export']) && $_GET['export'] === 'csv' && $mode === 'paid_ticket') {
    $exportRows = eventify_ticket_sales_export_rows($conn, $eventId);
    $filename = 'ticket-sales-event-' . $eventId . '-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order ref', 'Ticket code', 'Student', 'Student ID', 'Type', 'Price', 'Status', 'Paid at', 'Used at']);
    foreach ($exportRows as $r) {
        fputcsv($out, [
            $r['order_ref'] ?? '',
            $r['ticket_code'] ?? '',
            $r['student_name'] ?? '',
            $r['student_id'] ?? '',
            $r['type_name'] ?? '',
            $r['price'] ?? '',
            $r['status'] ?? '',
            $r['paid_at'] ?? '',
            $r['used_at'] ?? '',
        ]);
    }
    fclose($out);
    $conn->close();
    exit();
}

$conn->close();

$shell_title = 'Ticket sales';
$shell_subtitle = (string) ($event['title'] ?? '');
$shell_page_title = 'Manage tickets — ' . $shell_subtitle;
$shell_back_url = $dashNav['url'];
$shell_back_label = $dashNav['label'];
$shell_body_class = 'eventify-standalone' . (in_array($role, ['admin', 'super_admin'], true) ? ' eventify-standalone--admin' : '');
include __DIR__ . '/views/partials/standalone_page_shell_open.php';
?>

    <?php if (!$eventLive): ?>
        <div class="alert alert-secondary">
            <i class="fas fa-lock me-1"></i>
            Event status: <strong><?= htmlspecialchars($statusUi['label']) ?></strong>.
            New ticket types and student purchases are disabled. You can still confirm pending GCash payments and review past sales.
        </div>
    <?php endif; ?>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="efs-card">
        <div class="efs-card__head">Registration mode</div>
        <div class="efs-card__body">
            <form method="post" class="row g-2 align-items-end<?= $eventLive ? '' : ' opacity-75' ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="event_id" value="<?= $eventId ?>">
                <input type="hidden" name="action" value="set_mode">
                <div class="col-md-8">
                    <label class="form-label small">How students join this event</label>
                    <select name="registration_mode" class="form-select"<?= $eventLive ? '' : ' disabled' ?>>
                        <option value="rsvp" <?= $mode === 'rsvp' ? 'selected' : '' ?>>Free RSVP (intramurals, etc.)</option>
                        <option value="paid_ticket" <?= $mode === 'paid_ticket' ? 'selected' : '' ?>>Paid tickets (pageant, concert)</option>
                        <option value="open" <?= $mode === 'open' ? 'selected' : '' ?>>Open — no registration</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-success w-100"<?= $eventLive ? '' : ' disabled' ?>>Save mode</button>
                </div>
            </form>
            <?php if ($mode === 'rsvp'): ?>
                <p class="small mt-2 mb-0 text-muted">Main event stays <strong>free RSVP</strong>. Add ticket types below for paid hub activities (e.g. Mr &amp; Ms), then set each activity to <em>Ticket required</em> in the activities manager.</p>
                <?php if ($eventLive && $types !== []): ?>
                    <p class="small mt-1 mb-0">Activity ticket shop: <a href="<?= htmlspecialchars($shopUrl) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($shopUrl) ?></a></p>
                <?php endif; ?>
            <?php elseif ($mode === 'paid_ticket' && $eventLive): ?>
                <p class="small mt-2 mb-0">Student shop: <a href="<?= htmlspecialchars($shopUrl) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($shopUrl) ?></a></p>
            <?php elseif ($mode === 'paid_ticket'): ?>
                <p class="small mt-2 mb-0 text-muted">Student shop is closed for this event.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($mode === 'paid_ticket' || $mode === 'rsvp'): ?>
        <?php if ($eventLive): ?>
        <div class="efs-card">
            <div class="efs-card__head"><?= $mode === 'rsvp' ? 'Add activity ticket type' : 'Add ticket type' ?></div>
            <div class="efs-card__body">
                <form method="post" class="row g-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="event_id" value="<?= $eventId ?>">
                    <input type="hidden" name="action" value="add_type">
                    <div class="col-md-4">
                        <input type="text" name="type_name" class="form-control" placeholder="e.g. VIP, General" required>
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="type_price" class="form-control" placeholder="Price ₱" min="0" step="0.01" required>
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="type_quantity" class="form-control" placeholder="Qty cap" min="1">
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="type_sort_order" class="form-control" placeholder="Sort" min="0" value="0">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-success w-100">Add</button>
                    </div>
                    <div class="col-12">
                        <input type="text" name="type_description" class="form-control form-control-sm" placeholder="Optional description">
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="efs-card">
            <div class="efs-card__head">Ticket types</div>
            <?php if ($types === []): ?>
                <div class="efs-card__body text-muted small">No ticket types yet.</div>
            <?php else: ?>
                <?php foreach ($types as $t):
                    $tid = (int) ($t['id'] ?? 0);
                    $sold = (int) ($t['sold_count'] ?? 0);
                    $isActive = !empty($t['is_active']);
                    $rem = eventify_ticket_type_remaining($t);
                    $editId = 'editType' . $tid;
                ?>
                    <div class="efs-type-row">
                        <div class="flex-grow-1">
                            <strong><?= htmlspecialchars($t['name'] ?? '') ?></strong>
                            <?php if (!$isActive): ?><span class="badge bg-secondary ms-1">Inactive</span><?php endif; ?>
                            <?php if ($sold > 0): ?><span class="badge bg-light text-dark border ms-1"><?= $sold ?> sold</span><?php endif; ?>
                            <?php if (!empty($t['description'])): ?>
                                <div class="small text-muted"><?= htmlspecialchars($t['description']) ?></div>
                            <?php endif; ?>
                            <div class="small text-muted mt-1">
                                <?= eventify_format_ticket_price((float) ($t['price'] ?? 0)) ?>
                                <?php if ($rem !== null): ?> · <?= $rem ?> left<?php endif; ?>
                                · Sort <?= (int) ($t['sort_order'] ?? 0) ?>
                            </div>
                            <?php if ($eventLive): ?>
                            <button type="button" class="btn btn-link btn-sm ps-0" data-bs-toggle="collapse" data-bs-target="#<?= $editId ?>">Edit</button>
                            <?php endif; ?>
                            <?php if ($eventLive || !$isActive): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('<?= $isActive ? 'Deactivate this ticket type? Students will no longer see it in the shop.' : 'Reactivate this ticket type?' ?>');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="event_id" value="<?= $eventId ?>">
                                <input type="hidden" name="action" value="toggle_type">
                                <input type="hidden" name="type_id" value="<?= $tid ?>">
                                <input type="hidden" name="is_active" value="<?= $isActive ? '0' : '1' ?>">
                                <button type="submit" class="btn btn-link btn-sm <?= $isActive ? 'text-danger' : 'text-success' ?>">
                                    <?= $isActive ? 'Deactivate' : 'Reactivate' ?>
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if ($eventLive): ?>
                            <div class="collapse efs-type-edit" id="<?= $editId ?>">
                                <form method="post" class="row g-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="event_id" value="<?= $eventId ?>">
                                    <input type="hidden" name="action" value="edit_type">
                                    <input type="hidden" name="type_id" value="<?= $tid ?>">
                                    <div class="col-md-4">
                                        <input type="text" name="type_name" class="form-control form-control-sm" value="<?= htmlspecialchars($t['name'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" name="type_price" class="form-control form-control-sm" value="<?= htmlspecialchars((string) ($t['price'] ?? '0')) ?>" min="0" step="0.01" <?= $sold > 0 ? 'readonly' : '' ?>>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" name="type_quantity" class="form-control form-control-sm" value="<?= $t['quantity'] !== null && $t['quantity'] !== '' ? (int) $t['quantity'] : '' ?>" min="<?= max(1, $sold) ?>" placeholder="∞">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" name="type_sort_order" class="form-control form-control-sm" value="<?= (int) ($t['sort_order'] ?? 0) ?>" min="0">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-sm btn-outline-success w-100">Save</button>
                                    </div>
                                    <div class="col-12">
                                        <input type="text" name="type_description" class="form-control form-control-sm" value="<?= htmlspecialchars($t['description'] ?? '') ?>">
                                    </div>
                                    <?php if ($sold > 0): ?>
                                        <div class="col-12"><span class="small text-muted">Price is locked after sales. Quantity must be ≥ <?= $sold ?>.</span></div>
                                    <?php endif; ?>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="efs-card">
            <div class="efs-card__head d-flex justify-content-between align-items-center">
                <span>Sales summary</span>
                <a class="btn btn-sm btn-outline-success" href="?event_id=<?= $eventId ?>&export=csv">
                    <i class="fas fa-download me-1"></i>Export CSV
                </a>
            </div>
            <div class="efs-card__body">
                <div class="row g-3 text-center mb-3">
                    <div class="col-4">
                        <div class="fw-bold fs-5 text-success"><?= eventify_format_ticket_price((float) $salesSummary['total_revenue']) ?></div>
                        <div class="small text-muted">Revenue</div>
                    </div>
                    <div class="col-4">
                        <div class="fw-bold fs-5"><?= (int) $salesSummary['tickets_sold'] ?></div>
                        <div class="small text-muted">Tickets sold</div>
                    </div>
                    <div class="col-4">
                        <div class="fw-bold fs-5"><?= (int) $salesSummary['tickets_used'] ?></div>
                        <div class="small text-muted">Checked in</div>
                    </div>
                </div>
                <?php if (!empty($salesSummary['by_type'])): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($salesSummary['by_type'] as $bt): ?>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span><?= htmlspecialchars($bt['name'] ?? '') ?></span>
                                <span class="text-muted small">
                                    <?= (int) ($bt['sold'] ?? 0) ?> sold · <?= eventify_format_ticket_price((float) ($bt['revenue'] ?? 0)) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <p class="small text-muted mb-0 mt-2">
                    <?= (int) $salesSummary['orders_paid'] ?> paid order(s)
                    <?php if ((int) $salesSummary['orders_pending'] > 0): ?>
                        · <?= (int) $salesSummary['orders_pending'] ?> pending
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="efs-card" id="pendingPayments">
            <div class="efs-card__head">Pending payments</div>
            <div class="efs-card__body p-0">
                <?php if ($pendingOrders === []): ?>
                    <p class="p-3 text-muted small mb-0">No payments awaiting verification.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Student</th>
                                    <th>Items</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($pendingOrders as $o):
                                $oid = (int) ($o['id'] ?? 0);
                                $hasRef = trim((string) ($o['payment_reference'] ?? '')) !== '';
                                $rowClass = ($highlightOrderId > 0 && $highlightOrderId === $oid) ? 'efs-order-row--highlight' : '';
                            ?>
                                <tr class="<?= $rowClass ?>">
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($o['order_ref'] ?? '') ?></div>
                                        <div class="small text-muted"><?= !empty($o['created_at']) ? date('M j, g:i A', strtotime((string) $o['created_at'])) : '' ?></div>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($o['student_name'] ?? '') ?>
                                        <?php if (!empty($o['student_number'])): ?>
                                            <div class="small text-muted"><?= htmlspecialchars($o['student_number']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?= htmlspecialchars(eventify_format_order_items_summary($o['items'] ?? [])) ?></td>
                                    <td><?= eventify_format_ticket_price((float) ($o['total_amount'] ?? 0)) ?></td>
                                    <td>
                                        <?php if ($hasRef): ?>
                                            <span class="efs-pill efs-pill--pending">GCash ref</span>
                                            <div class="small mt-1"><code><?= htmlspecialchars($o['payment_reference']) ?></code></div>
                                        <?php else: ?>
                                            <span class="efs-pill efs-pill--awaiting">Awaiting ref</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <form method="post" class="d-inline" onsubmit="return confirm('Confirm payment and issue tickets?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="event_id" value="<?= $eventId ?>">
                                            <input type="hidden" name="action" value="confirm_order">
                                            <input type="hidden" name="order_id" value="<?= $oid ?>">
                                            <button type="submit" class="btn btn-sm btn-success"<?= $hasRef ? '' : ' title="Student has not submitted a GCash reference yet"' ?>>Confirm</button>
                                        </form>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Reject this order?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="event_id" value="<?= $eventId ?>">
                                            <input type="hidden" name="action" value="reject_order">
                                            <input type="hidden" name="order_id" value="<?= $oid ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

<?php include __DIR__ . '/views/partials/standalone_page_shell_close.php'; ?>
