<?php
/** @var array<int, array<string, mixed>> $activities_hub_events */
$activities_hub_events = $activities_hub_events ?? [];
$activities_hub_count = isset($activities_hub_visible_count)
    ? (int) $activities_hub_visible_count
    : count($activities_hub_events);
if (!isset($activities_hub_visible_count) && !empty($activities_hub_badge_active_only)) {
    $activities_hub_count = function_exists('eventify_count_hub_events_in_statuses')
        ? eventify_count_hub_events_in_statuses($activities_hub_events, ['active'])
        : count($activities_hub_events);
}
$activities_hub_btn_class = $activities_hub_btn_class ?? 'w-100 text-start border-0 bg-transparent';
$activities_hub_show_chevron = !empty($activities_hub_show_chevron);
$activities_hub_sa_style = !empty($activities_hub_sa_style);
$activities_hub_student_label = (string) ($activities_hub_student_label ?? 'My Activities and Events');
$activities_hub_btn_classes = $activities_hub_sa_style
    ? trim($activities_hub_btn_class)
    : trim('action-btn ' . $activities_hub_btn_class);
$activities_hub_url = BASE_URL . '/activities_hub.php';
$activities_hub_link_label = !empty($activities_hub_use_student_label) ? $activities_hub_student_label : 'Activities hub';
?>
<a href="<?= htmlspecialchars($activities_hub_url) ?>" class="<?= htmlspecialchars($activities_hub_btn_classes) ?> text-decoration-none" title="<?= htmlspecialchars($activities_hub_link_label) ?>" aria-label="<?= htmlspecialchars($activities_hub_link_label) ?>">
    <?php if ($activities_hub_sa_style): ?>
        <span><i class="fas fa-th-large me-2"></i><?= htmlspecialchars($activities_hub_link_label) ?><?php if ($activities_hub_count > 0): ?> <span class="badge bg-primary ms-1"><?= $activities_hub_count > 99 ? '99+' : $activities_hub_count ?></span><?php endif; ?></span>
        <?php if ($activities_hub_show_chevron): ?><i class="fas fa-chevron-right"></i><?php endif; ?>
    <?php else: ?>
        <i class="fas fa-th-large"></i>
        <span><?= htmlspecialchars($activities_hub_link_label) ?></span>
        <?php if ($activities_hub_count > 0): ?>
            <span class="badge bg-primary ms-1"><?= $activities_hub_count > 99 ? '99+' : $activities_hub_count ?></span>
        <?php endif; ?>
        <?php if ($activities_hub_show_chevron): ?>
            <i class="fas fa-chevron-right ms-auto"></i>
        <?php endif; ?>
    <?php endif; ?>
</a>
