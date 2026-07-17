<?php
/**
 * Optional class-section audience fields for create/edit event forms.
 *
 * Expects (optional):
 *   $conn (mysqli) — preferred, loads sections from DB
 *   $adminClassSections / $dashboardClassSections — fallback list
 *   $sectionFieldIdPrefix (string) e.g. 'ce', 'edit', 'standalone'
 *   $postedSections (list<string>|null)
 *   $newSectionValue (string)
 */
$sectionFieldIdPrefix = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($sectionFieldIdPrefix ?? 'sec')) ?: 'sec';
$postedSections = is_array($postedSections ?? null) ? $postedSections : [];
$newSectionValue = (string) ($newSectionValue ?? '');

$classSections = [];
if (isset($conn) && $conn instanceof mysqli) {
    if (is_file(__DIR__ . '/../../config/student_sections.php')) {
        require_once __DIR__ . '/../../config/student_sections.php';
    }
    if (function_exists('eventify_sections_schema_ensure')) {
        eventify_sections_schema_ensure($conn);
    }
    if (function_exists('eventify_list_class_sections')) {
        $classSections = eventify_list_class_sections($conn);
    }
}
if ($classSections === []) {
    if (!empty($adminClassSections) && is_array($adminClassSections)) {
        $classSections = $adminClassSections;
    } elseif (!empty($dashboardClassSections) && is_array($dashboardClassSections)) {
        $classSections = $dashboardClassSections;
    }
}

$postedSectionKeys = [];
if (function_exists('eventify_section_match_key')) {
    foreach ($postedSections as $ps) {
        $k = eventify_section_match_key((string) $ps);
        if ($k !== '') {
            $postedSectionKeys[$k] = true;
        }
    }
} else {
    foreach ($postedSections as $ps) {
        $k = strtolower(trim((string) $ps));
        if ($k !== '') {
            $postedSectionKeys[$k] = true;
        }
    }
}

// Saved labels not yet in the catalog still need visible checked boxes on edit.
$catalogKeys = [];
foreach ($classSections as $sec) {
    $lab = (string) ($sec['label'] ?? '');
    if ($lab === '') {
        continue;
    }
    $k = function_exists('eventify_section_match_key')
        ? eventify_section_match_key($lab)
        : strtolower(trim($lab));
    if ($k !== '') {
        $catalogKeys[$k] = true;
    }
}
foreach ($postedSections as $psLab) {
    $psLab = trim((string) $psLab);
    if ($psLab === '') {
        continue;
    }
    $pk = function_exists('eventify_section_match_key')
        ? eventify_section_match_key($psLab)
        : strtolower($psLab);
    if ($pk === '' || isset($catalogKeys[$pk])) {
        continue;
    }
    $classSections[] = ['id' => 0, 'label' => $psLab];
    $catalogKeys[$pk] = true;
}

$newId = $sectionFieldIdPrefix . 'NewSection';
?>
<section class="ce-form-section ce-section-audience" id="<?= htmlspecialchars($sectionFieldIdPrefix) ?>SectionAudience">
  <p class="ce-form-section__label"><i class="fas fa-layer-group me-1" aria-hidden="true"></i> Limit to class section(s)</p>
  <p class="ce-form-help mb-2">
    <strong>Section-only event:</strong> check or type the section (e.g. <code>BSIT4201</code>).
    You do <strong>not</strong> need All departments checked — leave it unchecked for section-only events.
    Only students assigned that section can see and join.
  </p>
  <?php if ($classSections !== []): ?>
  <div class="ce-dept-checkbox-list mb-2" style="max-height: 160px; overflow-y: auto;">
    <?php foreach ($classSections as $sec): ?>
      <?php
        $lab = (string) ($sec['label'] ?? '');
        if ($lab === '') {
            continue;
        }
        $sid = $sectionFieldIdPrefix . '_sec_' . (int) ($sec['id'] ?? 0) . '_' . substr(md5($lab), 0, 8);
        $matchKey = function_exists('eventify_section_match_key')
            ? eventify_section_match_key($lab)
            : strtolower(trim($lab));
        $checked = isset($postedSectionKeys[$matchKey]);
      ?>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="section[]" value="<?= htmlspecialchars($lab) ?>" id="<?= htmlspecialchars($sid) ?>" <?= $checked ? 'checked' : '' ?>>
        <label class="form-check-label" for="<?= htmlspecialchars($sid) ?>"><?= htmlspecialchars($lab) ?></label>
      </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <p class="text-muted small mb-2">No saved sections yet — type one below (e.g. BSIT4201). Admin can also add sections under All users.</p>
  <?php endif; ?>
  <div class="mb-0">
    <label class="form-label small mb-1" for="<?= htmlspecialchars($newId) ?>">Or type a section name</label>
    <input type="text" class="form-control form-control-sm" name="new_section" id="<?= htmlspecialchars($newId) ?>"
           maxlength="80" placeholder="e.g. BSIT4201"
           value="<?= htmlspecialchars($newSectionValue) ?>">
  </div>
</section>
