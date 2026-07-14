<?php
/**
 * @deprecated Use POST backend/auth/register_event_rsvp.php from the student dashboard.
 */
session_start();
if (!defined('BASE_URL')) {
    define('BASE_URL', '');
}
header('Location: ' . BASE_URL . '/backend/auth/dashboard_student.php?msg=' . urlencode('Please RSVP from your dashboard calendar — open the event and use the RSVP button.'));
exit();
