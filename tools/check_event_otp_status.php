<?php
/**
 * CLI: php tools/check_event_otp_status.php [organizer_email]
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../backend/lib/event_approval_otp.php';

$email = strtolower(trim($argv[1] ?? ''));
echo 'event_approval_otps table: ' . (eventify_event_otp_table_ready($conn) ? 'yes' : 'no') . PHP_EOL;

$col = $conn->query("SHOW COLUMNS FROM users LIKE 'organizer_contact_method'");
echo 'organizer_contact_method column: ' . (($col && $col->num_rows > 0) ? 'yes' : 'no') . PHP_EOL;

echo PHP_EOL . "Pending events:\n";
$pending = $conn->query("SELECT e.id, e.title, e.organizer_id, u.email, u.organizer_contact_method FROM events e JOIN users u ON u.id = e.organizer_id WHERE e.status = 'pending' ORDER BY e.id DESC LIMIT 10");
if ($pending) {
    while ($row = $pending->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
}

if ($email !== '') {
    echo PHP_EOL . "Organizer: {$email}\n";
    $u = $conn->prepare('SELECT id, email, organizer_contact_email, organizer_phone, organizer_contact_method FROM users WHERE email = ? LIMIT 1');
    $u->bind_param('s', $email);
    $u->execute();
    $user = $u->get_result()->fetch_assoc();
    $u->close();
    echo json_encode($user, JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

echo PHP_EOL . "Recent event OTP rows (no codes):\n";
$otp = $conn->query('SELECT id, event_id, organizer_id, delivery_method, delivery_target, expires_at, used_at, created_at FROM event_approval_otps ORDER BY id DESC LIMIT 10');
if ($otp) {
    while ($row = $otp->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
}

$checkEventId = (int) ($argv[2] ?? 0);
if ($checkEventId > 0) {
    echo PHP_EOL . "Active OTP for event {$checkEventId}:\n";
    $stmt = $conn->prepare('SELECT id, expires_at, used_at, created_at FROM event_approval_otps WHERE event_id = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $checkEventId);
    $stmt->execute();
    $active = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    echo $active ? json_encode($active, JSON_UNESCAPED_SLASHES) : 'none' . PHP_EOL;
    if ($active && !empty($active['expires_at'])) {
        $expired = strtotime((string) $active['expires_at']) < time();
        echo 'expired=' . ($expired ? 'yes' : 'no') . PHP_EOL;
    }
}
