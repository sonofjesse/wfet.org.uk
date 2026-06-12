/**
 * Smooth Scrolling with Lenis
 * Provides smooth scrolling functionality with configurable options
 */

import Lenis from "lenis";

class SmoothScroll {
  constructor() {
    this.lenis = null;
    this.isInitialized = false;
    this.config = {
      duration: 1.2,
      easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)), // https://www.desmos.com/calculator/brs54l4xou
      direction: "vertical",
      gestureDirection: "vertical",
      smooth: true,
      mouseMultiplier: 1,
      smoothTouch: false,
      touchMultiplier: 2,
      infinite: false,
    };

    this.init();
  }

  init() {
    // Skip in WordPress admin
    if (document.body?.classList?.contains?.("wp-admin")) {
      return;
    }

    // Only initialize on desktop devices
    if (this.isMobile()) {
      return;
    }

    try {
      this.lenis = new Lenis(this.config);

      // RAF loop
      this.raf(0);

      // Integrate with GSAP if available
      this.integrateWithGSAP();

      // Add event listeners
      this.addEventListeners();

      this.isInitialized = true;

      if (this.isDebugEnabled()) {
        console.log("[SOJ] Lenis smooth scrolling initialized");
      }
    } catch (error) {
      if (this.isDebugEnabled()) {
        console.error("[SOJ] Failed to initialize Lenis:", error);
      }
    }
  }

  /**
   * Check if debug mode is enabled
   * @returns {boolean} True if debug is enabled
   */
  isDebugEnabled() {
    return typeof sojTheme !== "undefined" && sojTheme.debug;
  }

  raf(time) {
    if (this.lenis) {
      this.lenis.raf(time);
    }
    requestAnimationFrame(this.raf.bind(this));
  }

  integrateWithGSAP() {
    // Check if GSAP is available
    if (typeof gsap !== "undefined" && this.lenis) {
      this.lenis.on("scroll", ScrollTrigger.update);

      gsap.ticker.add((time) => {
        this.lenis.raf(time * 1000);
      });

      gsap.ticker.lagSmoothing(0);
    }
  }

  addEventListeners() {
    // Pause scrolling when mobile menu is open
    document.addEventListener("mobileMenuOpen", () => {
      if (this.lenis) {
        this.lenis.stop();
      }
    });

    document.addEventListener("mobileMenuClose", () => {
      if (this.lenis) {
        this.lenis.start();
      }
    });

    // Pause scrolling when modals are open
    document.addEventListener("modalOpen", () => {
      if (this.lenis) {
        this.lenis.stop();
      }
    });

    document.addEventListener("modalClose", () => {
      if (this.lenis) {
        this.lenis.start();
      }
    });
  }

  // Public methods
  scrollTo(target, options = {}) {
    if (this.lenis) {
      this.lenis.scrollTo(target, options);
    }
  }

  scrollToTop() {
    if (this.lenis) {
      this.lenis.scrollTo(0, { duration: 1.5 });
    }
  }

  scrollToElement(selector, options = {}) {
    const element = document.querySelector(selector);
    if (element && this.lenis) {
      this.lenis.scrollTo(element, {
        duration: 1.5,
        offset: -100, // Offset for fixed header
        ...options,
      });
    }
  }

  pause() {
    if (this.lenis) {
      this.lenis.stop();
    }
  }

  resume() {
    if (this.lenis) {
      this.lenis.start();
    }
  }

  destroy() {
    if (this.lenis) {
      this.lenis.destroy();
      this.lenis = null;
      this.isInitialized = false;
    }
  }

  // Utility methods
  isMobile() {
    return window.innerWidth <= 768;
  }

  // Get scroll progress (0 to 1)
  getScrollProgress() {
    if (this.lenis) {
      return this.lenis.progress;
    }
    return 0;
  }

  // Get current scroll position
  getScrollPosition() {
    if (this.lenis) {
      return this.lenis.scroll;
    }
    return window.pageYOffset;
  }

  // Get scroll direction
  getScrollDirection() {
    if (this.lenis) {
      return this.lenis.direction;
    }
    return 1;
  }
}

// Initialize smooth scrolling
const smoothScroll = new SmoothScroll();

// Export for use in other modules
export default smoothScroll;

// Make available globally for debugging
window.smoothScroll = smoothScroll;
