<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/staff_messaging.php';

$uid = (int) ($_SESSION['user_id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? ''));
if ($uid < 1 || !in_array($role, ['admin', 'organizer'], true)) {
    header('Location: ' . BASE_URL . '/views/login.php?error=' . rawurlencode('Sign in to use messages.'));
    exit;
}

$pageTitle = ($role === 'admin') ? 'Messages — Organizers' : 'Messages — Admins';
$dashboardHref = ($role === 'admin')
    ? BASE_URL . '/backend/admin/dashboard.php'
    : BASE_URL . '/backend/auth/dashboardorganizer.php';

$ctx = eventify_staff_messenger_load_context($conn, $uid, $role, (int) ($_GET['with'] ?? 0));
$peersList = $ctx['peers_list'];
$myName = $ctx['my_name'];
$messaging_error = $ctx['messaging_error'];
$initialWith = $ctx['initial_with'];

$conn->close();

include __DIR__ . '/../../views/staff_messenger.php';
