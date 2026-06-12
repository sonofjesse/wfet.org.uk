/**
 * SOJ Core Modern - Performance Monitoring Module
 *
 * @package SOJ_Core_Modern
 * @since 2.0.0
 * @author Son of Jesse
 */

/**
 * Performance monitoring class
 */
class PerformanceMonitor {
    constructor() {
        this.initialized = false;
        this.observers = new Map();
    }

    /**
     * Initialize performance monitoring
     */
    init() {
        if (this.initialized) {
            return;
        }

        if (this.isDebugEnabled()) {
            console.log('[PERFORMANCE] Performance monitoring initialized');
        }

        // Immediate performance metrics
        this.logImmediateMetrics();

        // Monitor DOM ready time
        this.monitorDOMReady();

        // Monitor Core Web Vitals
        this.monitorCoreWebVitals();

        // Monitor Navigation Timing
        this.monitorNavigationTiming();

        // Monitor Resource Loading
        this.monitorResourceLoading();

        this.initialized = true;
    }

    /**
     * Check if debug mode is enabled
     * @returns {boolean} True if debug is enabled
     */
    isDebugEnabled() {
        return typeof sojTheme !== 'undefined' && sojTheme.debug;
    }

    /**
     * Log immediate performance metrics
     */
    logImmediateMetrics() {
        if (this.isDebugEnabled()) {
            const pageLoadTime = performance.now();
            console.log(`[PERFORMANCE] Current page load time: ${pageLoadTime.toFixed(2)}ms`);
        }
    }

    /**
     * Monitor DOM ready time
     */
    monitorDOMReady() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                if (this.isDebugEnabled()) {
                    const domReadyTime = performance.now();
                    console.log(`[PERFORMANCE] DOM Ready Time: ${domReadyTime.toFixed(2)}ms`);
                }
            });
        } else {
            if (this.isDebugEnabled()) {
                const domReadyTime = performance.now();
                console.log(`[PERFORMANCE] DOM Ready Time: ${domReadyTime.toFixed(2)}ms`);
            }
        }
    }

    /**
     * Monitor Core Web Vitals
     */
    monitorCoreWebVitals() {
        if (!('PerformanceObserver' in window)) {
            if (this.isDebugEnabled()) {
                console.log('[PERFORMANCE] PerformanceObserver not supported in this browser');
            }
            return;
        }

        try {
            // Monitor Largest Contentful Paint
            const lcpObserver = new PerformanceObserver(list => {
                if (this.isDebugEnabled()) {
                    const entries = list.getEntries();
                    const lastEntry = entries[entries.length - 1];
                    console.log(`[PERFORMANCE] LCP: ${lastEntry.startTime.toFixed(2)}ms`);
                }
            });
            lcpObserver.observe({ entryTypes: ['largest-contentful-paint'] });
            this.observers.set('lcp', lcpObserver);

            // Monitor First Input Delay
            const fidObserver = new PerformanceObserver(list => {
                if (this.isDebugEnabled()) {
                    const entries = list.getEntries();
                    entries.forEach(entry => {
                        console.log(`[PERFORMANCE] FID: ${entry.processingStart - entry.startTime}ms`);
                    });
                }
            });
            fidObserver.observe({ entryTypes: ['first-input'] });
            this.observers.set('fid', fidObserver);

            // Monitor Cumulative Layout Shift
            const clsObserver = new PerformanceObserver(list => {
                if (this.isDebugEnabled()) {
                    let clsValue = 0;
                    const entries = list.getEntries();
                    entries.forEach(entry => {
                        if (!entry.hadRecentInput) {
                            clsValue += entry.value;
                        }
                    });
                    console.log(`[PERFORMANCE] CLS: ${clsValue.toFixed(4)}`);
                }
            });
            clsObserver.observe({ entryTypes: ['layout-shift'] });
            this.observers.set('cls', clsObserver);

        } catch (error) {
            if (this.isDebugEnabled()) {
                console.error('[PERFORMANCE] Failed to initialize Core Web Vitals monitoring:', error);
            }
        }
    }

    /**
     * Monitor Navigation Timing
     */
    monitorNavigationTiming() {
        window.addEventListener('load', () => {
            const navigation = performance.getEntriesByType('navigation')[0];
            if (navigation) {
                console.log(`[PERFORMANCE] Page Load Time: ${navigation.loadEventEnd - navigation.loadEventStart}ms`);
                console.log(`[PERFORMANCE] DOM Content Loaded: ${navigation.domContentLoadedEventEnd - navigation.domContentLoadedEventStart}ms`);
                console.log(`[PERFORMANCE] First Paint: ${performance.getEntriesByType('paint')[0]?.startTime || 'N/A'}ms`);
                console.log(`[PERFORMANCE] First Contentful Paint: ${performance.getEntriesByType('paint')[1]?.startTime || 'N/A'}ms`);
            }
        });
    }

    /**
     * Monitor Resource Loading
     */
    monitorResourceLoading() {
        if (!('PerformanceObserver' in window)) {
            return;
        }

        try {
            const resourceObserver = new PerformanceObserver(list => {
                list.getEntries().forEach(entry => {
                    if (entry.entryType === 'resource' && entry.initiatorType === 'script') {
                        console.log(`[PERFORMANCE] Script Loaded: ${entry.name} (${entry.duration.toFixed(2)}ms)`);
                    }
                });
            });
            resourceObserver.observe({ entryTypes: ['resource'] });
            this.observers.set('resource', resourceObserver);

        } catch (error) {
            console.error('[PERFORMANCE] Failed to initialize resource monitoring:', error);
        }
    }

    /**
     * Get memory usage (if available)
     * @returns {Object|null} Memory usage object
     */
    getMemoryUsage() {
        if (performance.memory) {
            return {
                used: this.formatBytes(performance.memory.usedJSHeapSize),
                total: this.formatBytes(performance.memory.totalJSHeapSize),
                limit: this.formatBytes(performance.memory.jsHeapSizeLimit),
            };
        }
        return null;
    }

    /**
     * Measure function execution time
     * @param {Function} func - Function to measure
     * @param {string} label - Performance label
     * @returns {any} Function result
     */
    measure(func, label = 'Function execution') {
        const start = performance.now();
        const result = func();
        const end = performance.now();

        console.log(`[PERFORMANCE] ${label}: ${(end - start).toFixed(2)}ms`);
        return result;
    }

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
    }

    /**
     * Log memory usage
     */
    logMemoryUsage() {
        const memoryUsage = this.getMemoryUsage();
        if (memoryUsage) {
            console.log('[PERFORMANCE] Memory usage:', memoryUsage);
        }
    }

    /**
     * Destroy performance monitoring
     */
    destroy() {
        // Disconnect all observers
        this.observers.forEach(observer => {
            if (observer && typeof observer.disconnect === 'function') {
                observer.disconnect();
            }
        });

        this.observers.clear();
        this.initialized = false;

        console.log('[PERFORMANCE] Performance monitoring destroyed');
    }
}

// Create and export performance monitor instance
const performanceMonitor = new PerformanceMonitor();

// Auto-initialize when module is loaded
performanceMonitor.init();

export default performanceMonitor;
