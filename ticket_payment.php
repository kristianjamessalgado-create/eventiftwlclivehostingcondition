<?php
session_start();
include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
include __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/backend/lib/event_ticketing.php';
require_once __DIR__ . '/backend/lib/paymongo.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ' . BASE_URL . '/views/login.php');
    exit();
}

eventify_ticketing_ensure_schema($conn);
$userId = (int) $_SESSION['user_id'];
$orderId = (int) ($_GET['order_id'] ?? 0);
$error = trim((string) ($_GET['error'] ?? ''));
$msg = trim((string) ($_GET['msg'] ?? ''));

$order = eventify_load_ticket_order($conn, $orderId, $userId);
if (!$order) {
    $conn->close();
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_student.php?error=' . urlencode('Order not found'));
    exit();
}

if (($order['status'] ?? '') === 'paid') {
    $conn->close();
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_student.php?panel=tickets&order_id=' . $orderId . '&msg=' . urlencode('Payment already completed'));
    exit();
}

$payMode = eventify_payment_mode();
$allowSimulate = in_array($payMode, ['simulate', 'both'], true);
$allowGcash = in_array($payMode, ['gcash_manual', 'both'], true);
$allowPaymongo = eventify_paymongo_enabled();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_validate()) {
    $action = trim((string) ($_POST['payment_action'] ?? ''));
    if ($action === 'paymongo_gcash' && $allowPaymongo) {
        // Look up buyer details for the PayMongo billing block.
        $buyerName = (string) ($_SESSION['name'] ?? '');
        $buyerEmail = '';
        $uStmt = $conn->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
        if ($uStmt) {
            $uStmt->bind_param('i', $userId);
            $uStmt->execute();
            if ($uRow = $uStmt->get_result()->fetch_assoc()) {
                $buyerName = (string) ($uRow['name'] ?? $buyerName);
                $buyerEmail = (string) ($uRow['email'] ?? '');
            }
            $uStmt->close();
        }

        $session = eventify_paymongo_create_checkout_session([
            'amount' => (float) ($order['total_amount'] ?? 0),
            'description' => 'EVENTIFY order ' . (string) ($order['order_ref'] ?? '') . ' — ' . (string) ($order['event_title'] ?? ''),
            'line_item_name' => (string) ($order['event_title'] ?? 'Event') . ' ticket(s)',
            'reference_number' => (string) ($order['order_ref'] ?? ''),
            'success_url' => eventify_paymongo_absolute_url('/ticket_payment_return.php?order_id=' . $orderId),
            'cancel_url' => eventify_paymongo_absolute_url('/ticket_payment.php?order_id=' . $orderId . '&msg=' . rawurlencode('Payment cancelled. You can try again.')),
            'buyer_name' => $buyerName,
            'buyer_email' => $buyerEmail,
        ]);

        if (!empty($session['ok'])) {
            // Stash the session id on the order so the return handler can verify it.
            $sessionId = (string) $session['id'];
            $method = 'gcash_paymongo';
            $upd = $conn->prepare(
                "UPDATE ticket_orders SET payment_method = ?, payment_reference = ? WHERE id = ? AND user_id = ? AND status = 'pending'"
            );
            if ($upd) {
                $upd->bind_param('ssii', $method, $sessionId, $orderId, $userId);
                $upd->execute();
                $upd->close();
            }
            $conn->close();
            header('Location: ' . $session['checkout_url']);
            exit();
        }
        $error = $session['error'] ?? 'Could not start GCash payment. Please try again.';
    } elseif ($action === 'simulate' && $allowSimulate) {
        $result = eventify_fulfill_ticket_order($conn, $orderId, 'simulate', 'DEMO-' . date('YmdHis'));
        if ($result['ok']) {
            $conn->close();
            header('Location: ' . BASE_URL . '/backend/auth/dashboard_student.php?panel=tickets&order_id=' . $orderId . '&msg=' . urlencode('Payment successful! Your digital passes are ready.'));
            exit();
        }
        $error = $result['error'] ?? 'Payment failed';
    } elseif ($action === 'gcash' && $allowGcash) {
        $ref = trim((string) ($_POST['gcash_reference'] ?? ''));
        if ($ref === '' || strlen($ref) < 6) {
            $error = 'Enter your GCash reference number (at least 6 characters).';
        } else {
            $upd = $conn->prepare(
                "UPDATE ticket_orders SET payment_method = 'gcash', payment_reference = ? WHERE id = ? AND user_id = ? AND status = 'pending'"
            );
            if ($upd) {
                $upd->bind_param('sii', $ref, $orderId, $userId);
                $upd->execute();
                $upd->close();
            }
            try {
                $evId = (int) ($order['event_id'] ?? 0);
                $org = $conn->prepare('SELECT organizer_id FROM events WHERE id = ? LIMIT 1');
                if ($org) {
                    $org->bind_param('i', $evId);
                    $org->execute();
                    $or = $org->get_result()->fetch_assoc();
                    $org->close();
                    $orgId = (int) ($or['organizer_id'] ?? 0);
                    if ($orgId > 0) {
                        $n = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, 'ticket_payment_pending', 'Ticket payment to verify', ?, ?)");
                        if ($n) {
                            $nMsg = ($_SESSION['name'] ?? 'A student') . ' submitted GCash ref ' . $ref . ' for order ' . ($order['order_ref'] ?? '') . '.';
                            $n->bind_param('isi', $orgId, $nMsg, $evId);
                            $n->execute();
                            $n->close();
                        }
                    }
                }
            } catch (Throwable $e) {
            }
            $conn->close();
            header('Location: ' . BASE_URL . '/ticket_payment.php?order_id=' . $orderId . '&msg=' . urlencode('Reference saved. Tickets will be issued after the organizer verifies your payment.'));
            exit();
        }
    }
}

$order = eventify_load_ticket_order($conn, $orderId, $userId);
$orderTotal = (float) ($order['total_amount'] ?? 0);
$paymongoMinPeso = eventify_paymongo_gcash_min_peso();
$paymongoOnlineOk = $allowPaymongo && eventify_paymongo_gcash_amount_allowed($orderTotal);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay for tickets | EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/event_tickets.css">
</head>
<body class="ticket-shop-page">
<div class="container py-4" style="max-width: 520px;">
    <h1 class="h5 mb-3"><i class="fas fa-credit-card me-2 text-success"></i>Complete payment</h1>
    <p class="text-muted small">Order <strong><?= htmlspecialchars($order['order_ref'] ?? '') ?></strong> · <?= htmlspecialchars($order['event_title'] ?? '') ?></p>

    <?php if ($msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <ul class="list-group mb-3">
        <?php foreach ($order['items'] ?? [] as $item): ?>
            <li class="list-group-item d-flex justify-content-between">
                <span><?= (int) ($item['quantity'] ?? 0) ?> × <?= htmlspecialchars($item['type_name'] ?? '') ?></span>
                <span><?= eventify_format_ticket_price((float) ($item['subtotal'] ?? 0)) ?></span>
            </li>
        <?php endforeach; ?>
        <li class="list-group-item d-flex justify-content-between fw-bold">
            <span>Total</span>
            <span class="text-success"><?= eventify_format_ticket_price((float) ($order['total_amount'] ?? 0)) ?></span>
        </li>
    </ul>

    <?php if ($allowPaymongo && $paymongoOnlineOk): ?>
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h6"><i class="fas fa-bolt me-1 text-primary"></i>Pay online with GCash</h2>
                <p class="small text-muted mb-2">You'll be redirected to GCash to authorize the payment. Your digital passes are issued automatically once payment is confirmed.<?= eventify_paymongo_is_test_key() ? ' <span class="badge bg-secondary">TEST MODE</span>' : '' ?></p>
                <?php if ($paymongoMinPeso <= 0 && $orderTotal > 0 && $orderTotal < 20): ?>
                    <p class="small text-warning mb-2"><i class="fas fa-circle-info me-1"></i>PayMongo GCash may not accept amounts under ₱20. If online payment fails, use <strong>manual GCash</strong> below or <strong>Demo payment</strong> for testing.</p>
                <?php endif; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="payment_action" value="paymongo_gcash">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-mobile-screen-button me-1"></i> Pay <?= eventify_format_ticket_price($orderTotal) ?> with GCash
                    </button>
                </form>
            </div>
        </div>
    <?php elseif ($allowPaymongo && !$paymongoOnlineOk && $paymongoMinPeso > 0): ?>
        <div class="alert alert-warning small">
            <i class="fas fa-circle-info me-1"></i>
            Online GCash needs at least <strong>₱<?= number_format($paymongoMinPeso, 2) ?></strong> per order.
            Buy more tickets in one order, use <strong>manual GCash</strong> below, or use <strong>Demo payment</strong> for testing.
        </div>
    <?php endif; ?>

    <?php if ($allowGcash): ?>
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h6">Pay via GCash<?= $allowPaymongo ? ' (manual)' : '' ?></h2>
                <p class="small text-muted mb-2">Send the exact amount to the school GCash account shown by the organizer, then enter your reference number below.</p>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="payment_action" value="gcash">
                    <div class="mb-2">
                        <label class="form-label small">GCash reference no.</label>
                        <input type="text" name="gcash_reference" class="form-control" placeholder="e.g. 1234567890" required minlength="6" maxlength="120"
                               value="<?= htmlspecialchars((string) ($order['payment_reference'] ?? '')) ?>">
                    </div>
                    <button type="submit" class="btn btn-outline-success w-100">Submit reference for verification</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($allowSimulate): ?>
        <div class="card border-warning mb-3">
            <div class="card-body">
                <h2 class="h6">Demo / test payment</h2>
                <p class="small text-muted mb-2">For school demo only — marks this order as paid instantly and issues digital passes.</p>
                <form method="post" onsubmit="return confirm('Mark this order as paid for demo?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="payment_action" value="simulate">
                    <button type="submit" class="btn btn-warning w-100">Pay now (demo)</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <a href="<?= BASE_URL ?>/event_tickets.php?id=<?= (int) ($order['event_id'] ?? 0) ?>" class="btn btn-link">← Back to tickets</a>
</div>
</body>
</html>
