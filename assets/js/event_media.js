/**
 * EVENTIFY event media helpers — photos and videos share one gallery.
 * A stored file whose extension is mp4 / m4v / webm / mov is treated as a video.
 */
(function (global) {
    'use strict';

    function isVideo(path) {
        return /\.(mp4|m4v|webm|mov)(\?|#|$)/i.test(String(path || ''));
    }

    /**
     * Thumbnail element: <img> for photos; for videos a muted first-frame
     * preview with a play badge (wrapped in .efy-media-video-thumb).
     */
    function thumb(url, alt) {
        if (!isVideo(url)) {
            var img = document.createElement('img');
            img.src = url;
            img.alt = alt || 'Event photo';
            img.loading = 'lazy';
            return img;
        }
        var wrap = document.createElement('span');
        wrap.className = 'efy-media-video-thumb';
        var video = document.createElement('video');
        video.src = url + '#t=0.5';
        video.muted = true;
        video.setAttribute('muted', '');
        video.setAttribute('playsinline', '');
        video.preload = 'metadata';
        video.setAttribute('aria-label', alt || 'Event video');
        var badge = document.createElement('span');
        badge.className = 'efy-media-play';
        badge.setAttribute('aria-hidden', 'true');
        badge.innerHTML = '<i class="fas fa-play"></i>';
        wrap.appendChild(video);
        wrap.appendChild(badge);
        return wrap;
    }

    /**
     * Lightbox helper: shows url in imgEl (photo) or videoEl (video).
     * Creates videoEl next to imgEl when missing. Returns the visible element.
     */
    function show(imgEl, url) {
        if (!imgEl) {
            return null;
        }
        var videoEl = imgEl._efyVideo;
        if (!videoEl) {
            videoEl = document.createElement('video');
            videoEl.controls = true;
            videoEl.setAttribute('playsinline', '');
            videoEl.preload = 'metadata';
            videoEl.className = imgEl.className + ' efy-media-viewer-video';
            videoEl.hidden = true;
            imgEl.parentNode.insertBefore(videoEl, imgEl.nextSibling);
            imgEl._efyVideo = videoEl;
        }
        stop(imgEl);
        if (isVideo(url)) {
            imgEl.hidden = true;
            imgEl.style.display = 'none';
            videoEl.hidden = false;
            videoEl.style.display = '';
            videoEl.src = url;
            return videoEl;
        }
        videoEl.hidden = true;
        videoEl.style.display = 'none';
        imgEl.hidden = false;
        imgEl.style.display = '';
        imgEl.src = url;
        return imgEl;
    }

    /** Pause and unload any video shown by show(). */
    function stop(imgEl) {
        var videoEl = imgEl && imgEl._efyVideo;
        if (videoEl) {
            try { videoEl.pause(); } catch (e) { /* ignore */ }
            videoEl.removeAttribute('src');
            try { videoEl.load(); } catch (e) { /* ignore */ }
        }
    }

    global.eventifyMedia = { isVideo: isVideo, thumb: thumb, show: show, stop: stop };
})(window);
