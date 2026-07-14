<?php
/**
 * Legacy typo URL — redirect to the real admin dashboard.
 */
if (!defined('BASE_URL')) {
    define('BASE_URL', '');
}
header('Location: ' . BASE_URL . '/backend/admin/dashboard.php', true, 301);
exit();
