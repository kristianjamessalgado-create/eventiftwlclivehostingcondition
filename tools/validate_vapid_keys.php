<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

eventify_load_env_file();

$pub = trim((string) (getenv('VAPID_PUBLIC_KEY') ?: ''));
$priv = trim((string) (getenv('VAPID_PRIVATE_KEY') ?: ''));

if ($pub === '' || $priv === '') {
    fwrite(STDERR, "Missing VAPID keys in .env\n");
    exit(1);
}

echo "Public key length (chars): " . strlen($pub) . "\n";

try {
    Minishlink\WebPush\VAPID::validate([
        'subject' => 'mailto:test@example.com',
        'publicKey' => $pub,
        'privateKey' => $priv,
    ]);
    echo "VAPID validate (length): OK\n";
} catch (Throwable $e) {
    echo "VAPID validate: FAIL - " . $e->getMessage() . "\n";
    exit(1);
}

require_once __DIR__ . '/../backend/lib/web_push.php';
$validated = Minishlink\WebPush\VAPID::validate([
    'subject' => trim((string) (getenv('VAPID_SUBJECT') ?: 'mailto:test@example.com')),
    'publicKey' => preg_replace('/\s+/', '', $pub),
    'privateKey' => preg_replace('/\s+/', '', $priv),
]);
if (!eventify_web_push_keys_can_sign($validated)) {
    echo "VAPID sign test: FAIL - public/private keys are not a valid pair.\n";
    echo "Run: php tools/generate_vapid_openssl.php\n";
    exit(1);
}
echo "VAPID sign test: OK\n";
