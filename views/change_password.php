<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/views/login.php?error=" . urlencode("Please login first."));
    exit();
}

$from = trim((string)($_GET['from'] ?? ''));
$next = trim((string)($_GET['next'] ?? ''));
$msg = trim((string)($_GET['msg'] ?? ''));
$error = trim((string)($_GET['error'] ?? ''));
$userId = (int)($_SESSION['user_id'] ?? 0);
$forceReset = false;
try {
    $col = $conn->query("SHOW COLUMNS FROM users LIKE 'must_change_password'");
    $hasCol = (bool)($col && $col->num_rows > 0);
    if ($hasCol && $userId > 0) {
        $st = $conn->prepare("SELECT must_change_password FROM users WHERE id = ? LIMIT 1");
        if ($st) {
            $st->bind_param("i", $userId);
            $st->execute();
            $r = $st->get_result()->fetch_assoc();
            $st->close();
            $forceReset = ((int)($r['must_change_password'] ?? 0) === 1);
        }
    }
} catch (Throwable $e) {
    $forceReset = ($from === 'reactivation' || $from === 'required');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - EVENTIFY</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --cp-surface: #f8f9fa;
            --cp-card: #ffffff;
            --cp-emerald: #2ecc71;
            --cp-emerald-dim: #27ae60;
            --cp-emerald-soft: rgba(46, 204, 113, 0.12);
            --cp-text: #1a1a2e;
            --cp-muted: #64748b;
            --cp-border: #e2e8f0;
            --cp-shadow: 0 10px 40px rgba(15, 23, 42, 0.10);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        html, body { min-height: 100%; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background:
                radial-gradient(circle at 0% 0%, var(--cp-emerald-soft), transparent 45%),
                radial-gradient(circle at 100% 100%, var(--cp-emerald-soft), transparent 45%),
                var(--cp-surface);
            color: var(--cp-text);
        }

        .cp-card {
            width: 100%;
            max-width: 420px;
            background: var(--cp-card);
            border: 1px solid var(--cp-border);
            border-radius: 20px;
            box-shadow: var(--cp-shadow);
            padding: 36px 32px 32px;
            animation: cpIn 0.45s ease-out;
        }
        @keyframes cpIn {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .cp-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--cp-emerald) 0%, var(--cp-emerald-dim) 100%);
            box-shadow: 0 8px 20px rgba(46, 204, 113, 0.35);
        }
        .cp-icon i { font-size: 26px; color: #fff; }

        .cp-card h1 {
            font-size: 24px;
            font-weight: 700;
            text-align: center;
            color: var(--cp-text);
        }
        .cp-note {
            margin: 8px 0 22px;
            color: var(--cp-muted);
            font-size: 13.5px;
            line-height: 1.55;
            text-align: center;
        }

        .cp-msg {
            display: flex;
            align-items: center;
            gap: 9px;
            width: 100%;
            border-radius: 12px;
            padding: 11px 14px;
            margin-bottom: 16px;
            font-size: 13px;
            line-height: 1.4;
        }
        .cp-msg i { font-size: 15px; flex-shrink: 0; }
        .cp-msg.ok { background: var(--cp-emerald-soft); border: 1px solid rgba(46, 204, 113, 0.4); color: var(--cp-emerald-dim); }
        .cp-msg.err { background: rgba(239, 68, 68, 0.10); border: 1px solid rgba(239, 68, 68, 0.35); color: #dc2626; }

        .cp-field { margin-bottom: 14px; }
        .cp-field label {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--cp-text);
            margin-bottom: 6px;
        }
        .pw-field { position: relative; width: 100%; }
        .pw-field input {
            width: 100%;
            padding: 12px 46px 12px 14px;
            border: 1px solid var(--cp-border);
            border-radius: 12px;
            background: #fff;
            color: var(--cp-text);
            font-size: 14px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .pw-field input::placeholder { color: #9ca3af; }
        .pw-field input:focus {
            outline: none;
            border-color: var(--cp-emerald);
            box-shadow: 0 0 0 3px var(--cp-emerald-soft);
        }
        .pw-toggle {
            position: absolute;
            top: 50%;
            right: 6px;
            transform: translateY(-50%);
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            background: transparent;
            color: var(--cp-muted);
            cursor: pointer;
            border-radius: 8px;
            transition: color 0.2s ease, background 0.2s ease;
        }
        .pw-toggle i { font-size: 15px; }
        .pw-toggle:hover { color: var(--cp-emerald-dim); background: var(--cp-emerald-soft); }

        .cp-hint {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            width: 100%;
            margin: 4px 0 18px;
            padding: 11px 13px;
            border-radius: 12px;
            background: var(--cp-surface);
            border: 1px solid var(--cp-border);
            color: var(--cp-muted);
            font-size: 12px;
            line-height: 1.5;
        }
        .cp-hint i { color: var(--cp-emerald-dim); margin-top: 1px; }

        .cp-submit {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--cp-emerald) 0%, var(--cp-emerald-dim) 100%);
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.2s ease;
        }
        .cp-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 22px rgba(46, 204, 113, 0.4);
        }
        .cp-submit:active { transform: translateY(0); }

        @media (max-width: 480px) {
            .cp-card { padding: 28px 22px 24px; border-radius: 16px; }
        }
    </style>
</head>
<body>
<div class="cp-card">
    <div class="cp-icon"><i class="fa-solid fa-lock"></i></div>
    <h1>Change Password</h1>
    <?php if ($from === 'reactivation'): ?>
        <p class="cp-note">Your account was reactivated. For security, please set a new password now.</p>
    <?php else: ?>
        <p class="cp-note">Keep your account secure by choosing a strong new password.</p>
    <?php endif; ?>

    <?php if ($msg !== ''): ?><div class="cp-msg ok"><i class="fa-solid fa-circle-check"></i><span><?= htmlspecialchars($msg) ?></span></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="cp-msg err"><i class="fa-solid fa-circle-exclamation"></i><span><?= htmlspecialchars($error) ?></span></div><?php endif; ?>

    <form action="<?= BASE_URL ?>/backend/auth/change_password.php" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
        <input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>">

        <?php if (!$forceReset): ?>
            <div class="cp-field">
                <label for="current_password">Current Password</label>
                <div class="pw-field">
                    <input type="password" name="current_password" id="current_password" placeholder="Enter current password" required>
                    <button type="button" class="pw-toggle" data-target="current_password" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
                </div>
            </div>
        <?php endif; ?>

        <div class="cp-field">
            <label for="new_password">New Password</label>
            <div class="pw-field">
                <input type="password" name="new_password" id="new_password" placeholder="Enter new password" required>
                <button type="button" class="pw-toggle" data-target="new_password" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
            </div>
        </div>

        <div class="cp-field">
            <label for="confirm_password">Confirm New Password</label>
            <div class="pw-field">
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-enter new password" required>
                <button type="button" class="pw-toggle" data-target="confirm_password" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
            </div>
        </div>

        <div class="cp-hint">
            <i class="fa-solid fa-circle-info"></i>
            <span>Password must be at least 8 characters with 1 uppercase letter and 1 special character.</span>
        </div>

        <button type="submit" class="cp-submit">Update Password</button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.pw-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-target') || '');
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            var icon = btn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye', !show);
                icon.classList.toggle('fa-eye-slash', show);
            }
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });
});
</script>
</body>
</html>
