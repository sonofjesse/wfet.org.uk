/**
 * Text and Icons Block JavaScript
 */

(function () {
    'use strict';

    function initTextAndIcons() {
        document.querySelectorAll('.text-and-icons').forEach(() => {
            // No interactive behaviour for this block.
        });
    }

    function onPageReady(e) {
        const container = e?.detail?.container || document;
        if (container.querySelector('.text-and-icons')) {
            initTextAndIcons();
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
