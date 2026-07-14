<?php
session_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';

// Only allow logged-in multimedia users
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'multimedia') {
    header("Location: " . BASE_URL . "/views/login.php?error=" . urlencode("Access denied"));
    exit();
}

$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        header("Location: " . BASE_URL . "/backend/auth/dashboard_multimedia.php?msg=" . urlencode("Invalid request. Please try again."));
        exit();
    }
    $name  = trim($_POST['name'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $error = '';
    $profilePicturePath = null;

    if ($name === '') {
        $error = "Full name is required.";
    } elseif (strlen($name) > 100) {
        $error = "Full name must be 100 characters or less.";
    }

    $allowedDepartments = [
        'High school department',
        'College of Communication, Information and Technology',
        'College of Accountancy and Business',
        'School of Law and Political Science',
        'College of Education',
        'College of Nursing and Allied health sciences',
        'College of Hospitality Management',
    ];
    if ($department !== '' && !in_array($department, $allowedDepartments, true)) {
        $error = "Invalid department selected.";
    }

    // Handle profile picture upload (optional)
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file        = $_FILES['profile_picture'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize      = 5 * 1024 * 1024; // 5MB

        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            $error = "Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.";
        } elseif ($file['size'] > $maxSize) {
            $error = "File size exceeds 5MB limit.";
        } else {
            $baseDir    = dirname(__DIR__, 2);
            $uploadsDir = $baseDir . '/uploads/profile_pictures';
            if (!is_dir($uploadsDir)) {
                @mkdir($uploadsDir, 0755, true);
            }

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename  = 'profile_' . $userId . '_' . time() . '_' . uniqid() . '.' . $extension;
            $target    = $uploadsDir . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $target)) {
                $profilePicturePath = 'uploads/profile_pictures/' . $filename;

                // Delete old picture if it exists
                $stmtOld = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
                $stmtOld->bind_param("i", $userId);
                $stmtOld->execute();
                $resOld = $stmtOld->get_result();
                if ($rowOld = $resOld->fetch_assoc()) {
                    if (!empty($rowOld['profile_picture'])) {
                        $oldPath = $baseDir . '/' . $rowOld['profile_picture'];
                        if (is_file($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                }
                $stmtOld->close();
            } else {
                $error = "Failed to upload profile picture. Please try again.";
            }
        }
    } elseif (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
        $error = "Error uploading file. Please try again.";
    }

    if ($error !== '') {
        header("Location: " . BASE_URL . "/backend/auth/dashboard_multimedia.php?msg=" . urlencode($error));
        exit();
    }

    $departmentParam = $department === '' ? null : $department;

    // Update name/department and optionally profile picture
    if ($profilePicturePath !== null) {
        $stmt = $conn->prepare("UPDATE users SET name = ?, department = ?, profile_picture = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $departmentParam, $profilePicturePath, $userId);
    } else {
        $stmt = $conn->prepare("UPDATE users SET name = ?, department = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $departmentParam, $userId);
    }

    if ($stmt) {
        $stmt->execute();
        $stmt->close();
        $_SESSION['name'] = $name;

        $successMsg = ($profilePicturePath !== null)
            ? "Profile and picture updated successfully."
            : "Profile updated successfully.";

        header("Location: " . BASE_URL . "/backend/auth/dashboard_multimedia.php?msg=" . urlencode($successMsg));
        exit();
    } else {
        header("Location: " . BASE_URL . "/backend/auth/dashboard_multimedia.php?msg=" . urlencode("Failed to update profile."));
        exit();
    }
}

header("Location: " . BASE_URL . "/backend/auth/dashboard_multimedia.php");
exit();

