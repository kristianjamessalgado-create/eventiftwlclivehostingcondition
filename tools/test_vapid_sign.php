<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

eventify_load_env_file();

$pub = preg_replace('/\s+/', '', trim((string) ($_ENV['VAPID_PUBLIC_KEY'] ?? '')));
$priv = preg_replace('/\s+/', '', trim((string) ($_ENV['VAPID_PRIVATE_KEY'] ?? '')));
$sub = trim((string) ($_ENV['VAPID_SUBJECT'] ?? 'mailto:test@example.com'));

echo "Public len: " . strlen($pub) . "\n";
echo "Private len: " . strlen($priv) . "\n";

try {
    $validated = Minishlink\WebPush\VAPID::validate([
        'subject' => $sub,
        'publicKey' => $pub,
        'privateKey' => $priv,
    ]);
    echo "Validate OK\n";
    echo "Validated public binary len: " . strlen($validated['publicKey']) . "\n";
    echo "Validated private binary len: " . strlen($validated['privateKey']) . "\n";

    $headers = Minishlink\WebPush\VAPID::getVapidHeaders(
        'https://fcm.googleapis.com',
        $validated['subject'],
        $validated['publicKey'],
        $validated['privateKey'],
        Minishlink\WebPush\ContentEncoding::aesgcm
    );
    echo "Headers OK: " . (isset($headers['Authorization']) ? 'yes' : 'no') . "\n";
} catch (Throwable $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
    exit(1);
}
