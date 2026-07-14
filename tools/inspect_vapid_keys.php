<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

eventify_load_env_file();

$pub = preg_replace('/\s+/', '', trim((string) ($_ENV['VAPID_PUBLIC_KEY'] ?? '')));
$priv = preg_replace('/\s+/', '', trim((string) ($_ENV['VAPID_PRIVATE_KEY'] ?? '')));

$pubBin = Base64Url\Base64Url::decode($pub);
$privBin = Base64Url\Base64Url::decode($priv);

echo 'Public binary: ' . strlen($pubBin) . ' bytes, prefix 0x' . bin2hex($pubBin[0]) . PHP_EOL;
echo 'Private binary: ' . strlen($privBin) . ' bytes' . PHP_EOL;

// Build uncompressed public PEM for OpenSSL verification.
$der = hex2bin(
    '3059301306072a8648ce3d020106082a8648ce3d030107034200'
) . $pubBin;
$pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
$pubKey = openssl_pkey_get_public($pem);
echo 'openssl_pkey_get_public: ' . ($pubKey ? 'OK' : 'FAIL - ' . openssl_error_string()) . PHP_EOL;

// Build private PEM from raw scalar (may fail if d invalid).
$privDer = null;
// Use JWK approach via web-push utils
[$x, $y] = Minishlink\WebPush\Utils::unserializePublicKey($pubBin);
$jwk = new Jose\Component\Core\JWK([
    'kty' => 'EC',
    'crv' => 'P-256',
    'x' => Base64Url\Base64Url::encode($x),
    'y' => Base64Url\Base64Url::encode($y),
    'd' => Base64Url\Base64Url::encode($privBin),
]);
echo 'JWK created' . PHP_EOL;

try {
    $headers = Minishlink\WebPush\VAPID::getVapidHeaders(
        'https://fcm.googleapis.com',
        'mailto:test@example.com',
        $pubBin,
        $privBin,
        Minishlink\WebPush\ContentEncoding::aesgcm
    );
    echo 'Sign: OK' . PHP_EOL;
} catch (Throwable $e) {
    echo 'Sign: FAIL - ' . $e->getMessage() . PHP_EOL;
}
