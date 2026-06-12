/**
 * Intro Block JavaScript
 *
 * Scroll highlight is initialized globally for [data-highlight-text] in gsap-animations.js.
 * This file only re-inits after Barba page transitions (when enabled).
 */

(function () {
    'use strict';

    function initIntro(container) {
        const root = container || document;
        if (!root.querySelector('[data-highlight-text]')) {
            return;
        }

        window.SOJ_GSAP_ANIMATIONS?.initHighlightText?.(root);
        window.SOJ_GSAP_ANIMATIONS?.refresh?.(root);
    }

    if (document.body?.hasAttribute?.('data-barba')) {
        document.addEventListener('barba:pageReady', (e) => {
            initIntro(e?.detail?.container || document);
        });
    }
})();
