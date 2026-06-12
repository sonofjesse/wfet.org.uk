/**
 * SOJ Core Modern - Core Module Loader
 *
 * @package SOJ_Core_Modern
 * @since 2.0.0
 * @author Son of Jesse
 */

import smoothScroll from './modules/smooth-scroll.js';
import MobileMenu from './modules/mobile-menu.js';

/**
 * Core module loader and initializer
 */
class SOJCore {
    constructor() {
        this.modules = new Map();
        this.initialized = false;
    }

    /**
     * Check if debug mode is enabled
     * @returns {boolean} True if debug is enabled
     */
    isDebugEnabled() {
        return typeof sojTheme !== 'undefined' && sojTheme.debug;
    }

    /**
     * Debug-aware logging
     * @param {...any} args - Arguments to log
     */
    log(...args) {
        if (this.isDebugEnabled()) {
            console.log('[SOJ]', ...args);
        }
    }

    /**
     * Debug-aware error logging
     * @param {...any} args - Arguments to log
     */
    error(...args) {
        if (this.isDebugEnabled()) {
            console.error('[SOJ]', ...args);
        }
    }

    /**
     * Initialize all core modules
     */
    init() {
        if (this.initialized) {
            return;
        }

        try {
            this.log('Initializing SOJ Core Modern modules...');

            // Only initialize smooth scrolling on frontend (not in admin)
            if (!this.isAdmin()) {
                this.initSmoothScrolling();
                this.initMobileMenu();
            } else {
                this.log('Skipping smooth scrolling in admin area');
            }

            // Performance monitoring is auto-initialized by the module

            this.initialized = true;
            this.log('All core modules initialized successfully');
        } catch (error) {
            this.error('Failed to initialize core modules:', error);
        }
    }

    /**
     * Check if we're in the WordPress admin area
     * @returns {boolean} True if in admin area
     */
    isAdmin() {
        return document.body.classList.contains('wp-admin') || 
               document.body.classList.contains('block-editor-page') ||
               window.location.href.includes('/wp-admin/');
    }

    /**
     * Initialize smooth scrolling functionality
     */
    initSmoothScrolling() {
        try {
            this.modules.set('smoothScroll', smoothScroll);
            this.log('Smooth scrolling initialized');
        } catch (error) {
            this.error('Failed to initialize smooth scrolling:', error);
        }
    }

    /**
     * Initialize mobile menu functionality
     */
    initMobileMenu() {
        try {
            const mobileMenu = new MobileMenu();
            this.modules.set('mobileMenu', mobileMenu);
            this.log('Mobile menu initialized');
        } catch (error) {
            this.error('Failed to initialize mobile menu:', error);
        }
    }



    /**
     * Get a module instance
     * @param {string} name - Module name
     * @returns {any} Module instance
     */
    getModule(name) {
        return this.modules.get(name);
    }

    /**
     * Get smooth scroll instance
     * @returns {Object} Smooth scroll instance
     */
    getSmoothScroll() {
        return this.modules.get('smoothScroll');
    }

    /**
     * Get mobile menu instance
     * @returns {Object} Mobile menu instance
     */
    getMobileMenu() {
        return this.modules.get('mobileMenu');
    }



    /**
     * Get performance monitor instance
     * @returns {Object} Performance monitor instance
     */
    getPerformanceMonitor() {
        // Performance monitoring is currently disabled
        return null;
    }
}

// Create and initialize core
const core = new SOJCore();

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        try {
            core.init();
        } catch (error) {
            if (core.isDebugEnabled()) {
                console.error('[SOJ] Core initialization error:', error);
            }
        }
    });
} else {
    try {
        core.init();
    } catch (error) {
        if (core.isDebugEnabled()) {
            console.error('[SOJ] Core initialization error:', error);
        }
    }
}

// Make available globally
window.SOJ = core;

// Export for use in main.js
export default core;
