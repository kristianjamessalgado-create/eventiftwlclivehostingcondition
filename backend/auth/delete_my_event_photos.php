<?php
session_start();
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'multimedia') {
    header("Location: " . BASE_URL . "/views/login.php?error=" . urlencode("Access denied"));
    exit();
}

$user_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['event_id'])) {
    header("Location: " . BASE_URL . "/backend/auth/dashboard_multimedia.php?msg=" . urlencode("Invalid delete request."));
    exit();
}
if (!csrf_validate()) {
    header("Location: " . BASE_URL . "/backend/auth/dashboard_multimedia.php?msg=" . urlencode("Invalid request. Please try again."));
    exit();
}

$event_id = (int) $_POST['event_id'];
if ($event_id <= 0) {
    header("Location: " . BASE_URL . "/backend/auth/dashboard_multimedia.php?msg=" . urlencode("Invalid event."));
    exit();
}

// Fetch all file paths uploaded by this user for this event
$stmt = $conn->prepare("SELECT id, file_path FROM event_photos WHERE event_id = ? AND uploaded_by = ?");
$stmt->bind_param("ii", $event_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();

$photo_ids = [];
$file_paths = [];
while ($row = $res->fetch_assoc()) {
    $photo_ids[] = (int)$row['id'];
    if (!empty($row['file_path'])) $file_paths[] = $row['file_path'];
}
$stmt->close();

if (empty($photo_ids)) {
    $conn->close();
    header("Location: " . BASE_URL . "/backend/auth/dashboard_multimedia.php?msg=" . urlencode("No photos to delete for this event."));
    exit();
}

// Delete files on disk
$base_dir = dirname(__DIR__, 2); // project root
foreach ($file_paths as $p) {
    $full_path = $base_dir . '/' . $p;
    if (is_file($full_path)) {
        @unlink($full_path);
    }
}

// Delete DB rows (bulk)
$placeholders = implode(',', array_fill(0, count($photo_ids), '?'));
$types = str_repeat('i', count($photo_ids));
$sql = "DELETE FROM event_photos WHERE id IN ($placeholders)";
$del = $conn->prepare($sql);
$del->bind_param($types, ...$photo_ids);
$del->execute();
$deleted = $del->affected_rows;
$del->close();
$conn->close();

header("Location: " . BASE_URL . "/backend/auth/dashboard_multimedia.php?msg=" . urlencode($deleted . " photo(s) deleted."));
exit();

