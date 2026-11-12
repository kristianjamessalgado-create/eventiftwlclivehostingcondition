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

// Filter chosen on the student's card (carried in the QR link).
$acFilter = [
    'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date'] ?? '')) ? (string) $_GET['date'] : '',
    'event_id' => max(0, (int) ($_GET['event'] ?? 0)),
    'session_id' => max(0, (int) ($_GET['activity'] ?? 0)),
    'kind' => in_array((string) ($_GET['kind'] ?? ''), ['events', 'activities', 'checked_in'], true) ? (string) $_GET['kind'] : 'all',
];
$acFilterQuery = array_filter([
    't' => $token,
    'date' => $acFilter['date'],
    'event' => $acFilter['event_id'] ?: '',
    'activity' => $acFilter['session_id'] ?: '',
    'kind' => $acFilter['kind'] !== 'all' ? $acFilter['kind'] : '',
], static fn ($v) => $v !== '' && $v !== null);
$selfUrl = BASE_URL . '/activity_card_view.php?' . http_build_query($acFilterQuery);
$unfilteredUrl = BASE_URL . '/activity_card_view.php?t=' . rawurlencode($token);
$acFilterActive = $acFilter['date'] !== '' || $acFilter['event_id'] > 0 || $acFilter['session_id'] > 0 || $acFilter['kind'] !== 'all';

$role = (string) ($_SESSION['role'] ?? '');
if (!isset($_SESSION['user_id'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $back = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $selfUrl;
    header('Location: ' . BASE_URL . '/views/login.php?redirect=' . urlencode($back));
    exit();
}

// Admins (teachers) can stamp any row; organizers can open cards but only stamp
// rows for events they organize.
$isAdminStamper = in_array($role, ['admin', 'super_admin'], true);
$isTeacher = $isAdminStamper || $role === 'organizer';
$adminId = (int) $_SESSION['user_id'];
$adminName = trim((string) ($_SESSION['name'] ?? ''));

/** Rows on this student's card that match the QR filter, with 'can_stamp' set for this viewer. */
function ac_view_rows(mysqli $conn, int $studentId, ?string $dept, array $filter, bool $isAdminStamper, int $viewerId): array
{
    $rows = eventify_activity_card_load_rows($conn, $studentId, $dept);
    $rows = eventify_activity_card_apply_filters($rows, $filter);
    $ownEvents = [];
    if (!$isAdminStamper) {
        $ids = array_values(array_unique(array_filter(array_map(static fn ($r) => (int) ($r['event_id'] ?? 0), $rows))));
        if ($ids !== []) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = $conn->prepare("SELECT id FROM events WHERE organizer_id = ? AND id IN ($in)");
            if ($st) {
                $types = 'i' . str_repeat('i', count($ids));
                $st->bind_param($types, $viewerId, ...$ids);
                $st->execute();
                $res = $st->get_result();
                while ($res && ($r = $res->fetch_assoc())) {
                    $ownEvents[(int) $r['id']] = true;
                }
                $st->close();
            }
        }
    }
    foreach ($rows as &$r) {
        $r['can_stamp'] = $isAdminStamper || isset($ownEvents[(int) ($r['event_id'] ?? 0)]);
    }
    unset($r);
    return $rows;
}

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
    // Only rows inside the QR filter that this viewer may stamp are valid targets.
    $rows = ac_view_rows($conn, $studentId, (string) ($student['department'] ?? '') ?: null, $acFilter, $isAdminStamper, $adminId);
    $validKeys = [];
    foreach ($rows as $r) {
        $k = eventify_activity_card_row_key($r);
        if ($k !== '' && !empty($r['can_stamp'])) {
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
            header('Location: ' . $back . '&error=' . urlencode('You can\'t stamp that row from this card view.'));
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
        $rows = ac_view_rows($conn, $studentId, $studentDept !== '' ? $studentDept : null, $acFilter, $isAdminStamper, $adminId);
        $rows = eventify_activity_card_attach_stamps($rows, eventify_activity_card_load_stamps($conn, $studentId));
    } catch (Throwable $e) {
        $rows = [];
    }
    // Human-readable description of the QR filter for the banner.
    $filterBits = [];
    if ($acFilter['date'] !== '') {
        $filterBits[] = date('M j, Y', strtotime($acFilter['date']));
    }
    if ($acFilter['event_id'] > 0 || $acFilter['session_id'] > 0) {
        foreach ($rows as $r) {
            if ($acFilter['session_id'] > 0 && (int) ($r['session_id'] ?? 0) === $acFilter['session_id']) {
                $filterBits[] = (string) ($r['activity_title'] ?? 'Activity');
                break;
            }
            if ($acFilter['session_id'] < 1 && (int) ($r['event_id'] ?? 0) === $acFilter['event_id']) {
                $filterBits[] = (string) ($r['event_title'] ?? 'Event');
                break;
            }
        }
    }
    $kindLabels = ['events' => 'Events only', 'activities' => 'Activities only', 'checked_in' => 'Checked-in rows'];
    if (isset($kindLabels[$acFilter['kind']])) {
        $filterBits[] = $kindLabels[$acFilter['kind']];
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
        } elseif ($in && !empty($r['can_stamp'])) {
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
    : ($role === 'organizer'
        ? BASE_URL . '/backend/auth/dashboardorganizer.php'
        : BASE_URL . '/backend/admin/dashboard.php');
$filterBits = $filterBits ?? [];
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
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/activity_card.css?v=7">
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
            <p>Only admin (teacher) and organizer accounts can open a student's activity card from its QR code. Sign out and sign in with your teacher account.</p>
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

        <?php if ($acFilterActive): ?>
            <div class="ac-teacher-filter" role="note">
                <i class="fas fa-filter" aria-hidden="true"></i>
                <span>
                    Showing only what the student filtered<?= $filterBits !== [] ? ': <strong>' . htmlspecialchars(implode(' · ', $filterBits)) . '</strong>' : '' ?>.
                    Stamps apply to these rows only.
                </span>
                <a href="<?= htmlspecialchars($unfilteredUrl) ?>">Show all</a>
            </div>
        <?php endif; ?>
        <?php if (!$isAdminStamper): ?>
            <p class="ac-teacher-hint"><i class="fas fa-circle-info" aria-hidden="true"></i> As an organizer you can stamp rows for events you organize.</p>
        <?php endif; ?>

        <?php if ($flash !== ''): ?>
            <div class="alert alert-success py-2 small"><i class="fas fa-circle-check me-1"></i><?= htmlspecialchars($flash) ?></div>
        <?php elseif ($flashError !== ''): ?>
            <div class="alert alert-danger py-2 small"><i class="fas fa-triangle-exclamation me-1"></i><?= htmlspecialchars($flashError) ?></div>
        <?php endif; ?>

        <?php if (empty($acDisplayRows)): ?>
            <div class="ac-teacher-empty">
                <i class="fas fa-clipboard" aria-hidden="true"></i>
                <h1><?= $acFilterActive ? 'Nothing to stamp here' : 'No attendance yet' ?></h1>
                <p><?= $acFilterActive
                    ? 'The student has no check-ins that match this filter.'
                    : 'This student hasn\'t checked in to any event or activity yet.' ?></p>
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
