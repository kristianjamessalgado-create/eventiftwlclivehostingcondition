<?php
/**
 * Admin announcements compose + history panel.
 *
 * @var bool $admin_announcements_panel_open
 * @var list<array<string, mixed>> $adminAnnouncements
 * @var array<string, string> $organizer_department_choices
 * @var mysqli|null $conn
 */

$admin_announcements_panel_open = !empty($admin_announcements_panel_open);
$adminAnnouncements = is_array($adminAnnouncements ?? null) ? $adminAnnouncements : [];
$organizer_department_choices = is_array($organizer_department_choices ?? null) ? $organizer_department_choices : [];
$panelEnterClass = $admin_announcements_panel_open ? ' adm-dash-panel--enter' : '';
$historyCount = count($adminAnnouncements);

require_once __DIR__ . '/../../config/student_profile_fields.php';
$courseOptions = eventify_student_course_program_options();
?>

<section
    class="adm-dash-panel adm-announcements-panel<?= $panelEnterClass ?><?= $admin_announcements_panel_open ? '' : ' d-none' ?>"
    id="adminAnnouncementsPanel"
    aria-label="Announcements"
    <?= $admin_announcements_panel_open ? '' : ' hidden' ?>
>
    <div class="adm-dash-panel__shell">
        <div class="adm-dash-panel__toolbar">
            <button type="button" class="adm-dash-panel__back" data-admin-panel="home">
                <span class="adm-dash-panel__back-icon" aria-hidden="true"><i class="fas fa-arrow-left"></i></span>
                <span class="adm-dash-panel__back-label">Back to calendar</span>
            </button>
            <?php if ($historyCount > 0): ?>
                <span class="adm-dash-panel__count-pill">
                    <i class="fas fa-bullhorn" aria-hidden="true"></i>
                    <?= $historyCount ?> sent
                </span>
            <?php endif; ?>
        </div>

        <header class="adm-dash-panel__hero">
            <div class="adm-dash-panel__hero-icon" aria-hidden="true"><i class="fas fa-bullhorn"></i></div>
            <div class="adm-dash-panel__hero-text">
                <h2 class="adm-dash-panel__title">Announcements</h2>
                <p class="adm-dash-panel__subtitle mb-0">
                    Send a message to students by department, section, or course.
                    Matching students get a bell notification and push (if enabled).
                    At least one audience filter is required.
                </p>
            </div>
        </header>

        <form
            method="post"
            action="<?= htmlspecialchars(BASE_URL . '/backend/admin/create_announcement.php') ?>"
            class="adm-announce-form mb-4"
            id="adminAnnouncementForm"
            novalidate
        >
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="announceTitle" class="form-label fw-semibold">Title</label>
                <input
                    type="text"
                    class="form-control"
                    name="title"
                    id="announceTitle"
                    maxlength="255"
                    required
                    placeholder="e.g. Campus schedule update"
                >
            </div>

            <div class="mb-3">
                <label for="announceBody" class="form-label fw-semibold">Message</label>
                <textarea
                    class="form-control"
                    name="body"
                    id="announceBody"
                    rows="4"
                    maxlength="4000"
                    required
                    placeholder="Write the announcement students will see…"
                ></textarea>
            </div>

            <fieldset class="border rounded p-3 mb-3" id="announceAudienceFilters">
                <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-0">Audience filters <span class="text-danger">*</span></legend>
                <p class="small text-muted mb-3">Select at least one. Combine filters to narrow the audience (AND). Within a filter, any checked option matches (OR).</p>

                <p class="fw-semibold small mb-2">Department / college</p>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="department[]" value="ALL" id="announceDeptAll" data-announce-dept-all>
                    <label class="form-check-label fw-semibold" for="announceDeptAll">All departments</label>
                </div>
                <div class="ce-dept-checkbox-list mb-3" style="max-height: 160px; overflow-y: auto;">
                    <?php foreach ($organizer_department_choices as $deptVal => $deptLabel): ?>
                        <?php if ($deptVal === 'ALL') {
                            continue;
                        } ?>
                        <?php $cbId = 'announce_dept_' . substr(md5((string) $deptVal), 0, 12); ?>
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="department[]"
                                value="<?= htmlspecialchars((string) $deptVal) ?>"
                                id="<?= htmlspecialchars($cbId) ?>"
                                data-announce-dept-specific
                            >
                            <label class="form-check-label" for="<?= htmlspecialchars($cbId) ?>"><?= htmlspecialchars((string) $deptLabel) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php
                $announceSections = [];
                if (!empty($adminClassSections) && is_array($adminClassSections)) {
                    $announceSections = $adminClassSections;
                }
                ?>
                <p class="fw-semibold small mb-2">Class section</p>
                <p class="small text-muted mb-2">Only students assigned these sections are included when you check one or more.</p>
                <?php if ($announceSections !== []): ?>
                    <div class="ce-dept-checkbox-list mb-2" style="max-height: 160px; overflow-y: auto;">
                        <?php foreach ($announceSections as $sec): ?>
                            <?php
                            $lab = trim((string) ($sec['label'] ?? ''));
                            if ($lab === '') {
                                continue;
                            }
                            $sid = 'announce_sec_' . (int) ($sec['id'] ?? 0) . '_' . substr(md5($lab), 0, 8);
                            ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="section[]" value="<?= htmlspecialchars($lab) ?>" id="<?= htmlspecialchars($sid) ?>">
                                <label class="form-check-label" for="<?= htmlspecialchars($sid) ?>"><?= htmlspecialchars($lab) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-2">No saved sections yet — type one below, or add sections under All users.</p>
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label small mb-1" for="announceNewSection">Or type a section name</label>
                    <input type="text" class="form-control form-control-sm" name="new_section" id="announceNewSection" maxlength="80" placeholder="e.g. BSIT4201">
                </div>

                <p class="fw-semibold small mb-2">Course / program</p>
                <div class="ce-dept-checkbox-list mb-0" style="max-height: 180px; overflow-y: auto;">
                    <?php foreach ($courseOptions as $courseVal => $courseLabel): ?>
                        <?php if ($courseVal === '') {
                            continue;
                        } ?>
                        <?php $cId = 'announce_course_' . substr(md5((string) $courseVal), 0, 12); ?>
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="course[]"
                                value="<?= htmlspecialchars((string) $courseVal) ?>"
                                id="<?= htmlspecialchars($cId) ?>"
                            >
                            <label class="form-check-label" for="<?= htmlspecialchars($cId) ?>"><?= htmlspecialchars((string) $courseLabel) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <p class="small text-danger d-none mb-2" id="announceFilterError" role="alert">
                Choose at least one audience filter before sending.
            </p>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane me-1" aria-hidden="true"></i>
                Send announcement
            </button>
        </form>

        <h3 class="h6 text-uppercase text-muted mb-2">Recent announcements</h3>
        <?php if ($adminAnnouncements !== []): ?>
            <ul class="list-unstyled mb-0 small">
                <?php foreach ($adminAnnouncements as $row): ?>
                    <?php
                    $annId = (int) ($row['id'] ?? 0);
                    $annTitle = (string) ($row['title'] ?? '');
                    $annBody = (string) ($row['body'] ?? '');
                    $filtersRaw = $row['target_filters'] ?? null;
                    $filtersLabel = function_exists('eventify_announcement_filters_label')
                        ? eventify_announcement_filters_label($filtersRaw)
                        : '—';
                    $createdAt = (string) ($row['created_at'] ?? '');
                    $when = $createdAt !== '' ? date('M j, Y g:i A', strtotime($createdAt)) : '—';
                    ?>
                    <li class="border rounded p-2 mb-2 bg-light">
                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
                            <span class="fw-semibold"><?= htmlspecialchars($annTitle) ?></span>
                            <span class="text-muted text-nowrap"><?= htmlspecialchars($when) ?></span>
                        </div>
                        <div class="text-muted mb-1">
                            <?= (int) ($row['recipient_count'] ?? 0) ?> student<?= (int) ($row['recipient_count'] ?? 0) === 1 ? '' : 's' ?>
                            · <?= htmlspecialchars((string) ($row['created_by_name'] ?? 'Admin')) ?>
                            · <?= htmlspecialchars($filtersLabel) ?>
                        </div>
                        <div class="mb-2"><?= nl2br(htmlspecialchars($annBody)) ?></div>
                        <?php if ($annId > 0): ?>
                            <div class="d-flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-success js-edit-announcement"
                                    data-announce-id="<?= $annId ?>"
                                    data-announce-title="<?= htmlspecialchars($annTitle, ENT_QUOTES, 'UTF-8') ?>"
                                    data-announce-body="<?= htmlspecialchars($annBody, ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <i class="fas fa-pen me-1" aria-hidden="true"></i>Edit
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger js-delete-announcement"
                                    data-announce-id="<?= $annId ?>"
                                    data-announce-title="<?= htmlspecialchars($annTitle, ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <i class="fas fa-trash me-1" aria-hidden="true"></i>Delete
                                </button>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="mb-0 text-muted small">No announcements sent yet.</p>
        <?php endif; ?>
    </div>
</section>
