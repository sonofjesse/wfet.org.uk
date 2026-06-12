/**
 * SOJ Core Modern - Utilities Module
 *
 * @package SOJ_Core_Modern
 * @since 2.0.0
 * @author Son of Jesse
 */

/**
 * Utility functions for common operations
 */
export const utils = {
    /**
     * Debounce function execution
     * @param {Function} func - Function to debounce
     * @param {number} wait - Wait time in milliseconds
     * @returns {Function} Debounced function
     */
    debounce(func, wait) {
        if (typeof func !== 'function') {
            throw new TypeError('First argument must be a function');
        }

        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Throttle function execution
     * @param {Function} func - Function to throttle
     * @param {number} limit - Throttle limit in milliseconds
     * @returns {Function} Throttled function
     */
    throttle(func, limit) {
        if (typeof func !== 'function') {
            throw new TypeError('First argument must be a function');
        }

        let inThrottle;
        return function (...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => (inThrottle = false), limit);
            }
        };
    },

    /**
     * Check if element is in viewport
     * @param {Element} element - Element to check
     * @param {number} threshold - Threshold percentage (0-1)
     * @returns {boolean} True if element is in viewport
     */
    isInViewport(element, threshold = 0) {
        if (!element || !(element instanceof Element)) {
            return false;
        }

        const rect = element.getBoundingClientRect();
        const windowHeight = window.innerHeight || document.documentElement.clientHeight;
        const windowWidth = window.innerWidth || document.documentElement.clientWidth;

        const thresholdHeight = windowHeight * threshold;

        return (
            rect.top >= -thresholdHeight &&
            rect.left >= 0 &&
            rect.bottom <= windowHeight + thresholdHeight &&
            rect.right <= windowWidth
        );
    },

    /**
     * Get element's position relative to viewport
     * @param {Element} element - Element to get position for
     * @returns {Object} Position object with top, left, bottom, right
     */
    getElementPosition(element) {
        if (!element || !(element instanceof Element)) {
            return null;
        }

        return element.getBoundingClientRect();
    },

    /**
     * Format bytes to human readable format
     * @param {number} bytes - Bytes to format
     * @param {number} decimals - Number of decimal places
     * @returns {string} Formatted string
     */
    formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';

        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];

        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    },
};

/**
 * Event handling utilities
 */
export const events = {
    /**
     * Add event listener with delegation
     * @param {Element} element - Parent element
     * @param {string} event - Event type
     * @param {string} selector - CSS selector for delegation
     * @param {Function} handler - Event handler function
     * @param {Object} options - Event listener options
     */
    delegate(element, event, selector, handler, options = {}) {
        if (!element || !(element instanceof Element)) {
            throw new TypeError('Element must be a valid DOM element');
        }

        element.addEventListener(
            event,
            function (e) {
                const target = e.target.closest(selector);
                if (target && element.contains(target)) {
                    handler.call(target, e);
                }
            },
            options
        );
    },

    /**
     * Remove event listener
     * @param {Element} element - Element to remove listener from
     * @param {string} event - Event type
     * @param {Function} handler - Event handler function
     * @param {Object} options - Event listener options
     */
    remove(element, event, handler, options = {}) {
        if (element && element.removeEventListener) {
            element.removeEventListener(event, handler, options);
        }
    },

    /**
     * Add one-time event listener
     * @param {Element} element - Element to add listener to
     * @param {string} event - Event type
     * @param {Function} handler - Event handler function
     * @param {Object} options - Event listener options
     */
    once(element, event, handler, options = {}) {
        const onceHandler = function (e) {
            handler.call(this, e);
            element.removeEventListener(event, onceHandler, options);
        };

        element.addEventListener(event, onceHandler, options);
    },
};

/**
 * DOM manipulation utilities
 */
export const dom = {
    /**
     * Create element with attributes
     * @param {string} tag - HTML tag name
     * @param {Object} attributes - Element attributes
     * @param {string} content - Element content
     * @returns {Element} Created element
     */
    create(tag, attributes = {}, content = '') {
        const element = document.createElement(tag);

        Object.entries(attributes).forEach(([key, value]) => {
            if (key === 'className') {
                element.className = value;
            } else if (key === 'textContent') {
                element.textContent = value;
            } else if (key === 'innerHTML') {
                element.innerHTML = value;
            } else {
                element.setAttribute(key, value);
            }
        });

        if (content) {
            element.textContent = content;
        }

        return element;
    },

    /**
     * Add CSS class to element
     * @param {Element} element - Target element
     * @param {string} className - CSS class name
     */
    addClass(element, className) {
        if (element && element.classList) {
            element.classList.add(className);
        }
    },

    /**
     * Remove CSS class from element
     * @param {Element} element - Target element
     * @param {string} className - CSS class name
     */
    removeClass(element, className) {
        if (element && element.classList) {
            element.classList.remove(className);
        }
    },

    /**
     * Toggle CSS class on element
     * @param {Element} element - Target element
     * @param {string} className - CSS class name
     * @param {boolean} force - Force add or remove
     */
    toggleClass(element, className, force) {
        if (element && element.classList) {
            element.classList.toggle(className, force);
        }
    },
};

/**
 * Debug utilities
 */
function isDebugEnabled() {
    return typeof sojTheme !== 'undefined' && sojTheme.debug;
}

/**
 * Logging utilities (only show when debug is enabled)
 */
export const log = {
    /**
     * Log message
     * @param {...any} args - Arguments to log
     */
    log(...args) {
        if (isDebugEnabled() && typeof console !== 'undefined' && console.log) {
            console.log('[SOJ]', ...args);
        }
    },

    /**
     * Log warning message
     * @param {...any} args - Arguments to log
     */
    warn(...args) {
        if (isDebugEnabled() && typeof console !== 'undefined' && console.warn) {
            console.warn('[SOJ]', ...args);
        }
    },

    /**
     * Log error message
     * @param {...any} args - Arguments to log
     */
    error(...args) {
        if (isDebugEnabled() && typeof console !== 'undefined' && console.error) {
            console.error('[SOJ]', ...args);
        }
    },

    /**
     * Log info message
     * @param {...any} args - Arguments to log
     */
    info(...args) {
        if (isDebugEnabled() && typeof console !== 'undefined' && console.info) {
            console.info('[SOJ]', ...args);
        }
    },

    /**
     * Log debug message (only when WP_DEBUG is enabled)
     * @param {...any} args - Arguments to log
     */
    debug(...args) {
        if (isDebugEnabled() && typeof console !== 'undefined' && console.debug) {
            console.debug('[SOJ]', ...args);
        }
    },
};



// Export all utilities as a single object
export default {
    utils,
    events,
    dom,
    log,
};
