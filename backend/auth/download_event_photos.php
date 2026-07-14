<?php
session_start();
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/departments.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'multimedia') {
    http_response_code(403);
    exit('Access denied.');
}

$user_id = (int) $_SESSION['user_id'];
$event_id = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;

if ($event_id <= 0) {
    http_response_code(400);
    exit('Invalid event.');
}

// Optional comma-separated list of specific photo ids to download
$selectedIds = [];
$idsParam = trim((string) ($_GET['ids'] ?? ''));
if ($idsParam !== '') {
    foreach (explode(',', $idsParam) as $piece) {
        $pid = (int) trim($piece);
        if ($pid > 0) {
            $selectedIds[] = $pid;
        }
    }
    $selectedIds = array_values(array_unique($selectedIds));
}

// Resolve the current user's department for access control
$user_department = null;
$uStmt = $conn->prepare("SELECT department FROM users WHERE id = ? LIMIT 1");
if ($uStmt) {
    $uStmt->bind_param('i', $user_id);
    $uStmt->execute();
    $uRow = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();
    $user_department = $uRow['department'] ?? null;
}

// Validate that this multimedia user may access the event (department scoped)
if (empty($user_department)) {
    $evStmt = $conn->prepare("SELECT id, title FROM events WHERE id = ? LIMIT 1");
    if ($evStmt) {
        $evStmt->bind_param('i', $event_id);
    }
} else {
    $deptWhere = eventify_department_match_sql('department');
    $evStmt = $conn->prepare("SELECT id, title FROM events WHERE id = ? AND {$deptWhere} LIMIT 1");
    if ($evStmt) {
        $evStmt->bind_param('iss', $event_id, $user_department, $user_department);
    }
}

if (!$evStmt) {
    http_response_code(500);
    exit('Database error.');
}
$evStmt->execute();
$event = $evStmt->get_result()->fetch_assoc();
$evStmt->close();

if (!$event) {
    http_response_code(404);
    exit('Event not found or access denied.');
}

// Collect photo file paths for this event (optionally filtered to selected ids)
$photos = [];
if ($selectedIds !== []) {
    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
    $types = 'i' . str_repeat('i', count($selectedIds));
    $pStmt = $conn->prepare(
        "SELECT id, file_path FROM event_photos WHERE event_id = ? AND id IN ($placeholders) ORDER BY created_at DESC, id DESC"
    );
    if ($pStmt) {
        $params = array_merge([$event_id], $selectedIds);
        $pStmt->bind_param($types, ...$params);
    }
} else {
    $pStmt = $conn->prepare(
        "SELECT id, file_path FROM event_photos WHERE event_id = ? ORDER BY created_at DESC, id DESC"
    );
    if ($pStmt) {
        $pStmt->bind_param('i', $event_id);
    }
}

if (!$pStmt) {
    http_response_code(500);
    exit('Database error.');
}
$pStmt->execute();
$pRes = $pStmt->get_result();
while ($row = $pRes->fetch_assoc()) {
    $photos[] = $row;
}
$pStmt->close();
$conn->close();

if ($photos === []) {
    http_response_code(404);
    exit('No photos to download.');
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('ZIP support is not enabled on this server. Enable the php_zip extension in php.ini.');
}

$base_dir = dirname(__DIR__, 2);
$uploadsRoot = realpath($base_dir . '/uploads');

$tmpZip = tempnam(sys_get_temp_dir(), 'evt_photos_');
if ($tmpZip === false) {
    http_response_code(500);
    exit('Could not create temporary file.');
}

$zip = new ZipArchive();
if ($zip->open($tmpZip, ZipArchive::OVERWRITE) !== true) {
    @unlink($tmpZip);
    http_response_code(500);
    exit('Could not create ZIP archive.');
}

$added = 0;
$usedNames = [];
foreach ($photos as $p) {
    $rel = ltrim((string) $p['file_path'], '/\\');
    $realFile = realpath($base_dir . '/' . $rel);

    // Guard against path traversal: file must live under /uploads
    if ($realFile === false || $uploadsRoot === false || strpos($realFile, $uploadsRoot) !== 0) {
        continue;
    }
    if (!is_file($realFile)) {
        continue;
    }

    $entryName = basename($rel);
    $key = strtolower($entryName);
    if (isset($usedNames[$key])) {
        $usedNames[$key]++;
        $dot = strrpos($entryName, '.');
        if ($dot !== false) {
            $entryName = substr($entryName, 0, $dot) . '_' . $usedNames[$key] . substr($entryName, $dot);
        } else {
            $entryName .= '_' . $usedNames[$key];
        }
    } else {
        $usedNames[$key] = 0;
    }

    $zip->addFile($realFile, $entryName);
    $added++;
}
$zip->close();

if ($added === 0) {
    @unlink($tmpZip);
    http_response_code(404);
    exit('No photo files were found on the server.');
}

// Build a friendly, safe zip filename from the event title
$slug = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $event['title']);
$slug = trim((string) $slug, '_');
if ($slug === '') {
    $slug = 'event_' . $event_id;
}
$zipName = $slug . '_photos.zip';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($tmpZip));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($tmpZip);
@unlink($tmpZip);
exit;
