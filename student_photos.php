<?php
/**
 * Legacy entry — photo gallery now opens inside the student dashboard.
 */
session_start();
require_once __DIR__ . '/config/config.php';

$eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;

$url = BASE_URL . '/backend/auth/dashboard_student.php?panel=photos';
if ($eventId > 0) {
    $url .= '&event_id=' . $eventId;
}

header('Location: ' . $url);
exit();
