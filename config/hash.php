<?php
// Utility script for generating password hashes during development.
// Do NOT expose this in production routes.

if (php_sapi_name() === 'cli') {
    $password = $argv[1] ?? null;
    if (!$password) {
        fwrite(STDERR, "Usage: php hash.php <password>\n");
        exit(1);
    }
    echo password_hash($password, PASSWORD_DEFAULT) . PHP_EOL;
    exit(0);
}

http_response_code(404);
exit();
