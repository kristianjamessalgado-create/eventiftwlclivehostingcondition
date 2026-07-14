<?php
/** @var array<string, array{avg: float|null, count: int}> $evaluation_averages */
$evaluation_averages = $evaluation_averages ?? [];
if ($evaluation_averages === []) {
    return;
}
require_once __DIR__ . '/../../backend/lib/event_evaluation.php';
$hasAny = false;
foreach (eventify_evaluation_question_keys() as $key) {
    if (!empty($evaluation_averages[$key]['count'])) {
        $hasAny = true;
        break;
    }
}
if (!$hasAny) {
    return;
}
?>
<hr class="my-3">
<h6 class="small text-uppercase text-muted mb-2">Evaluation averages (1 = lowest, 5 = highest)</h6>
<?php foreach (eventify_evaluation_sections() as $section): ?>
  <div class="mb-3">
    <div class="fw-semibold small text-success mb-2"><?= htmlspecialchars((string) ($section['label'] ?? '')) ?></div>
    <ul class="list-unstyled mb-0 small">
      <?php foreach (($section['questions'] ?? []) as $q): ?>
        <?php
          $key = (string) ($q['key'] ?? '');
          $stat = $evaluation_averages[$key] ?? ['avg' => null, 'count' => 0];
          $cnt = (int) ($stat['count'] ?? 0);
          if ($cnt < 1) {
              continue;
          }
          $avg = $stat['avg'];
        ?>
        <li class="border rounded p-2 mb-2 bg-white">
          <div class="d-flex justify-content-between gap-2 align-items-start">
            <span><?= htmlspecialchars((string) ($q['text'] ?? $key)) ?></span>
            <span class="text-warning text-nowrap fw-semibold"><?= number_format((float) $avg, 2) ?>/5</span>
          </div>
          <div class="text-muted" style="font-size: 0.75rem;"><?= $cnt ?> response<?= $cnt === 1 ? '' : 's' ?></div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endforeach; ?>
