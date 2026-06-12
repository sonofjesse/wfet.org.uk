/**
 * Hydrate deferred SOJ picture elements (see defer_browser_load in soj-dynamic-images.php).
 *
 * @package SOJ_Core_Modern
 */

/**
 * @param {HTMLPictureElement} picture
 */
export function hydrateDeferredPicture(picture) {
    if (picture.dataset.sojDeferHydrated === 'true') {
        return;
    }

    const payload = picture.getAttribute('data-soj-defer-inner');

    if (!payload) {
        return;
    }

    let innerHtml;

    try {
        innerHtml = atob(payload);
    } catch (error) {
        return;
    }

    const template = document.createElement('template');
    template.innerHTML = innerHtml.trim();

    picture.replaceChildren(...template.content.childNodes);
    picture.dataset.sojDeferHydrated = 'true';
    picture.removeAttribute('data-soj-defer-inner');
}

/**
 * @param {ParentNode} root
 */
export function initDeferredPictures(root) {
    const pictures = root.querySelectorAll(
        'picture.soj-picture--deferred[data-soj-defer-inner]'
    );

    if (!pictures.length) {
        return;
    }

    if (!('IntersectionObserver' in window)) {
        pictures.forEach(hydrateDeferredPicture);
        return;
    }

    const observer = new IntersectionObserver(
        (entries, obs) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                hydrateDeferredPicture(entry.target);
                obs.unobserve(entry.target);
            });
        },
        {
            rootMargin: '0px 0px 200px 0px',
            threshold: 0,
        }
    );

    pictures.forEach((picture) => observer.observe(picture));
}
