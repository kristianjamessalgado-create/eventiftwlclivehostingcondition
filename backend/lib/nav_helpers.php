<?php

/**
 * Dashboard URLs for standalone EVENTIFY pages (back links).
 *
 * @return array{url: string, label: string}
 */
function eventify_dashboard_nav_for_role(string $role): array
{
    $base = defined('BASE_URL') ? BASE_URL : '';
    $role = strtolower(trim($role));
    switch ($role) {
        case 'admin':
            return ['url' => $base . '/backend/admin/dashboard.php', 'label' => 'Admin dashboard'];
        case 'super_admin':
            return ['url' => $base . '/backend/super_admin/dashboardsuperadmin.php', 'label' => 'Super admin'];
        case 'student':
            return ['url' => $base . '/backend/auth/dashboard_student.php', 'label' => 'Student dashboard'];
        case 'multimedia':
            return ['url' => $base . '/backend/auth/dashboard_multimedia.php', 'label' => 'Multimedia dashboard'];
        case 'organizer':
        default:
            return ['url' => $base . '/backend/auth/dashboardorganizer.php', 'label' => 'Organizer dashboard'];
    }
}
