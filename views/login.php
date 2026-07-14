<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/config.php';

// Keep existing query context so old links still work.
$params = [];
$error = trim((string)($_GET['error'] ?? ''));
$success = trim((string)($_GET['success'] ?? ''));
$reactivateEmail = trim((string)($_GET['reactivate_email'] ?? ''));
$redirect = trim((string)($_GET['redirect'] ?? ''));
$form = trim((string)($_GET['form'] ?? ''));

$params['auth_modal'] = ($form === 'register') ? 'register' : 'login';
if ($error !== '') $params['auth_error'] = $error;
if ($success !== '') $params['auth_success'] = $success;
if ($reactivateEmail !== '') $params['reactivate_email'] = $reactivateEmail;
if ($redirect !== '') $params['redirect'] = $redirect;

$target = BASE_URL . '/index.php';
if (!empty($params)) {
    $target .= '?' . http_build_query($params);
}

header('Location: ' . $target);
exit();
