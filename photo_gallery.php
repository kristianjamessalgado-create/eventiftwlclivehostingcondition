<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
require_once __DIR__ . '/backend/lib/event_photos.php';

$token = trim($_GET['t'] ?? '');
$eventIdParam = (int) ($_GET['event_id'] ?? 0);
$staffPreview = isset($_GET['staff_preview']) && (string) $_GET['staff_preview'] === '1';
$staffRoles = ['multimedia', 'admin', 'super_admin'];
$role = $_SESSION['role'] ?? '';

if ($staffPreview && !isset($_SESSION['user_id'])) {
    $previewPath = BASE_URL . '/photo_gallery.php?staff_preview=1';
    if ($eventIdParam > 0) {
        $previewPath .= '&event_id=' . $eventIdParam;
    } elseif ($token !== '') {
        $previewPath .= '&t=' . urlencode($token);
    }
    $previewReturn = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $previewPath;
    header('Location: ' . BASE_URL . '/views/login.php?redirect=' . urlencode($previewReturn));
    exit();
}

$isStaffPreview = $staffPreview
    && isset($_SESSION['user_id'])
    && in_array($role, $staffRoles, true);
$error = '';
$event = null;
$photos = [];
$staffBackUrl = BASE_URL . '/backend/auth/dashboard_multimedia.php';
if ($role === 'admin') {
    $staffBackUrl = BASE_URL . '/backend/admin/dashboard.php';
} elseif ($role === 'super_admin') {
    $staffBackUrl = BASE_URL . '/backend/super_admin/dashboardsuperadmin.php';
}

if ($isStaffPreview && $eventIdParam > 0) {
    $stmt = $conn->prepare('SELECT id, title, date, location, status, department FROM events WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $eventIdParam);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} elseif ($token !== '') {
    $stmt = $conn->prepare('SELECT id, title, date, location, status, department FROM events WHERE checkin_token = ?');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} elseif (!$isStaffPreview && $token === '') {
    $error = 'Invalid or missing gallery link. Please scan the event QR code again.';
} elseif ($staffPreview && !$isStaffPreview) {
    $error = 'Staff preview is only available to multimedia and administrators.';
}

if (!$error && $event) {
    if (!in_array(strtolower($event['status'] ?? ''), ['active', 'closed'], true)) {
        $error = 'Photos are only available for approved events.';
        $event = null;
    }
} elseif (!$error && !$event) {
    $error = $isStaffPreview
        ? 'Event not found.'
        : 'This gallery link is invalid or has expired.';
}

// Student gallery access (default) or staff preview for multimedia/admin
if (!$error && $event) {
    if ($isStaffPreview) {
        if ($role === 'multimedia') {
            $uid = (int) $_SESSION['user_id'];
            $mmStmt = $conn->prepare('SELECT department FROM users WHERE id = ? LIMIT 1');
            $mmStmt->bind_param('i', $uid);
            $mmStmt->execute();
            $mmUser = $mmStmt->get_result()->fetch_assoc();
            $mmStmt->close();
            $mmDept = trim((string) ($mmUser['department'] ?? ''));
            $eventDept = trim((string) ($event['department'] ?? ''));
            if ($mmDept !== '' && $eventDept !== '' && strtoupper($eventDept) !== 'ALL' && $eventDept !== $mmDept) {
                $error = 'This event is outside your multimedia department.';
                $event = null;
            }
        }
    } else {
        if (!isset($_SESSION['user_id'])) {
            $returnUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? '')
                . BASE_URL . '/photo_gallery.php?t=' . urlencode($token);
            header('Location: ' . BASE_URL . '/views/login.php?redirect=' . urlencode($returnUrl));
            exit();
        }

        if ($_SESSION['role'] !== 'student') {
            $error = 'Only students can view this gallery. Please log in with a student account.';
            $event = null;
        } else {
            $uid = (int) $_SESSION['user_id'];
            $stuStmt = $conn->prepare('SELECT department FROM users WHERE id = ?');
            $stuStmt->bind_param('i', $uid);
            $stuStmt->execute();
            $student = $stuStmt->get_result()->fetch_assoc();
            $stuStmt->close();

            $studentDept = $student['department'] ?? null;
            $eventDept = $event['department'] ?? null;

            if ($eventDept && $eventDept !== 'ALL' && $studentDept !== $eventDept) {
                $error = 'This gallery is restricted to students from the ' . htmlspecialchars($eventDept) . '.';
                $event = null;
            }
        }
    }
}

if (!$error && $event) {
    $eid = (int) $event['id'];
    eventify_event_photos_ensure_metadata_columns($conn);
    $metaCols = eventify_event_photos_metadata_select_sql($conn, false);
    $pStmt = $conn->prepare("SELECT id, file_path{$metaCols} FROM event_photos WHERE event_id = ? AND status = 'published' ORDER BY created_at DESC, id DESC");
    if (!$pStmt) {
        // Backward compatibility when photo publishing columns aren't migrated yet
        $pStmt = $conn->prepare("SELECT id, file_path FROM event_photos WHERE event_id = ? ORDER BY created_at DESC, id DESC");
    }
    if ($pStmt) {
        $pStmt->bind_param("i", $eid);
        if ($pStmt->execute()) {
            $pRes = $pStmt->get_result();
            if ($pRes) {
                while ($row = $pRes->fetch_assoc()) {
                    $photos[] = $row;
                }
            }
        }
        $pStmt->close();
    }
}

$conn->close();

$pageTitle = $event ? htmlspecialchars($event['title']) . ' – Event Photos' : 'Event Photos';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --school-cream: #f7f4e7;
            --school-olive-top: #b7be77;
            --school-forest-mid: #3f6a2a;
            --school-forest-deep: #153313;
            --school-forest-card: #1b4a1b;
            --school-gold: #e6c54a;
            --school-gold-dim: #b88f2a;
            --school-border: rgba(230, 197, 74, 0.42);
        }
        body {
            min-height: 100vh;
            background: linear-gradient(180deg, var(--school-olive-top) 0%, var(--school-forest-mid) 42%, var(--school-forest-deep) 100%);
            background-attachment: fixed;
            padding: 1.5rem 0.75rem;
        }
        .gallery-card {
            max-width: 960px;
            margin: 0 auto;
            border-radius: 18px;
            border: 2px solid var(--school-border);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            background:#fff;
        }
        .gallery-header {
            padding: 1.5rem 1.75rem;
            background: linear-gradient(180deg, var(--school-forest-card) 0%, #021a08 100%);
            border-bottom: 3px solid var(--school-gold);
            color: #fff;
        }
        .gallery-header h1 { font-size: 1.4rem; margin:0 0 .25rem 0; display:flex; align-items:center; gap:.5rem; }
        .gallery-header h1 i { font-size:1.25rem; color: var(--school-gold); }
        .gallery-subtitle { font-size: .9rem; opacity:.9; margin:0; }
        .event-meta { font-size: .9rem; opacity:.9; margin-top:.35rem; display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; }
        .pill { display:inline-flex; align-items:center; padding:0.2rem 0.6rem; border-radius:999px; font-size:.75rem; border:1px solid rgba(230, 197, 74, .58); background:rgba(230, 197, 74, .15); color:#fff; font-weight:700; }
        .gallery-body { padding: 1.5rem 1.75rem 1.75rem; background: linear-gradient(180deg, #ffffff 0%, var(--school-cream) 100%); }
        .thumb-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:0.75rem; }
        .thumb { position:relative; border-radius:12px; overflow:hidden; background:#e5e7eb; cursor:pointer; border:1px solid rgba(1, 50, 32, 0.22); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .thumb img { width:100%; height:140px; object-fit:cover; display:block; transition:transform .2s ease; }
        .thumb:hover img { transform:scale(1.03); }
        .thumb:hover { border-color: var(--school-gold-dim); }
        .thumb-overlay { position:absolute; inset:0; background:linear-gradient(to bottom,rgba(0,0,0,0) 40%,rgba(0,0,0,0.45)); display:flex; align-items:flex-end; justify-content:space-between; color:#fff; padding:.4rem .45rem; font-size:.75rem; opacity:0; transition:opacity .2s ease; }
        .thumb:hover .thumb-overlay { opacity:1; }
        .badge-count { background:rgba(0,0,0,0.6); padding:.15rem .45rem; border-radius:999px; font-size:.7rem; }
        .empty-state { text-align:center; padding:2.5rem 1.5rem; color:#4b5563; }
        .empty-state i { font-size:2.5rem; margin-bottom:.75rem; opacity:.75; color: var(--school-forest-card); }
        .empty-state .btn {
            background: linear-gradient(180deg, var(--school-forest-card) 0%, var(--school-forest-deep) 100%);
            border: 2px solid var(--school-gold);
            color: var(--school-gold);
            font-weight: 700;
        }
        .empty-state .btn:hover {
            background: var(--school-forest-deep);
            border-color: #fff7a8;
            color: var(--school-gold);
        }
        .footer-note { font-size:.75rem; color:#9ca3af; margin-top:1rem; text-align:right; }
        .staff-preview-banner {
            margin: 0 0 1rem;
            padding: 0.65rem 0.85rem;
            border-radius: 10px;
            border: 1px solid rgba(230, 197, 74, 0.55);
            background: rgba(230, 197, 74, 0.14);
            color: #3f2f05;
            font-size: 0.85rem;
        }
        .staff-preview-banner a { color: var(--school-forest-card); font-weight: 700; }
        .thumb-caption {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            padding: 0.45rem 0.5rem;
            background: linear-gradient(to top, rgba(0,0,0,0.72), transparent);
            color: #fff;
            font-size: 0.68rem;
            line-height: 1.25;
        }
        .thumb-caption__credit { opacity: 0.85; font-size: 0.62rem; margin-top: 0.1rem; }
    </style>
</head>
<body>
    <div class="gallery-card">
        <div class="gallery-header">
            <h1><i class="fas fa-images"></i><span>Event Photos</span></h1>
            <?php if ($error): ?>
                <p class="gallery-subtitle text-warning mb-0"><?= htmlspecialchars($error) ?></p>
            <?php elseif ($event): ?>
                <p class="gallery-subtitle mb-0"><?= htmlspecialchars($event['title']) ?></p>
                <div class="event-meta">
                    <?php if (!empty($event['date'])): ?>
                        <span><i class="fas fa-calendar-day me-1"></i><?= date('l, M j, Y', strtotime($event['date'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($event['location'])): ?>
                        <span><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($event['location']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($event['department']) && $event['department'] !== 'ALL'): ?>
                        <span class="pill"><i class="fas fa-building me-1"></i><?= htmlspecialchars($event['department']) ?> only</span>
                    <?php else: ?>
                        <span class="pill"><i class="fas fa-users me-1"></i>All departments</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="gallery-body">
            <?php if ($isStaffPreview && !$error && $event): ?>
                <div class="staff-preview-banner" role="status">
                    <i class="fas fa-eye me-1"></i>
                    <strong>Staff preview</strong> — this is what students see after they scan the gallery QR (published photos only).
                    <a href="<?= htmlspecialchars($staffBackUrl) ?>" class="ms-1">Back to dashboard</a>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="empty-state">
                    <i class="fas fa-triangle-exclamation"></i>
                    <p class="mb-1"><?= htmlspecialchars($error) ?></p>
                    <?php if ($isStaffPreview): ?>
                        <a href="<?= htmlspecialchars($staffBackUrl) ?>" class="btn btn-outline-primary btn-sm mt-2">Back to dashboard</a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/backend/auth/dashboard_student.php" class="btn btn-outline-primary btn-sm mt-2">Back to dashboard</a>
                    <?php endif; ?>
                </div>
            <?php elseif ($event && empty($photos)): ?>
                <div class="empty-state">
                    <i class="fas fa-image"></i>
                    <p class="mb-1">No official photos have been uploaded yet for this event.</p>
                    <p class="mb-0 small">Please check again later or contact your multimedia team.</p>
                </div>
            <?php elseif ($event): ?>
                <div class="thumb-grid">
                    <?php foreach ($photos as $index => $p): ?>
                        <?php
                            $pCaption = trim((string) ($p['caption'] ?? ''));
                            $pCredit = trim((string) ($p['credit_line'] ?? ''));
                        ?>
                        <div class="thumb" data-index="<?= (int)$index ?>">
                            <img src="<?= BASE_URL . '/' . htmlspecialchars($p['file_path']) ?>" alt="<?= htmlspecialchars($pCaption !== '' ? $pCaption : 'Event photo') ?>">
                            <?php if ($pCaption !== '' || $pCredit !== ''): ?>
                                <div class="thumb-caption">
                                    <?php if ($pCaption !== ''): ?><div><?= htmlspecialchars($pCaption) ?></div><?php endif; ?>
                                    <?php if ($pCredit !== ''): ?><div class="thumb-caption__credit"><?= htmlspecialchars($pCredit) ?></div><?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="thumb-overlay">
                                <span><i class="fas fa-eye me-1"></i>Tap to view</span>
                                <span class="badge-count"><?= ($index + 1) ?>/<?= count($photos) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="footer-note mb-0">Gallery is only visible to students in the allowed department.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($event && !empty($photos)): ?>
    <!-- Simple full-image viewer -->
    <div id="viewerOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.9); z-index:10000; align-items:center; justify-content:center;">
        <div style="position:absolute; inset:0; cursor:pointer;" onclick="hideViewer()"></div>
        <button type="button" onclick="hideViewer()" style="position:absolute; top:1.25rem; right:1.25rem; width:40px; height:40px; border-radius:50%; border:none; background:rgba(255,255,255,0.15); color:#fff; display:flex; align-items:center; justify-content:center;"><i class="fas fa-times"></i></button>
        <button type="button" onclick="prevPhoto()" style="position:absolute; left:1rem; top:50%; transform:translateY(-50%); width:44px; height:44px; border-radius:50%; border:none; background:rgba(255,255,255,0.15); color:#fff; display:flex; align-items:center; justify-content:center;"><i class="fas fa-chevron-left"></i></button>
        <button type="button" onclick="nextPhoto()" style="position:absolute; right:1rem; top:50%; transform:translateY(-50%); width:44px; height:44px; border-radius:50%; border:none; background:rgba(255,255,255,0.15); color:#fff; display:flex; align-items:center; justify-content:center;"><i class="fas fa-chevron-right"></i></button>
        <div style="position:relative; max-width:90vw; max-height:90vh;">
            <img id="viewerImg" src="" alt="Event photo" style="max-width:100%; max-height:85vh; object-fit:contain; border-radius:10px; box-shadow:0 10px 40px rgba(0,0,0,0.5);">
            <div id="viewerCounter" style="position:absolute; bottom:0.75rem; right:0.75rem; background:rgba(0,0,0,0.6); color:#fff; font-size:.8rem; padding:.2rem .6rem; border-radius:999px;"></div>
        </div>
    </div>

    <script>
    (function() {
        var thumbs = document.querySelectorAll('.thumb');
        var images = <?php
            $srcs = array_map(function($p) {
                return BASE_URL . '/' . $p['file_path'];
            }, $photos);
            echo json_encode($srcs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        ?>;
        var current = 0;
        var overlay = document.getElementById('viewerOverlay');
        var imgEl = document.getElementById('viewerImg');
        var counterEl = document.getElementById('viewerCounter');

        function show(index) {
            if (!images.length) return;
            current = index;
            if (current < 0) current = images.length - 1;
            if (current >= images.length) current = 0;
            imgEl.src = images[current];
            if (counterEl) counterEl.textContent = (current + 1) + ' / ' + images.length;
            overlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        window.hideViewer = function() {
            overlay.style.display = 'none';
            document.body.style.overflow = '';
        };

        window.prevPhoto = function() {
            show(current - 1);
        };

        window.nextPhoto = function() {
            show(current + 1);
        };

        thumbs.forEach(function(t) {
            t.addEventListener('click', function() {
                var idx = parseInt(t.getAttribute('data-index') || '0', 10) || 0;
                show(idx);
            });
        });

        document.addEventListener('keydown', function(e) {
            if (overlay.style.display === 'flex') {
                if (e.key === 'Escape') hideViewer();
                else if (e.key === 'ArrowLeft') prevPhoto();
                else if (e.key === 'ArrowRight') nextPhoto();
            }
        });
    })();
    </script>
    <?php endif; ?>
</body>
</html>

