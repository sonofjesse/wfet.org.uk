/**
 * Animation System - Scroll-Triggered Animations
 *
 * Automatically triggers animation classes when elements come into view
 * using IntersectionObserver. Works with existing animation classes.
 *
 * Below 992px (theme laptop breakpoint), the observer is skipped; CSS shows
 * elements immediately (see _animations.scss).
 *
 * @package Alliant
 * @since 2.0.0
 */

const CSS_OBSERVER_MOTION_MIN_WIDTH = 992;

function isCssScrollAnimationViewport() {
    return typeof window.matchMedia === 'function'
        && window.matchMedia(`(min-width: ${CSS_OBSERVER_MOTION_MIN_WIDTH}px)`).matches;
}

class AnimationSystem {
    constructor() {
        this.observer = null;
        this.animatedElements = new Set();
        this.init();
    }

    init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupObserver());
        } else {
            this.setupObserver();
        }
    }

    setupObserver() {
        if (!isCssScrollAnimationViewport()) {
            console.log('[SOJ] CSS scroll animations skipped (viewport < ' + CSS_OBSERVER_MOTION_MIN_WIDTH + 'px)');
            return;
        }

        // Create intersection observer
        this.observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        this.triggerAnimation(entry.target);
                        // Stop observing once animation is triggered
                        this.observer.unobserve(entry.target);
                    }
                });
            },
            {
                threshold: 0.5, // Trigger when 20% of element is visible
                rootMargin: '0px 0px 10% 0px' // Start animation slightly before element enters viewport
            }
        );

        // Find and observe all elements with animation classes
        this.observeAnimationElements();
        
        console.log('[SOJ] Animation system initialized');
    }

    observeAnimationElements() {
        // Find all elements with the base 'animate' class
        const elements = document.querySelectorAll('.animate');
        
        elements.forEach(element => {
            // Only observe elements that haven't been animated yet
            if (!this.animatedElements.has(element)) {
                this.observer.observe(element);
            }
        });
    }

    triggerAnimation(element) {
        // Mark element as animated
        this.animatedElements.add(element);
        
        // Add 'in-view' class to trigger the animation
        element.classList.add('in-view');
        
        console.log('[SOJ] Animation triggered for element:', element);
        console.log('[SOJ] Element classes:', element.className);
        console.log('[SOJ] Child elements:', element.children);
        
        // For stagger animations, also log child elements
        if (element.classList.contains('animate-stagger')) {
            console.log('[SOJ] Stagger animation detected');
            Array.from(element.children).forEach((child, index) => {
                console.log(`[SOJ] Child ${index}:`, child, 'Classes:', child.className);
            });
        }
    }

    // Method to manually observe new elements (useful for dynamically added content)
    observeElement(element) {
        if (this.observer && !this.animatedElements.has(element)) {
            this.observer.observe(element);
        }
    }

    // Method to observe all animation elements again (useful for dynamic content)
    refresh() {
        this.observeAnimationElements();
    }
}

// Export the class
export default AnimationSystem;
