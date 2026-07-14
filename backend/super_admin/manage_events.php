<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/event_organizer_assign.php';

// Only admin or super_admin can manage event approvals
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'], true)) {
    header("Location: " . BASE_URL . "/views/login.php?error=Access denied");
    exit();
}

// Optional success / error messages
$success = $_GET['success'] ?? '';
$error   = $_GET['error'] ?? '';

// Back URL depends on who is viewing this page
$backUrl = BASE_URL . '/backend/super_admin/dashboardsuperadmin.php';
$backLabel = 'Back to Users';
if ($_SESSION['role'] === 'admin') {
    $backUrl = BASE_URL . '/backend/admin/dashboard.php';
    $backLabel = 'Back to Dashboard';
}

// Fetch pending events with organizer info
$pendingEvents = [];
$stmt = $conn->prepare("
    SELECT e.id, e.organizer_id, e.title, e.description, e.date, e.location, e.department, e.status,
           u.name AS organizer_name, u.email AS organizer_email
    FROM events e
    JOIN users u ON e.organizer_id = u.id
    WHERE e.status = 'pending'
    ORDER BY e.date ASC, e.id ASC
");
if ($stmt && $stmt->execute()) {
    $result = $stmt->get_result();
    if ($result) {
        $pendingEvents = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}

$assignableOrganizers = eventify_fetch_assignable_organizers($conn);

$conn->close();

include __DIR__ . '/../../super_admin/manage_events.php';

