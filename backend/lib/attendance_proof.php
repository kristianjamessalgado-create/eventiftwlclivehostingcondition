<?php
/**
 * Optional student attendance photo proof (one photo per check-in record).
 * QR check-in stays official; photos never block attendance.
 */

function eventify_attendance_proof_column_exists(mysqli $conn, string $table, bool $refresh = false): bool
{
    static $cache = [];
    $table = $table === 'event_day_session_attendance' ? 'event_day_session_attendance' : 'registrations';
    if (!$refresh && array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    try {
        $chk = @$conn->query("SHOW COLUMNS FROM `{$table}` LIKE 'proof_photo'");
        $cache[$table] = (bool) ($chk && $chk->num_rows > 0);
    } catch (Throwable $e) {
        $cache[$table] = false;
    }
    return $cache[$table];
}

function eventify_attendance_proof_ensure_columns(mysqli $conn): bool
{
    $ok = true;
    foreach (['registrations', 'event_day_session_attendance'] as $table) {
        if (eventify_attendance_proof_column_exists($conn, $table)) {
            continue;
        }
        try {
            $altered = @$conn->query(
                "ALTER TABLE `{$table}` ADD COLUMN `proof_photo` VARCHAR(255) NULL DEFAULT NULL"
            );
            if (!$altered || !eventify_attendance_proof_column_exists($conn, $table, true)) {
                $ok = false;
            }
        } catch (Throwable $e) {
            $ok = false;
        }
    }
    return $ok;
}

function eventify_attendance_proof_public_url(?string $path): string
{
    $rel = eventify_attendance_proof_safe_rel_path($path);
    if ($rel === '') {
        return '';
    }
    $base = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') : '';
    return $base . '/' . $rel;
}

function eventify_attendance_proof_safe_rel_path(?string $path): string
{
    $path = str_replace('\\', '/', trim((string) $path));
    if ($path === '' || strpos($path, '..') !== false) {
        return '';
    }
    $path = ltrim($path, '/');
    if (!preg_match('#^uploads/attendance_proof/[0-9]+/[A-Za-z0-9._-]+$#', $path)) {
        return '';
    }
    return $path;
}

function eventify_attendance_proof_delete_file(?string $path): void
{
    $rel = eventify_attendance_proof_safe_rel_path($path);
    if ($rel === '') {
        return;
    }
    $abs = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (is_file($abs)) {
        @unlink($abs);
    }
}

/**
 * @return array{ok:bool,path:string,error:string}
 */
function eventify_attendance_proof_store_upload(array $file, int $userId, string $kind, int $recordId): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => '', 'error' => 'Could not read that photo. Try another one.'];
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'path' => '', 'error' => 'Could not read that photo. Try another one.'];
    }
    $maxBytes = 8 * 1024 * 1024;
    if ((int) ($file['size'] ?? 0) > $maxBytes) {
        return ['ok' => false, 'path' => '', 'error' => 'Photo is too large. Use a file under 8MB.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? (string) finfo_file($finfo, $tmp) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    $extByMime = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($extByMime[$mime])) {
        return ['ok' => false, 'path' => '', 'error' => 'Use a JPG, PNG, GIF, or WEBP photo.'];
    }
    $info = @getimagesize($tmp);
    if ($info === false) {
        return ['ok' => false, 'path' => '', 'error' => 'That file does not look like a photo.'];
    }

    $userId = max(1, $userId);
    $kind = $kind === 'activity' ? 'activity' : 'event';
    $recordId = max(1, $recordId);
    $baseDir = dirname(__DIR__, 2);
    $dir = $baseDir . '/uploads/attendance_proof/' . $userId;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'path' => '', 'error' => 'Could not save the photo. Please try again.'];
    }
    $protect = dirname($dir) . '/.htaccess';
    if (!is_file($protect)) {
        @file_put_contents($protect, "Options -Indexes\n<FilesMatch \"\\.(?i:php|phtml|phar)$\">\nDeny from all\n</FilesMatch>\n");
    }

    $filename = $kind . '_' . $recordId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extByMime[$mime];
    $target = $dir . '/' . $filename;
    if (!move_uploaded_file($tmp, $target)) {
        return ['ok' => false, 'path' => '', 'error' => 'Could not save the photo. Please try again.'];
    }

    return [
        'ok' => true,
        'path' => 'uploads/attendance_proof/' . $userId . '/' . $filename,
        'error' => '',
    ];
}

function eventify_attendance_proof_thumb_html(?string $path, string $alt = 'Attendance photo'): string
{
    $url = eventify_attendance_proof_public_url($path);
    if ($url === '') {
        return '<span class="att-proof-empty">No photo</span>';
    }
    $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $safeAlt = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
    return '<button type="button" class="att-proof-thumb" data-attendance-proof-view="' . $safeUrl . '" aria-label="View attendance photo">'
        . '<img src="' . $safeUrl . '" alt="' . $safeAlt . '">'
        . '</button>';
}
