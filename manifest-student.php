<?php
require_once __DIR__ . '/config/config.php';

$base = rtrim(BASE_URL, '/');
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache');

echo json_encode([
    'id' => $base . '/student',
    'name' => 'EVENTIFY Student',
    'short_name' => 'EVENTIFY',
    'description' => 'School events, RSVP, tickets, and QR check-in',
    'start_url' => $base . '/backend/auth/dashboard_student.php?source=pwa',
    'scope' => $base . '/',
    'display' => 'standalone',
    'background_color' => '#064e3b',
    'theme_color' => '#064e3b',
    'orientation' => 'portrait-primary',
    'icons' => [
        [
            'src' => $base . '/assets/pwa/icon-192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src' => $base . '/assets/pwa/icon-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src' => $base . '/assets/pwa/icon-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'maskable',
        ],
    ],
    'categories' => ['education', 'events'],
    'shortcuts' => [
        [
            'name' => 'My tickets',
            'short_name' => 'Tickets',
            'url' => $base . '/backend/auth/dashboard_student.php?panel=tickets',
            'icons' => [['src' => $base . '/assets/pwa/icon-192.png', 'sizes' => '192x192']],
        ],
        [
            'name' => 'Scan QR',
            'short_name' => 'Scan',
            'url' => $base . '/backend/auth/dashboard_student.php?open_modal=scan',
            'icons' => [['src' => $base . '/assets/pwa/icon-192.png', 'sizes' => '192x192']],
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
