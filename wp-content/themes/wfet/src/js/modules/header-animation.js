/**
 * Header scroll state
 *
 * At the top of the page the masthead is position:absolute (see SCSS).
 * After scrolling past the first viewport height, .past-hero applies: fixed bar + light theme styles.
 * Entering and leaving that state uses matching slide animations (see _header.scss).
 */

function initHeaderScrollBehavior() {
  const header = document.getElementById("masthead");

  if (!header) return;

  let exitAnimationEndHandler = null;

  function prefersReducedMotion() {
    return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  }

  function removeExitListener() {
    if (exitAnimationEndHandler) {
      header.removeEventListener("animationend", exitAnimationEndHandler);
      exitAnimationEndHandler = null;
    }
  }

  function isPastFirstFold() {
    return window.scrollY >= window.innerHeight;
  }

  function finishExitIfStillAboveFold() {
    if (window.scrollY >= window.innerHeight) {
      header.classList.remove("past-hero-exit");
      removeExitListener();
      return;
    }
    header.classList.remove("past-hero", "past-hero-exit");
    removeExitListener();
  }

  function updateHeaderState() {
    const past = isPastFirstFold();

    if (past) {
      removeExitListener();
      header.classList.remove("past-hero-exit");
      header.classList.add("past-hero");
      return;
    }

    if (!header.classList.contains("past-hero")) {
      return;
    }

    if (prefersReducedMotion()) {
      header.classList.remove("past-hero", "past-hero-exit");
      removeExitListener();
      return;
    }

    if (header.classList.contains("past-hero-exit")) {
      return;
    }

    header.classList.add("past-hero-exit");
    removeExitListener();
    exitAnimationEndHandler = (event) => {
      if (event.target !== header) return;
      if (event.animationName !== "masthead-past-hero-hide") return;
      finishExitIfStillAboveFold();
    };
    header.addEventListener("animationend", exitAnimationEndHandler);
  }

  window.addEventListener("scroll", updateHeaderState, { passive: true });
  window.addEventListener("resize", updateHeaderState);

  if (isPastFirstFold()) {
    header.classList.add("past-hero");
  } else {
    header.classList.remove("past-hero", "past-hero-exit");
  }
}

export { initHeaderScrollBehavior };
