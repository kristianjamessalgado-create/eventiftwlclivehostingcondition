<?php
require_once __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Privacy Notice | EVENTIFY</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/index.css">
    <style>
        body {
            background:
                radial-gradient(1100px 420px at 12% -10%, rgba(6, 78, 59, 0.28), transparent 62%),
                radial-gradient(900px 360px at 92% 4%, rgba(180, 83, 9, 0.16), transparent 64%),
                #ecf4f1;
            color: #1f2937;
            min-height: 100vh;
            margin: 0;
            padding: 2rem 1.25rem;
            font-family: "Inter", "Segoe UI", Arial, sans-serif;
        }
        .legal-standalone-wrap { max-width: 980px; margin: 0 auto; }
        .legal-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        .legal-back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            text-decoration: none;
            font-weight: 700;
            color: #064e3b;
            background: #ffffff;
            border: 1px solid #86efac;
            border-radius: 999px;
            padding: 0.55rem 0.9rem;
        }
        .legal-back-link:hover { background: #dcfce7; }
        .legal-badge {
            display: inline-block;
            font-size: 0.78rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            font-weight: 700;
            color: #854d0e;
            background: #fef3c7;
            border: 1px solid #fde68a;
            border-radius: 999px;
            padding: 0.4rem 0.75rem;
        }
        .legal-standalone-card {
            background: #ffffff;
            border: 1px solid #a7f3d0;
            border-radius: 18px;
            padding: 1.6rem;
            box-shadow: 0 16px 40px rgba(2, 44, 34, 0.12);
        }
        .legal-standalone-card::before {
            content: "";
            display: block;
            height: 6px;
            border-radius: 999px;
            margin-bottom: 1rem;
            background: linear-gradient(90deg, #065f46, #16a34a 55%, #ca8a04);
        }
        .legal-standalone-card h1 {
            font-size: clamp(1.35rem, 2.5vw, 1.8rem);
            margin: 0 0 0.5rem;
            color: #064e3b;
            line-height: 1.3;
            background: none !important;
            -webkit-text-fill-color: #064e3b !important;
            animation: none !important;
        }
        .legal-standalone-wrap h1,
        .legal-standalone-wrap h2,
        .legal-standalone-wrap h3,
        .legal-standalone-wrap h4,
        .legal-standalone-wrap p,
        .legal-standalone-wrap li {
            -webkit-text-fill-color: currentColor !important;
            text-shadow: none !important;
            max-width: none !important;
            animation: none !important;
        }
        .legal-standalone-sub {
            margin: 0 0 1.2rem;
            color: #334155;
            font-size: 0.96rem;
        }
        .legal-standalone-card .legal-doc-body {
            color: #1f2937;
            line-height: 1.7;
            font-size: 0.96rem;
        }
        .legal-standalone-card .legal-doc-body p {
            color: #111827;
            font-size: 0.97rem;
            line-height: 1.72;
            width: 100%;
            margin-right: 0;
        }
        .legal-standalone-card .legal-doc-body .legal-doc-meta {
            color: #334155;
            font-weight: 600;
        }
        .legal-standalone-card .legal-doc-h3 {
            color: #065f46;
            margin-top: 1.2rem;
            border-bottom-color: rgba(202, 138, 4, 0.35);
        }
        .legal-standalone-card .legal-doc-body .legal-doc-list {
            color: #1f2937;
            font-size: 0.95rem;
        }
        .legal-standalone-card .legal-doc-body a {
            color: #065f46;
            font-weight: 700;
        }
        @media (max-width: 640px) {
            body { padding: 1.15rem 0.8rem; }
            .legal-standalone-card { padding: 1.15rem; border-radius: 14px; }
        }
    </style>
</head>
<body>
<div class="legal-standalone-wrap">
    <div class="legal-topbar">
        <a class="legal-back-link" href="<?= BASE_URL ?>/index.php">&larr; Back to EVENTIFY</a>
        <span class="legal-badge">RA 10173 Compliant</span>
    </div>
    <div class="legal-standalone-card">
        <h1>EVENTIFY Data Privacy Notice</h1>
        <p class="legal-standalone-sub">Your privacy rights and how EVENTIFY collects, uses, and protects your personal data.</p>
        <div class="legal-doc-body">
            <?php include __DIR__ . '/views/partials/legal_privacy_inner.php'; ?>
        </div>
    </div>
</div>
</body>
</html>
