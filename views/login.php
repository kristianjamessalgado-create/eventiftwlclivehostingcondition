<?php
/**
 * Legacy login URL — opens the landing auth modal.
 * Never redirects to role dashboards (avoids ERR_TOO_MANY_REDIRECTS when a dashboard fatals).
 */
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/config.php';

$error = trim((string) ($_GET['error'] ?? ''));
$success = trim((string) ($_GET['success'] ?? ''));
$reactivateEmail = trim((string) ($_GET['reactivate_email'] ?? ''));
$redirect = trim((string) ($_GET['redirect'] ?? ''));
$form = trim((string) ($_GET['form'] ?? ''));

// Access denied / broken dashboard bounce: clear session so we do not re-enter a loop.
// Do not show "Access denied" — just land on a clean sign-in.
if ($error !== '' && stripos($error, 'access denied') !== false) {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/../config/session.php';
    }
    $error = '';
}

$params = [];
$params['auth_modal'] = ($form === 'register') ? 'register' : 'login';
if ($error !== '') {
    $params['auth_error'] = $error;
}
if ($success !== '') {
    $params['auth_success'] = $success;
}
if ($reactivateEmail !== '') {
    $params['reactivate_email'] = $reactivateEmail;
}
if ($redirect !== '') {
    $params['redirect'] = $redirect;
}

$target = BASE_URL . '/index.php';
if ($params !== []) {
    $target .= '?' . http_build_query($params);
}

header('Location: ' . $target);
exit();
