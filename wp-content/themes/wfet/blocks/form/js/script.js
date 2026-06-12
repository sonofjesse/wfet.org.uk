/**
 * Form Block JavaScript
 */

(function () {
    'use strict';

    function initForm() {
        document.querySelectorAll('.form').forEach(block => {
            // Add your block functionality here
        });
    }

    // Barba-safe: use barba:pageReady when active (avoids double-init on first load)
    // Fall back to DOMContentLoaded when Barba is not used
    function onPageReady(e) {
        const container = e?.detail?.container || document;
        if (container.querySelector('.form')) {
            initForm();
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
