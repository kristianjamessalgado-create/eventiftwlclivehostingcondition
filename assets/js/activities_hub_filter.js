/**
 * Activities hub index — toggle status chips to show/hide events.
 */
(function (global) {
    'use strict';

    var list = document.getElementById('eahHubEventList');
    if (!list) {
        return;
    }

    var listHost = document.getElementById('eahHubEventListHost') || list.parentElement;
    var chips = document.querySelectorAll('[data-eah-status]');
    if (!chips.length) {
        return;
    }

    var emptyEl = document.getElementById('eahHubFilterEmpty');
    var emptyTextEl = document.getElementById('eahHubFilterEmptyText');
    var countEl = document.getElementById('eahHubEventCount');
    var cards = list.querySelectorAll('.eah-picker-card[data-filter-status]');
    var storageKey = 'eventify_activities_hub_statuses';
    var allOptions = Array.isArray(window.__eahHubStatusOptions)
        ? window.__eahHubStatusOptions.slice()
        : ['active', 'pending', 'closed', 'rejected'];
    var defaultSelected = normalizeList(window.__eahHubStatusDefault || ['active']);
    var selected = normalizeList(window.__eahHubStatusInitial || defaultSelected);
    var skipLoadingOnce = true;

    try {
        var saved = sessionStorage.getItem(storageKey);
        if (saved) {
            var parsed = JSON.parse(saved);
            if (Array.isArray(parsed)) {
                selected = normalizeList(parsed);
            }
        }
    } catch (e) {
        /* ignore */
    }

    // Never leave users on an empty filter (shows “nothing matches”).
    if (selected.length === 0) {
        selected = defaultSelected.slice();
    }

    function normalizeList(arr) {
        var out = [];
        allOptions.forEach(function (key) {
            if (arr.indexOf(key) >= 0 && out.indexOf(key) < 0) {
                out.push(key);
            }
        });
        return out;
    }

    function arraysEqual(a, b) {
        if (a.length !== b.length) {
            return false;
        }
        for (var i = 0; i < a.length; i += 1) {
            if (a[i] !== b[i]) {
                return false;
            }
        }
        return true;
    }

    function visibleCount() {
        var n = 0;
        cards.forEach(function (card) {
            if (!card.hidden) {
                n += 1;
            }
        });
        return n;
    }

    function updateCount() {
        if (!countEl) {
            return;
        }
        var n = visibleCount();
        countEl.innerHTML = '<i class="fas fa-th-large" aria-hidden="true"></i> ' +
            n + ' event' + (n === 1 ? '' : 's');
    }

    function updateChipUi() {
        chips.forEach(function (chip) {
            var key = chip.getAttribute('data-eah-status');
            var on = selected.indexOf(key) >= 0;
            chip.classList.toggle('is-selected', on);
            chip.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function updateEmptyState() {
        if (!emptyEl) {
            return;
        }
        var visible = visibleCount();
        if (selected.length === 0) {
            emptyEl.hidden = false;
            if (emptyTextEl) {
                emptyTextEl.innerHTML = 'No events match the selected statuses. Try <strong>Select all</strong> or turn on more chips.';
            }
            return;
        }
        if (visible === 0) {
            emptyEl.hidden = false;
            if (emptyTextEl) {
                emptyTextEl.innerHTML = 'No events match the selected statuses. Try turning on more status chips.';
            }
            return;
        }
        emptyEl.hidden = true;
    }

    function persist() {
        try {
            sessionStorage.setItem(storageKey, JSON.stringify(selected));
        } catch (e) {
            /* ignore */
        }
        if (window.history && typeof window.history.replaceState === 'function') {
            var url = new URL(window.location.href);
            if (arraysEqual(selected, defaultSelected)) {
                url.searchParams.delete('status');
            } else {
                url.searchParams.set('status', selected.join(','));
            }
            url.searchParams.delete('filter');
            window.history.replaceState({}, '', url.pathname + url.search + url.hash);
        }
    }

    function applyFilterCore() {
        cards.forEach(function (card) {
            var bucket = card.getAttribute('data-filter-status') || 'closed';
            card.hidden = selected.indexOf(bucket) < 0;
        });
        updateChipUi();
        updateCount();
        updateEmptyState();
        persist();
    }

    function applyFilter() {
        if (skipLoadingOnce) {
            skipLoadingOnce = false;
            applyFilterCore();
            return;
        }

        if (global.EventifySpinner && listHost) {
            global.EventifySpinner.run(listHost, function (finish) {
                global.requestAnimationFrame(function () {
                    applyFilterCore();
                    finish();
                });
            }, { message: 'Updating filters…', minMs: 320 });
            return;
        }

        applyFilterCore();
    }

    function toggleStatus(key) {
        if (allOptions.indexOf(key) < 0) {
            return;
        }
        var idx = selected.indexOf(key);
        if (idx >= 0) {
            selected.splice(idx, 1);
        } else {
            selected.push(key);
            selected.sort(function (a, b) {
                return allOptions.indexOf(a) - allOptions.indexOf(b);
            });
        }
        if (selected.length === 0) {
            selected = defaultSelected.slice();
        }
        applyFilter();
    }

    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            toggleStatus(chip.getAttribute('data-eah-status'));
        });
    });

    var allBtn = document.querySelector('[data-eah-filter-all]');
    if (allBtn) {
        allBtn.addEventListener('click', function () {
            selected = allOptions.slice();
            applyFilter();
        });
    }

    var noneBtn = document.querySelector('[data-eah-filter-none]');
    if (noneBtn) {
        noneBtn.addEventListener('click', function () {
            // Reset to default (Active) instead of showing zero events.
            selected = defaultSelected.slice();
            applyFilter();
        });
    }

    applyFilter();
}(typeof window !== 'undefined' ? window : this));
