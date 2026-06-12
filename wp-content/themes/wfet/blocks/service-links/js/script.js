/**
 * Service Links Block JavaScript
 */

(function () {
    'use strict';

    function onPageReady(e) {
        const container = e?.detail?.container || document;

        if (typeof window.SOJ_initDeferredPictures === 'function') {
            window.SOJ_initDeferredPictures(container);
        }
    }

    if (document.body?.hasAttribute?.('data-barba')) {
        document.addEventListener('barba:pageReady', onPageReady);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => onPageReady({ detail: { container: document } }));
    } else {
        onPageReady({ detail: { container: document } });
    }
})();
