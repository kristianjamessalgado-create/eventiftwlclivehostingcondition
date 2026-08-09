<?php
/** @var bool $student_photos_panel_open */
/** @var int $student_photos_event_id */
/** @var list<array<string, mixed>> $student_photo_galleries */
/** @var ?array<string, mixed> $student_photo_event */
/** @var list<array<string, mixed>> $student_photo_list */
/** @var string $student_photo_error */

$student_photos_panel_open = !empty($student_photos_panel_open);
$student_photos_event_id = (int) ($student_photos_event_id ?? 0);
$student_photo_galleries = is_array($student_photo_galleries ?? null) ? $student_photo_galleries : [];
$student_photo_event = is_array($student_photo_event ?? null) ? $student_photo_event : null;
$student_photo_list = is_array($student_photo_list ?? null) ? $student_photo_list : [];
$student_photo_error = trim((string) ($student_photo_error ?? ''));
$galleryCount = count($student_photo_galleries);
$photoCount = count($student_photo_list);
$dashboardUrl = BASE_URL . '/backend/auth/dashboard_student.php';
$photosPanelUrl = $dashboardUrl . '?panel=photos';
$panelEnterClass = $student_photos_panel_open ? ' student-dash-panel--enter' : '';
$viewingEvent = $student_photos_event_id > 0;
?>

<section
    class="student-dash-panel student-photos-panel<?= $panelEnterClass ?><?= $student_photos_panel_open ? '' : ' d-none' ?>"
    id="studentPhotoGalleryPanel"
    aria-label="Photo gallery"
    data-rendered-event-id="<?= (int) $student_photos_event_id ?>"
    <?= $student_photos_panel_open ? '' : ' hidden' ?>
>
    <div class="student-dash-panel__shell">
        <div class="student-dash-panel__toolbar">
            <?php if ($viewingEvent): ?>
                <button type="button" class="student-dash-panel__back" data-student-panel="photos">
                    <span class="student-dash-panel__back-icon" aria-hidden="true"><i class="fas fa-arrow-left"></i></span>
                    <span class="student-dash-panel__back-label">All galleries</span>
                </button>
            <?php else: ?>
                <button type="button" class="student-dash-panel__back" data-student-panel="home">
                    <span class="student-dash-panel__back-icon" aria-hidden="true"><i class="fas fa-arrow-left"></i></span>
                    <span class="student-dash-panel__back-label">Back to calendar</span>
                </button>
            <?php endif; ?>
            <?php if ($viewingEvent && $photoCount > 0): ?>
                <span class="student-dash-panel__count-pill">
                    <i class="fas fa-camera" aria-hidden="true"></i>
                    <?= $photoCount ?> photo<?= $photoCount === 1 ? '' : 's' ?>
                </span>
            <?php elseif (!$viewingEvent && $galleryCount > 0): ?>
                <span class="student-dash-panel__count-pill">
                    <i class="fas fa-images" aria-hidden="true"></i>
                    <?= $galleryCount ?> <?= $galleryCount === 1 ? 'gallery' : 'galleries' ?>
                </span>
            <?php endif; ?>
        </div>

        <header class="student-dash-panel__hero">
            <div class="student-dash-panel__hero-icon" aria-hidden="true"><i class="fas fa-images"></i></div>
            <div class="student-dash-panel__hero-text">
                <h2 class="student-dash-panel__title">
                    <?php if ($viewingEvent && $student_photo_event): ?>
                        <?= htmlspecialchars((string) ($student_photo_event['title'] ?? 'Event photos')) ?>
                    <?php elseif ($viewingEvent): ?>
                        Event photos
                    <?php else: ?>
                        Photo gallery
                    <?php endif; ?>
                </h2>
                <p class="student-dash-panel__subtitle mb-0">
                    <?php if ($viewingEvent): ?>
                        Official published photos from this event.
                    <?php else: ?>
                        Browse official photos from your department events.
                    <?php endif; ?>
                </p>
            </div>
        </header>

        <?php if ($viewingEvent && !$student_photo_error): ?>
            <div class="student-photo-event-meta">
                <?php if (!empty($student_photo_event['date'])): ?>
                    <span><i class="fas fa-calendar-day" aria-hidden="true"></i> <?= htmlspecialchars(date('M j, Y', strtotime((string) $student_photo_event['date']))) ?></span>
                <?php endif; ?>
                <?php if (!empty($student_photo_event['location'])): ?>
                    <span><i class="fas fa-map-marker-alt" aria-hidden="true"></i> <?= htmlspecialchars(mb_strimwidth((string) $student_photo_event['location'], 0, 48, '…')) ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($student_photo_error !== ''): ?>
            <div class="student-dash-panel__filter-note student-dash-panel__filter-note--warn" role="alert">
                <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                <?= htmlspecialchars($student_photo_error) ?>
                <a href="<?= htmlspecialchars($photosPanelUrl) ?>" data-student-panel="photos">Back to galleries</a>
            </div>
        <?php endif; ?>

        <?php if ($viewingEvent && $student_photo_error === '' && $photoCount === 0): ?>
            <div class="student-dash-panel__empty">
                <div class="student-dash-panel__empty-icon" aria-hidden="true"><i class="fas fa-image"></i></div>
                <h3 class="student-dash-panel__empty-title">No photos yet</h3>
                <p class="student-dash-panel__empty-text mb-0">
                    No official photos have been published for this event. Check again later.
                </p>
                <button type="button" class="student-dash-panel__empty-cta" data-student-panel="photos">
                    <i class="fas fa-th-large me-1"></i> All galleries
                </button>
            </div>
        <?php elseif ($viewingEvent && $student_photo_error === '' && $photoCount > 0): ?>
            <div class="student-photo-thumb-grid" id="studentPhotoThumbGrid">
                <?php foreach ($student_photo_list as $i => $p): ?>
                    <?php $staggerStyle = '--panel-stagger: ' . min($i, 10) * 0.03 . 's'; ?>
                    <button
                        type="button"
                        class="student-photo-thumb"
                        style="<?= htmlspecialchars($staggerStyle) ?>"
                        data-photo-index="<?= (int) $i ?>"
                        aria-label="View photo <?= (int) $i + 1 ?> of <?= $photoCount ?>"
                    >
                        <img src="<?= BASE_URL . '/' . htmlspecialchars((string) ($p['file_path'] ?? '')) ?>" alt="Event photo" loading="lazy">
                        <span class="student-photo-thumb__overlay">
                            <i class="fas fa-expand" aria-hidden="true"></i>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>
            <p class="student-photo-panel__footer-note">Showing <?= $photoCount ?> photo<?= $photoCount === 1 ? '' : 's' ?>.</p>
        <?php elseif (!$viewingEvent && $galleryCount === 0): ?>
            <div class="student-dash-panel__empty">
                <div class="student-dash-panel__empty-icon" aria-hidden="true"><i class="fas fa-images"></i></div>
                <h3 class="student-dash-panel__empty-title">No galleries yet</h3>
                <p class="student-dash-panel__empty-text mb-0">
                    When your multimedia team publishes photos, event galleries will appear here.
                </p>
                <button type="button" class="student-dash-panel__empty-cta" data-student-panel="home">
                    <i class="fas fa-calendar me-1"></i> Back to calendar
                </button>
            </div>
        <?php elseif (!$viewingEvent): ?>
            <?php
                $galleryYears = [];
                foreach ($student_photo_galleries as $gRow) {
                    if (!empty($gRow['date'])) {
                        $galleryYears[date('Y', strtotime((string) $gRow['date']))] = true;
                    }
                }
                krsort($galleryYears, SORT_NUMERIC);
                $galleryYears = array_keys($galleryYears);
            ?>
            <div class="student-photo-gallery-filters" id="studentPhotoGalleryFilters">
                <div class="student-photo-gallery-filters__search-wrap">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input
                        type="search"
                        class="student-photo-gallery-filters__search"
                        id="studentPhotoGallerySearch"
                        placeholder="Search event name or location…"
                        autocomplete="off"
                        aria-label="Search photo galleries"
                    >
                </div>
                <div class="student-photo-gallery-filters__row">
                    <?php if (count($galleryYears) > 1): ?>
                        <label class="student-photo-gallery-filters__field">
                            <span class="student-photo-gallery-filters__label">Year</span>
                            <select class="student-photo-gallery-filters__select" id="studentPhotoGalleryYear" aria-label="Filter galleries by year">
                                <option value="all">All years</option>
                                <?php foreach ($galleryYears as $galleryYear): ?>
                                    <option value="<?= htmlspecialchars((string) $galleryYear) ?>"><?= htmlspecialchars((string) $galleryYear) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                    <label class="student-photo-gallery-filters__field">
                        <span class="student-photo-gallery-filters__label">Sort</span>
                        <select class="student-photo-gallery-filters__select" id="studentPhotoGallerySort" aria-label="Sort photo galleries">
                            <option value="newest">Newest photos</option>
                            <option value="date-desc">Event date (newest)</option>
                            <option value="date-asc">Event date (oldest)</option>
                            <option value="title">Title A–Z</option>
                        </select>
                    </label>
                </div>
                <p class="student-photo-gallery-filters__status" id="studentPhotoGalleryFilterStatus" aria-live="polite">
                    Showing <?= $galleryCount ?> <?= $galleryCount === 1 ? 'gallery' : 'galleries' ?>
                </p>
            </div>
            <div class="student-dash-panel__empty student-photo-gallery-filters__empty" id="studentPhotoGalleryFilterEmpty" hidden>
                <div class="student-dash-panel__empty-icon" aria-hidden="true"><i class="fas fa-filter"></i></div>
                <h3 class="student-dash-panel__empty-title">No galleries match</h3>
                <p class="student-dash-panel__empty-text mb-0">Try a different search or clear your filters.</p>
                <button type="button" class="student-dash-panel__empty-cta" id="studentPhotoGalleryFilterClear">
                    <i class="fas fa-times me-1"></i> Clear filters
                </button>
            </div>
            <div class="student-photo-gallery-grid" id="studentPhotoGalleryGrid">
                <?php foreach ($student_photo_galleries as $i => $g): ?>
                    <?php
                        $gid = (int) ($g['id'] ?? 0);
                        $count = (int) ($g['photo_count'] ?? 0);
                        $cover = (string) ($g['cover'] ?? '');
                        $title = (string) ($g['title'] ?? 'Event');
                        $location = (string) ($g['location'] ?? '');
                        $dateRaw = !empty($g['date']) ? substr((string) $g['date'], 0, 10) : '';
                        $galleryYear = $dateRaw !== '' ? date('Y', strtotime($dateRaw)) : '';
                        $latestTs = !empty($g['latest_photo'])
                            ? strtotime((string) $g['latest_photo'])
                            : ($dateRaw !== '' ? strtotime($dateRaw) : 0);
                        $staggerStyle = '--panel-stagger: ' . min($i, 8) * 0.035 . 's';
                    ?>
                    <a
                        class="student-photo-gallery-card"
                        style="<?= htmlspecialchars($staggerStyle) ?>"
                        href="<?= htmlspecialchars($photosPanelUrl . '&event_id=' . $gid) ?>"
                        data-student-panel="photos"
                        data-student-photos-event="<?= $gid ?>"
                        data-gallery-title="<?= htmlspecialchars(mb_strtolower($title)) ?>"
                        data-gallery-location="<?= htmlspecialchars(mb_strtolower($location)) ?>"
                        data-gallery-date="<?= htmlspecialchars($dateRaw) ?>"
                        data-gallery-year="<?= htmlspecialchars($galleryYear) ?>"
                        data-gallery-latest="<?= (int) $latestTs ?>"
                    >
                        <div class="student-photo-gallery-card__cover">
                            <?php if ($cover !== ''): ?>
                                <img src="<?= BASE_URL . '/' . htmlspecialchars($cover) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <span class="student-photo-gallery-card__placeholder" aria-hidden="true"><i class="fas fa-image"></i></span>
                            <?php endif; ?>
                            <span class="student-photo-gallery-card__count">
                                <i class="fas fa-camera" aria-hidden="true"></i> <?= $count ?>
                            </span>
                        </div>
                        <div class="student-photo-gallery-card__body">
                            <h3 class="student-photo-gallery-card__title"><?= htmlspecialchars($title) ?></h3>
                            <div class="student-photo-gallery-card__meta">
                                <?php if (!empty($g['date'])): ?>
                                    <span><i class="fas fa-calendar-day" aria-hidden="true"></i> <?= htmlspecialchars(date('M j, Y', strtotime((string) $g['date']))) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($g['location'])): ?>
                                    <span><i class="fas fa-map-marker-alt" aria-hidden="true"></i> <?= htmlspecialchars(mb_strimwidth($location, 0, 32, '…')) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($viewingEvent && $student_photo_error === '' && $photoCount > 0): ?>
<div id="studentPhotoViewer" class="student-photo-viewer" hidden>
    <button type="button" class="student-photo-viewer__close" id="studentPhotoViewerClose" aria-label="Close viewer">
        <i class="fas fa-times" aria-hidden="true"></i>
    </button>
    <button type="button" class="student-photo-viewer__nav student-photo-viewer__nav--prev" id="studentPhotoViewerPrev" aria-label="Previous photo">
        <i class="fas fa-chevron-left" aria-hidden="true"></i>
    </button>
    <button type="button" class="student-photo-viewer__nav student-photo-viewer__nav--next" id="studentPhotoViewerNext" aria-label="Next photo">
        <i class="fas fa-chevron-right" aria-hidden="true"></i>
    </button>
    <div class="student-photo-viewer__stage">
        <img id="studentPhotoViewerImg" src="" alt="Event photo">
        <span id="studentPhotoViewerCounter" class="student-photo-viewer__counter"></span>
    </div>
</div>
<script>
window.__studentPhotoViewerUrls = <?= json_encode(array_map(static function ($p) {
    return BASE_URL . '/' . ($p['file_path'] ?? '');
}, $student_photo_list), JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php endif; ?>
