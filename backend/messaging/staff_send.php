<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/staff_messaging.php';
require_once __DIR__ . '/../lib/activity_logger.php';

header('Content-Type: application/json; charset=utf-8');

$uid = (int)($_SESSION['user_id'] ?? 0);
$role = strtolower((string)($_SESSION['role'] ?? ''));
if ($uid < 1 || !in_array($role, ['admin', 'organizer'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_validate()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
    exit;
}

$recipientId = (int)($_POST['recipient_id'] ?? 0);
$body = trim((string)($_POST['body'] ?? ''));
$attachmentPath = null;

if ($recipientId < 1 || $recipientId === $uid) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid recipient.']);
    exit;
}

if (!empty($_FILES['attachment']) && is_array($_FILES['attachment']) && (int)($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['attachment'];
    if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Could not upload attachment.']);
        exit;
    }
    if ((int)($file['size'] ?? 0) > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Attachment must be 5MB or smaller.']);
        exit;
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Attachment must be JPG, PNG, GIF, or WEBP.']);
        exit;
    }
    $uploadDir = dirname(__DIR__, 2) . '/uploads/staff_messages';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Upload folder unavailable.']);
        exit;
    }
    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    $dest = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not save attachment.']);
        exit;
    }
    $attachmentPath = 'uploads/staff_messages/' . $filename;
    if ($body === '') {
        $body = '[Image]';
    }
}

if ($body === '' || mb_strlen($body) > 8000) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Message must be 1–8000 characters, or include an image.']);
    exit;
}

if (!eventify_staff_messaging_pair_allowed($conn, $uid, $recipientId)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'You can only message admins or organizers.']);
    exit;
}

if (!eventify_staff_messages_ensure_table($conn)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Messaging is unavailable.']);
    exit;
}

$stmt = $conn->prepare('INSERT INTO staff_messages (sender_id, recipient_id, body, attachment_path) VALUES (?, ?, ?, ?)');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save message.']);
    exit;
}
$stmt->bind_param('iiss', $uid, $recipientId, $body, $attachmentPath);
$ok = $stmt->execute();
$newId = (int)$stmt->insert_id;
$stmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save message.']);
    exit;
}

log_activity($conn, $uid, $role, 'staff_message_sent', 'user', $recipientId, 'Staff message (admin↔organizer)');

try {
    $snippet = mb_strimwidth($body, 0, 120, '…');
    $title = $role === 'admin' ? 'Message from Admin' : 'Message from Organizer';
    $ins = $conn->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'staff_message', ?, ?)");
    if ($ins) {
        $ins->bind_param('iss', $recipientId, $title, $snippet);
        $ins->execute();
        $ins->close();
    }
} catch (Throwable $e) {
    // ignore if notifications schema differs
}

$conn->close();

echo json_encode(['ok' => true, 'id' => $newId, 'attachment_path' => $attachmentPath]);
