/**
 * Activity photos lightbox.
 * Opens activity photos in an in-page viewer (instead of a raw new tab)
 * so students can close them with the X button, a click outside, or Esc.
 */
(function () {
    'use strict';

    var lightbox = document.getElementById('eahPhotoLightbox');
    if (!lightbox) {
        return;
    }

    var imgEl = lightbox.querySelector('.eah-lightbox__img');
    var closeBtn = lightbox.querySelector('.eah-lightbox__close');

    function openLightbox(src, alt) {
        if (!imgEl || !src) {
            return;
        }
        if (window.eventifyMedia) {
            window.eventifyMedia.show(imgEl, src);
        } else {
            imgEl.src = src;
        }
        imgEl.alt = alt || 'Activity photo';
        lightbox.hidden = false;
        document.body.classList.add('eah-lightbox-open');
    }

    function closeLightbox() {
        lightbox.hidden = true;
        if (window.eventifyMedia && imgEl) {
            window.eventifyMedia.stop(imgEl);
        }
        if (imgEl) {
            imgEl.src = '';
        }
        document.body.classList.remove('eah-lightbox-open');
    }

    // Intercept clicks on activity photo links and show the viewer instead.
    document.addEventListener('click', function (e) {
        var link = e.target.closest('.eah-photo-grid__item a');
        if (!link) {
            return;
        }
        var href = link.getAttribute('href');
        if (!href) {
            return;
        }
        e.preventDefault();
        var innerImg = link.querySelector('img, video');
        openLightbox(href, innerImg ? (innerImg.getAttribute('alt') || innerImg.getAttribute('aria-label')) : 'Activity photo');
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closeLightbox);
    }

    // Click on the dark backdrop (but not the image) closes it.
    lightbox.addEventListener('click', function (e) {
        if (e.target === lightbox) {
            closeLightbox();
        }
    });

    document.addEventListener('keydown', function (e) {
        if ((e.key === 'Escape' || e.key === 'Esc') && !lightbox.hidden) {
            closeLightbox();
        }
    });
})();
