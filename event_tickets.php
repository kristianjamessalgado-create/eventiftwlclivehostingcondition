<?php
/**
 * Student ticket shop for paid-ticket events (e.g. pageant).
 */
session_start();
include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
include __DIR__ . '/config/csrf.php';
include __DIR__ . '/config/departments.php';
require_once __DIR__ . '/backend/lib/event_calendar.php';
require_once __DIR__ . '/backend/lib/event_ticketing.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ' . BASE_URL . '/views/login.php?redirect=' . urlencode(BASE_URL . '/event_tickets.php?' . http_build_query($_GET)));
    exit();
}

eventify_ticketing_ensure_schema($conn);
// Accept both ?id= (activities hub) and ?event_id= (student dashboard) so every
// "Buy tickets" entry point reaches the shop.
$eventId = (int) ($_GET['id'] ?? ($_GET['event_id'] ?? 0));
$msg = trim((string) ($_GET['msg'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));

if ($eventId < 1) {
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_student.php');
    exit();
}

$stmt = $conn->prepare('SELECT * FROM events WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $eventId);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event || !eventify_event_is_live($event)) {
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_student.php?error=' . urlencode('This event has ended. Ticket sales are closed.'));
    exit();
}
if (!eventify_event_allows_ticket_shop($conn, $event)) {
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_student.php?msg=' . urlencode('Ticket sales are not available for this event.'));
    exit();
}

$highlightTypeId = (int) ($_GET['type'] ?? 0);
$fromActivityId = (int) ($_GET['activity'] ?? 0);
$shopContextNote = '';
if (!eventify_event_uses_paid_ticketing($event)) {
    $shopContextNote = 'These tickets are for paid activities inside this event\'s hub (e.g. pageant). Free RSVP still applies to the main event and free activities.';
}

$types = eventify_load_ticket_types_for_event($conn, $eventId, true);
$userId = (int) $_SESSION['user_id'];
$myTickets = eventify_load_user_tickets($conn, $userId, $eventId);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buy tickets — <?= htmlspecialchars($event['title'] ?? 'Event') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/event_tickets.css">
</head>
<body class="ticket-shop-page">
<div class="container py-4" style="max-width: 720px;">
    <a href="<?= BASE_URL ?>/backend/auth/dashboard_student.php" class="btn btn-link text-decoration-none ps-0 mb-2"><i class="fas fa-arrow-left"></i> Dashboard</a>

    <div class="ticket-shop-hero mb-4">
        <h1 class="h4 mb-1"><?= htmlspecialchars($event['title'] ?? 'Event') ?></h1>
        <p class="text-muted small mb-0">
            <i class="fas fa-calendar-day me-1"></i><?= htmlspecialchars($event['date'] ?? '') ?>
            <?php if (!empty($event['location'])): ?>
                · <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($event['location']) ?>
            <?php endif; ?>
        </p>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($shopContextNote !== ''): ?>
        <div class="alert alert-secondary small"><?= htmlspecialchars($shopContextNote) ?></div>
    <?php endif; ?>

    <?php if (!empty($myTickets)): ?>
        <div class="alert alert-info">
            <i class="fas fa-ticket-alt me-1"></i>
            You already have <?= count($myTickets) ?> ticket(s) for this event.
            <a href="<?= BASE_URL ?>/backend/auth/dashboard_student.php?panel=tickets&event_id=<?= $eventId ?>">View my tickets</a>
        </div>
    <?php endif; ?>

    <?php if ($types === []): ?>
        <div class="alert alert-warning">Ticket types are not on sale yet. Check back later.</div>
    <?php else: ?>
        <form method="post" action="<?= BASE_URL ?>/ticket_checkout.php" class="ticket-shop-form">
            <?= csrf_field() ?>
            <input type="hidden" name="event_id" value="<?= $eventId ?>">
            <div class="list-group mb-3">
                <?php foreach ($types as $type): ?>
                    <?php
                    $tid = (int) $type['id'];
                    $remaining = eventify_ticket_type_remaining($type);
                    $soldOut = $remaining !== null && $remaining < 1;
                    $price = (float) ($type['price'] ?? 0);
                    ?>
                    <label class="list-group-item ticket-type-row <?= $soldOut ? 'ticket-type-row--disabled' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="flex-grow-1">
                                <div class="fw-semibold"><?= htmlspecialchars($type['name'] ?? 'Ticket') ?></div>
                                <?php if (!empty($type['description'])): ?>
                                    <div class="small text-muted"><?= htmlspecialchars($type['description']) ?></div>
                                <?php endif; ?>
                                <?php if ($remaining !== null): ?>
                                    <div class="small <?= $soldOut ? 'text-danger' : 'text-muted' ?>">
                                        <?= $soldOut ? 'Sold out' : $remaining . ' left' ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-success"><?= eventify_format_ticket_price($price) ?></div>
                                <?php if (!$soldOut): ?>
                                    <select name="qty[<?= $tid ?>]" class="form-select form-select-sm mt-1 ticket-qty-select" aria-label="Quantity">
                                        <?php for ($q = 0; $q <= min(5, $remaining ?? 5); $q++): ?>
                                            <option value="<?= $q ?>"><?= $q ?></option>
                                        <?php endfor; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="hidden" name="qty[<?= $tid ?>]" value="0">
                                <?php endif; ?>
                            </div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-success w-100 btn-lg">
                <i class="fas fa-shopping-cart me-2"></i>Continue to payment
            </button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
