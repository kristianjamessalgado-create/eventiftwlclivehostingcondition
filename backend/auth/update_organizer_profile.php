<?php
session_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';

// Only allow logged-in organizers
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'organizer') {
    header("Location: " . BASE_URL . "/views/login.php?error=" . urlencode("Access denied"));
    exit();
}

$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php?msg=" . urlencode("Invalid request. Please try again."));
        exit();
    }
    $name  = trim($_POST['name'] ?? '');
    $organizerContactEmail = trim($_POST['organizer_contact_email'] ?? '');
    $organizerPhone = trim($_POST['organizer_phone'] ?? '');
    $organizerContactMethod = $_POST['organizer_contact_method'] ?? 'email';
    $error = '';
    $profilePicturePath = null;

    if ($name === '') {
        $error = "Full name is required.";
    } elseif (strlen($name) > 100) {
        $error = "Full name must be 100 characters or less.";
    }

    if (!in_array($organizerContactMethod, ['email', 'phone'], true)) {
        $organizerContactMethod = 'email';
    }
    if ($organizerContactEmail !== '' && !filter_var($organizerContactEmail, FILTER_VALIDATE_EMAIL)) {
        $error = "Verification email is invalid.";
    }
    if ($organizerPhone !== '' && !preg_match('/^[0-9+\-\s()]{7,25}$/', $organizerPhone)) {
        $error = "Verification phone number format is invalid.";
    }
    if ($error === '' && $organizerContactMethod === 'phone' && $organizerPhone === '') {
        $error = "Please provide a phone number for OTP verification.";
    }
    if ($error === '' && $organizerContactMethod === 'email' && $organizerContactEmail === '') {
        $error = "Please provide a verification email for OTP verification.";
    }

    // Handle profile picture upload (optional)
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file         = $_FILES['profile_picture'];
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
        header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php?msg=" . urlencode($error));
        exit();
    }

    $hasContactColumns = false;
    try {
        $colCheck = $conn->query("SHOW COLUMNS FROM users WHERE Field IN ('organizer_contact_email','organizer_phone','organizer_contact_method')");
        $hasContactColumns = (bool) ($colCheck && $colCheck->num_rows >= 3);
    } catch (Throwable $e) {
        $hasContactColumns = false;
    }

    // Update name and optionally profile picture + contact fields
    if ($profilePicturePath !== null) {
        if ($hasContactColumns) {
            $stmt = $conn->prepare("UPDATE users SET name = ?, profile_picture = ?, organizer_contact_email = ?, organizer_phone = ?, organizer_contact_method = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $name, $profilePicturePath, $organizerContactEmail, $organizerPhone, $organizerContactMethod, $userId);
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ?, profile_picture = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $profilePicturePath, $userId);
        }
    } else {
        if ($hasContactColumns) {
            $stmt = $conn->prepare("UPDATE users SET name = ?, organizer_contact_email = ?, organizer_phone = ?, organizer_contact_method = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $organizerContactEmail, $organizerPhone, $organizerContactMethod, $userId);
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $name, $userId);
        }
    }

    if ($stmt) {
        $stmt->execute();
        $stmt->close();
        $_SESSION['name'] = $name;

        $successMsg = ($profilePicturePath !== null)
            ? "Profile and picture updated successfully."
            : "Profile updated successfully.";
        if (!$hasContactColumns) {
            $successMsg .= " Run school_events_event_approval_otp.sql to enable OTP contact fields.";
        }

        header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php?msg=" . urlencode($successMsg));
        exit();
    } else {
        header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php?msg=" . urlencode("Failed to update profile."));
        exit();
    }
}

header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php");
exit();
