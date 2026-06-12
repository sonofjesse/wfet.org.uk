/**
 * Image Carousel Block — GSAP carousel (center mode, infinite loop, autoplay).
 */

import { gsap } from 'gsap';
import { Draggable } from 'gsap/Draggable';

gsap.registerPlugin(Draggable);

const AUTOPLAY_DELAY = 2;
const ANIM_DURATION = 2;
const ANIM_EASE = 'power2.inOut';

/**
 * @typedef {object} ImageCarouselInstance
 * @property {() => void} destroy
 */

/**
 * @param {HTMLElement} block
 * @returns {ImageCarouselInstance|null}
 */
function createImageCarousel(block) {
    const track = block.querySelector('.image-carousel__track');

    if (!track) {
        return null;
    }

    const originalSlides = [...track.querySelectorAll('.image-carousel__slide')];
    const count = originalSlides.length;

    if (count < 2) {
        return null;
    }

    const strip = document.createElement('div');
    strip.className = 'image-carousel__strip';
    strip.setAttribute('aria-hidden', 'false');

    const prependSet = document.createDocumentFragment();
    const appendSet = document.createDocumentFragment();

    originalSlides.forEach((slide) => {
        prependSet.appendChild(slide.cloneNode(true));
    });

    originalSlides.forEach((slide) => {
        strip.appendChild(slide);
    });

    originalSlides.forEach((slide) => {
        appendSet.appendChild(slide.cloneNode(true));
    });

    strip.insertBefore(prependSet, strip.firstChild);
    strip.appendChild(appendSet);
    track.appendChild(strip);
    track.classList.add('is-initialized');

    const totalSlides = count * 3;
    let index = count;
    let tween = null;
    let draggable = null;
    let autoplayCall = null;
    let resizeObserver = null;

    const autoplayEnabled = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /**
     * Layout-based x offset to center a slide (stable regardless of transforms).
     *
     * @param {number} slideIndex
     * @returns {number}
     */
    function offsetForIndex(slideIndex) {
        const slide = strip.children[slideIndex];

        if (!slide) {
            return 0;
        }

        const trackCenter = track.offsetWidth / 2;
        const slideCenter = slide.offsetLeft + slide.offsetWidth / 2;

        return trackCenter - slideCenter;
    }

    /**
     * @param {number} startIndex
     * @param {number} endIndex
     * @returns {number[]}
     */
    function snapPositionsForRange(startIndex, endIndex) {
        const positions = [];

        for (let i = startIndex; i <= endIndex; i += 1) {
            positions.push(offsetForIndex(i));
        }

        return positions;
    }

    /**
     * @returns {number[]}
     */
    function middleSnapPositions() {
        return snapPositionsForRange(count, count * 2 - 1);
    }

    /**
     * @param {number} x
     * @param {number} startIndex
     * @param {number} endIndex
     * @returns {number}
     */
    function nearestIndexForXInRange(x, startIndex, endIndex) {
        const positions = snapPositionsForRange(startIndex, endIndex);
        let nearest = startIndex;
        let minDistance = Infinity;

        positions.forEach((position, i) => {
            const distance = Math.abs(x - position);

            if (distance < minDistance) {
                minDistance = distance;
                nearest = startIndex + i;
            }
        });

        return nearest;
    }

    /**
     * @param {number} x
     * @returns {number}
     */
    function nearestIndexForX(x) {
        return nearestIndexForXInRange(x, 0, totalSlides - 1);
    }

    function updateCenterClass() {
        [...strip.children].forEach((slide, i) => {
            slide.classList.toggle('is-center', i === index);
        });
    }

    function normalizeIndex() {
        if (index >= count * 2) {
            index -= count;
            gsap.set(strip, { x: offsetForIndex(index) });
        } else if (index < count) {
            index += count;
            gsap.set(strip, { x: offsetForIndex(index) });
        }
    }

    function syncActiveSlideFromX(allowWrap = true) {
        const x = Number(gsap.getProperty(strip, 'x')) || 0;
        index = nearestIndexForX(x);

        if (allowWrap && (index >= count * 2 || index < count)) {
            normalizeIndex();

            if (draggable) {
                draggable.update();
            }
        }

        updateCenterClass();
    }

    function pauseAutoplay() {
        if (autoplayCall) {
            autoplayCall.kill();
            autoplayCall = null;
        }
    }

    function scheduleAutoplay() {
        pauseAutoplay();

        if (!autoplayEnabled) {
            return;
        }

        autoplayCall = gsap.delayedCall(AUTOPLAY_DELAY, () => {
            goTo(index + 1, true, true);
        });
    }

    /**
     * @param {number} targetIndex
     * @param {boolean} [animate=true]
     * @param {boolean} [fromAutoplay=false]
     */
    function goTo(targetIndex, animate = true, fromAutoplay = false) {
        index = targetIndex;
        const x = offsetForIndex(index);

        if (tween) {
            tween.kill();
        }

        pauseAutoplay();

        if (!animate) {
            gsap.set(strip, { x });
            normalizeIndex();
            updateCenterClass();

            if (fromAutoplay || autoplayEnabled) {
                scheduleAutoplay();
            }

            return;
        }

        tween = gsap.to(strip, {
            x,
            duration: ANIM_DURATION,
            ease: ANIM_EASE,
            overwrite: true,
            onUpdate: () => syncActiveSlideFromX(false),
            onComplete: () => {
                normalizeIndex();
                updateCenterClass();
                scheduleAutoplay();
            },
        });
    }

    function getDragBounds() {
        const allPositions = snapPositionsForRange(0, totalSlides - 1);

        return {
            minX: Math.min(...allPositions),
            maxX: Math.max(...allPositions),
        };
    }

    function setupDraggable() {
        const bounds = getDragBounds();

        draggable = Draggable.create(strip, {
            type: 'x',
            inertia: true,
            bounds,
            onPress: pauseAutoplay,
            onDragStart: pauseAutoplay,
            onDrag: () => syncActiveSlideFromX(true),
            snap: {
                x: (endValue) => gsap.utils.snap(middleSnapPositions(), endValue),
            },
            onDragEnd: () => {
                const x = Number(gsap.getProperty(strip, 'x')) || 0;
                index = nearestIndexForXInRange(x, count, count * 2 - 1);
                gsap.set(strip, { x: offsetForIndex(index) });
                updateCenterClass();
                scheduleAutoplay();
            },
        })[0];
    }

    function onResize() {
        const previousIndex = index;
        gsap.set(strip, { x: offsetForIndex(previousIndex) });
        normalizeIndex();
        updateCenterClass();

        if (draggable) {
            draggable.applyBounds(getDragBounds());
        }
    }

    if (typeof ResizeObserver !== 'undefined') {
        resizeObserver = new ResizeObserver(() => onResize());
        resizeObserver.observe(track);
    } else {
        window.addEventListener('resize', onResize);
    }

    requestAnimationFrame(() => {
        goTo(index, false);
        setupDraggable();
        scheduleAutoplay();
    });

    return {
        destroy() {
            pauseAutoplay();

            if (tween) {
                tween.kill();
            }

            if (draggable) {
                draggable.kill();
            }

            if (resizeObserver) {
                resizeObserver.disconnect();
            } else {
                window.removeEventListener('resize', onResize);
            }

            gsap.killTweensOf(strip);

            const slidesToRestore = [...strip.querySelectorAll('.image-carousel__slide')].slice(
                count,
                count * 2
            );

            slidesToRestore.forEach((slide) => {
                slide.classList.remove('is-center');
                track.appendChild(slide);
            });

            strip.remove();
            track.classList.remove('is-initialized');
        },
    };
}

/** @type {Map<HTMLElement, ImageCarouselInstance>} */
const instances = new Map();

/**
 * @param {HTMLElement} block
 */
function initImageCarousel(block) {
    if (
        block.dataset.sojCarouselInit === 'true' ||
        block.classList.contains('image-carousel--editor-preview') ||
        block.classList.contains('image-carousel--single')
    ) {
        return;
    }

    const slides = block.querySelectorAll('.image-carousel__slide');

    if (slides.length < 2) {
        if (slides.length === 1) {
            block.classList.add('image-carousel--single');
        }

        block.dataset.sojCarouselInit = 'true';
        return;
    }

    if (instances.has(block)) {
        return;
    }

    const instance = createImageCarousel(block);

    if (!instance) {
        return;
    }

    instances.set(block, instance);
    block.dataset.sojCarouselInit = 'true';
}

/**
 * @param {HTMLElement} block
 */
function destroyImageCarousel(block) {
    const instance = instances.get(block);

    if (instance) {
        instance.destroy();
        instances.delete(block);
    }

    delete block.dataset.sojCarouselInit;
    block.classList.remove('image-carousel--single');
}

/**
 * @param {ParentNode} root
 */
function initImageCarousels(root) {
    root.querySelectorAll('.image-carousel').forEach((block) => {
        initImageCarousel(block);
    });
}

/**
 * @param {ParentNode} root
 */
function destroyImageCarousels(root) {
    root.querySelectorAll('.image-carousel').forEach((block) => {
        destroyImageCarousel(block);
    });
}

/**
 * @param {CustomEvent} [e]
 */
function onPageReady(e) {
    const container = e?.detail?.container || document;

    if (!container.querySelector('.image-carousel')) {
        return;
    }

    initImageCarousels(container);
}

/**
 * @param {CustomEvent} [e]
 */
function onPageLeave(e) {
    const container = e?.detail?.container || document;
    destroyImageCarousels(container);
}

if (document.body?.hasAttribute?.('data-barba')) {
    document.addEventListener('barba:pageReady', onPageReady);
    document.addEventListener('barba:pageLeave', onPageLeave);
} else if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => onPageReady({ detail: { container: document } }));
} else {
    onPageReady({ detail: { container: document } });
}
