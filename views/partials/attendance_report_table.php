<?php
/** @var list<array<string, mixed>> $attRows */
/** @var bool $attShowTimeOut */
/** @var bool $attShowPhotos */
/** @var array{total:int,present:int,absent:int,incomplete:int,unassigned:int} $attSummary */
/** @var bool $attRosterView */

$attRows = is_array($attRows ?? null) ? $attRows : [];
$attShowTimeOut = !empty($attShowTimeOut);
$attShowPhotos = !isset($attShowPhotos) || $attShowPhotos;
$attRosterView = !empty($attRosterView);
$attSummary = is_array($attSummary ?? null) ? $attSummary : [
    'total' => count($attRows),
    'present' => 0,
    'absent' => 0,
    'incomplete' => 0,
    'unassigned' => 0,
];
?>

<?php if ($attRows === []): ?>
    <div class="att-empty">
        <div><i class="fas fa-clipboard" aria-hidden="true"></i></div>
        <?php if ($attRosterView): ?>
            <p class="mb-0">No students match this class filter. Check the department, course, year, or section.</p>
        <?php else: ?>
            <p class="mb-0">No one has checked in yet<?= !empty($attFilters['has_filter'] ?? false) ? ' for this filter' : '' ?>. Students scan the QR to appear here.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-attendees table-striped align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Student ID</th>
                    <th>Course</th>
                    <th>Year</th>
                    <th>Section</th>
                    <th>Department</th>
                    <th>Time in</th>
                    <?php if ($attShowTimeOut): ?><th>Time out</th><?php endif; ?>
                    <?php if ($attShowPhotos): ?><th class="no-print">Photo</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attRows as $i => $a): ?>
                    <?php
                    $status = (string) ($a['status'] ?? 'absent');
                    $rowClass = $status === 'absent' ? 'is-absent' : ($status === 'incomplete' ? 'is-incomplete' : '');
                    $course = trim((string) ($a['student_course'] ?? ''));
                    $yrl = trim((string) ($a['student_year_level'] ?? ''));
                    $sec = trim((string) ($a['student_section'] ?? ''));
                    $deptDisp = trim((string) ($a['department_label'] ?? ''));
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars(($a['name'] ?? '') !== '' ? (string) $a['name'] : '—') ?></td>
                        <td><?= htmlspecialchars(($a['student_school_id'] ?? '') !== '' ? (string) $a['student_school_id'] : '—') ?></td>
                        <td><?= $course !== '' ? htmlspecialchars($course) : '—' ?></td>
                        <td><?= $yrl !== '' ? htmlspecialchars($yrl) : '—' ?></td>
                        <td><?= $sec !== '' ? htmlspecialchars($sec) : '—' ?></td>
                        <td><?= $deptDisp !== '' ? htmlspecialchars($deptDisp) : '—' ?></td>
                        <td><?= ($a['time_in'] ?? '') !== '' ? htmlspecialchars(eventify_attendance_report_format_when((string) $a['time_in'])) : '—' ?></td>
                        <?php if ($attShowTimeOut): ?>
                            <td><?= ($a['time_out'] ?? '') !== '' ? htmlspecialchars(eventify_attendance_report_format_when((string) $a['time_out'])) : '—' ?></td>
                        <?php endif; ?>
                        <?php if ($attShowPhotos): ?>
                            <td class="no-print">
                                <?= function_exists('eventify_attendance_proof_thumb_html')
                                    ? eventify_attendance_proof_thumb_html($a['proof_photo'] ?? '', (string) ($a['name'] ?? 'Student') . ' attendance photo')
                                    : '—' ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
