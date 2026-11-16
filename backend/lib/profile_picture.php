<?php
/**
 * Shared profile picture helpers (student, multimedia, organizer, admin).
 */

if (!function_exists('eventify_profile_picture_extension')) {
    /** Safe file extension for an uploaded image's detected MIME type ('' if not allowed). */
    function eventify_profile_picture_extension(string $mime): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        return $map[$mime] ?? '';
    }
}

if (!function_exists('eventify_profile_picture_remove_requested')) {
    /** True when the profile form asked to remove the current picture. */
    function eventify_profile_picture_remove_requested(): bool
    {
        return (string) ($_POST['remove_profile_picture'] ?? '') === '1';
    }
}

if (!function_exists('eventify_profile_picture_remove')) {
    /**
     * Clears the user's profile picture (DB column + file in uploads/profile_pictures).
     * Returns true when a picture was removed.
     */
    function eventify_profile_picture_remove(mysqli $conn, int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }
        $old = '';
        $st = $conn->prepare('SELECT profile_picture FROM users WHERE id = ? LIMIT 1');
        if ($st) {
            $st->bind_param('i', $userId);
            $st->execute();
            $old = (string) (($st->get_result()->fetch_assoc() ?: [])['profile_picture'] ?? '');
            $st->close();
        }
        if ($old === '') {
            return false;
        }
        $up = $conn->prepare('UPDATE users SET profile_picture = NULL WHERE id = ?');
        if (!$up) {
            return false;
        }
        $up->bind_param('i', $userId);
        $ok = $up->execute();
        $up->close();
        if ($ok) {
            // Only delete files inside uploads/profile_pictures.
            $baseDir = dirname(__DIR__, 2);
            $root = realpath($baseDir . '/uploads/profile_pictures');
            $file = realpath($baseDir . '/' . ltrim($old, '/\\'));
            if ($root !== false && $file !== false && strpos($file, $root) === 0 && is_file($file)) {
                @unlink($file);
            }
        }
        return $ok;
    }
}
