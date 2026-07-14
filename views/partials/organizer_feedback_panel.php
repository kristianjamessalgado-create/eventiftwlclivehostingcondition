<?php
/** @var bool $organizer_feedback_panel_open */
/** @var array<string, mixed> $feedbackStats */
/** @var list<array<string, mixed>> $organizer_feedback_list */
/** @var array<string, array{avg: float|null, count: int}> $organizer_evaluation_averages */

$organizer_feedback_panel_open = !empty($organizer_feedback_panel_open);
$fb = is_array($feedbackStats ?? null) ? $feedbackStats : ['total_feedback' => 0, 'avg_rating' => 0.0, 'five_star' => 0];
$organizer_feedback_list = is_array($organizer_feedback_list ?? null) ? $organizer_feedback_list : [];
$organizer_evaluation_averages = is_array($organizer_evaluation_averages ?? null) ? $organizer_evaluation_averages : [];
$feedbackCount = (int) ($fb['total_feedback'] ?? 0);
$panelEnterClass = $organizer_feedback_panel_open ? ' org-dash-panel--enter' : '';
?>

<section
    class="org-dash-panel org-feedback-panel<?= $panelEnterClass ?><?= $organizer_feedback_panel_open ? '' : ' d-none' ?>"
    id="organizerFeedbackPanel"
    aria-label="Feedback insights"
    <?= $organizer_feedback_panel_open ? '' : ' hidden' ?>
>
    <div class="org-dash-panel__shell">
        <div class="org-dash-panel__toolbar">
            <button type="button" class="org-dash-panel__back" data-organizer-panel="home">
                <span class="org-dash-panel__back-icon" aria-hidden="true"><i class="fas fa-arrow-left"></i></span>
                <span class="org-dash-panel__back-label">Back to calendar</span>
            </button>
            <?php if ($feedbackCount > 0): ?>
                <span class="org-dash-panel__count-pill">
                    <i class="fas fa-star" aria-hidden="true"></i>
                    <?= $feedbackCount ?> review<?= $feedbackCount === 1 ? '' : 's' ?>
                </span>
            <?php endif; ?>
        </div>

        <header class="org-dash-panel__hero">
            <div class="org-dash-panel__hero-icon" aria-hidden="true"><i class="fas fa-star-half-stroke"></i></div>
            <div class="org-dash-panel__hero-text">
                <h2 class="org-dash-panel__title">Feedback insights</h2>
                <p class="org-dash-panel__subtitle mb-0">
                    Anonymous ratings from students who <strong>attended</strong> your events (QR check-in). Department only — no names.
                </p>
            </div>
        </header>

        <div class="org-feedback-stats">
            <div class="org-feedback-stats__card">
                <span class="org-feedback-stats__label">Feedback entries</span>
                <span class="org-feedback-stats__value"><?= (int) ($fb['total_feedback'] ?? 0) ?></span>
            </div>
            <div class="org-feedback-stats__card">
                <span class="org-feedback-stats__label">Average rating</span>
                <span class="org-feedback-stats__value"><?= number_format((float) ($fb['avg_rating'] ?? 0), 2) ?> <small>/ 5</small></span>
            </div>
            <div class="org-feedback-stats__card">
                <span class="org-feedback-stats__label">5-star ratings</span>
                <span class="org-feedback-stats__value"><?= (int) ($fb['five_star'] ?? 0) ?></span>
            </div>
        </div>

        <?php
        $evaluation_averages = $organizer_evaluation_averages;
        include __DIR__ . '/evaluation_averages.php';
        ?>

        <?php if ($organizer_feedback_list !== []): ?>
            <div class="org-dash-panel__section">
                <h3 class="org-dash-panel__section-title">
                    <i class="fas fa-comments" aria-hidden="true"></i> Recent evaluations
                </h3>
                <ul class="org-feedback-list list-unstyled mb-0">
                    <?php foreach ($organizer_feedback_list as $i => $fc): ?>
                        <?php
                            $respondentLabel = function_exists('eventify_feedback_respondent_label')
                                ? eventify_feedback_respondent_label($fc['student_department'] ?? null)
                                : 'Anonymous';
                            $staggerStyle = '--panel-stagger: ' . min($i, 8) * 0.035 . 's';
                        ?>
                        <li class="org-feedback-card" style="<?= htmlspecialchars($staggerStyle) ?>">
                            <div class="org-feedback-card__top">
                                <span class="org-feedback-card__event"><?= htmlspecialchars((string) ($fc['event_title'] ?? 'Event')) ?></span>
                                <span class="org-feedback-card__rating"><i class="fas fa-star" aria-hidden="true"></i> <?= (int) ($fc['rating'] ?? 0) ?>/5</span>
                            </div>
                            <div class="org-feedback-card__meta">
                                <span><?= date('M j, Y', strtotime($fc['created_at'] ?? 'now')) ?></span>
                                <span class="org-feedback-card__dept"><?= htmlspecialchars($respondentLabel) ?></span>
                            </div>
                            <?php if (trim((string) ($fc['comment'] ?? '')) !== ''): ?>
                                <p class="org-feedback-card__comment mb-0"><?= nl2br(htmlspecialchars((string) ($fc['comment'] ?? ''))) ?></p>
                            <?php else: ?>
                                <p class="org-feedback-card__comment org-feedback-card__comment--empty mb-0">(No written comment)</p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <div class="org-dash-panel__empty org-dash-panel__empty--compact">
                <div class="org-dash-panel__empty-icon" aria-hidden="true"><i class="fas fa-star"></i></div>
                <h3 class="org-dash-panel__empty-title">No evaluations yet</h3>
                <p class="org-dash-panel__empty-text mb-0">
                    Students can submit feedback after they attend via QR check-in and the event has ended.
                </p>
            </div>
        <?php endif; ?>
    </div>
</section>
