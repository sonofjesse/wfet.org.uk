/**
 * Service Hero Block JavaScript
 */

(function () {
    'use strict';

    function initServiceHero() {
        document.querySelectorAll('.service-hero__video').forEach((video) => {
            if (!(video instanceof HTMLVideoElement)) {
                return;
            }

            video.muted = true;
            video.defaultMuted = true;

            const playPromise = video.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(() => {});
            }
        });
    }

    // Barba-safe: use barba:pageReady when active (avoids double-init on first load)
    // Fall back to DOMContentLoaded when Barba is not used
    function onPageReady(e) {
        const container = e?.detail?.container || document;
        if (container.querySelector('.service-hero')) {
            initServiceHero();
        }
    }

    if (document.body?.hasAttribute?.('data-barba')) {
        document.addEventListener('barba:pageReady', onPageReady);
    } else {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => onPageReady({ detail: { container: document } }));
        } else {
            onPageReady({ detail: { container: document } });
        }
    }
})();
