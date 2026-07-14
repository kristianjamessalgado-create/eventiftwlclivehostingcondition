<?php
/** @var bool $admin_feedback_panel_open */
/** @var list<array<string, mixed>> $admin_feedback_list */
/** @var array<string, mixed> $admin_evaluation_averages */

$admin_feedback_panel_open = !empty($admin_feedback_panel_open);
$admin_feedback_list = is_array($admin_feedback_list ?? null) ? $admin_feedback_list : [];
$feedbackCount = count($admin_feedback_list);
$panelEnterClass = $admin_feedback_panel_open ? ' adm-dash-panel--enter' : '';
?>

<section
    class="adm-dash-panel adm-student-feedback-panel<?= $panelEnterClass ?><?= $admin_feedback_panel_open ? '' : ' d-none' ?>"
    id="adminStudentFeedbackPanel"
    aria-label="Student feedback"
    <?= $admin_feedback_panel_open ? '' : ' hidden' ?>
>
    <div class="adm-dash-panel__shell">
        <div class="adm-dash-panel__toolbar">
            <button type="button" class="adm-dash-panel__back" data-admin-panel="home">
                <span class="adm-dash-panel__back-icon" aria-hidden="true"><i class="fas fa-arrow-left"></i></span>
                <span class="adm-dash-panel__back-label">Back to calendar</span>
            </button>
            <?php if ($feedbackCount > 0): ?>
                <span class="adm-dash-panel__count-pill">
                    <i class="fas fa-star-half-stroke" aria-hidden="true"></i>
                    <?= $feedbackCount ?> review<?= $feedbackCount === 1 ? '' : 's' ?>
                </span>
            <?php endif; ?>
        </div>

        <header class="adm-dash-panel__hero">
            <div class="adm-dash-panel__hero-icon" aria-hidden="true"><i class="fas fa-star-half-stroke"></i></div>
            <div class="adm-dash-panel__hero-text">
                <h2 class="adm-dash-panel__title">Student feedback</h2>
                <p class="adm-dash-panel__subtitle mb-0">
                    From students who attended (QR check-in). Each evaluation uses 10 questions rated 1–5. Students stay anonymous; only department is shown.
                </p>
            </div>
        </header>

        <?php
        $evaluation_averages = $admin_evaluation_averages;
        include __DIR__ . '/evaluation_averages.php';
        ?>

        <?php if (!empty($admin_feedback_list)): ?>
            <ul class="list-unstyled mb-0 small adm-feedback-list">
                <?php foreach ($admin_feedback_list as $fc): ?>
                    <?php
                    $respondentLabel = function_exists('eventify_feedback_respondent_label')
                        ? eventify_feedback_respondent_label($fc['student_department'] ?? null)
                        : 'Anonymous';
                    ?>
                    <li class="border rounded p-2 mb-2 bg-light">
                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
                            <span class="fw-semibold"><?= htmlspecialchars((string) ($fc['event_title'] ?? 'Event')) ?></span>
                            <span class="text-warning text-nowrap"><i class="fas fa-star me-1"></i><?= (int) ($fc['rating'] ?? 0) ?>/5</span>
                        </div>
                        <div class="text-muted small mb-1">
                            <?= date('M j, Y g:i A', strtotime($fc['created_at'] ?? 'now')) ?>
                            · Organizer: <?= htmlspecialchars((string) ($fc['organizer_name'] ?? '—')) ?>
                            · <span class="badge bg-secondary"><?= htmlspecialchars($respondentLabel) ?></span>
                        </div>
                        <?php if (trim((string) ($fc['comment'] ?? '')) !== ''): ?>
                            <div><?= nl2br(htmlspecialchars((string) ($fc['comment'] ?? ''))) ?></div>
                        <?php else: ?>
                            <div class="text-muted fst-italic">(No comment)</div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="mb-0 text-muted small">No feedback entries yet.</p>
        <?php endif; ?>
    </div>
</section>
