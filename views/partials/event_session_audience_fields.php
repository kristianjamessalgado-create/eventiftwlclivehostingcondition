<?php
/**
 * Per-activity audience fields (department + class sections) for add/edit activity modal.
 *
 * Expects (optional):
 *   $conn (mysqli)
 *   $organizer_department_choices (array<string,string>)
 *   $adminClassSections / $dashboardClassSections
 *   $sessionAudienceFieldPrefix (string) default eds
 */
$sessionAudienceFieldPrefix = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($sessionAudienceFieldPrefix ?? 'eds')) ?: 'eds';
if (!isset($organizer_department_choices) || !is_array($organizer_department_choices)) {
    if (is_file(__DIR__ . '/../../config/organizer_departments.php')) {
        require_once __DIR__ . '/../../config/organizer_departments.php';
    }
    $organizer_department_choices = function_exists('eventify_organizer_department_choices')
        ? eventify_organizer_department_choices()
        : [];
}
$inheritId = $sessionAudienceFieldPrefix . 'AudienceInherit';
$inheritLabelId = $sessionAudienceFieldPrefix . 'AudienceInheritLabel';
$customWrapId = $sessionAudienceFieldPrefix . 'AudienceCustom';
$parentHintId = $sessionAudienceFieldPrefix . 'ParentAudienceHint';
$allDeptId = $sessionAudienceFieldPrefix . 'DeptAll';
$sectionFieldIdPrefix = $sessionAudienceFieldPrefix;
?>
<div class="eds-form-section eds-audience-section" id="<?= htmlspecialchars($sessionAudienceFieldPrefix) ?>AudienceSection">
  <p class="eds-form-section__label"><i class="fas fa-users me-1" aria-hidden="true"></i> Who can join this activity?</p>
  <p class="eds-form-help mb-2" id="<?= htmlspecialchars($parentHintId) ?>">
    By default, the same students who can see the whole event (for example, Intramurals) can join this activity too.
  </p>
  <div class="form-check mb-2">
    <input class="form-check-input" type="checkbox" name="audience_inherit" value="1" id="<?= htmlspecialchars($inheritId) ?>" checked>
    <label class="form-check-label fw-semibold" for="<?= htmlspecialchars($inheritId) ?>">
      <span id="<?= htmlspecialchars($inheritLabelId) ?>">Same as the whole event</span>
    </label>
  </div>
  <div id="<?= htmlspecialchars($customWrapId) ?>" class="eds-audience-custom" style="display:none;">
    <p class="eds-form-help mb-2">
      Limit this activity to specific departments or class sections — for example, Intramurals is open to everyone, but <strong>Badminton</strong> is only for CICTE and Nursing.
    </p>
    <p class="eds-form-section__label" style="font-size:0.8rem;margin-top:0.35rem;">Department / college</p>
    <div class="form-check mb-2">
      <input class="form-check-input" type="checkbox" name="department[]" value="ALL" id="<?= htmlspecialchars($allDeptId) ?>">
      <label class="form-check-label fw-semibold" for="<?= htmlspecialchars($allDeptId) ?>">All departments</label>
    </div>
    <div class="ce-dept-checkbox-list mb-3" style="max-height: 160px; overflow-y: auto;">
      <?php foreach ($organizer_department_choices as $deptVal => $deptLabel): ?>
        <?php if ($deptVal === 'ALL') {
            continue;
        } ?>
        <?php $cbId = $sessionAudienceFieldPrefix . '_dept_' . substr(md5($deptVal), 0, 14); ?>
        <div class="form-check">
          <input class="form-check-input eds-dept-specific" type="checkbox" name="department[]" value="<?= htmlspecialchars($deptVal) ?>" id="<?= htmlspecialchars($cbId) ?>">
          <label class="form-check-label" for="<?= htmlspecialchars($cbId) ?>"><?= htmlspecialchars($deptLabel) ?></label>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
      $postedSections = [];
      $newSectionValue = '';
      if (!isset($parentEventSections)) {
          $parentEventSections = [];
      }
      include __DIR__ . '/event_section_audience_fields.php';
    ?>
  </div>
</div>
