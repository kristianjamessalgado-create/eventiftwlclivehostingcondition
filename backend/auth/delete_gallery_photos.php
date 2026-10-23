<?php
session_start();
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/multimedia_moderator.php';

function eventify_gallery_delete_json(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'multimedia') {
    eventify_gallery_delete_json(403, ['ok' => false, 'error' => 'Access denied.', 'message' => '', 'deleted_ids' => []]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['event_id'])) {
    eventify_gallery_delete_json(400, ['ok' => false, 'error' => 'Invalid delete request.', 'message' => '', 'deleted_ids' => []]);
}
if (!csrf_validate()) {
    eventify_gallery_delete_json(400, ['ok' => false, 'error' => 'Invalid request. Please try again.', 'message' => '', 'deleted_ids' => []]);
}

$userId = (int) $_SESSION['user_id'];
$eventId = (int) $_POST['event_id'];
$deleteAll = ((string) ($_POST['delete_all'] ?? '')) === '1';
$reason = trim((string) ($_POST['reason'] ?? ''));
$photoIds = [];
if (isset($_POST['photo_ids']) && is_array($_POST['photo_ids'])) {
    $photoIds = $_POST['photo_ids'];
}

$result = eventify_multimedia_delete_gallery_photos(
    $conn,
    $eventId,
    $userId,
    $photoIds,
    $deleteAll,
    $reason,
    dirname(__DIR__, 2)
);
$conn->close();

eventify_gallery_delete_json(
    !empty($result['ok']) ? 200 : 400,
    $result
);
