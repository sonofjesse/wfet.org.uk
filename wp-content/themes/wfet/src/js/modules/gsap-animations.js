/**
 * GSAP Animation System with ScrollTrigger
 *
 * Advanced animation system using GSAP and ScrollTrigger for smooth,
 * performant animations with scroll-based triggers.
 *
 * @package Alliant
 * @since 2.0.0
 */

import { gsap } from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { CustomEase } from "gsap/CustomEase";
import { SplitText } from "gsap/SplitText";

// Register GSAP plugins
gsap.registerPlugin(ScrollTrigger, CustomEase, SplitText);

/** Viewport min-width (px) — matches $breakpoint-lg; scroll-triggered motion runs at this and above only. */
const GSAP_SCROLL_MOTION_MIN_WIDTH = 992;

function isGsapScrollMotionViewport() {
  return (
    typeof window.matchMedia === "function" &&
    window.matchMedia(`(min-width: ${GSAP_SCROLL_MOTION_MIN_WIDTH}px)`).matches
  );
}

class GSAPAnimationSystem {
  constructor() {
    this.animations = new Map();
    /** @type {{ split: SplitText, srOnly: HTMLElement, heading: HTMLElement }[]} */
    this.highlightSplits = [];
    /** False when viewport is below GSAP_SCROLL_MOTION_MIN_WIDTH — skip ScrollTrigger entrance animations. */
    this.scrollMotionEnabled = false;
    this.init();
  }

  init() {
    // Wait for DOM to be ready
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", () =>
        this.setupAnimations(),
      );
    } else {
      this.setupAnimations();
    }
  }

  setupAnimations() {
    this.scrollMotionEnabled = isGsapScrollMotionViewport();

    if (!this.scrollMotionEnabled) {
      console.log(
        `[GSAP] Scroll-triggered animations skipped (viewport < ${GSAP_SCROLL_MOTION_MIN_WIDTH}px)`,
      );
      return;
    }

    // Convert data attributes into GSAP classes (site-wide convention)
    // This allows blocks to declare animations declaratively without block-specific JS.
    this.applyDataAttributeAnimations();

    // Set up default animations
    this.setupFadeInAnimations();
    this.setupSlideInAnimations();
    this.setupStaggerAnimations();
    this.setupSlowStaggerAnimations();
    this.setupScaleAnimations();
    this.initHighlightText();

    // Refresh ScrollTrigger to ensure all animations are registered
    ScrollTrigger.refresh();

    console.log(
      "[GSAP] Animation system initialized with ScrollTrigger and CustomEase",
    );
  }

  /**
   * Convert [data-gsap-animate] + [data-gsap-delay] into GSAP classes
   * understood by this animation system (e.g. gsap-slide-up, gsap-delay-300).
   */
  applyDataAttributeAnimations() {
    const elements = document.querySelectorAll("[data-gsap-animate]");

    elements.forEach((element) => {
      const typeRaw = (element.dataset.gsapAnimate || "").trim().toLowerCase();
      const delayRaw = element.dataset.gsapDelay;

      // Map attribute values to the system's class-based API
      const typeToClass = {
        "fade-in": "gsap-fade-in",
        "slide-up": "gsap-slide-up",
        "slide-down": "gsap-slide-down",
        "slide-left": "gsap-slide-left",
        "slide-right": "gsap-slide-right",
        stagger: "gsap-stagger",
        "stagger-slow": "gsap-stagger-slow",
        scale: "gsap-scale",
      };

      const typeClass = typeToClass[typeRaw];
      if (typeClass) {
        element.classList.add(typeClass);
      }

      // Delay convention: data-gsap-delay="300" => class gsap-delay-300
      // Only apply if it's a clean integer (avoid adding gsap-delay-NaN).
      if (typeof delayRaw === "string" && delayRaw.trim() !== "") {
        const delayInt = parseInt(delayRaw, 10);
        if (Number.isFinite(delayInt) && delayInt >= 0) {
          element.classList.add(`gsap-delay-${delayInt}`);
        }
      }
    });
  }

  /**
   * Fade in animations
   */
  setupFadeInAnimations() {
    const fadeElements = document.querySelectorAll(".gsap-fade-in");

    fadeElements.forEach((element) => {
      // Check for delay classes
      const delay = this.getDelayFromClasses(element);

      const animation = gsap.fromTo(
        element,
        {
          opacity: 0,
        },
        {
          opacity: 1,
          duration: 1,
          delay: delay,
          ease: "power2.out",
          scrollTrigger: {
            trigger: element,
            start: "top 80%",
            toggleActions: "play none none none",
          },
        },
      );

      this.animations.set(element, animation);
    });
  }

  /**
   * Slide in animations from different directions
   */
  setupSlideInAnimations() {
    // Slide from left
    const slideLeftElements = document.querySelectorAll(".gsap-slide-left");
    slideLeftElements.forEach((element) => {
      const delay = this.getDelayFromClasses(element);
      const animation = gsap.fromTo(
        element,
        {
          x: -100,
          opacity: 0,
        },
        {
          x: 0,
          opacity: 1,
          duration: 1,
          delay: delay,
          ease: "power2.out",
          scrollTrigger: {
            trigger: element,
            start: "top 80%",
            toggleActions: "play none none none",
          },
        },
      );
      this.animations.set(element, animation);
    });

    // Slide from right
    const slideRightElements = document.querySelectorAll(".gsap-slide-right");
    slideRightElements.forEach((element) => {
      const delay = this.getDelayFromClasses(element);
      const animation = gsap.fromTo(
        element,
        {
          x: 100,
          opacity: 0,
        },
        {
          x: 0,
          opacity: 1,
          duration: 1,
          delay: delay,
          ease: "power2.out",
          scrollTrigger: {
            trigger: element,
            start: "top 80%",
            toggleActions: "play none none none",
          },
        },
      );
      this.animations.set(element, animation);
    });

    // Slide from bottom
    const slideUpElements = document.querySelectorAll(".gsap-slide-up");
    slideUpElements.forEach((element) => {
      const delay = this.getDelayFromClasses(element);
      const animation = gsap.fromTo(
        element,
        {
          y: 100,
          opacity: 0,
        },
        {
          y: 0,
          opacity: 1,
          duration: 1,
          delay: delay,
          ease: "power2.out",
          scrollTrigger: {
            trigger: element,
            start: "top 80%",
            toggleActions: "play none none none",
          },
        },
      );
      this.animations.set(element, animation);
    });

    // Slide from top
    const slideDownElements = document.querySelectorAll(".gsap-slide-down");
    slideDownElements.forEach((element) => {
      const delay = this.getDelayFromClasses(element);
      const animation = gsap.fromTo(
        element,
        {
          y: -100,
          opacity: 0,
        },
        {
          y: 0,
          opacity: 1,
          duration: 1,
          delay: delay,
          ease: "power2.out",
          scrollTrigger: {
            trigger: element,
            start: "top 80%",
            toggleActions: "play none none none",
          },
        },
      );
      this.animations.set(element, animation);
    });
  }

  /**
   * Stagger animations for lists and grids
   */
  setupStaggerAnimations() {
    const staggerElements = document.querySelectorAll(".gsap-stagger");

    staggerElements.forEach((element) => {
      const children = element.children;

      if (children.length > 0) {
        const animation = gsap.fromTo(
          children,
          {
            opacity: 0,
            y: 50,
          },
          {
            opacity: 1,
            y: 0,
            duration: 0.8,
            stagger: 0.1,
            ease: "power2.out",
            scrollTrigger: {
              trigger: element,
              start: "top 80%",
              toggleActions: "play none none none",
            },
          },
        );

        this.animations.set(element, animation);
      }
    });
  }

  /**
   * Slow stagger animations for lists and grids
   */
  setupSlowStaggerAnimations() {
    const slowStaggerElements = document.querySelectorAll(".gsap-stagger-slow");

    slowStaggerElements.forEach((element) => {
      const children = element.children;

      if (children.length > 0) {
        const animation = gsap.fromTo(
          children,
          {
            opacity: 0,
            y: 50,
          },
          {
            opacity: 1,
            y: 0,
            duration: 1.2,
            stagger: 0.3,
            ease: "power2.out",
            scrollTrigger: {
              trigger: element,
              start: "top 80%",
              toggleActions: "play none none none",
            },
          },
        );

        this.animations.set(element, animation);
      }
    });
  }

  /**
   * Scale animations
   */
  setupScaleAnimations() {
    const scaleElements = document.querySelectorAll(".gsap-scale");

    scaleElements.forEach((element) => {
      const delay = this.getDelayFromClasses(element);
      const animation = gsap.fromTo(
        element,
        {
          scale: 0.8,
          opacity: 0,
        },
        {
          scale: 1,
          opacity: 1,
          duration: 1,
          delay: delay,
          ease: "back.out(1.7)",
          scrollTrigger: {
            trigger: element,
            start: "top 80%",
            toggleActions: "play none none none",
          },
        },
      );

      this.animations.set(element, animation);
    });
  }

  /**
   * Create custom animation
   */
  createCustomAnimation(
    element,
    fromProps,
    toProps,
    scrollTriggerOptions = {},
  ) {
    const defaultScrollTrigger = {
      trigger: element,
      start: "top 80%",
      toggleActions: "play none none reverse",
    };

    const animation = gsap.fromTo(element, fromProps, {
      ...toProps,
      scrollTrigger: {
        ...defaultScrollTrigger,
        ...scrollTriggerOptions,
      },
    });

    this.animations.set(element, animation);
    return animation;
  }

  /**
   * Scroll-scrubbed character reveal for [data-highlight-text] elements.
   *
   * @param {Document|Element} root Scope to search within (defaults to document).
   */
  initHighlightText(root = document) {
    if (!this.scrollMotionEnabled) {
      return;
    }

    if (
      typeof window.matchMedia === "function" &&
      window.matchMedia("(prefers-reduced-motion: reduce)").matches
    ) {
      return;
    }

    const scope = root instanceof Element ? root : document;
    const targets = scope.querySelectorAll(
      "[data-highlight-text]:not([data-highlight-initialized])",
    );

    targets.forEach((heading) => {
      heading.setAttribute("data-highlight-initialized", "true");
      heading.removeAttribute("aria-label");

      const srOnly = document.createElement("span");
      srOnly.className = "sr-only";
      srOnly.innerHTML = heading.innerHTML;
      heading.before(srOnly);
      heading.setAttribute("aria-hidden", "true");

      const scrollStart =
        heading.getAttribute("data-highlight-scroll-start") || "top 90%";
      const scrollEnd =
        heading.getAttribute("data-highlight-scroll-end") || "center 40%";
      const fadedValue = parseFloat(
        heading.getAttribute("data-highlight-fade") || "0.2",
      );
      const staggerValue = parseFloat(
        heading.getAttribute("data-highlight-stagger") || "0.1",
      );

      const split = new SplitText(heading, {
        type: "words,chars",
        autoSplit: true,
        aria: "none",
        onSplit(self) {
          const ctx = gsap.context(() => {
            const tl = gsap.timeline({
              scrollTrigger: {
                scrub: true,
                trigger: heading,
                start: scrollStart,
                end: scrollEnd,
              },
            });

            tl.from(self.chars, {
              autoAlpha: fadedValue,
              stagger: staggerValue,
              ease: "linear",
            });
          }, heading);

          return ctx;
        },
      });

      this.highlightSplits.push({ split, srOnly, heading });
    });
  }

  killHighlightText() {
    this.highlightSplits.forEach((entry) => {
      const split = entry.split ?? entry;

      if (typeof split.revert === "function") {
        split.revert();
      }

      entry.srOnly?.remove();
      entry.heading?.removeAttribute("aria-hidden");
    });
    this.highlightSplits = [];
  }

  /**
   * Refresh all animations (useful for dynamic content)
   */
  refresh(root = document) {
    if (this.scrollMotionEnabled) {
      this.initHighlightText(root);
      ScrollTrigger.refresh();
      console.log("[GSAP] Animations refreshed");
    }
  }

  /**
   * Kill all animations
   */
  killAll() {
    this.animations.forEach((animation) => {
      animation.kill();
    });
    this.animations.clear();
    this.killHighlightText();
    ScrollTrigger.killAll();
  }

  /**
   * Get animation for specific element
   */
  getAnimation(element) {
    return this.animations.get(element);
  }

  /**
   * Get delay value from CSS classes
   */
  getDelayFromClasses(element) {
    const classList = element.classList;

    // Check for delay classes (in seconds)
    if (classList.contains("gsap-delay-0")) return 0;
    if (classList.contains("gsap-delay-100")) return 0.1;
    if (classList.contains("gsap-delay-200")) return 0.2;
    if (classList.contains("gsap-delay-300")) return 0.3;
    if (classList.contains("gsap-delay-400")) return 0.4;
    if (classList.contains("gsap-delay-500")) return 0.5;
    if (classList.contains("gsap-delay-600")) return 0.6;
    if (classList.contains("gsap-delay-700")) return 0.7;
    if (classList.contains("gsap-delay-800")) return 0.8;
    if (classList.contains("gsap-delay-900")) return 0.9;
    if (classList.contains("gsap-delay-1000")) return 1.0;
    if (classList.contains("gsap-delay-1200")) return 1.2;
    if (classList.contains("gsap-delay-1500")) return 1.5;
    if (classList.contains("gsap-delay-2000")) return 2.0;
    if (classList.contains("gsap-delay-2500")) return 2.5;
    if (classList.contains("gsap-delay-3000")) return 3.0;

    return 0; // No delay
  }
}

// Export the class
export default GSAPAnimationSystem;
