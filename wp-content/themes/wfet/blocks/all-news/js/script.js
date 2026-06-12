/**
 * All News Block JavaScript
 */

(function () {
    'use strict';

    function getAjaxConfig() {
        if (typeof sojTheme === 'undefined') {
            return null;
        }

        return {
            url: sojTheme.ajaxUrl,
            nonce: sojTheme.nonce,
        };
    }

    function updateFilterState(filtersNav, activeCategoryId) {
        const buttons = filtersNav.querySelectorAll('[data-category-id]');

        filtersNav.classList.toggle('has-active', activeCategoryId > 0);

        buttons.forEach((button) => {
            const categoryId = parseInt(button.dataset.categoryId, 10) || 0;
            const isActive = categoryId === activeCategoryId;

            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    async function loadAllNewsResults(block, activeCategoryId, paged) {
        const results = block.querySelector('[data-all-news-results]');
        const ajaxConfig = getAjaxConfig();

        if (!results || !ajaxConfig) {
            return;
        }

        results.classList.add('all-news__results--loading');

        const formData = new FormData();
        formData.append('action', 'soj_filter_all_news');
        formData.append('nonce', ajaxConfig.nonce);
        formData.append('categoryId', String(activeCategoryId));
        formData.append('paged', String(paged));
        formData.append('contextPostId', results.dataset.contextPostId || '0');
        formData.append('showCategory', results.dataset.showCategory || '0');

        try {
            const response = await fetch(ajaxConfig.url, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
            });

            const payload = await response.json();

            if (!response.ok || !payload?.success || typeof payload.data?.html !== 'string') {
                throw new Error('All News filter request failed');
            }

            results.innerHTML = payload.data.html;

            if (typeof window.SOJ_initDeferredPictures === 'function') {
                window.SOJ_initDeferredPictures(results);
            }

            if (typeof window.SOJ_GSAP_ANIMATIONS?.refresh === 'function') {
                window.SOJ_GSAP_ANIMATIONS.refresh(results);
            }
        } catch (error) {
            console.error(error);
        } finally {
            results.classList.remove('all-news__results--loading');
        }
    }

    function initAllNewsBlock(block) {
        if (block.classList.contains('all-news--editor-preview')) {
            return;
        }

        const filtersNav = block.querySelector('[data-news-filters]');
        const results = block.querySelector('[data-all-news-results]');

        if (!filtersNav || !results) {
            return;
        }

        let activeCategoryId = 0;
        let isLoading = false;

        filtersNav.addEventListener('click', async (event) => {
            if (isLoading) {
                return;
            }

            const clearButton = event.target.closest('[data-news-filters-clear]');

            if (clearButton) {
                activeCategoryId = 0;
                updateFilterState(filtersNav, activeCategoryId);

                isLoading = true;

                try {
                    await loadAllNewsResults(block, 0, 1);
                } finally {
                    isLoading = false;
                }

                return;
            }

            const button = event.target.closest('[data-category-id]');

            if (!button) {
                return;
            }

            const categoryId = parseInt(button.dataset.categoryId, 10) || 0;

            activeCategoryId = categoryId;
            updateFilterState(filtersNav, activeCategoryId);

            isLoading = true;

            try {
                await loadAllNewsResults(block, activeCategoryId, 1);
            } finally {
                isLoading = false;
            }
        });

        results.addEventListener('click', async (event) => {
            const pageLink = event.target.closest('.all-news__pagination a.page-numbers');

            if (!pageLink || isLoading) {
                return;
            }

            event.preventDefault();

            const page = parseInt(pageLink.textContent, 10) || 1;

            isLoading = true;

            try {
                await loadAllNewsResults(block, activeCategoryId, page);
            } finally {
                isLoading = false;
            }
        });
    }

    function initAllNews() {
        document.querySelectorAll('.all-news').forEach(initAllNewsBlock);
    }

    function onPageReady(e) {
        const container = e?.detail?.container || document;

        if (container.querySelector('.all-news')) {
            initAllNews();
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
