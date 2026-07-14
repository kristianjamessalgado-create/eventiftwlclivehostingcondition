<?php
/**
 * CLI: php tools/check_otp_status.php [email]
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../backend/lib/email_sender.php';

$email = strtolower(trim($argv[1] ?? 'kristianjames.salgado@wlcormoc.edu.ph'));
echo "Email: {$email}\n";
echo 'SMTP enabled: ' . (eventify_email_enabled() ? 'yes' : 'no') . "\n\n";

$stmt = $conn->prepare(
    'SELECT id, purpose, created_at, expires_at, used_at, attempt_count
     FROM account_email_otps WHERE email = ? ORDER BY id DESC LIMIT 10'
);
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
echo "Recent OTP rows:\n";
while ($row = $res->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_SLASHES) . "\n";
}
$stmt->close();

$u = $conn->prepare('SELECT id, email, status, created_at FROM users WHERE email = ? LIMIT 1');
$u->bind_param('s', $email);
$u->execute();
$user = $u->get_result()->fetch_assoc();
$u->close();
echo "\nUser row: " . ($user ? json_encode($user) : 'none') . "\n";
