/**
 * SOJ Core Modern - Main JavaScript Entry Point
 *
 * This file is ready for site-specific JavaScript code.
 * All core functionality is handled by core.js and its modules.
 *
 * @package SOJ_Core_Modern
 * @since 2.0.0
 * @author Son of Jesse
 */

// Import core functionality (this will initialize all modules)
import core from "./core.js";

// Import utilities if needed for site-specific code
import { utils, events, dom, log } from "./modules/utilities.js";

// Import animation systems
import AnimationSystem from "./modules/animations.js";
import GSAPAnimationSystem from "./modules/gsap-animations.js";

// Import GSAP and plugins so they are available globally (for draggable sliders, etc.)
import { gsap } from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { Draggable } from "gsap/Draggable";
import { InertiaPlugin } from "gsap/InertiaPlugin";
import { SplitText } from "gsap/SplitText";

// Import header animation functionality
import { initHeaderScrollBehavior } from "./modules/header-animation.js";
import { initDeferredPictures } from "./modules/deferred-pictures.js";

// Barba.js page transitions have been disabled for this site

// Expose GSAP and plugins globally for block scripts like gallery-slider
window.SOJ_initDeferredPictures = initDeferredPictures;

window.gsap = gsap;
window.ScrollTrigger = ScrollTrigger;
window.Draggable = Draggable;
window.InertiaPlugin = InertiaPlugin;
window.SplitText = SplitText;
gsap.registerPlugin(ScrollTrigger, Draggable, InertiaPlugin, SplitText);

// Initialize animation systems
let animationSystem;
let gsapAnimationSystem;

document.addEventListener("DOMContentLoaded", function () {
  // Initialize scroll-triggered animations (CSS-based)
  animationSystem = new AnimationSystem();

  // Initialize GSAP animations
  gsapAnimationSystem = new GSAPAnimationSystem();

  // Make animation systems available globally for dynamic content
  window.SOJ_ANIMATIONS = animationSystem;
  window.SOJ_GSAP_ANIMATIONS = gsapAnimationSystem;

  initDeferredPictures(document);
});
