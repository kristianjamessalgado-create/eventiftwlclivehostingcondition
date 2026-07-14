<?php
/**
 * Legacy URL — OTP verification now lives on the landing page modal.
 */
require_once __DIR__ . '/../config/config.php';

$purpose = (($_GET['purpose'] ?? 'register') === 'reactivate') ? 'reactivate' : 'register';
$email = trim((string) ($_GET['email'] ?? ''));
$params = [
    'auth_modal' => 'verify',
    'verify_purpose' => $purpose,
    'verify_email' => $email,
];
if (!empty($_GET['error'])) {
    $params['auth_error'] = (string) $_GET['error'];
}
if (!empty($_GET['success'])) {
    $params['auth_success'] = (string) $_GET['success'];
}

header('Location: ' . BASE_URL . '/index.php?' . http_build_query($params));
exit();
