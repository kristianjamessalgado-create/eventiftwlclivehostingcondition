<?php
/**
 * Generate a valid VAPID key pair. On Windows/XAMPP set OPENSSL_CONF first:
 *   set OPENSSL_CONF=C:\xamppfinal\php\extras\ssl\openssl.cnf
 *   php tools/generate_vapid_openssl.php
 */
$opensslConf = getenv('OPENSSL_CONF');
if (!$opensslConf) {
    $guess = dirname(__DIR__) . '/../php/extras/ssl/openssl.cnf';
    if (is_readable($guess)) {
        putenv('OPENSSL_CONF=' . $guess);
    }
}

echo 'OpenSSL: ' . (extension_loaded('openssl') ? 'yes' : 'no') . PHP_EOL;
echo 'PHP: ' . PHP_VERSION . PHP_EOL;

$config = [
    'private_key_type' => OPENSSL_KEYTYPE_EC,
    'curve_name' => 'prime256v1',
];

$key = @openssl_pkey_new($config);
if (!$key) {
    echo 'openssl_pkey_new failed: ' . openssl_error_string() . PHP_EOL;
    exit(1);
}

$details = openssl_pkey_get_details($key);
if (!$details || empty($details['ec']) || !isset($details['ec']['x'], $details['ec']['y'], $details['ec']['d'])) {
    echo 'Missing EC details' . PHP_EOL;
    exit(1);
}

$x = $details['ec']['x'];
$y = $details['ec']['y'];
$d = $details['ec']['d'];
$publicKey = "\x04" . $x . $y;

function b64url(string $bin): string {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

$pub = b64url($publicKey);
$priv = b64url($d);

echo 'VAPID_PUBLIC_KEY=' . $pub . PHP_EOL;
echo 'VAPID_PRIVATE_KEY=' . $priv . PHP_EOL;
echo 'Public len: ' . strlen($pub) . PHP_EOL;
echo 'Private len: ' . strlen($priv) . PHP_EOL;

require_once __DIR__ . '/../vendor/autoload.php';
$validated = Minishlink\WebPush\VAPID::validate([
    'subject' => 'mailto:test@example.com',
    'publicKey' => $pub,
    'privateKey' => $priv,
]);
$headers = Minishlink\WebPush\VAPID::getVapidHeaders(
    'https://fcm.googleapis.com',
    'mailto:test@example.com',
    $validated['publicKey'],
    $validated['privateKey'],
    Minishlink\WebPush\ContentEncoding::aesgcm
);
echo 'Sign test: OK' . PHP_EOL;
