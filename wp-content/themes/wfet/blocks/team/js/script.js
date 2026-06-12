/**
 * Team Block JavaScript
 */

(function () {
    'use strict';

    const BODY_LOCK_CLASS = 'team-modal-open';

    function dispatchModalEvent(name) {
        document.dispatchEvent(new CustomEvent(name));
    }

    function getFocusableElements(container) {
        return Array.from(
            container.querySelectorAll(
                'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])'
            )
        ).filter((element) => !element.hasAttribute('disabled') && element.offsetParent !== null);
    }

    function openModal(block, trigger) {
        const modal = block.querySelector('.team__modal');
        const templateId = trigger.getAttribute('data-member-content-id');
        const memberName = trigger.getAttribute('data-member-name') || '';
        const template = templateId ? document.getElementById(templateId) : null;

        if (!modal || !template) {
            return;
        }

        const title = modal.querySelector('.team__modal-title');
        const body = modal.querySelector('.team__modal-body');

        if (title) {
            title.textContent = memberName;
            title.hidden = memberName === '';
        }

        if (body) {
            body.innerHTML = template.innerHTML;
        }

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add(BODY_LOCK_CLASS);
        dispatchModalEvent('modalOpen');

        const closeButton = modal.querySelector('.team__modal-close');
        if (closeButton) {
            closeButton.focus();
        }

        block._teamModalLastFocus = trigger;
    }

    function closeModal(block) {
        const modal = block.querySelector('.team__modal');

        if (!modal || modal.hidden) {
            return;
        }

        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');

        const body = modal.querySelector('.team__modal-body');
        if (body) {
            body.innerHTML = '';
        }

        document.body.classList.remove(BODY_LOCK_CLASS);
        dispatchModalEvent('modalClose');

        if (block._teamModalLastFocus && typeof block._teamModalLastFocus.focus === 'function') {
            block._teamModalLastFocus.focus();
        }
    }

    function initTeamBlock(block) {
        if (block.dataset.teamInit === 'true' || block.classList.contains('team--editor-preview')) {
            return;
        }

        block.dataset.teamInit = 'true';

        block.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-team-modal-trigger]');
            const closeTarget = event.target.closest('[data-team-modal-close]');

            if (trigger) {
                event.preventDefault();
                openModal(block, trigger);
                return;
            }

            if (closeTarget) {
                event.preventDefault();
                closeModal(block);
            }
        });

        block.addEventListener('keydown', (event) => {
            const modal = block.querySelector('.team__modal');

            if (!modal || modal.hidden) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeModal(block);
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const dialog = modal.querySelector('.team__modal-dialog');
            if (!dialog) {
                return;
            }

            const focusable = getFocusableElements(dialog);
            if (focusable.length === 0) {
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
    }

    function initTeam(container) {
        container.querySelectorAll('.team').forEach(initTeamBlock);
    }

    function onPageReady(e) {
        const container = e?.detail?.container || document;
        initTeam(container);
    }

    if (document.body?.hasAttribute?.('data-barba')) {
        document.addEventListener('barba:pageReady', onPageReady);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => onPageReady({ detail: { container: document } }));
    } else {
        onPageReady({ detail: { container: document } });
    }
})();
