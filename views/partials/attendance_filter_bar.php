<?php
/** @var array{dept:string,course:string,year:string,section:string,view:string,has_filter:bool} $attFilters */
/** @var array{departments:array<string,string>,courses:array<string,string>,years:array<string,string>,sections:array<string,string>,course_departments?:array<string,string>} $attFilterOptions */
/** @var string $attFilterAction */
/** @var int $attFilterId */
/** @var string $attClearUrl */

$attFilters = is_array($attFilters ?? null) ? $attFilters : [];
$attFilterOptions = is_array($attFilterOptions ?? null) ? $attFilterOptions : [];
$attFilterAction = (string) ($attFilterAction ?? '');
$attFilterId = (int) ($attFilterId ?? 0);
$attClearUrl = (string) ($attClearUrl ?? $attFilterAction);
$courseDeptMap = is_array($attFilterOptions['course_departments'] ?? null) ? $attFilterOptions['course_departments'] : [];
$view = (string) ($attFilters['view'] ?? 'present');
$hasFilter = !empty($attFilters['has_filter']);

$presentQs = eventify_attendance_report_query_string($attFilters, ['id' => $attFilterId, 'view' => 'present']);
$rosterQs = eventify_attendance_report_query_string($attFilters, ['id' => $attFilterId, 'view' => 'roster']);
?>
<form id="attReportFilterForm" class="att-filters no-print" method="get" action="<?= htmlspecialchars($attFilterAction) ?>">
    <input type="hidden" name="id" value="<?= $attFilterId ?>">
    <input type="hidden" name="view" value="<?= htmlspecialchars((string) ($attFilters['view_param'] ?? 'auto')) ?>">

    <div class="att-filters__top">
        <div>
            <p class="att-filters__title"><i class="fas fa-filter me-1" aria-hidden="true"></i> Filter class</p>
            <p class="att-filters__help mb-0">Pick department, course, year, then section — only that class appears on the report.</p>
        </div>
        <?php if ($hasFilter): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($attClearUrl) ?>">Clear filters</a>
        <?php endif; ?>
    </div>

    <div class="att-filters__grid">
        <div>
            <label for="attFilterDept">Department</label>
            <select class="form-select form-select-sm" id="attFilterDept" name="dept">
                <option value="">All departments</option>
                <?php foreach (($attFilterOptions['departments'] ?? []) as $val => $label): ?>
                    <option value="<?= htmlspecialchars((string) $val) ?>"<?= ((string) ($attFilters['dept'] ?? '')) === (string) $val ? ' selected' : '' ?>>
                        <?= htmlspecialchars((string) $label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="attFilterCourse">Course</label>
            <select class="form-select form-select-sm" id="attFilterCourse" name="course">
                <option value="">All courses</option>
                <?php foreach (($attFilterOptions['courses'] ?? []) as $val => $label): ?>
                    <?php $optDept = (string) ($courseDeptMap[$val] ?? ''); ?>
                    <option
                        value="<?= htmlspecialchars((string) $val) ?>"
                        data-department="<?= htmlspecialchars($optDept) ?>"
                        <?= ((string) ($attFilters['course'] ?? '')) === (string) $val ? ' selected' : '' ?>
                    >
                        <?= htmlspecialchars((string) $label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="attFilterYear">Year / standing</label>
            <select class="form-select form-select-sm" id="attFilterYear" name="year">
                <option value="">All year levels</option>
                <?php foreach (($attFilterOptions['years'] ?? []) as $val => $label): ?>
                    <option value="<?= htmlspecialchars((string) $val) ?>"<?= ((string) ($attFilters['year'] ?? '')) === (string) $val ? ' selected' : '' ?>>
                        <?= htmlspecialchars((string) $label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="attFilterSection">Section</label>
            <select class="form-select form-select-sm" id="attFilterSection" name="section">
                <option value="">All sections</option>
                <?php foreach (($attFilterOptions['sections'] ?? []) as $val => $label): ?>
                    <option value="<?= htmlspecialchars((string) $val) ?>"<?= ((string) ($attFilters['section'] ?? '')) === (string) $val ? ' selected' : '' ?>>
                        <?= htmlspecialchars((string) $label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="att-filters__actions">
        <div class="att-view-toggle" role="group" aria-label="Report view">
            <a class="<?= $view === 'present' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($attFilterAction . '?' . $presentQs) ?>">
                <i class="fas fa-qrcode" aria-hidden="true"></i> Checked in only
            </a>
            <a class="<?= $view === 'roster' ? 'is-active' : '' ?><?= $hasFilter ? '' : ' is-disabled' ?>" href="<?= $hasFilter ? htmlspecialchars($attFilterAction . '?' . $rosterQs) : $attFilterAction . '?' . $presentQs ?>" <?= $hasFilter ? '' : 'aria-disabled="true" tabindex="-1"' ?>>
                <i class="fas fa-users" aria-hidden="true"></i> Full class roster
            </a>
        </div>
        <?php if (!$hasFilter): ?>
            <span class="small text-muted">Choose a department, course, year, or section to include absences.</span>
        <?php endif; ?>
    </div>
</form>
