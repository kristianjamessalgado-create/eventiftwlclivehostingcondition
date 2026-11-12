<?php
/**
 * Teacher view of a student's activity card, opened by scanning the QR printed
 * on the card. Admins (teachers) can apply or remove a digital stamp per row.
 */
session_start();

include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
include __DIR__ . '/config/csrf.php';
include __DIR__ . '/config/departments.php';
require_once __DIR__ . '/backend/lib/activity_card.php';
require_once __DIR__ . '/backend/lib/activity_logger.php';

$token = strtolower(trim((string) ($_GET['t'] ?? '')));
$selfUrl = BASE_URL . '/activity_card_view.php?t=' . rawurlencode($token);

$role = (string) ($_SESSION['role'] ?? '');
if (!isset($_SESSION['user_id'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $back = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $selfUrl;
    header('Location: ' . BASE_URL . '/views/login.php?redirect=' . urlencode($back));
    exit();
}

$isTeacher = in_array($role, ['admin', 'super_admin'], true);
$adminId = (int) $_SESSION['user_id'];
$adminName = trim((string) ($_SESSION['name'] ?? ''));

$studentId = $isTeacher ? eventify_activity_card_user_by_token($conn, $token) : 0;
$student = null;
if ($studentId > 0) {
    $st = $conn->prepare("SELECT id, name, department, user_id FROM users WHERE id = ? AND role = 'student' LIMIT 1");
    if ($st) {
        $st->bind_param('i', $studentId);
        $st->execute();
        $student = $st->get_result()->fetch_assoc() ?: null;
        $st->close();
    }
}

$flash = trim((string) ($_GET['msg'] ?? ''));
$flashError = trim((string) ($_GET['error'] ?? ''));

if ($isTeacher && $student && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = $selfUrl;
    if (!csrf_validate()) {
        header('Location: ' . $back . '&error=' . urlencode('Session expired. Please try again.'));
        exit();
    }
    if ($adminName === '') {
        $nm = $conn->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
        if ($nm) {
            $nm->bind_param('i', $adminId);
            $nm->execute();
            $adminName = (string) (($nm->get_result()->fetch_assoc() ?: [])['name'] ?? 'Teacher');
            $nm->close();
        }
    }
    $action = (string) ($_POST['action'] ?? '');
    $rows = eventify_activity_card_load_rows($conn, $studentId, (string) ($student['department'] ?? '') ?: null);
    $validKeys = [];
    foreach ($rows as $r) {
        $k = eventify_activity_card_row_key($r);
        if ($k !== '') {
            $validKeys[$k] = trim((string) ($r['time_in'] ?? '')) !== '';
        }
    }
    $count = 0;
    if ($action === 'stamp_all') {
        $existing = eventify_activity_card_load_stamps($conn, $studentId);
        foreach ($validKeys as $k => $checkedIn) {
            if ($checkedIn && !isset($existing[$k]) && eventify_activity_card_set_stamp($conn, $studentId, $k, $adminId, $adminName, true)) {
                $count++;
            }
        }
        $msg = $count > 0 ? "Stamped {$count} row" . ($count === 1 ? '' : 's') . '.' : 'Every checked-in row is already stamped.';
    } else {
        $rowKey = (string) ($_POST['row_key'] ?? '');
        if (!array_key_exists($rowKey, $validKeys)) {
            header('Location: ' . $back . '&error=' . urlencode('That row is no longer on this card.'));
            exit();
        }
        if ($action === 'stamp') {
            if (!$validKeys[$rowKey]) {
                header('Location: ' . $back . '&error=' . urlencode('Only rows with a check-in can be stamped.'));
                exit();
            }
            $count = eventify_activity_card_set_stamp($conn, $studentId, $rowKey, $adminId, $adminName, true) ? 1 : 0;
            $msg = 'Row stamped.';
        } elseif ($action === 'unstamp') {
            $count = eventify_activity_card_set_stamp($conn, $studentId, $rowKey, $adminId, $adminName, false) ? 1 : 0;
            $msg = 'Stamp removed.';
        } else {
            $msg = '';
        }
    }
    if ($count > 0 && function_exists('log_activity')) {
        log_activity($conn, $adminId, $role, $action === 'unstamp' ? 'activity_card_unstamp' : 'activity_card_stamp', 'user', $studentId,
            ($action === 'unstamp' ? 'Removed' : 'Applied') . " {$count} activity card stamp(s) for " . (string) ($student['name'] ?? 'student'));
    }
    header('Location: ' . $back . ($msg !== '' ? '&msg=' . urlencode($msg) : ''));
    exit();
}

$acDisplayRows = [];
$checkedInUnstamped = 0;
$stampedCount = 0;
if ($isTeacher && $student) {
    $studentDept = trim((string) ($student['department'] ?? ''));
    try {
        $rows = eventify_activity_card_load_rows($conn, $studentId, $studentDept !== '' ? $studentDept : null);
        $rows = eventify_activity_card_attach_stamps($rows, eventify_activity_card_load_stamps($conn, $studentId));
    } catch (Throwable $e) {
        $rows = [];
    }
    // Teachers only need rows the student actually attended or that are stamped.
    foreach ($rows as $r) {
        $in = trim((string) ($r['time_in'] ?? '')) !== '';
        if (!$in && empty($r['signed'])) {
            continue;
        }
        $acDisplayRows[] = $r;
        if (!empty($r['signed'])) {
            $stampedCount++;
        } elseif ($in) {
            $checkedInUnstamped++;
        }
    }
    $acStudentName = (string) ($student['name'] ?? '');
    $acDeptLabel = $studentDept !== '' && function_exists('eventify_format_department_label')
        ? eventify_format_department_label($studentDept)
        : $studentDept;
    $acTeacherMode = true;
    $acTeacherFormAction = $selfUrl;
    $acQrImageUrl = '';
}
$conn->close();

$dashboardUrl = $role === 'super_admin'
    ? BASE_URL . '/backend/super_admin/dashboardsuperadmin.php'
    : BASE_URL . '/backend/admin/dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0A3C26">
    <meta name="robots" content="noindex">
    <title>Student activity card | EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/eventify_modal.css?v=10">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/activity_card.css?v=6">
</head>
<body class="ac-page ac-teacher-page">
<header class="ac-teacher-bar">
    <a class="ac-teacher-bar__brand" href="<?= htmlspecialchars($isTeacher ? $dashboardUrl : BASE_URL . '/index.php') ?>">
        <i class="fas fa-calendar-alt" aria-hidden="true"></i><span>EVENTIFY</span>
    </a>
    <?php if ($isTeacher): ?>
        <a class="ac-teacher-bar__link" href="<?= htmlspecialchars($dashboardUrl) ?>"><i class="fas fa-gauge-high" aria-hidden="true"></i><span>Dashboard</span></a>
    <?php endif; ?>
</header>

<main class="ac-teacher-main">
    <?php if (!$isTeacher): ?>
        <div class="ac-teacher-empty">
            <i class="fas fa-lock" aria-hidden="true"></i>
            <h1>Teachers only</h1>
            <p>Only admin (teacher) accounts can open a student's activity card from its QR code. Sign out and sign in with your teacher account.</p>
        </div>
    <?php elseif (!$student): ?>
        <div class="ac-teacher-empty">
            <i class="fas fa-qrcode" aria-hidden="true"></i>
            <h1>Card not found</h1>
            <p>This QR code doesn't match any student activity card. Ask the student to print a fresh card.</p>
        </div>
    <?php else: ?>
        <section class="ac-teacher-summary">
            <div>
                <p class="ac-teacher-summary__kicker">Student activity card</p>
                <h1 class="ac-teacher-summary__name"><?= htmlspecialchars($acStudentName) ?></h1>
                <p class="ac-teacher-summary__meta">
                    <?php if (!empty($student['user_id'])): ?>ID <?= htmlspecialchars((string) $student['user_id']) ?> · <?php endif; ?>
                    <?= count($acDisplayRows) ?> attended · <?= $stampedCount ?> stamped
                </p>
            </div>
            <?php if ($checkedInUnstamped > 0): ?>
                <form method="post" action="<?= htmlspecialchars($selfUrl) ?>"
                      data-efy-confirm="Apply your digital stamp to all <?= $checkedInUnstamped ?> checked-in row<?= $checkedInUnstamped === 1 ? '' : 's' ?> that are not stamped yet?"
                      data-efy-confirm-title="Stamp all rows" data-efy-confirm-label="Stamp all" data-efy-confirm-type="info">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="stamp_all">
                    <button type="submit" class="ac-btn ac-btn--print"><i class="fas fa-stamp" aria-hidden="true"></i> Stamp all (<?= $checkedInUnstamped ?>)</button>
                </form>
            <?php endif; ?>
        </section>

        <?php if ($flash !== ''): ?>
            <div class="alert alert-success py-2 small"><i class="fas fa-circle-check me-1"></i><?= htmlspecialchars($flash) ?></div>
        <?php elseif ($flashError !== ''): ?>
            <div class="alert alert-danger py-2 small"><i class="fas fa-triangle-exclamation me-1"></i><?= htmlspecialchars($flashError) ?></div>
        <?php endif; ?>

        <?php if (empty($acDisplayRows)): ?>
            <div class="ac-teacher-empty">
                <i class="fas fa-clipboard" aria-hidden="true"></i>
                <h1>No attendance yet</h1>
                <p>This student hasn't checked in to any event or activity yet.</p>
            </div>
        <?php else: ?>
            <div class="ac-sheet-wrap">
                <?php include __DIR__ . '/views/partials/activity_card_sheet.php'; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_alert_modal.js?v=4"></script>
</body>
</html>
