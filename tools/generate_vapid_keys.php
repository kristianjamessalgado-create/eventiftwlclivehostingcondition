<?php
require_once __DIR__ . '/../vendor/autoload.php';

try {
    $keys = Minishlink\WebPush\VAPID::createVapidKeys();
    echo "VAPID_PUBLIC_KEY=" . $keys['publicKey'] . PHP_EOL;
    echo "VAPID_PRIVATE_KEY=" . $keys['privateKey'] . PHP_EOL;
    echo "VAPID_SUBJECT=mailto:bojiking31@gmail.com" . PHP_EOL;

    $validated = Minishlink\WebPush\VAPID::validate([
        'subject' => 'mailto:bojiking31@gmail.com',
        'publicKey' => $keys['publicKey'],
        'privateKey' => $keys['privateKey'],
    ]);

    $headers = Minishlink\WebPush\VAPID::getVapidHeaders(
        'https://fcm.googleapis.com',
        $validated['subject'],
        $validated['publicKey'],
        $validated['privateKey'],
        Minishlink\WebPush\ContentEncoding::aesgcm
    );
    echo PHP_EOL . "Sign test: OK" . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
