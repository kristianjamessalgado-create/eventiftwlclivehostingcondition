<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../lib/web_push.php';

$configured = eventify_web_push_is_configured();
echo json_encode([
    'ok' => true,
    'configured' => $configured,
    'publicKey' => $configured ? eventify_web_push_public_key() : '',
]);
