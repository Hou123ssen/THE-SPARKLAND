import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { useLayoutEffect } from "react";

gsap.registerPlugin(ScrollTrigger);

export const MOTION = Object.freeze({
  micro: 0.18,
  control: 0.4,
  interface: 0.44,
  panel: 0.58,
  route: 0.58,
  shared: 0.72,
  ease: "power3.out",
  scrollScrub: 0.7,
  scrollScrubEditorial: 0.9,
});

export function reducedMotion() {
  return typeof window !== "undefined" && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
}

let pendingCollection = null;
export function collectionTransitionName(collection) {
  // One Collection card can transition at a time. A stable shared name keeps
  // the CSS handoff generic rather than coupling it to individual slugs.
  return "sparkland-collection-media";
}
export function markCollectionTransition(collection) {
  pendingCollection = { slug: collection.slug, at: performance.now() };
  document.documentElement.dataset.sparklandSharedCollection = collection.slug;
}
export function matchesCollectionTransition(collection) {
  return pendingCollection?.slug === collection?.slug && performance.now() - pendingCollection.at < 4000;
}
export function isActiveCollectionTransition(collection) {
  return typeof document !== "undefined" && document.documentElement.dataset.sparklandSharedCollection === collection?.slug;
}
export function clearCollectionTransition() {
  pendingCollection = null;
  if (typeof document !== "undefined") delete document.documentElement.dataset.sparklandSharedCollection;
}

export function useStorefrontEntrance(ref, enabled = true) {
  useLayoutEffect(() => {
    if (!enabled || !ref.current || reducedMotion()) return undefined;
    const context = gsap.context(() => {
      gsap.fromTo(ref.current, { autoAlpha: 0, y: 30, scale: 1.01 }, {
        autoAlpha: 1, y: 0, scale: 1, duration: MOTION.route, ease: MOTION.ease,
        clearProps: "opacity,visibility,transform",
      });
    }, ref);
    return () => context.revert();
  }, [ref, enabled]);
}

// A track enters, remains still while it is read, then exits. All tweens use
// linear easing because ScrollTrigger owns their progress in both directions.
export function addScrollTrack(timeline, element, enter, exit, { enterEnd = 0.28, exitStart = 0.72 } = {}) {
  if (!element) return;
  timeline.fromTo(element, enter, { ...Object.fromEntries(Object.keys(enter).map((key) => [key, key === "opacity" ? 1 : key.startsWith("scale") || key === "scale" ? 1 : 0])), ease: "none", duration: enterEnd, immediateRender: false }, 0);
  timeline.to(element, { ...exit, ease: "none", duration: 1 - exitStart }, exitStart);
}

export function scrollScene(trigger, { start = "top 88%", end = "bottom 12%", scrub = MOTION.scrollScrub } = {}) {
  return gsap.timeline({
    defaults: { ease: "none" },
    scrollTrigger: { trigger, start, end, scrub, invalidateOnRefresh: true },
  });
}

export function useScrubScenes(ref, key, build) {
  useLayoutEffect(() => {
    if (!ref.current || reducedMotion()) return undefined;
    const media = gsap.matchMedia();
    media.add({ mobile: "(max-width: 767px)", desktop: "(min-width: 768px)" }, (conditions) => {
      const context = gsap.context(() => build(ref.current, conditions.conditions.mobile), ref);
      return () => context.revert();
    });
    const frame = requestAnimationFrame(() => ScrollTrigger.refresh());
    const images = [...ref.current.querySelectorAll("img")].filter((image) => !image.complete);
    let active = true;
    const refresh = () => { if (active) ScrollTrigger.refresh(); };
    images.forEach((image) => image.addEventListener("load", refresh, { once: true }));
    document.fonts?.ready.then(refresh);
    return () => {
      active = false;
      cancelAnimationFrame(frame);
      images.forEach((image) => image.removeEventListener("load", refresh));
      media.revert();
    };
  }, [ref, key]);
}
