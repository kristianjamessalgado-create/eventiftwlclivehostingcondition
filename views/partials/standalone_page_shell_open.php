<?php
/**
 * EVENTIFY standalone page shell (open).
 *
 * @var string $shell_title
 * @var string|null $shell_subtitle
 * @var string $shell_back_url
 * @var string $shell_back_label
 * @var string|null $shell_body_class
 * @var string|null $shell_page_title
 */
$shell_title = $shell_title ?? 'EVENTIFY';
$shell_subtitle = $shell_subtitle ?? null;
$shell_back_url = $shell_back_url ?? (BASE_URL . '/');
$shell_back_label = $shell_back_label ?? 'Back';
$shell_body_class = trim((string) ($shell_body_class ?? 'eventify-standalone'));
$shell_page_title = $shell_page_title ?? $shell_title;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($shell_page_title) ?> — EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL) ?>/assets/css/eventify_standalone.css?v=1">
</head>
<body class="<?= htmlspecialchars($shell_body_class) ?>">
<header class="efs-topbar">
    <a href="<?= htmlspecialchars($shell_back_url) ?>" class="efs-brand">
        <i class="fas fa-calendar-check" aria-hidden="true"></i>
        <span>EVENTIFY</span>
    </a>
    <a href="<?= htmlspecialchars($shell_back_url) ?>" class="efs-back">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
        <?= htmlspecialchars($shell_back_label) ?>
    </a>
</header>
<main class="efs-main">
    <div class="efs-page-head">
        <h1 class="efs-page-title"><?= htmlspecialchars($shell_title) ?></h1>
        <?php if ($shell_subtitle !== null && $shell_subtitle !== ''): ?>
            <p class="efs-page-sub"><?= htmlspecialchars($shell_subtitle) ?></p>
        <?php endif; ?>
    </div>
