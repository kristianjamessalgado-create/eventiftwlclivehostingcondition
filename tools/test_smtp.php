<?php
/**
 * CLI: php tools/test_smtp.php [recipient@email.com]
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../backend/lib/email_sender.php';

$to = $argv[1] ?? EVENTIFY_SMTP_USERNAME;
$result = eventify_send_email(
    $to,
    '[EVENTIFY] SMTP test',
    "EVENTIFY SMTP test at " . date('Y-m-d H:i:s') . "\n\nIf you received this, email OTP delivery is working."
);

echo 'SMTP enabled: ' . (eventify_email_enabled() ? 'yes' : 'no') . PHP_EOL;
echo 'Host: ' . (defined('EVENTIFY_SMTP_HOST') ? EVENTIFY_SMTP_HOST : '(none)') . PHP_EOL;
echo 'Port: ' . (defined('EVENTIFY_SMTP_PORT') ? (string) EVENTIFY_SMTP_PORT : '(none)') . PHP_EOL;
echo 'Relay (no auth): ' . (function_exists('eventify_smtp_relay_mode') && eventify_smtp_relay_mode() ? 'yes' : 'no') . PHP_EOL;
var_export($result);
echo PHP_EOL;
