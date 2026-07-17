// ===============================
// LOADING SPLASH (fallback if inline script did not run)
// ===============================
function eventifyRevealLanding() {
    var root = document.documentElement;
    root.classList.add('eventify-landing-enter--active');
    window.setTimeout(function () {
        root.classList.add('eventify-landing-enter--done');
        root.classList.remove('eventify-landing-enter', 'eventify-landing-enter--active');
    }, 720);
}

function initEventifySplash() {
    var splash = document.getElementById('eventifySplash');
    if (!splash) return;
    if (splash.dataset.splashManaged === 'inline' || splash.dataset.splashDone === '1') return;
    if (splash.dataset.ready === '1') return;
    splash.dataset.ready = '1';

    var finished = false;
    var startedAt = Date.now();
    var minVisibleMs = 1400;

    document.documentElement.classList.add('eventify-splash-pending', 'eventify-landing-enter');

    function finishSplash() {
        if (finished) return;
        finished = true;
        splash.classList.add('eventify-splash--hide');
        eventifyRevealLanding();
        window.setTimeout(function () {
            if (splash.parentNode) splash.parentNode.removeChild(splash);
            document.documentElement.classList.remove('eventify-splash-pending');
            try {
                window.dispatchEvent(new CustomEvent('eventify:splash-done'));
            } catch (e) {
                window.dispatchEvent(new Event('eventify:splash-done'));
            }
        }, 580);
    }

    function scheduleFinish() {
        var wait = Math.max(0, minVisibleMs - (Date.now() - startedAt));
        window.setTimeout(finishSplash, wait);
    }

    if (document.readyState === 'complete') {
        scheduleFinish();
    } else {
        window.addEventListener('load', scheduleFinish, { once: true });
    }

    window.setTimeout(finishSplash, 6000);
}

initEventifySplash();

// ===============================
// SECTION NAVIGATION
// ===============================
let currentSection = document.querySelector('section.active');

var LANDING_SECTION_ORDER = ['public-calendar', 'hero', 'how-it-works', 'features', 'roles', 'faq'];

function isMobileView() {
    return typeof window !== 'undefined' && window.innerWidth <= 768;
}

function updateLandingScrollProgress() {
    var fill = document.getElementById('landingScrollProgress');
    if (!fill) return;
    if (isMobileView()) {
        var docEl = document.documentElement;
        var sh = docEl.scrollHeight - window.innerHeight;
        var pct = sh > 12 ? Math.min(100, Math.max(0, (window.scrollY / sh) * 100)) : 0;
        fill.style.width = pct + '%';
    } else {
        var activeId = currentSection && currentSection.id ? currentSection.id : 'public-calendar';
        var idx = LANDING_SECTION_ORDER.indexOf(activeId);
        if (idx < 0) idx = 0;
        var denom = Math.max(1, LANDING_SECTION_ORDER.length - 1);
        fill.style.width = (idx / denom) * 100 + '%';
    }
}

function syncLandingRevealDesktop() {
    if (isMobileView()) return;
    var active = currentSection || document.querySelector('section.active') || document.querySelector('section');
    document.querySelectorAll('section.reveal-scope').forEach(function (sec) {
        sec.classList.toggle('in-view', sec === active);
    });
}

function bindLandingMagnetic() {
    document.querySelectorAll('.magnetic-wrap').forEach(function (wrap) {
        wrap.addEventListener('mousemove', function (e) {
            if (window.innerWidth <= 768) return;
            var r = wrap.getBoundingClientRect();
            var x = (e.clientX - (r.left + r.width / 2)) * 0.15;
            var y = (e.clientY - (r.top + r.height / 2)) * 0.15;
            wrap.style.transform = 'translate(' + x + 'px,' + y + 'px)';
        });
        wrap.addEventListener('mouseleave', function () {
            wrap.style.transform = '';
        });
    });
}

function initFaqAccordion() {
    var acc = document.querySelector('.faq-accordion');
    if (!acc) return;
    acc.addEventListener('click', function (e) {
        var t = e.target.closest('.faq-trigger');
        if (!t || !acc.contains(t)) return;
        e.preventDefault();
        var item = t.closest('.faq-item');
        if (!item) return;
        var opening = !item.classList.contains('is-open');
        acc.querySelectorAll('.faq-item').forEach(function (fi) {
            fi.classList.remove('is-open');
            var btn = fi.querySelector('.faq-trigger');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        });
        if (opening) {
            item.classList.add('is-open');
            t.setAttribute('aria-expanded', 'true');
        }
    });
}

function initLandingPolish() {
    if (!currentSection) {
        currentSection = document.querySelector('section.active') || document.querySelector('section');
    }
    syncLandingRevealDesktop();
    updateLandingScrollProgress();

    var revealMobileObserver = null;

    function setupMobileReveal() {
        if (revealMobileObserver) {
            revealMobileObserver.disconnect();
            revealMobileObserver = null;
        }
        if (!isMobileView()) return;
        revealMobileObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting && en.target.classList.contains('reveal-scope')) {
                    en.target.classList.add('in-view');
                }
            });
        }, { threshold: 0.08, rootMargin: '-52px 0px -14% 0px' });
        document.querySelectorAll('section.reveal-scope').forEach(function (sec) {
            revealMobileObserver.observe(sec);
        });
    }

    setupMobileReveal();

    var scrollQueued = false;
    window.addEventListener('scroll', function () {
        if (!isMobileView()) return;
        if (scrollQueued) return;
        scrollQueued = true;
        window.requestAnimationFrame(function () {
            updateLandingScrollProgress();
            scrollQueued = false;
        });
    }, { passive: true });

    window.addEventListener('resize', function () {
        if (isMobileView()) {
            setupMobileReveal();
        } else {
            if (revealMobileObserver) {
                revealMobileObserver.disconnect();
                revealMobileObserver = null;
            }
            syncLandingRevealDesktop();
        }
        updateLandingScrollProgress();
    });

    bindLandingMagnetic();
    initFaqAccordion();
}

function initLandingPhotoRail() {
    var rail = document.getElementById('landingPhotoRail');
    if (!rail) return;
    var wrap = rail.closest('.landing-photo-rail-wrap');
    if (!wrap) return;

    var prevBtn = wrap.querySelector('.landing-rail-prev');
    var nextBtn = wrap.querySelector('.landing-rail-next');
    var step = function () {
        return Math.max(180, Math.floor(rail.clientWidth * 0.72));
    };

    function updateNavState() {
        var max = Math.max(0, rail.scrollWidth - rail.clientWidth);
        var pos = rail.scrollLeft;
        if (prevBtn) prevBtn.disabled = pos <= 4;
        if (nextBtn) nextBtn.disabled = pos >= (max - 4);
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            rail.scrollBy({ left: -step(), behavior: 'smooth' });
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            rail.scrollBy({ left: step(), behavior: 'smooth' });
        });
    }

    rail.addEventListener('scroll', updateNavState, { passive: true });
    window.addEventListener('resize', updateNavState);
    updateNavState();

    var cards = rail.querySelectorAll('.landing-photo-card');
    cards.forEach(function (card, idx) {
        var img = card.querySelector('.landing-photo-img');
        if (!img) return;

        var raw = card.getAttribute('data-photo-urls') || '[]';
        var photos = [];
        try {
            photos = JSON.parse(raw);
        } catch (e) {
            photos = [];
        }
        photos = Array.isArray(photos) ? photos.filter(function (u) { return typeof u === 'string' && u; }) : [];
        if (photos.length <= 1) return;

        var pointer = 0;
        var delay = 2000;
        var timerId = null;

        function rotateOnce() {
            pointer = (pointer + 1) % photos.length;
            card.classList.add('is-fading');
            window.setTimeout(function () {
                img.src = photos[pointer];
                card.classList.remove('is-fading');
            }, 240);
        }

        function startRotation() {
            if (timerId !== null) return;
            timerId = window.setInterval(rotateOnce, delay);
        }

        function stopRotation() {
            if (timerId === null) return;
            window.clearInterval(timerId);
            timerId = null;
        }

        // Rotate only while hovering/focusing the card.
        card.addEventListener('mouseenter', startRotation);
        card.addEventListener('mouseleave', stopRotation);
        card.addEventListener('focus', startRotation);
        card.addEventListener('blur', stopRotation);
    });
}

function syncLandingNavActive() {
    var activeId = currentSection && currentSection.id ? currentSection.id : 'public-calendar';
    document.querySelectorAll('[data-nav-section]').forEach(function (link) {
        var match = link.getAttribute('data-nav-section') === activeId;
        link.classList.toggle('is-active', match);
        if (link.closest('.mobile-nav')) return;
        if (link.tagName === 'A' && !link.classList.contains('btn')) {
            link.setAttribute('aria-current', match ? 'page' : 'false');
        }
    });
}

function initLandingEventTabs() {
    var tabs = document.querySelectorAll('.landing-events-tab[data-landing-tab]');
    var panels = document.querySelectorAll('.landing-tab-panel');
    if (!tabs.length || !panels.length) return;

    function activateTab(tabName) {
        var panelId = ({
            upcoming: 'landingTabUpcoming',
            past: 'landingTabPast',
            calendar: 'landingTabCalendar'
        })[tabName] || 'landingTabUpcoming';

        tabs.forEach(function (tab) {
            var isActive = tab.getAttribute('data-landing-tab') === tabName;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        panels.forEach(function (panel) {
            var isActive = panel.id === panelId;
            panel.classList.toggle('is-active', isActive);
            panel.hidden = !isActive;
        });
        if (tabName === 'calendar' && window.__eventifyPublicCalendar && typeof window.__eventifyPublicCalendar.updateSize === 'function') {
            window.requestAnimationFrame(function () {
                window.__eventifyPublicCalendar.updateSize();
            });
        }
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activateTab(tab.getAttribute('data-landing-tab') || 'upcoming');
        });
    });
}

function goToSection(id) {
    const nextSection = document.getElementById(id);
    if (!nextSection) return;

    // On mobile: sections stack; scroll to the section
    if (isMobileView()) {
        nextSection.classList.add('active');
        if (currentSection && currentSection !== nextSection) {
            currentSection.classList.remove('active');
        }
        currentSection = nextSection;
        nextSection.classList.add('in-view');
        nextSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        updateLandingScrollProgress();
        syncLandingNavActive();
        return;
    }

    if (currentSection === nextSection) {
        updateLandingScrollProgress();
        syncLandingNavActive();
        return;
    }

    // Exit current section
    if (currentSection) {
        currentSection.classList.remove('active');
        currentSection.classList.add('exit-left');
    }

    // Prepare next section
    nextSection.classList.remove('exit-left');
    nextSection.classList.add('enter-left');

    // Force browser reflow
    nextSection.offsetHeight;

    // Activate next
    nextSection.classList.remove('enter-left');
    nextSection.classList.add('active');

    currentSection = nextSection;

    // Smooth scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
    syncLandingRevealDesktop();
    updateLandingScrollProgress();
    syncLandingNavActive();
}

// ===============================
// MOBILE MENU
// ===============================
function closeMobileNav() {
    const nav = document.getElementById('mobileNav');
    const btn = document.getElementById('hamburgerBtn');
    const overlay = document.getElementById('mobileNavOverlay');
    if (nav) nav.classList.remove('open');
    if (btn) btn.classList.remove('active');
    if (overlay) overlay.classList.remove('show');
    document.body.classList.remove('mobile-menu-open');
    document.body.style.overflow = '';
}

function toggleMobileNav() {
    const nav = document.getElementById('mobileNav');
    const btn = document.getElementById('hamburgerBtn');
    const overlay = document.getElementById('mobileNavOverlay');
    if (!nav || !btn) return;
    const isOpen = nav.classList.toggle('open');
    btn.classList.toggle('active', isOpen);
    if (overlay) overlay.classList.toggle('show', isOpen);
    document.body.classList.toggle('mobile-menu-open', isOpen);
    document.body.style.overflow = '';
}

document.addEventListener('DOMContentLoaded', function () {
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const registerRoleSelectModal = document.getElementById('registerRoleSelectModal');
    const registerDepartmentWrapModal = document.getElementById('registerDepartmentWrapModal');
    const registerDepartmentSelectModal = document.getElementById('registerDepartmentSelectModal');
    const registerCourseWrapModal = document.getElementById('registerCourseWrapModal');
    const registerCourseSelectModal = document.getElementById('registerCourseSelectModal');
    const registerYearLevelWrapModal = document.getElementById('registerYearLevelWrapModal');
    const registerYearLevelSelectModal = document.getElementById('registerYearLevelSelectModal');
    const loginModalPassword = document.getElementById('loginModalPassword');
    const toggleLoginModalPassword = document.getElementById('toggleLoginModalPassword');
    const registerModalPassword = document.getElementById('registerModalPassword');
    const registerModalConfirmPassword = document.getElementById('registerModalConfirmPassword');
    const registerPasswordGuide = document.getElementById('registerPasswordGuide');
    const registerPasswordMatchStatus = document.getElementById('registerPasswordMatchStatus');
    const toggleRegisterModalPassword = document.getElementById('toggleRegisterModalPassword');
    const toggleRegisterModalConfirmPassword = document.getElementById('toggleRegisterModalConfirmPassword');
    const loginModalForm = document.getElementById('loginModalForm');
    const registerModalForm = document.getElementById('registerModalForm');
    const verifyModalForm = document.getElementById('verifyModalForm');
    const verifyModalEmail = document.getElementById('verifyModalEmail');
    const loginModalMessage = document.getElementById('loginModalMessage');
    const registerModalMessage = document.getElementById('registerModalMessage');
    const verifyModalTopAlert = document.getElementById('verifyModalTopAlert');
    if (hamburgerBtn) {
        hamburgerBtn.addEventListener('click', toggleMobileNav);
    }

    initLandingPolish();
    initLandingPhotoRail();
    initLandingEventTabs();
    initRememberEmail();
    syncLandingNavActive();

    if (registerRoleSelectModal && registerDepartmentWrapModal) {
        const refreshCourseOptionsForDepartment = function () {
            if (!registerCourseSelectModal) return;
            var dept = registerDepartmentSelectModal ? String(registerDepartmentSelectModal.value || '') : '';
            var hasEnabled = false;
            Array.prototype.forEach.call(registerCourseSelectModal.options || [], function (opt) {
                var val = String(opt.value || '');
                if (val === '') {
                    opt.hidden = false;
                    opt.disabled = false;
                    return;
                }
                var optDept = String(opt.getAttribute('data-department') || '');
                var match = dept !== '' && optDept === dept;
                opt.hidden = dept !== '' ? !match : false;
                opt.disabled = dept === '' || !match;
                if (match) hasEnabled = true;
            });
            var selected = registerCourseSelectModal.options[registerCourseSelectModal.selectedIndex];
            if (!selected || selected.disabled) {
                registerCourseSelectModal.value = '';
            }
            if (!hasEnabled && dept !== '') {
                registerCourseSelectModal.value = '';
            }
        };

        const toggleStudentProfileFields = function () {
            var isStudent = registerRoleSelectModal.value === 'student';
            registerDepartmentWrapModal.style.display = isStudent ? 'block' : 'none';
            if (registerCourseWrapModal) {
                registerCourseWrapModal.style.display = isStudent ? 'block' : 'none';
            }
            if (registerYearLevelWrapModal) {
                registerYearLevelWrapModal.style.display = isStudent ? 'block' : 'none';
            }
            if (registerDepartmentSelectModal) {
                registerDepartmentSelectModal.required = isStudent;
                if (!isStudent) registerDepartmentSelectModal.value = '';
            }
            if (registerCourseSelectModal) {
                registerCourseSelectModal.required = isStudent;
                if (!isStudent) registerCourseSelectModal.value = '';
            }
            if (registerYearLevelSelectModal) {
                registerYearLevelSelectModal.required = isStudent;
                if (!isStudent) registerYearLevelSelectModal.value = '';
            }
            if (isStudent) {
                refreshCourseOptionsForDepartment();
            } else if (registerCourseSelectModal) {
                Array.prototype.forEach.call(registerCourseSelectModal.options || [], function (opt) {
                    opt.hidden = false;
                    opt.disabled = false;
                });
            }
        };
        registerRoleSelectModal.addEventListener('change', toggleStudentProfileFields);
        if (registerDepartmentSelectModal) {
            registerDepartmentSelectModal.addEventListener('change', refreshCourseOptionsForDepartment);
        }
        toggleStudentProfileFields();
    }

    function bindEyeToggle(buttonEl, inputEl) {
        if (!buttonEl || !inputEl) return;
        buttonEl.addEventListener('click', function () {
            const show = inputEl.type === 'password';
            inputEl.type = show ? 'text' : 'password';
            buttonEl.setAttribute('aria-pressed', show ? 'true' : 'false');
            buttonEl.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            var icon = buttonEl.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye', !show);
                icon.classList.toggle('fa-eye-slash', show);
            }
            inputEl.focus();
        });
    }

    bindEyeToggle(toggleLoginModalPassword, loginModalPassword);
    bindEyeToggle(toggleRegisterModalPassword, registerModalPassword);
    bindEyeToggle(toggleRegisterModalConfirmPassword, registerModalConfirmPassword);

    function updateRegisterPasswordFeedback() {
        if (!registerModalPassword || !registerModalConfirmPassword) return;
        var p = String(registerModalPassword.value || '');
        var c = String(registerModalConfirmPassword.value || '');
        var hasUpper = /[A-Z]/.test(p);
        var hasSpecial = /[\W_]/.test(p);
        var longEnough = p.length >= 8;

        if (registerPasswordGuide) {
            if (p.length === 0) {
                registerPasswordGuide.textContent = 'Password guide: at least 8 characters, with 1 uppercase letter and 1 special character.';
                registerPasswordGuide.classList.remove('met', 'unmet');
            } else if (longEnough && hasUpper && hasSpecial) {
                registerPasswordGuide.textContent = 'Password strength requirement met.';
                registerPasswordGuide.classList.remove('unmet');
                registerPasswordGuide.classList.add('met');
            } else {
                var missing = [];
                if (!longEnough) missing.push('8+ characters');
                if (!hasUpper) missing.push('1 uppercase');
                if (!hasSpecial) missing.push('1 special character');
                registerPasswordGuide.textContent = 'Missing: ' + missing.join(', ') + '.';
                registerPasswordGuide.classList.remove('met');
                registerPasswordGuide.classList.add('unmet');
            }
        }

        if (!registerPasswordMatchStatus) return;
        if (p.length === 0 && c.length === 0) {
            registerPasswordMatchStatus.textContent = '';
            registerPasswordMatchStatus.style.display = 'none';
            registerPasswordMatchStatus.classList.remove('match', 'mismatch');
            return;
        }
        registerPasswordMatchStatus.style.display = 'block';
        if (c.length === 0) {
            registerPasswordMatchStatus.textContent = 'Confirm your password to check if it matches.';
            registerPasswordMatchStatus.classList.remove('match');
            registerPasswordMatchStatus.classList.add('mismatch');
            return;
        }
        if (p === c) {
            registerPasswordMatchStatus.textContent = 'Passwords match.';
            registerPasswordMatchStatus.classList.remove('mismatch');
            registerPasswordMatchStatus.classList.add('match');
        } else {
            registerPasswordMatchStatus.textContent = 'Passwords do not match.';
            registerPasswordMatchStatus.classList.remove('match');
            registerPasswordMatchStatus.classList.add('mismatch');
        }
    }

    if (registerModalPassword) registerModalPassword.addEventListener('input', updateRegisterPasswordFeedback);
    if (registerModalConfirmPassword) registerModalConfirmPassword.addEventListener('input', updateRegisterPasswordFeedback);
    updateRegisterPasswordFeedback();

    function setInlineMessage(el, type, text) {
        if (!el) return;
        if (!text) {
            el.textContent = '';
            el.style.display = 'none';
            el.classList.remove('error', 'success');
            return;
        }
        el.textContent = text;
        el.classList.remove('error', 'success');
        el.classList.add(type === 'success' ? 'success' : 'error');
        el.style.display = 'block';
    }

    function setVerifyTopAlert(type, text) {
        if (!verifyModalTopAlert) return;
        if (!text) {
            verifyModalTopAlert.textContent = '';
            verifyModalTopAlert.classList.remove('error', 'success');
            verifyModalTopAlert.style.display = 'none';
            return;
        }
        verifyModalTopAlert.textContent = text;
        verifyModalTopAlert.classList.remove('error', 'success');
        verifyModalTopAlert.classList.add(type === 'success' ? 'success' : 'error');
        verifyModalTopAlert.style.display = 'block';
    }

    function clearAllModalMessages() {
        setInlineMessage(loginModalMessage, '', '');
        setInlineMessage(registerModalMessage, '', '');
        setVerifyTopAlert('', '');
    }

    function resolveFinalUrl(rawUrl) {
        try {
            return new URL(rawUrl, window.location.origin);
        } catch (e) {
            return null;
        }
    }

    function getRoleHomeUrl() {
        var base = window.EVENTIFY_BASE_URL || '';
        return base + '/index.php';
    }

    function safeNavigate(urlLike) {
        var target = (typeof urlLike === 'string') ? urlLike : '';
        if (!target || target.indexOf('[object') !== -1) {
            window.location.href = getRoleHomeUrl();
            return;
        }
        window.location.href = target;
    }

    function setFormSubmitting(formEl, submitting, label) {
        if (!formEl) return;
        var btn = formEl.querySelector('button[type="submit"]');
        if (!btn) return;
        if (submitting) {
            if (!btn.dataset.originalHtml) {
                btn.dataset.originalHtml = btn.innerHTML;
            }
            btn.disabled = true;
            btn.classList.add('auth-submit-btn--loading');
            btn.innerHTML =
                '<span class="auth-submit-spinner" aria-hidden="true"></span>' +
                '<span class="auth-submit-label">' + (label || 'Please wait...') + '</span>';
        } else {
            btn.disabled = false;
            btn.classList.remove('auth-submit-btn--loading');
            if (btn.dataset.originalHtml) {
                btn.innerHTML = btn.dataset.originalHtml;
            }
        }
    }

    function extractRedirectFromAuthHtml(htmlText) {
        if (typeof htmlText !== 'string' || htmlText === '') return '';
        var m = htmlText.match(/window\.top\.location\.href\s*=\s*([^;]+);/i);
        if (!m || !m[1]) return '';
        var rhs = m[1].trim();
        // Backend writes: window.top.location.href = "<url>";
        // Try JSON parsing first so escaped slashes are decoded properly.
        try {
            var parsed = JSON.parse(rhs);
            if (typeof parsed === 'string' && parsed) return parsed;
        } catch (e) {
            // ignore and try fallback below
        }
        // Fallback: strip quotes and unescape common slash escaping.
        rhs = rhs.replace(/^['"]|['"]$/g, '').replace(/\\\//g, '/');
        return rhs;
    }

    async function submitModalForm(formEl, onHandled) {
        if (!formEl) return;
        const body = new FormData(formEl);
        const response = await fetch(formEl.action, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            redirect: 'follow'
        });
        const finalUrl = resolveFinalUrl(response.url);
        const responseText = await response.text();
        if (onHandled) onHandled(finalUrl, response, responseText);
    }

    // Hard reliability fix: do NOT AJAX-submit login.
    // Normal form submit ensures server session + role redirects always work.
    if (loginModalForm) {
        loginModalForm.addEventListener('submit', function () {
            clearAllModalMessages();
            setFormSubmitting(loginModalForm, true, 'Logging in...');
        });
    }

    // Hard reliability fix for registration too: do normal submit.
    // Backend already handles validation + redirects with clear error messages.
    if (registerModalForm) {
        registerModalForm.addEventListener('submit', function () {
            clearAllModalMessages();
            setFormSubmitting(registerModalForm, true, 'Registering...');
        });
    }

    // Hard reliability fix for OTP verify too: do normal submit (same as login/register).
    // AJAX fetch + redirect parsing was dropping errors and breaking CSRF on some browsers.
    if (verifyModalForm) {
        verifyModalForm.addEventListener('submit', function (e) {
            if (typeof window.eventifySyncVerifyOtp === 'function') {
                window.eventifySyncVerifyOtp();
            }
            var hiddenOtp = document.getElementById('verifyOtpCode');
            var code = hiddenOtp ? String(hiddenOtp.value || '') : '';
            if (!/^\d{6}$/.test(code)) {
                e.preventDefault();
                var topAlert = document.getElementById('verifyModalTopAlert');
                if (topAlert) {
                    topAlert.textContent = 'Please enter all 6 digits of the OTP.';
                    topAlert.classList.remove('success');
                    topAlert.classList.add('error');
                    topAlert.style.display = 'block';
                }
                var firstEmpty = document.querySelector('.auth-otp-box:not(.is-filled)') || document.getElementById('verifyOtpDigit0');
                if (firstEmpty) firstEmpty.focus();
                return;
            }
            clearAllModalMessages();
            setFormSubmitting(verifyModalForm, true, 'Verifying...');
        });
    }

    initVerifyOtpBoxes();

    // Re-open specific auth modal after backend redirect — wait until splash finishes.
    function openAuthModalAfterSplash(fn) {
        if (window.EVENTIFY_SKIP_SPLASH || !document.getElementById('eventifySplash')) {
            fn();
            return;
        }
        window.addEventListener('eventify:splash-done', fn, { once: true });
    }

    openAuthModalAfterSplash(function () {
        if (window.AUTH_MODAL === 'register') {
            openRegisterModal();
            var serverErrEl = document.getElementById('registerModalMessageServer');
            if (window.AUTH_ERROR && registerModalMessage && !serverErrEl) {
                setInlineMessage(registerModalMessage, 'error', window.AUTH_ERROR);
            }
            if (window.AUTH_ERROR && /password/i.test(window.AUTH_ERROR)) {
                if (registerModalPassword) registerModalPassword.value = '';
                if (registerModalConfirmPassword) registerModalConfirmPassword.value = '';
            }
        } else if (window.AUTH_MODAL === 'verify') {
            openVerifyModal({
                purpose: window.VERIFY_PURPOSE || 'register',
                email: window.VERIFY_EMAIL || '',
                error: window.AUTH_ERROR || '',
                success: window.AUTH_SUCCESS || ''
            });
        } else if (window.AUTH_MODAL === 'login') {
            openLoginModal();
            var loginServerEl = document.getElementById('loginModalMessageServer');
            if (!loginServerEl && window.AUTH_ERROR && loginModalMessage) {
                setInlineMessage(loginModalMessage, 'error', window.AUTH_ERROR);
            } else if (!loginServerEl && window.AUTH_SUCCESS && loginModalMessage) {
                setInlineMessage(loginModalMessage, 'success', window.AUTH_SUCCESS);
            }
        }
    });

    function promptLogin(loginUrl) {
        openLoginModal();
    }

    document.querySelectorAll('.login-trigger').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            openLoginModal();
        });
    });

    document.querySelectorAll('.get-started-trigger').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            openGetStartedGuide();
        });
    });

    initGetStartedGuide();


    // Public calendar: show events but require login on interaction
    try {
        var calEl = document.getElementById('publicCalendar');
        var monthEl = document.getElementById('publicCalendarMonth');
        if (calEl && window.FullCalendar) {
            var events = Array.isArray(window.PUBLIC_CALENDAR_EVENTS) ? window.PUBLIC_CALENDAR_EVENTS : [];
            var loginUrl = window.PUBLIC_LOGIN_URL || '';
            var syncMonth = function (cal) {
                if (!monthEl || !cal) return;
                monthEl.textContent = (cal.view && cal.view.title) ? cal.view.title : monthEl.textContent;
            };

            var calendar = new FullCalendar.Calendar(calEl, {
                initialView: 'dayGridMonth',
                height: 'auto',
                fixedWeekCount: false,
                showNonCurrentDates: true,
                navLinks: false,
                selectable: false,
                nowIndicator: true,
                eventColor: '#047857',
                eventTextColor: '#fefce8',
                events: events,
                eventDidMount: function (info) {
                    if (typeof eventifyApplyCalendarEventMount === 'function') {
                        eventifyApplyCalendarEventMount(info);
                        return;
                    }
                    var st = String((info.event.extendedProps && info.event.extendedProps.status) || '').toLowerCase();
                    if (st === 'closed' || st === 'completed') {
                        info.el.style.backgroundColor = '#64748b';
                        info.el.style.borderColor = '#475569';
                    }
                },
                dateClick: function () {
                    promptLogin(loginUrl);
                },
                eventClick: function (info) {
                    if (info && info.jsEvent) info.jsEvent.preventDefault();
                    promptLogin(loginUrl);
                },
                datesSet: function () {
                    syncMonth(calendar);
                }
            });
            calendar.render();
            syncMonth(calendar);
            window.__eventifyPublicCalendar = calendar;

            var syncLandingVideoToCalendar = function () {
                var card = document.querySelector('#public-calendar .public-calendar-card');
                var panel = document.querySelector('#public-calendar .landing-events-panel');
                var wrap = document.querySelector('.landing-calendar-video-wrap');
                var video = wrap && wrap.querySelector('.landing-calendar-video');
                if (!wrap || !video) return;
                if (window.innerWidth <= 768) {
                    wrap.style.width = '';
                    wrap.style.height = '';
                    return;
                }
                var widthSource = (panel && panel.offsetWidth) || (card && card.offsetWidth) || wrap.offsetWidth;
                if (widthSource > 0) wrap.style.width = widthSource + 'px';
                var targetHeight = card && card.offsetHeight > 120 ? card.offsetHeight : Math.round(widthSource * 0.56);
                if (targetHeight > 0) wrap.style.height = targetHeight + 'px';
            };

            syncLandingVideoToCalendar();
            calendar.on('datesSet', syncLandingVideoToCalendar);
            window.addEventListener('resize', syncLandingVideoToCalendar);
            if (typeof ResizeObserver !== 'undefined') {
                var cardEl = document.querySelector('#public-calendar .public-calendar-card');
                if (cardEl) {
                    var ro = new ResizeObserver(syncLandingVideoToCalendar);
                    ro.observe(cardEl);
                }
            }
        }
    } catch (err) {
        // ignore calendar init failures on landing
    }
});

// ===============================
// MODAL LOGIC
// ===============================
function closeAllLegalModals() {
    var p = document.getElementById('legalPrivacyModal');
    var t = document.getElementById('legalTermsModal');
    if (p) {
        p.style.display = 'none';
        p.setAttribute('aria-hidden', 'true');
    }
    if (t) {
        t.style.display = 'none';
        t.setAttribute('aria-hidden', 'true');
    }
}

function openLegalPrivacyModal() {
    var el = document.getElementById('legalPrivacyModal');
    if (!el) return;
    closeLegalTermsModal();
    el.style.display = 'flex';
    el.setAttribute('aria-hidden', 'false');
}

function closeLegalPrivacyModal() {
    var el = document.getElementById('legalPrivacyModal');
    if (el) {
        el.style.display = 'none';
        el.setAttribute('aria-hidden', 'true');
    }
}

function openLegalTermsModal() {
    var el = document.getElementById('legalTermsModal');
    if (!el) return;
    closeLegalPrivacyModal();
    el.style.display = 'flex';
    el.setAttribute('aria-hidden', 'false');
}

function closeLegalTermsModal() {
    var el = document.getElementById('legalTermsModal');
    if (el) {
        el.style.display = 'none';
        el.setAttribute('aria-hidden', 'true');
    }
}

function setAuthModalBodyLock(open) {
    document.body.classList.toggle('auth-modal-open', !!open);
    if (open) {
        document.body.style.overflow = 'hidden';
        if (typeof window.scrollTo === 'function') {
            window.scrollTo(0, 0);
        }
    } else {
        document.body.style.overflow = '';
    }
}

function resetAuthModalScroll() {
    ['loginModal', 'registerModal', 'verifyModal'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
            el.scrollTop = 0;
        }
    });
}

function initRememberEmail() {
    var emailInput = document.getElementById('loginModalEmail');
    var rememberToggle = document.getElementById('loginRememberEmail');
    var loginForm = document.getElementById('loginModalForm');
    if (!emailInput || !rememberToggle || !loginForm) return;

    try {
        var saved = localStorage.getItem('eventify_remember_email');
        if (saved) {
            emailInput.value = saved;
            rememberToggle.checked = true;
        }
    } catch (e) { /* ignore */ }

    loginForm.addEventListener('submit', function () {
        try {
            if (rememberToggle.checked) {
                localStorage.setItem('eventify_remember_email', String(emailInput.value || '').trim());
            } else {
                localStorage.removeItem('eventify_remember_email');
            }
        } catch (e) { /* ignore */ }
    });
}

function openLoginModal() {
    var modal = document.getElementById('loginModal');
    var registerModal = document.getElementById('registerModal');
    var verifyModal = document.getElementById('verifyModal');
    closeAllLegalModals();
    if (registerModal) registerModal.style.display = 'none';
    if (verifyModal) verifyModal.style.display = 'none';
    if (modal) modal.style.display = 'flex';
    setAuthModalBodyLock(true);
    resetAuthModalScroll();
}

function closeLoginModal() {
    document.getElementById('loginModal').style.display = 'none';
    closeAllLegalModals();
    setAuthModalBodyLock(false);
}

function openRegisterModal() {
    var modal = document.getElementById('loginModal');
    var registerModal = document.getElementById('registerModal');
    var verifyModal = document.getElementById('verifyModal');
    closeAllLegalModals();
    if (modal) modal.style.display = 'none';
    if (verifyModal) verifyModal.style.display = 'none';
    if (registerModal) registerModal.style.display = 'flex';
    setAuthModalBodyLock(true);
    resetAuthModalScroll();
}

function closeRegisterModal() {
    var registerModal = document.getElementById('registerModal');
    if (registerModal) registerModal.style.display = 'none';
    closeAllLegalModals();
    setAuthModalBodyLock(false);
}

function syncVerifyOtpHidden() {
    var hidden = document.getElementById('verifyOtpCode');
    var boxes = document.querySelectorAll('.auth-otp-box');
    if (!hidden || !boxes.length) return '';
    var code = '';
    boxes.forEach(function (box) {
        var d = String(box.value || '').replace(/\D/g, '').slice(0, 1);
        box.value = d;
        box.classList.toggle('is-filled', d !== '');
        code += d;
    });
    hidden.value = code;
    return code;
}

function clearVerifyOtpBoxes(focusFirst) {
    var boxes = document.querySelectorAll('.auth-otp-box');
    boxes.forEach(function (box) {
        box.value = '';
        box.classList.remove('is-filled');
    });
    syncVerifyOtpHidden();
    if (focusFirst && boxes[0]) {
        boxes[0].focus();
    }
}

function fillVerifyOtpBoxes(digits) {
    var boxes = document.querySelectorAll('.auth-otp-box');
    var clean = String(digits || '').replace(/\D/g, '').slice(0, boxes.length);
    boxes.forEach(function (box, i) {
        box.value = clean.charAt(i) || '';
        box.classList.toggle('is-filled', box.value !== '');
    });
    syncVerifyOtpHidden();
    var nextIdx = Math.min(clean.length, boxes.length - 1);
    if (boxes[nextIdx]) {
        boxes[nextIdx].focus();
        if (typeof boxes[nextIdx].select === 'function' && boxes[nextIdx].value) {
            boxes[nextIdx].select();
        }
    }
}

function initVerifyOtpBoxes() {
    var boxes = Array.prototype.slice.call(document.querySelectorAll('.auth-otp-box'));
    if (!boxes.length) return;

    window.eventifySyncVerifyOtp = syncVerifyOtpHidden;
    window.eventifyClearVerifyOtp = function () {
        clearVerifyOtpBoxes(false);
    };

    boxes.forEach(function (box, index) {
        box.addEventListener('input', function () {
            var raw = String(box.value || '').replace(/\D/g, '');
            if (raw.length > 1) {
                var prefix = boxes.slice(0, index).map(function (b) {
                    return String(b.value || '').replace(/\D/g, '').slice(0, 1);
                }).join('');
                fillVerifyOtpBoxes(prefix + raw);
                return;
            }
            box.value = raw.slice(0, 1);
            box.classList.toggle('is-filled', box.value !== '');
            syncVerifyOtpHidden();
            if (box.value && boxes[index + 1]) {
                boxes[index + 1].focus();
            }
        });

        box.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace') {
                if (box.value) {
                    box.value = '';
                    box.classList.remove('is-filled');
                    syncVerifyOtpHidden();
                    e.preventDefault();
                    return;
                }
                if (boxes[index - 1]) {
                    boxes[index - 1].focus();
                    boxes[index - 1].value = '';
                    boxes[index - 1].classList.remove('is-filled');
                    syncVerifyOtpHidden();
                    e.preventDefault();
                }
                return;
            }
            if (e.key === 'ArrowLeft' && boxes[index - 1]) {
                boxes[index - 1].focus();
                e.preventDefault();
                return;
            }
            if (e.key === 'ArrowRight' && boxes[index + 1]) {
                boxes[index + 1].focus();
                e.preventDefault();
                return;
            }
        });

        box.addEventListener('paste', function (e) {
            var pasted = '';
            if (e.clipboardData) {
                pasted = e.clipboardData.getData('text') || '';
            } else if (window.clipboardData) {
                pasted = window.clipboardData.getData('Text') || '';
            }
            pasted = String(pasted).replace(/\D/g, '');
            if (!pasted) return;
            e.preventDefault();
            fillVerifyOtpBoxes(pasted);
        });

        box.addEventListener('focus', function () {
            if (typeof box.select === 'function') {
                box.select();
            }
        });
    });

    syncVerifyOtpHidden();
}

function openVerifyModal(opts) {
    opts = opts || {};
    var modal = document.getElementById('loginModal');
    var registerModal = document.getElementById('registerModal');
    var verifyModal = document.getElementById('verifyModal');
    var purposeInput = document.getElementById('verifyModalPurpose');
    var titleEl = document.getElementById('verifyModalTitle');
    var subtitleEl = document.getElementById('verifyModalSubtitle');
    var emailInput = document.getElementById('verifyModalEmail');
    var otpInput = document.getElementById('verifyOtpCode');
    var topAlert = document.getElementById('verifyModalTopAlert');
    var resendPurpose = document.getElementById('verifyResendPurpose');
    var resendEmail = document.getElementById('verifyResendEmail');
    var purpose = opts.purpose === 'reactivate' ? 'reactivate' : 'register';
    closeAllLegalModals();
    if (modal) modal.style.display = 'none';
    if (registerModal) registerModal.style.display = 'none';
    if (purposeInput) purposeInput.value = purpose;
    if (titleEl) {
        titleEl.textContent = purpose === 'register' ? 'Verify your email' : 'Verify reactivation OTP';
    }
    if (subtitleEl) {
        subtitleEl.textContent = purpose === 'register'
            ? 'Enter the 6-digit code we sent to your email. After verification, super admin will approve your account.'
            : 'Enter the code sent to your registered email.';
    }
    if (emailInput && opts.email) {
        emailInput.value = opts.email;
        if (purpose === 'register') {
            emailInput.readOnly = true;
        }
    }
    if (resendPurpose) {
        resendPurpose.value = purpose;
    }
    if (resendEmail && emailInput && emailInput.value) {
        resendEmail.value = emailInput.value;
    }
    if (otpInput && !opts.keepOtp) {
        otpInput.value = '';
        if (typeof window.eventifyClearVerifyOtp === 'function') {
            window.eventifyClearVerifyOtp();
        }
    }
    if (topAlert) {
        var existingMsg = (topAlert.textContent || '').trim();
        if (opts.error) {
            topAlert.textContent = opts.error;
            topAlert.classList.remove('success');
            topAlert.classList.add('error');
            topAlert.style.display = 'block';
        } else if (opts.success) {
            topAlert.textContent = opts.success;
            topAlert.classList.remove('error');
            topAlert.classList.add('success');
            topAlert.style.display = 'block';
        } else if (!existingMsg) {
            topAlert.textContent = '';
            topAlert.classList.remove('error', 'success');
            topAlert.style.display = 'none';
        }
    }
    if (verifyModal) verifyModal.style.display = 'flex';
    setAuthModalBodyLock(true);
    resetAuthModalScroll();
    window.setTimeout(function () {
        var firstDigit = document.getElementById('verifyOtpDigit0');
        var vm = document.getElementById('verifyModal');
        if (firstDigit && vm && vm.style.display !== 'none') {
            firstDigit.focus();
        }
    }, 80);
}

function closeVerifyModal() {
    var verifyModal = document.getElementById('verifyModal');
    if (verifyModal) verifyModal.style.display = 'none';
    closeAllLegalModals();
    setAuthModalBodyLock(false);
}

window.onclick = function(e) {
    const modal = document.getElementById('loginModal');
    const registerModal = document.getElementById('registerModal');
    const verifyModal = document.getElementById('verifyModal');
    const legalPrivacy = document.getElementById('legalPrivacyModal');
    const legalTerms = document.getElementById('legalTermsModal');
    if (e.target === modal) closeLoginModal();
    if (e.target === registerModal) closeRegisterModal();
    if (e.target === verifyModal) closeVerifyModal();
    if (e.target === legalPrivacy) closeLegalPrivacyModal();
    if (e.target === legalTerms) closeLegalTermsModal();
};

// Legal modals: capture phase so footer/register triggers never fall through to navigation.
document.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-legal-open]');
    if (!opener) return;
    e.preventDefault();
    e.stopPropagation();
    var kind = opener.getAttribute('data-legal-open');
    if (kind === 'privacy') {
        openLegalPrivacyModal();
    } else if (kind === 'terms') {
        openLegalTermsModal();
    }
}, true);

// ===============================
// GET STARTED — new-user flashcard guide
// ===============================
var guideState = { index: 0, total: 0, animating: false };
var GUIDE_ANIM_MS = 520;
var GUIDE_SWIPE_MS = 420;

function initGetStartedGuide() {
    var modal = document.getElementById('getStartedGuideModal');
    var stage = document.getElementById('guideFlashcardStage');
    var dotsHost = document.getElementById('guideDots');
    var prevBtn = document.getElementById('guidePrevBtn');
    var nextBtn = document.getElementById('guideNextBtn');
    var skipBtn = document.getElementById('guideSkipBtn');
    var registerBtn = document.getElementById('guideRegisterBtn');
    var loginBtn = document.getElementById('guideLoginBtn');
    if (!modal || !stage) {
        return;
    }

    var slides = Array.prototype.slice.call(stage.querySelectorAll('.guide-flashcard'));
    guideState.total = slides.length;
    if (guideState.total < 1) {
        return;
    }

    if (dotsHost && dotsHost.childElementCount === 0) {
        slides.forEach(function (_, i) {
            var dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'guide-dot' + (i === 0 ? ' is-active' : '');
            dot.setAttribute('role', 'tab');
            dot.setAttribute('aria-label', 'Step ' + (i + 1));
            dot.setAttribute('aria-selected', i === 0 ? 'true' : 'false');
            dot.addEventListener('click', function () {
                setGuideSlide(i);
            });
            dotsHost.appendChild(dot);
        });
    }

    function updateGuideChrome(index) {
        var dots = dotsHost ? dotsHost.querySelectorAll('.guide-dot') : [];
        dots.forEach(function (dot, i) {
            dot.classList.toggle('is-active', i === index);
            dot.setAttribute('aria-selected', i === index ? 'true' : 'false');
        });
        var navHost = modal.querySelector('.guide-modal__nav');
        var touchNav = window.matchMedia('(max-width: 768px), (hover: none) and (pointer: coarse)').matches;
        if (navHost) {
            navHost.hidden = touchNav;
            navHost.setAttribute('aria-hidden', touchNav ? 'true' : 'false');
        }
        if (prevBtn) {
            prevBtn.disabled = index === 0 || guideState.animating;
        }
        if (nextBtn) {
            var isLast = index === guideState.total - 1;
            nextBtn.hidden = isLast;
            nextBtn.style.display = isLast ? 'none' : '';
            nextBtn.disabled = guideState.animating;
        }
        if (skipBtn) {
            skipBtn.hidden = index === guideState.total - 1;
        }
    }

    function resetSlideClasses(slide) {
        slide.classList.remove(
            'is-enter-right',
            'is-enter-left',
            'is-exit-left',
            'is-exit-right'
        );
    }

    function settleGuideSlide(index) {
        index = Math.max(0, Math.min(guideState.total - 1, index));
        guideState.index = index;
        guideState.animating = false;
        slides.forEach(function (slide, i) {
            resetSlideClasses(slide);
            slide.style.transform = '';
            slide.style.transition = '';
            slide.style.opacity = '';
            slide.classList.remove('is-dragging', 'is-swipe-peek');
            var on = i === index;
            slide.classList.toggle('is-active', on);
            slide.hidden = !on;
            slide.setAttribute('aria-hidden', on ? 'false' : 'true');
        });
        updateGuideChrome(index);
    }

    function setGuideSlide(index, instant) {
        index = Math.max(0, Math.min(guideState.total - 1, index));
        if (!instant && (guideState.animating || index === guideState.index)) {
            return;
        }

        var prev = guideState.index;
        if (instant || prev === index) {
            settleGuideSlide(index);
            return;
        }

        var dir = index > prev ? 1 : -1;
        var outgoing = slides[prev];
        var incoming = slides[index];
        guideState.animating = true;
        updateGuideChrome(prev);

        incoming.hidden = false;
        incoming.setAttribute('aria-hidden', 'false');
        outgoing.setAttribute('aria-hidden', 'false');

        resetSlideClasses(outgoing);
        resetSlideClasses(incoming);
        outgoing.classList.remove('is-active');
        incoming.classList.remove('is-active');

        var exitClass = dir > 0 ? 'is-exit-left' : 'is-exit-right';
        var enterClass = dir > 0 ? 'is-enter-right' : 'is-enter-left';

        void stage.offsetWidth;

        outgoing.classList.add(exitClass);
        incoming.classList.add(enterClass);

        slides.forEach(function (slide) {
            slide.style.transform = '';
            slide.style.transition = '';
        });

        window.setTimeout(function () {
            resetSlideClasses(outgoing);
            resetSlideClasses(incoming);
            settleGuideSlide(index);
        }, GUIDE_ANIM_MS);
    }

    window.setGuideSlide = setGuideSlide;

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            setGuideSlide(guideState.index - 1);
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            setGuideSlide(guideState.index + 1);
        });
    }
    if (skipBtn) {
        skipBtn.addEventListener('click', closeGetStartedGuide);
    }
    if (registerBtn) {
        registerBtn.addEventListener('click', function () {
            closeGetStartedGuide();
            openRegisterModal();
        });
    }
    if (loginBtn) {
        loginBtn.addEventListener('click', function () {
            closeGetStartedGuide();
            openLoginModal();
        });
    }

    modal.querySelectorAll('[data-guide-close]').forEach(function (el) {
        el.addEventListener('click', closeGetStartedGuide);
    });

    document.addEventListener('keydown', function (e) {
        if (modal.hidden || guideState.animating) {
            return;
        }
        if (e.key === 'Escape') {
            closeGetStartedGuide();
        } else if (e.key === 'ArrowRight' && guideState.index < guideState.total - 1) {
            setGuideSlide(guideState.index + 1);
        } else if (e.key === 'ArrowLeft' && guideState.index > 0) {
            setGuideSlide(guideState.index - 1);
        }
    });

    (function bindGuideSwipe() {
        var viewport = modal.querySelector('.guide-flashcard-viewport');
        var touchTarget = viewport || stage;
        var swipeStartX = 0;
        var swipeStartY = 0;
        var swipeCurrentX = 0;
        var swipeTracking = false;
        var swipeDragging = false;
        var swipePeekIndex = -1;
        var SWIPE_THRESHOLD = 48;
        var SWIPE_EASE = 'transform ' + (GUIDE_SWIPE_MS / 1000) + 's cubic-bezier(0.25, 0.46, 0.45, 0.94), opacity ' + (GUIDE_SWIPE_MS / 1000) + 's ease';

        function viewportWidth() {
            var box = (viewport || stage).getBoundingClientRect();
            return box.width || window.innerWidth;
        }

        function activeGuideSlide() {
            return slides[guideState.index] || null;
        }

        function resetSwipePeek() {
            if (swipePeekIndex < 0 || swipePeekIndex === guideState.index) {
                swipePeekIndex = -1;
                return;
            }
            var peek = slides[swipePeekIndex];
            if (peek && !peek.classList.contains('is-active')) {
                peek.classList.remove('is-dragging', 'is-swipe-peek');
                peek.hidden = true;
                peek.setAttribute('aria-hidden', 'true');
            }
            swipePeekIndex = -1;
        }

        function clearSwipeInlineStyles() {
            slides.forEach(function (slide) {
                slide.style.transform = '';
                slide.style.transition = '';
                slide.style.opacity = '';
                slide.classList.remove('is-dragging', 'is-swipe-peek');
            });
            swipePeekIndex = -1;
        }

        function applySwipeDrag(dx) {
            var width = viewportWidth();
            var current = activeGuideSlide();
            if (!current) {
                return;
            }

            var offset = dx;
            if (guideState.index === 0 && offset > 0) {
                offset = offset * 0.28;
            } else if (guideState.index === guideState.total - 1 && offset < 0) {
                offset = offset * 0.28;
            }

            resetSwipePeek();

            current.classList.add('is-dragging');
            current.style.transition = 'none';
            current.style.transform = 'translateX(' + offset + 'px)';

            if (offset < 0 && guideState.index < guideState.total - 1) {
                swipePeekIndex = guideState.index + 1;
            } else if (offset > 0 && guideState.index > 0) {
                swipePeekIndex = guideState.index - 1;
            }

            if (swipePeekIndex >= 0) {
                var peek = slides[swipePeekIndex];
                var peekOffset = swipePeekIndex > guideState.index
                    ? width + offset
                    : -width + offset;
                peek.hidden = false;
                peek.setAttribute('aria-hidden', 'false');
                peek.classList.add('is-dragging', 'is-swipe-peek');
                peek.style.transition = 'none';
                peek.style.transform = 'translateX(' + peekOffset + 'px)';
            }
        }

        function snapSwipeBack() {
            var current = activeGuideSlide();
            var peek = swipePeekIndex >= 0 ? slides[swipePeekIndex] : null;
            guideState.animating = true;

            if (current) {
                current.style.transition = SWIPE_EASE;
                current.style.transform = 'translateX(0)';
            }
            if (peek) {
                var width = viewportWidth();
                var peekRest = swipePeekIndex > guideState.index ? width : -width;
                peek.style.transition = SWIPE_EASE;
                peek.style.transform = 'translateX(' + peekRest + 'px)';
            }

            window.setTimeout(function () {
                settleGuideSlide(guideState.index);
            }, GUIDE_SWIPE_MS);
        }

        function completeSwipe(direction) {
            var targetIndex = guideState.index + direction;
            if (targetIndex < 0 || targetIndex >= guideState.total) {
                snapSwipeBack();
                return;
            }

            var width = viewportWidth();
            var current = slides[guideState.index];
            var incoming = slides[targetIndex];
            swipePeekIndex = -1;
            guideState.animating = true;
            updateGuideChrome(guideState.index);

            incoming.hidden = false;
            incoming.setAttribute('aria-hidden', 'false');
            incoming.classList.add('is-dragging', 'is-swipe-peek');

            var dx = swipeCurrentX - swipeStartX;
            var incomingStart = direction > 0 ? width + dx : -width + dx;
            incoming.style.transition = 'none';
            incoming.style.transform = 'translateX(' + incomingStart + 'px)';

            void incoming.offsetWidth;

            current.style.transition = SWIPE_EASE;
            incoming.style.transition = SWIPE_EASE;
            current.style.transform = 'translateX(' + (direction > 0 ? -width : width) + 'px)';
            current.style.opacity = '0.55';
            incoming.style.transform = 'translateX(0)';
            incoming.style.opacity = '1';

            window.setTimeout(function () {
                settleGuideSlide(targetIndex);
            }, GUIDE_SWIPE_MS);
        }

        function onTouchStart(e) {
            if (modal.hidden || guideState.animating || e.touches.length !== 1) {
                return;
            }
            if (e.target.closest('button, a, input, textarea, select, label')) {
                return;
            }
            swipeTracking = true;
            swipeDragging = false;
            swipeStartX = e.touches[0].clientX;
            swipeStartY = e.touches[0].clientY;
            swipeCurrentX = swipeStartX;
        }

        function onTouchMove(e) {
            if (!swipeTracking || guideState.animating || e.touches.length !== 1) {
                return;
            }
            var x = e.touches[0].clientX;
            var y = e.touches[0].clientY;
            var dx = x - swipeStartX;
            var dy = y - swipeStartY;

            if (!swipeDragging) {
                if (Math.abs(dx) < 8 || Math.abs(dx) < Math.abs(dy)) {
                    return;
                }
                swipeDragging = true;
            }

            e.preventDefault();
            swipeCurrentX = x;
            applySwipeDrag(dx);
        }

        function onTouchEnd() {
            if (!swipeTracking) {
                return;
            }
            swipeTracking = false;

            var dx = swipeCurrentX - swipeStartX;

            if (!swipeDragging || guideState.animating) {
                swipeDragging = false;
                settleGuideSlide(guideState.index);
                return;
            }
            swipeDragging = false;

            if (dx <= -SWIPE_THRESHOLD && guideState.index < guideState.total - 1) {
                completeSwipe(1);
            } else if (dx >= SWIPE_THRESHOLD && guideState.index > 0) {
                completeSwipe(-1);
            } else {
                snapSwipeBack();
            }
        }

        touchTarget.addEventListener('touchstart', onTouchStart, { passive: true });
        touchTarget.addEventListener('touchmove', onTouchMove, { passive: false });
        touchTarget.addEventListener('touchend', onTouchEnd, { passive: true });
        touchTarget.addEventListener('touchcancel', onTouchEnd, { passive: true });
    })();

    window.addEventListener('resize', function () {
        if (!modal.hidden) {
            updateGuideChrome(guideState.index);
        }
    });

    setGuideSlide(0, true);
}

function openGetStartedGuide() {
    var modal = document.getElementById('getStartedGuideModal');
    if (!modal) {
        openRegisterModal();
        return;
    }
    if (typeof window.setGuideSlide === 'function') {
        window.setGuideSlide(0, true);
    }
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('guide-modal-open');
    var closeBtn = modal.querySelector('.guide-modal__close');
    if (closeBtn) {
        closeBtn.focus();
    }
}

function closeGetStartedGuide() {
    var modal = document.getElementById('getStartedGuideModal');
    if (!modal) {
        return;
    }
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('guide-modal-open');
}
