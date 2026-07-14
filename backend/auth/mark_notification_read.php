<?php

session_start();

include __DIR__ . '/../../config/db.php';

include __DIR__ . '/../../config/config.php';

include __DIR__ . '/../../config/csrf.php';

require_once __DIR__ . '/../lib/event_reminders.php';

$role = $_SESSION['role'] ?? '';

$user_id = (int) ($_SESSION['user_id'] ?? 0);

$wantsAjax = (isset($_POST['ajax']) && (string) $_POST['ajax'] === '1')

    || (isset($_GET['ajax']) && (string) $_GET['ajax'] === '1');



if ($user_id < 1 || !in_array($role, ['organizer', 'student', 'admin', 'super_admin', 'multimedia'], true)) {

    if ($wantsAjax) {

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(['ok' => false, 'message' => 'Access denied']);

        exit();

    }

    header('Location: ' . BASE_URL . '/views/login.php?error=' . urlencode('Access denied'));

    exit();

}



$redirect = BASE_URL . '/backend/auth/dashboardorganizer.php';

if ($role === 'student') {

    $redirect = BASE_URL . '/backend/auth/dashboard_student.php';

} elseif ($role === 'admin') {

    $redirect = BASE_URL . '/backend/admin/dashboard.php';

} elseif ($role === 'super_admin') {

    $redirect = BASE_URL . '/backend/super_admin/dashboardsuperadmin.php';

} elseif ($role === 'multimedia') {

    $redirect = BASE_URL . '/backend/auth/dashboard_multimedia.php';

}



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    if ($wantsAjax) {

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(['ok' => false, 'message' => 'POST required']);

        exit();

    }

    header('Location: ' . $redirect);

    exit();

}



if (!csrf_validate()) {

    if ($wantsAjax) {

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(['ok' => false, 'message' => 'Invalid request']);

        exit();

    }

    header('Location: ' . $redirect . '?error=' . urlencode('Invalid request'));

    exit();

}



$action = trim((string) ($_POST['action'] ?? ''));

$eventId = 0;

$unreadRemaining = 0;

$deletedCount = 0;



if ($action === 'clear_all') {

    eventify_backfill_reminder_ledger_for_user($conn, $user_id);

    $stmt = $conn->prepare('DELETE FROM notifications WHERE user_id = ?');

    $stmt->bind_param('i', $user_id);

    $stmt->execute();

    $deletedCount = (int) $stmt->affected_rows;

    $stmt->close();

} elseif ($action === 'mark_all') {

    $stmt = $conn->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL');

    $stmt->bind_param('i', $user_id);

    $stmt->execute();

    $stmt->close();

} elseif ($action === 'mark_one') {

    $id = (int) ($_POST['id'] ?? 0);

    if ($id > 0) {

        $fetch = $conn->prepare('SELECT event_id FROM notifications WHERE id = ? AND user_id = ? LIMIT 1');

        if ($fetch) {

            $fetch->bind_param('ii', $id, $user_id);

            $fetch->execute();

            $row = $fetch->get_result()->fetch_assoc();

            $fetch->close();

            if ($row) {

                $eventId = (int) ($row['event_id'] ?? 0);

            }

        }

        $stmt = $conn->prepare('UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ?');

        $stmt->bind_param('ii', $id, $user_id);

        $stmt->execute();

        $stmt->close();

    }

}



$cnt = $conn->prepare('SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND read_at IS NULL');

if ($cnt) {

    $cnt->bind_param('i', $user_id);

    $cnt->execute();

    $cr = $cnt->get_result()->fetch_assoc();

    $cnt->close();

    $unreadRemaining = (int) ($cr['c'] ?? 0);

}



$conn->close();



if ($wantsAjax) {

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([

        'ok' => true,

        'event_id' => $eventId > 0 ? $eventId : null,

        'unread_count' => $unreadRemaining,

        'deleted_count' => $deletedCount,

    ]);

    exit();

}



header('Location: ' . $redirect);

exit();

