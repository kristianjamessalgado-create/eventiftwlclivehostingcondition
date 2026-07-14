<?php
/**
 * Temporary diagnostic — visit /tools/check_base_url.php on live, then delete this file.
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: text/plain; charset=utf-8');

$cssRoot = '/assets/css/index.css';
$cssOld  = '/school_events/assets/css/index.css';

echo "BASE_URL (from config): " . json_encode(BASE_URL) . "\n\n";
echo "Expected for root deploy: \"\" (empty string)\n\n";
echo "Sample asset URLs this page would use:\n";
echo "  CSS: " . BASE_URL . "/assets/css/index.css\n";
echo "  Login: " . BASE_URL . "/views/login.php\n\n";

$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '(unknown)';
echo "DOCUMENT_ROOT: {$docRoot}\n";
echo "App folder: " . dirname(__DIR__) . "\n\n";

if (BASE_URL === '/school_events') {
    echo ">>> PROBLEM: BASE_URL is still /school_events.\n";
    echo ">>> Fix: edit public_html/.env and set BASE_URL= (empty), or remove that line.\n";
    echo ">>> Then upload config/env.php and config/config.php from your latest code.\n";
} elseif (BASE_URL === '') {
    echo ">>> BASE_URL looks correct for root deployment.\n";
}

echo "\nQuick file check (does CSS exist on disk?):\n";
$rootPath = rtrim($docRoot, '/\\') . $cssRoot;
$oldPath  = rtrim($docRoot, '/\\') . $cssOld;
echo "  {$cssRoot}: " . (is_file($rootPath) ? 'YES' : 'NO') . "\n";
echo "  {$cssOld}: " . (is_file($oldPath) ? 'YES' : 'NO') . "\n";
