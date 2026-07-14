<?php
/**
 * Load key=value pairs from .env in project root (gitignored).
 */
function eventify_load_env_file(?string $path = null): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;
    $path = $path ?? dirname(__DIR__) . '/.env';
    if (!is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '') {
            continue;
        }
        $len = strlen($value);
        if ($len >= 2) {
            $first = $value[0];
            $last = $value[$len - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        if (getenv($key) === false) {
            @putenv($key . '=' . $value);
        }
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function eventify_env(string $key, ?string $default = null): ?string
{
    eventify_load_env_file();
    if (array_key_exists($key, $_ENV)) {
        return (string) $_ENV[$key];
    }
    $val = getenv($key);
    if ($val !== false) {
        return (string) $val;
    }
    if (array_key_exists($key, $_SERVER)) {
        return (string) $_SERVER[$key];
    }
    return $default;
}

/**
 * Web path from document root to the app folder (e.g. "" at public_html root, "/school_events" in a subfolder).
 */
function eventify_detect_base_url(): string
{
    if (!isset($_SERVER['DOCUMENT_ROOT']) || $_SERVER['DOCUMENT_ROOT'] === '') {
        return '';
    }
    $docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
    $appRoot = rtrim(str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__)), '/');
    if ($docRoot !== '' && strpos($appRoot, $docRoot) === 0) {
        $relative = substr($appRoot, strlen($docRoot));
        return $relative === '' ? '' : $relative;
    }
    return '';
}

/**
 * Resolve BASE_URL: explicit .env (including empty string) wins, else auto-detect from install path.
 */
function eventify_resolve_base_url(): string
{
    eventify_load_env_file();
    if (array_key_exists('BASE_URL', $_ENV)) {
        return (string) $_ENV['BASE_URL'];
    }
    $fromEnv = getenv('BASE_URL');
    if ($fromEnv !== false) {
        return (string) $fromEnv;
    }
    return eventify_detect_base_url();
}
