<?php
/**
 * Organizer main hub toolbar — search + jump links (mirrors student hub toolbar).
 *
 * Vars: $organizerHubLiveSessions (list), optional
 */
$organizerHubLiveSessions = is_array($organizerHubLiveSessions ?? null) ? $organizerHubLiveSessions : [];
?>
<div class="eah-toolbar eah-toolbar--student eah-toolbar--organizer">
    <div class="eah-toolbar__filters">
        <?php if ($organizerHubLiveSessions !== []): ?>
            <a class="eah-tb-btn" href="#eah-sp-live" title="Live now" aria-label="Live now"><i class="fas fa-circle"></i></a>
        <?php endif; ?>
        <a class="eah-tb-btn" href="#eah-sp-all" title="All activities" aria-label="All activities"><i class="fas fa-list"></i></a>
        <a class="eah-tb-btn" href="#eah-sp-days" title="Other days" aria-label="Other days"><i class="fas fa-calendar-week"></i></a>
        <a class="eah-tb-btn" href="#eah-sp-cats" title="Categories" aria-label="Categories"><i class="fas fa-th-large"></i></a>
    </div>
    <label class="eah-toolbar__search-wrap">
        <i class="fas fa-search"></i>
        <input type="search" class="eah-toolbar__search" id="eahHubSearch" placeholder="Search activities" autocomplete="off">
    </label>
</div>
