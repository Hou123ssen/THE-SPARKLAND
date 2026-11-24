import { Head } from "@inertiajs/react";
import { useEffect, useLayoutEffect, useRef } from "react";
import gsap from "gsap";
import { ArrowLeft, ArrowRight } from "lucide-react";
import Navbar from "../Components/Navbar.jsx";
import { COLLECTION_PRESENTATION, collectionPresentation } from "../Components/CollectionPresentationSlots";
import StorefrontProductCard from "../Components/StorefrontProductCard.jsx";
import MotionLink from "../Components/MotionLink.jsx";
import { MOTION, addScrollTrack, collectionTransitionName, markCollectionTransition, matchesCollectionTransition, reducedMotion, scrollScene, useScrubScenes, useStorefrontEntrance } from "../Components/StorefrontMotion.js";

const display = "font-[Impact,Haettenschweiler,'Arial_Narrow_Bold',sans-serif] font-normal uppercase leading-[.8] tracking-[-.055em]";

function Pages({ pagination }) {
  if (!pagination || pagination.last_page <= 1) return null;
  const numbered = pagination.links.filter((item) => /^\d+$/.test(item.label));
  const previous = pagination.links.find((item) => item.label.includes("Previous"));
  const next = pagination.links.find((item) => item.label.includes("Next"));
  return <nav className="mt-7 flex flex-wrap items-center gap-2 border-t border-[#111111]/15 pt-4" aria-label="Collection catalogue pagination">{previous?.url ? <MotionLink href={previous.url} className="motion-press inline-flex h-11 w-11 items-center justify-center border border-[#111111]/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057c8] focus-visible:ring-offset-2" aria-label="Previous catalogue page"><ArrowLeft size={17} aria-hidden="true" /></MotionLink> : null}{numbered.map((item) => item.url ? <MotionLink key={item.label} href={item.url} aria-current={item.active ? "page" : undefined} className={`motion-press inline-flex h-11 min-w-11 items-center justify-center px-3 text-[.6rem] font-bold tracking-[.12em] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057c8] focus-visible:ring-offset-2 ${item.active ? "bg-[#0057c8] text-white" : "border border-[#111111]/20"}`}>{item.label.padStart(2, "0")}</MotionLink> : null)}{next?.url ? <MotionLink href={next.url} className="motion-press ml-auto inline-flex min-h-11 items-center gap-3 px-2 text-[.58rem] font-bold uppercase tracking-[.14em] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057c8] focus-visible:ring-offset-2">Next <ArrowRight size={16} aria-hidden="true" /></MotionLink> : null}</nav>;
}

export default function CollectionShow({ collection, products = [], pagination }) {
  const pageRef = useRef(null);
  const heroRef = useRef(null);
  // Temporary M3C presentation mapping: collection IDs begin at one, while
  // campaign slots are zero-indexed. A future CMS will replace this mapping.
  const temporaryPresentationIndex = Math.max(0, (collection?.id ?? 1) - 1);
  const hero = collectionPresentation(temporaryPresentationIndex) ?? COLLECTION_PRESENTATION.hero;
  const sharedHero = matchesCollectionTransition(collection);
  useStorefrontEntrance(pageRef, !sharedHero);
  useScrubScenes(pageRef, `${collection?.id}:${pagination?.current_page ?? 1}:${products.map((product) => product.id).join(",")}`, (page, mobile) => {
    const heroSection = page.querySelector('[aria-labelledby="collection-heading"]');
    const story = page.querySelector('[aria-labelledby="story-heading"]');
    const catalogue = page.querySelector("#collection-catalogue");
    const closing = page.querySelector('[aria-labelledby="closing-heading"]');
    if (heroSection) {
      const timeline = scrollScene(heroSection, { start: "top 76px", end: "bottom 12%" });
      timeline.to(heroSection.querySelector("#collection-heading"), { y: mobile ? -32 : -72, ease: "none", duration: 1 }, 0);
      timeline.to(heroRef.current, { y: mobile ? -18 : -35, scale: mobile ? 1.1 : 1.14, ease: "none", duration: 1 }, 0);
    }
    if (story) {
      const timeline = scrollScene(story, { start: "top 90%", end: "bottom 10%", scrub: MOTION.scrollScrubEditorial });
      story.querySelectorAll("[data-collection-story]").forEach((item, index) => {
        addScrollTrack(timeline, item, { y: (mobile ? 18 : 42) + index * 6, opacity: 0.7 }, { y: mobile ? -14 : -35, opacity: 0.88 });
      });
    }
    if (catalogue) {
      const timeline = scrollScene(catalogue, { start: "top 94%", end: "bottom 8%" });
      addScrollTrack(timeline, catalogue.querySelector("#catalogue-heading"), { y: mobile ? 20 : 42, opacity: 0.7 }, { y: mobile ? -20 : -45, opacity: 0.88 });
      catalogue.querySelectorAll("[data-motion-grid-card]").forEach((card, index) => {
        addScrollTrack(timeline, card, { y: (mobile ? 28 : 50) + (index % 2) * 9, scale: 0.985, opacity: 0.78 }, { y: mobile ? -16 : -38, scale: 0.985, opacity: 0.9 }, { enterEnd: 0.32, exitStart: 0.75 });
        addScrollTrack(timeline, card.querySelector("[data-product-card-media]"), { y: mobile ? 5 : 9 }, { y: mobile ? -5 : -9 }, { enterEnd: 0.32, exitStart: 0.75 });
      });
    }
    if (closing) {
      const timeline = scrollScene(closing, { start: "top 90%", end: "bottom 10%", scrub: MOTION.scrollScrubEditorial });
      addScrollTrack(timeline, closing.querySelector("#closing-heading"), { y: mobile ? 32 : 64, opacity: 0.72 }, { y: mobile ? -24 : -56, opacity: 0.9 });
      addScrollTrack(timeline, closing.querySelector("img"), { y: mobile ? 10 : 18, scale: 1.12 }, { y: mobile ? -7 : -8, scale: 1.06 });
    }
  });
  useLayoutEffect(() => {
    if (reducedMotion()) return undefined;
    const context = gsap.context(() => {
      gsap.timeline({ delay: sharedHero ? 0.38 : 0.08, defaults: { ease: "power3.out" } })
        .from("[data-collection-identity]", { autoAlpha: 0, y: 30, duration: 0.48, stagger: 0.075 })
        .from("[data-collection-story]", { autoAlpha: 0, y: 22, duration: 0.42, stagger: 0.07 }, "-=0.26");
    }, pageRef);
    return () => context.revert();
  }, [collection?.id, sharedHero]);
  useEffect(() => {
    const onCollectionReturn = (event) => {
      const link = event.target.closest('a[href="/collections"]');
      if (!link || reducedMotion() || !heroRef.current) return;
      markCollectionTransition(collection);
      heroRef.current.style.viewTransitionName = collectionTransitionName(collection);
      gsap.to(pageRef.current?.querySelectorAll("[data-collection-identity], [data-collection-story]"), {
        autoAlpha: 0,
        y: -14,
        duration: 0.22,
        ease: "power3.in",
        overwrite: true,
      });
    };
    document.addEventListener("click", onCollectionReturn, true);
    return () => document.removeEventListener("click", onCollectionReturn, true);
  }, [collection]);
  return <><Head><title>{collection ? `${collection.name} | The Sparkland` : "Collections | The Sparkland"}</title>{collection?.description ? <meta name="description" content={collection.description} /> : null}</Head><Navbar /><main ref={pageRef} className="overflow-hidden bg-white pt-[76px] text-[#111111]">
    <section className="mx-auto max-w-[1440px] px-4 py-4 sm:px-8 lg:px-12 xl:px-14" aria-labelledby="collection-heading"><div className="relative overflow-hidden bg-[#111111] text-white lg:grid lg:min-h-[34rem] lg:grid-cols-[42%_58%]"><div className="absolute inset-0 z-10 flex min-h-[27rem] flex-col justify-between p-5 sm:min-h-[31rem] sm:p-8 lg:relative lg:min-h-0 lg:bg-[#111111] lg:p-10"><div className="max-w-[9ch] lg:max-w-none"><p data-collection-identity className="text-[.58rem] font-bold uppercase tracking-[.19em] text-[#79aafb]">The Sparkland / Collection</p><h1 data-collection-identity id="collection-heading" className={`${display} mt-3 text-[clamp(3.15rem,15vw,8.2rem)] lg:mt-5 lg:max-w-[13ch] lg:text-[clamp(3.5rem,17vw,8.2rem)]`}>{collection?.name}</h1></div><a data-collection-identity href="#collection-catalogue" className="motion-nav-link inline-flex min-h-11 items-center gap-3 self-start text-[.58rem] font-bold uppercase tracking-[.16em] text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-4 focus-visible:ring-offset-[#111111]">View frames <ArrowRight size={16} aria-hidden="true" /></a></div><div className="relative min-h-[27rem] overflow-hidden bg-[#c7c8c5] sm:min-h-[31rem] lg:min-h-0"><img ref={heroRef} src={hero.src} alt="" className="collection-shared-media absolute inset-0 h-full w-full object-cover object-[18%_center] lg:object-center" style={sharedHero ? { viewTransitionName: collectionTransitionName(collection) } : undefined} fetchPriority="high" /><div className="absolute inset-0 bg-[linear-gradient(to_bottom,rgba(17,17,17,.38),rgba(17,17,17,.06)_42%,rgba(17,17,17,.75))] lg:hidden" aria-hidden="true" /><div className="absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-black/45 to-transparent lg:block" aria-hidden="true" /></div></div></section>
    <section className="border-y border-[#111111]/12 bg-[#f2f2ef]" aria-labelledby="story-heading"><div className="mx-auto grid max-w-[1440px] gap-3 px-5 py-6 sm:px-8 sm:py-10 lg:grid-cols-[25%_minmax(0,1fr)_22%] lg:gap-5 lg:px-12 xl:px-14"><h2 data-collection-story id="story-heading" className="text-[.56rem] font-bold uppercase tracking-[.17em] text-[#0057c8]">Collection note</h2><div>{collection?.description ? <p data-collection-story className="max-w-[38rem] text-[.78rem] leading-[1.65] text-[#111111]/68 lg:text-[.8rem] lg:leading-[1.7]">{collection.description}</p> : null}</div><p data-collection-story className="self-end text-[.56rem] font-bold uppercase tracking-[.16em] text-[#111111]/45">The Sparkland / Collection</p></div></section>
    <section id="collection-catalogue" className="mx-auto max-w-[1440px] px-4 pb-10 pt-4 sm:px-8 lg:px-12 xl:px-14" aria-labelledby="catalogue-heading"><div className="mb-4 flex items-end justify-between gap-4 border-b border-[#111111]/15 pb-3"><h2 id="catalogue-heading" className={`${display} text-[clamp(1.85rem,8vw,3.6rem)]`}>The collection</h2><p className="pb-1 text-right text-[.55rem] font-bold uppercase tracking-[.14em] text-[#111111]/48">{pagination?.total ?? products.length} frames</p></div>{products.length ? <div className="grid grid-cols-2 gap-2 sm:gap-4 lg:grid-cols-4">{products.map((product, index) => <div data-motion-grid-card key={product.id}><StorefrontProductCard product={product} eager={index < 2} compactMobile /></div>)}</div> : <div className="flex min-h-[20rem] flex-col justify-center bg-[#f2f2ef] p-6 sm:p-10"><p className="text-[.56rem] font-bold uppercase tracking-[.17em] text-[#0057c8]">Collection catalogue</p><h3 className={`${display} mt-4 max-w-[12ch] text-[clamp(2.5rem,7vw,5rem)]`}>No frames in this collection.</h3></div>}<Pages pagination={pagination} /></section>
    <section className="relative isolate overflow-hidden bg-[#111111] text-white md:grid md:grid-cols-[45%_55%]" aria-labelledby="closing-heading"><div className="relative z-10 flex min-h-[25rem] flex-col justify-between bg-[linear-gradient(to_bottom,rgba(17,17,17,.4),rgba(17,17,17,.92))] p-6 sm:min-h-[27rem] sm:p-8 md:min-h-[20rem] md:bg-[#111111] lg:p-12"><div><p className="text-[.56rem] font-bold uppercase tracking-[.18em] text-[#79aafb]">The Sparkland</p><h2 id="closing-heading" className={`${display} mt-5 max-w-[10ch] text-[clamp(2.7rem,10vw,5.5rem)]`}>Different perspectives. Same vision.</h2></div><div className="flex flex-col items-start gap-3 sm:flex-row sm:items-end sm:justify-between sm:gap-4"><p className="text-[.58rem] font-bold uppercase tracking-[.16em] text-white/70">Collection / {collection?.name}</p><MotionLink href="/collections" className="motion-press inline-flex min-h-11 items-center gap-3 border border-white/45 px-4 text-[.58rem] font-bold uppercase tracking-[.14em] text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-4 focus-visible:ring-offset-[#111111] md:border-0 md:px-0">Explore all collections <ArrowRight size={16} aria-hidden="true" /></MotionLink></div></div><div className="absolute inset-0 z-0 min-h-[25rem] overflow-hidden sm:min-h-[27rem] md:relative md:z-auto md:min-h-[20rem]"><img src={COLLECTION_PRESENTATION.ending.monochrome} alt="" loading="lazy" className="absolute inset-0 h-full w-full object-cover opacity-80" /><div className="absolute inset-0 bg-gradient-to-r from-[#0057c8]/45 to-transparent" aria-hidden="true" /></div></section>
  </main></>;
}
