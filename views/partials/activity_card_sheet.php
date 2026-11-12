<?php
/**
 * Printable activity card sheet (student page + teacher QR view).
 *
 * @var string $acStudentName
 * @var string $acDeptLabel
 * @var list<array<string,mixed>> $acDisplayRows  rows incl. 'blank' padding rows
 * @var string $acQrImageUrl   QR image (empty to hide)
 * @var bool   $acTeacherMode  show stamp / remove buttons
 * @var string $acTeacherFormAction POST target for stamp buttons (teacher mode)
 */
$acTeacherMode = !empty($acTeacherMode);
$acQrImageUrl = (string) ($acQrImageUrl ?? '');
$acTeacherFormAction = (string) ($acTeacherFormAction ?? '');

if (!function_exists('ac_h')) {
    function ac_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('ac_date_label')) {
    function ac_date_label(string $ymd): string
    {
        $ymd = trim($ymd);
        if ($ymd === '' || $ymd === '0000-00-00') {
            return '';
        }
        $ts = strtotime($ymd);
        return $ts ? date('M j, Y', $ts) : $ymd;
    }
}
if (!function_exists('ac_clock_minutes')) {
    function ac_clock_minutes(string $clock): int
    {
        $clock = trim($clock);
        if ($clock === '') {
            return -1;
        }
        $ts = strtotime($clock);
        if ($ts === false) {
            return -1;
        }
        return ((int) date('G', $ts) * 60) + (int) date('i', $ts);
    }
}
if (!function_exists('ac_period_from_minutes')) {
    function ac_period_from_minutes(int $minutes): string
    {
        if ($minutes < 0) {
            return '';
        }
        return $minutes < (12 * 60) ? 'morning' : 'afternoon';
    }
}
if (!function_exists('ac_stamp_html')) {
    /** Digital stamp shown in a signature cell. */
    function ac_stamp_html(string $signer, string $signedAt): string
    {
        $ts = $signedAt !== '' ? strtotime($signedAt) : false;
        $dateText = $ts ? date('M j, Y', $ts) : '';
        $title = 'Digitally stamped' . ($signer !== '' ? ' by ' . $signer : '') . ($dateText !== '' ? ' on ' . $dateText : '');
        return '<span class="ac-stamp" title="' . ac_h($title) . '">'
            . '<span class="ac-stamp__top"><i class="fas fa-circle-check" aria-hidden="true"></i> Verified</span>'
            . '<span class="ac-stamp__name">' . ac_h($signer !== '' ? $signer : 'Teacher') . '</span>'
            . ($dateText !== '' ? '<span class="ac-stamp__date">' . ac_h($dateText) . '</span>' : '')
            . '</span>';
    }
}
?>
<article class="ac-sheet" aria-label="Western Leyte College activity card">
    <header class="ac-school">
        <img class="ac-school__logo" src="<?= ac_h(BASE_URL . '/assets/img/wlc_logo.webp') ?>" alt="Western Leyte College logo" width="76" height="76">
        <div class="ac-school__text">
            <h2 class="ac-school__name">Western Leyte College</h2>
            <p class="ac-school__sub">Student activity card</p>
        </div>
        <?php if ($acQrImageUrl !== ''): ?>
            <figure class="ac-school__qr">
                <img src="<?= ac_h($acQrImageUrl) ?>" alt="QR code: teachers scan to view this activity card" width="76" height="76">
                <figcaption>Teacher scan</figcaption>
            </figure>
        <?php else: ?>
            <span class="ac-school__spacer" aria-hidden="true"></span>
        <?php endif; ?>
    </header>

    <div class="ac-fields">
        <div class="ac-field">
            <span class="ac-field__label">Name:</span>
            <span class="ac-field__value"><?= ac_h((string) ($acStudentName ?? '')) ?></span>
        </div>
        <div class="ac-field">
            <span class="ac-field__label">Department:</span>
            <span class="ac-field__value"><?= ac_h((string) ($acDeptLabel ?? '')) ?></span>
        </div>
    </div>

    <div class="ac-format" id="acFormatLog">
        <div class="ac-table-wrap">
            <table class="ac-table">
                <thead>
                    <tr>
                        <th class="ac-col-date" scope="col">Date</th>
                        <th class="ac-col-in" scope="col">Time in</th>
                        <th class="ac-col-out" scope="col">Time out</th>
                        <th class="ac-col-event" scope="col">Event</th>
                        <th class="ac-col-activity" scope="col">Activity</th>
                        <th class="ac-col-sign" scope="col">Signature</th>
                    </tr>
                </thead>
                <tbody id="acCardBody">
                    <?php foreach (($acDisplayRows ?? []) as $row): ?>
                        <?php
                            $rowKind = (string) ($row['kind'] ?? '');
                            $isBlank = $rowKind === 'blank';
                            $timeIn = trim((string) ($row['time_in'] ?? ''));
                            $timeOut = trim((string) ($row['time_out'] ?? ''));
                            $inMins = ac_clock_minutes($timeIn);
                            $outMins = ac_clock_minutes($timeOut);
                            $period = ac_period_from_minutes($inMins);
                            $dateYmd = (string) ($row['date'] ?? '');
                            $eventTitle = trim((string) ($row['event_title'] ?? $row['event'] ?? ''));
                            $activityTitle = $rowKind === 'activity' ? trim((string) ($row['activity_title'] ?? '')) : '';
                            $signed = !empty($row['signed']);
                            $signer = (string) ($row['signer'] ?? '');
                            $signedAt = (string) ($row['signed_at'] ?? '');
                            $signedAtTs = $signedAt !== '' ? strtotime($signedAt) : false;
                            $rowKey = (string) ($row['row_key'] ?? '');
                        ?>
                        <tr class="<?= $isBlank ? 'ac-row--blank' : 'ac-row--data' ?><?= $signed ? ' ac-row--stamped' : '' ?>"
                            data-kind="<?= ac_h($isBlank ? 'blank' : $rowKind) ?>"
                            data-date="<?= ac_h($dateYmd) ?>"
                            data-date-label="<?= ac_h(ac_date_label($dateYmd)) ?>"
                            data-event="<?= (int) ($row['event_id'] ?? 0) ?>"
                            data-event-title="<?= ac_h($eventTitle) ?>"
                            data-activity="<?= (int) ($row['session_id'] ?? 0) ?>"
                            data-activity-title="<?= ac_h($activityTitle) ?>"
                            data-checked-in="<?= $timeIn !== '' ? '1' : '0' ?>"
                            data-period="<?= ac_h($period) ?>"
                            data-time-in-text="<?= ac_h($timeIn) ?>"
                            data-time-out-text="<?= ac_h($timeOut) ?>"
                            data-time-in-mins="<?= $inMins ?>"
                            data-time-out-mins="<?= $outMins ?>"
                            data-stamped="<?= $signed ? '1' : '0' ?>"
                            data-stamp-by="<?= ac_h($signer) ?>"
                            data-stamp-at="<?= ac_h($signedAtTs ? date('Y-m-d H:i:s', $signedAtTs) : '') ?>">
                            <td class="ac-td-center" data-label="Date"><?= ac_h(ac_date_label($dateYmd)) ?></td>
                            <td class="ac-td-center" data-label="Time in"><?= ac_h($timeIn) ?></td>
                            <td class="ac-td-center" data-label="Time out"><?= ac_h($timeOut) ?></td>
                            <td data-label="Event"><?= ac_h($eventTitle) ?></td>
                            <td data-label="Activity"><?= $isBlank ? '' : ($activityTitle !== '' ? ac_h($activityTitle) : '<span class="ac-muted">Main event</span>') ?></td>
                            <td class="ac-td-sign" data-label="Signature">
                                <?php if ($signed): ?>
                                    <?= ac_stamp_html($signer, $signedAt) ?>
                                <?php else: ?>
                                    <span class="ac-sign-line"></span>
                                <?php endif; ?>
                                <?php if ($acTeacherMode && !$isBlank && $rowKey !== ''): ?>
                                    <?php if ($signed): ?>
                                        <form method="post" action="<?= ac_h($acTeacherFormAction) ?>" class="ac-stamp-form"
                                              data-efy-confirm="Remove your stamp from this row?" data-efy-confirm-title="Remove stamp" data-efy-confirm-label="Remove" data-efy-confirm-type="danger">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="unstamp">
                                            <input type="hidden" name="row_key" value="<?= ac_h($rowKey) ?>">
                                            <button type="submit" class="ac-stamp-btn ac-stamp-btn--remove"><i class="fas fa-eraser" aria-hidden="true"></i> Remove</button>
                                        </form>
                                    <?php elseif ($timeIn !== ''): ?>
                                        <form method="post" action="<?= ac_h($acTeacherFormAction) ?>" class="ac-stamp-form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="stamp">
                                            <input type="hidden" name="row_key" value="<?= ac_h($rowKey) ?>">
                                            <button type="submit" class="ac-stamp-btn"><i class="fas fa-stamp" aria-hidden="true"></i> Stamp</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="ac-stamp-note">Not checked in</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="ac-format ac-format--daily" id="acFormatDaily" hidden>
        <div id="acDailySheets"></div>
    </div>
    <p class="ac-note">A teacher's signature or digital stamp confirms attendance. Unsigned rows may be marked absent.</p>
</article>
