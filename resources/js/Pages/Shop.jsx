import { Head, router } from "@inertiajs/react";
import { useEffect, useLayoutEffect, useRef, useState } from "react";
import gsap from "gsap";
import { ArrowLeft, ArrowRight, ArrowUpRight, SlidersHorizontal } from "lucide-react";
import Navbar from "../Components/Navbar.jsx";
import { SHOP_PRESENTATION_CONTENT, SHOP_PRESENTATION_SLOTS } from "../Components/ShopPresentationSlots.jsx";
import StorefrontProductCard from "../Components/StorefrontProductCard.jsx";
import MotionLink from "../Components/MotionLink.jsx";
import { MOTION, addScrollTrack, reducedMotion, scrollScene, useScrubScenes, useStorefrontEntrance } from "../Components/StorefrontMotion.js";

function FilterSelect({ name, label, options, value }) {
  if (!options?.length) return null;

  return (
    <label className="flex min-w-[9.3rem] flex-1 items-center gap-2 rounded-full bg-white px-4 py-3 text-[0.59rem] font-semibold uppercase tracking-[0.15em] text-[#111111] ring-1 ring-[#111111]/10 focus-within:ring-[#0057C8]">
      <span className="shrink-0 text-[#111111]/62">{label}</span>
      <select name={name} defaultValue={value || ""} className="min-w-0 flex-1 appearance-none bg-transparent text-right text-[0.58rem] font-bold uppercase tracking-[0.1em] outline-none" aria-label={`Filter by ${label.toLowerCase()}`}>
        <option value="">All</option>
        {options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
      </select>
      <span aria-hidden="true" className="text-[#0057C8]">+</span>
    </label>
  );
}

function CampaignImage({ slot, imageClassName = "", eager = false }) {
  return <img src={slot.src} alt={slot.alt} className={`h-full w-full ${imageClassName}`} loading={eager ? "eager" : "lazy"} />;
}

function TextLink({ children, href = "#catalogue", inverted = false }) {
  return (
    <MotionLink href={href} className={`motion-nav-link inline-flex items-center gap-3 text-[0.61rem] font-bold uppercase tracking-[0.16em] transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057C8] focus-visible:ring-offset-4 ${inverted ? "text-white hover:text-[#78a9f7]" : "text-[#111111] hover:text-[#0057C8]"}`}>
      {children}<ArrowUpRight aria-hidden="true" size={15} strokeWidth={1.7} />
    </MotionLink>
  );
}

function RemainingProducts({ products }) {
  const [expanded, setExpanded] = useState(false);

  if (products.length === 0) return null;

  return (
    <div className="mt-4">
      <div id="remaining-page-products" className={`grid overflow-hidden transition-[grid-template-rows,opacity] duration-300 ease-out motion-reduce:transition-none ${expanded ? "mb-4 grid-rows-[1fr] opacity-100" : "grid-rows-[0fr] opacity-0"}`} inert={!expanded ? true : undefined}><div className="grid min-h-0 grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">{products.map((product) => <StorefrontProductCard key={product.id} product={product} />)}</div></div>
      <button type="button" aria-expanded={expanded} aria-controls="remaining-page-products" onClick={() => setExpanded(!expanded)} className="motion-press text-[0.6rem] font-bold uppercase tracking-[0.14em] text-[#0057C8] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057C8] focus-visible:ring-offset-2">{expanded ? "View less" : `View ${products.length} more on this page`}</button>
    </div>
  );
}

function MobileFilterSheet({ open, onClose, facets, filters, sort, clearHref, hasFilters, onSubmit }) {
  const panelRef = useRef(null);
  const backdropRef = useRef(null);
  const firstRef = useRef(null);
  const timelineRef = useRef(null);
  useLayoutEffect(() => {
    if (!panelRef.current || !backdropRef.current) return undefined;
    const context = gsap.context(() => {
      gsap.set(panelRef.current, { yPercent: 100 });
      const page = document.querySelector("main");
      timelineRef.current = gsap.timeline({ paused: true })
        .to(backdropRef.current, { opacity: 1, duration: MOTION.control, ease: MOTION.ease }, 0)
        .to(page, { scale: 0.988, y: -8, duration: MOTION.panel, ease: MOTION.ease }, 0)
        .to(panelRef.current, { yPercent: 0, duration: MOTION.panel, ease: MOTION.ease }, 0)
        .fromTo("[data-filter-sheet-item]", { y: 20, opacity: 0 }, { y: 0, opacity: 1, stagger: 0.055, duration: MOTION.control, ease: MOTION.ease }, 0.18);
    }, panelRef.current);
    return () => { timelineRef.current?.kill(); context.revert(); };
  }, []);
  useLayoutEffect(() => {
    if (reducedMotion()) {
      timelineRef.current?.progress(open ? 1 : 0).pause();
    } else if (open) timelineRef.current?.play();
    else timelineRef.current?.reverse();
  }, [open]);
  useEffect(() => {
    if (!open) return undefined;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    firstRef.current?.focus();
    const onKey = (event) => {
      if (event.key === "Escape") onClose();
      if (event.key === "Tab" && panelRef.current) {
        const focusable = [...panelRef.current.querySelectorAll("button,select,a")].filter((item) => !item.disabled);
        if (event.shiftKey && document.activeElement === focusable[0]) { event.preventDefault(); focusable.at(-1)?.focus(); }
        else if (!event.shiftKey && document.activeElement === focusable.at(-1)) { event.preventDefault(); focusable[0]?.focus(); }
      }
    };
    document.addEventListener("keydown", onKey);
    return () => { document.body.style.overflow = previousOverflow; document.removeEventListener("keydown", onKey); };
  }, [open, onClose]);
  return <div className={`fixed inset-0 z-[60] lg:hidden ${open ? "pointer-events-auto" : "pointer-events-none"}`} aria-hidden={!open}>
    <button ref={backdropRef} type="button" aria-label="Close filters" tabIndex={open ? 0 : -1} onClick={onClose} className="absolute inset-0 h-full w-full bg-[#111111]/45" style={{ opacity: 0 }} />
    <div ref={panelRef} role="dialog" aria-modal="true" aria-label="Shop filters and sort" inert={!open ? "" : undefined} className="absolute inset-x-0 bottom-0 max-h-[min(85svh,44rem)] overflow-y-auto rounded-t-[18px] bg-[#f3f3f0] px-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] pt-5 shadow-xl">
      <div data-filter-sheet-item className="mb-5 flex items-center justify-between"><p className="text-[.65rem] font-bold uppercase tracking-[.18em] text-[#0057C8]">Refine frames</p><button ref={firstRef} type="button" onClick={onClose} className="motion-press min-h-11 px-3 text-[.6rem] font-bold uppercase tracking-[.14em] focus-visible:ring-2 focus-visible:ring-[#0057C8]">Close</button></div>
      <form action="/shop" method="get" onSubmit={onSubmit} className="grid gap-3">
        <div data-filter-sheet-item><FilterSelect name="shape" label="Shape" options={facets.shape} value={filters.shape} /></div>
        <div data-filter-sheet-item><FilterSelect name="colour" label="Colour" options={facets.colour} value={filters.colour} /></div>
        <div data-filter-sheet-item><FilterSelect name="material" label="Material" options={facets.material} value={filters.material} /></div>
        <div data-filter-sheet-item><FilterSelect name="collection" label="Collection" options={facets.collection} value={filters.collection} /></div>
        <label data-filter-sheet-item className="flex min-h-11 items-center gap-2 rounded-full bg-white px-4 text-[.59rem] font-semibold uppercase tracking-[.14em] ring-1 ring-[#111111]/10 focus-within:ring-[#0057C8]"><span>Sort by</span><select name="sort" defaultValue={sort} className="min-w-0 flex-1 appearance-none bg-transparent text-right text-[.58rem] font-bold uppercase outline-none" aria-label="Sort products"><option value="featured">Featured</option><option value="newest">Newest</option></select></label>
        <div data-filter-sheet-item className="mt-2 flex items-center gap-4"><button type="submit" className="motion-press min-h-12 flex-1 rounded-full bg-[#0057C8] px-6 text-[.6rem] font-bold uppercase tracking-[.16em] text-white focus-visible:ring-2 focus-visible:ring-[#0057C8] focus-visible:ring-offset-2">Apply</button>{hasFilters && <MotionLink href={clearHref} className="motion-press text-[.6rem] font-bold uppercase tracking-[.14em] text-[#0057C8]">Clear</MotionLink>}</div>
      </form>
    </div>
  </div>;
}

export default function Shop({ products, facets, filters, sort, pagination }) {
  const [filterOpen, setFilterOpen] = useState(false);
  const filterButtonRef = useRef(null);
  const pageRef = useRef(null);
  useStorefrontEntrance(pageRef);
  useScrubScenes(pageRef, `${pagination.current_page}:${products.map((product) => product.id).join(",")}`, (page, mobile) => {
    const hero = page.querySelector('[aria-labelledby="shop-heading"]');
    const catalogue = page.querySelector("#catalogue");
    if (hero) {
      const timeline = scrollScene(hero, { start: "top 76px", end: "bottom 15%" });
      timeline.to(hero.querySelector("#shop-heading"), { y: mobile ? -28 : -68, ease: "none", duration: 1 }, 0);
      timeline.to(hero.querySelector("[data-shop-hero-copy]"), { y: mobile ? -20 : -46, ease: "none", duration: 1 }, 0);
      timeline.to(hero.querySelector("img"), { y: mobile ? -20 : -52, scale: mobile ? 1.025 : 1.045, ease: "none", duration: 1 }, 0);
      addScrollTrack(timeline, page.querySelector('[aria-label="Catalogue controls"]'), { y: mobile ? 12 : 24, opacity: 0.9 }, { y: mobile ? -10 : -22, opacity: 0.9 }, { enterEnd: 0.2, exitStart: 0.78 });
    }
    if (catalogue) {
      const timeline = scrollScene(catalogue, { start: "top 94%", end: "bottom 8%" });
      addScrollTrack(timeline, catalogue.querySelector("#catalogue-heading"), { y: mobile ? 18 : 30, opacity: 0.6 }, { y: mobile ? -18 : -38, opacity: 0.8 });
      catalogue.querySelectorAll("[data-motion-grid-card]").forEach((card, index) => {
        addScrollTrack(timeline, card, { y: (mobile ? 34 : 60) + (index % 2) * 12, scale: 0.985, opacity: 0.72 }, { y: mobile ? -20 : -45, scale: 0.985, opacity: 0.85 }, { enterEnd: 0.3, exitStart: 0.75 });
        addScrollTrack(timeline, card.querySelector("[data-product-card-media]"), { y: mobile ? 6 : 10 }, { y: mobile ? -6 : -10 }, { enterEnd: 0.3, exitStart: 0.75 });
      });
    }
    page.querySelectorAll("[data-shop-scroll-scene]").forEach((section) => {
      const timeline = scrollScene(section, { start: "top 90%", end: "bottom 10%", scrub: MOTION.scrollScrubEditorial });
      addScrollTrack(timeline, section.querySelector("h2")?.parentElement, { y: mobile ? 24 : 48, opacity: 0.7 }, { y: mobile ? -18 : -42, opacity: 0.88 });
      if (section.getAttribute("aria-labelledby") === "editorial-heading") {
        addScrollTrack(timeline, section.querySelector("a[href]"), { y: mobile ? 24 : 48, opacity: 0.7 }, { y: mobile ? -18 : -42, opacity: 0.88 });
      }
      section.querySelectorAll("img").forEach((image, index) => {
        addScrollTrack(timeline, image, { y: (mobile ? 10 : 18) * (index % 2 ? -1 : 1), scale: 1.12 }, { y: mobile ? -7 : -8, scale: 1.06 }, { enterEnd: 0.25, exitStart: 0.7 });
      });
    });
  });
  const closeFilters = () => { setFilterOpen(false); filterButtonRef.current?.focus(); };
  const submitFilters = (event) => {
    event.preventDefault();
    const entries = [...new FormData(event.currentTarget)].filter(([, value]) => value !== "");
    setFilterOpen(false);
    router.get("/shop", Object.fromEntries(entries), { viewTransition: !reducedMotion(), preserveState: false });
  };
  const initialProducts = products.slice(0, 5);
  const remainderProducts = products.slice(5);
  const hasFilters = Object.keys(filters).length > 0;
  const pageLinks = pagination.links.filter((link) => /^\d+$/.test(link.label));
  const previous = pagination.links.find((link) => link.label.includes("Previous"));
  const next = pagination.links.find((link) => link.label.includes("Next"));
  const clearHref = sort === "featured" ? "/shop" : `/shop?sort=${encodeURIComponent(sort)}`;

  return (
    <>
      <Head>
        <title>Shop | The Sparkland</title>
        <meta name="description" content="Browse The Sparkland eyewear catalogue." />
      </Head>

      <Navbar />

      <main ref={pageRef} className="overflow-hidden bg-white pt-[76px] text-[#111111]">
        <section className="mx-auto max-w-[1440px] px-5 py-4 sm:px-8 lg:px-12 xl:px-14" aria-labelledby="shop-heading">
          <div className="grid overflow-hidden bg-[#f3f3f0] lg:h-[475px] lg:grid-cols-[minmax(0,77fr)_minmax(0,23fr)] lg:gap-3">
            <div className="relative min-h-[480px] overflow-hidden bg-white lg:min-h-0">
              <CampaignImage slot={SHOP_PRESENTATION_SLOTS.hero} eager imageClassName="absolute inset-y-0 right-0 h-full w-full object-cover object-center lg:w-[76%]" />
              <div aria-hidden="true" className="absolute inset-y-0 left-0 w-[75%] bg-gradient-to-r from-white via-white/95 to-transparent lg:w-[58%]" />
              <div className="relative z-10 flex h-full min-h-[480px] flex-col justify-between px-4 py-4 sm:px-6 lg:min-h-0 lg:px-0 lg:py-4">
                <div>
                  <p className="text-[0.59rem] font-bold uppercase tracking-[0.2em] text-[#0057C8]">{SHOP_PRESENTATION_CONTENT.hero.eyebrow}</p>
                  <h1 id="shop-heading" className="mt-4 w-fit font-headline text-[4.5rem] font-black uppercase leading-[0.78] tracking-[-0.06em] sm:text-[5.5rem] lg:text-[6.6rem]">{SHOP_PRESENTATION_CONTENT.hero.heading[0]}<br />{SHOP_PRESENTATION_CONTENT.hero.heading[1]}<br />{SHOP_PRESENTATION_CONTENT.hero.heading[2]}</h1>
                </div>
                <div data-shop-hero-copy><p className="max-w-[19rem] text-[0.75rem] leading-[1.5] text-[#111111]/68">{SHOP_PRESENTATION_CONTENT.hero.copy}</p><a href={SHOP_PRESENTATION_CONTENT.hero.cta.href} className="motion-press mt-4 inline-flex min-w-[11rem] items-center justify-between gap-7 rounded-full bg-[#0057C8] px-6 py-3.5 text-[0.61rem] font-bold uppercase tracking-[0.16em] text-white transition-colors hover:bg-[#0047a4] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057C8] focus-visible:ring-offset-4">{SHOP_PRESENTATION_CONTENT.hero.cta.label} <ArrowRight aria-hidden="true" size={17} strokeWidth={1.8} /></a></div>
              </div>
            </div>
            <aside className="grid grid-cols-[1fr_1fr] bg-[#f4f4f1] lg:grid-cols-1 lg:grid-rows-[65%_35%]" aria-label="Campaign feature">
              <div className="relative min-h-[15rem] overflow-hidden bg-[#bebebb] lg:min-h-0"><CampaignImage slot={SHOP_PRESENTATION_SLOTS.heroEditorial} eager imageClassName="absolute inset-0 h-full w-full object-cover object-[54%_center]" /></div>
              <div className="flex flex-col justify-center p-5"><p className="text-[0.55rem] font-bold uppercase tracking-[0.18em] text-[#0057C8]">{SHOP_PRESENTATION_CONTENT.heroEditorial.eyebrow}</p><h2 className="mt-3 font-headline text-[1.4rem] font-black uppercase leading-[0.95]">{SHOP_PRESENTATION_CONTENT.heroEditorial.heading[0]}<br />{SHOP_PRESENTATION_CONTENT.heroEditorial.heading[1]}</h2><p className="mt-2 max-w-[12rem] text-[0.68rem] leading-[1.5] text-[#111111]/58">{SHOP_PRESENTATION_CONTENT.heroEditorial.copy}</p><div className="mt-3"><TextLink href={SHOP_PRESENTATION_CONTENT.heroEditorial.cta.href}>{SHOP_PRESENTATION_CONTENT.heroEditorial.cta.label}</TextLink></div></div>
            </aside>
          </div>
        </section>

        <section className="mx-auto max-w-[1440px] px-5 pb-7 pt-1 sm:px-8 lg:px-12 xl:px-14" aria-label="Catalogue controls">
          <button ref={filterButtonRef} type="button" onClick={() => setFilterOpen(true)} className="motion-press flex min-h-12 w-full items-center justify-between rounded-full bg-[#f0f0ed] px-5 text-[.62rem] font-bold uppercase tracking-[.17em] text-[#111111] focus-visible:ring-2 focus-visible:ring-[#0057C8] lg:hidden"><span className="flex items-center gap-3"><SlidersHorizontal aria-hidden="true" size={17} /> Refine / Sort</span><span className="text-[#0057C8]">+</span></button>
          <form action="/shop" method="get" onSubmit={submitFilters} className="hidden rounded-[18px] bg-[#f0f0ed] p-4 lg:flex lg:items-center lg:gap-3 lg:p-3">
            <div className="flex items-center gap-3 px-1 lg:w-[8rem] lg:shrink-0"><span className="inline-flex h-10 w-10 items-center justify-center rounded-full bg-[#0057C8] text-white"><SlidersHorizontal aria-hidden="true" size={16} strokeWidth={1.8} /></span><p className="text-[0.62rem] font-bold uppercase tracking-[0.17em]">Filter by</p></div>
            <div className="mt-4 flex flex-wrap gap-3 lg:mt-0 lg:flex-1"><FilterSelect name="shape" label="Shape" options={facets.shape} value={filters.shape} /><FilterSelect name="colour" label="Colour" options={facets.colour} value={filters.colour} /><FilterSelect name="material" label="Material" options={facets.material} value={filters.material} /><FilterSelect name="collection" label="Collection" options={facets.collection} value={filters.collection} /></div>
            <div className="mt-4 flex items-center gap-3 lg:mt-0 lg:shrink-0"><label className="flex min-w-[12rem] flex-1 items-center gap-2 rounded-full bg-white px-5 py-3.5 text-[0.59rem] font-semibold uppercase tracking-[0.14em] ring-1 ring-[#111111]/10 focus-within:ring-[#0057C8] lg:flex-none"><span className="text-[#111111]/62">Sort by</span><select name="sort" defaultValue={sort} className="min-w-0 flex-1 appearance-none bg-transparent text-right text-[0.58rem] font-bold uppercase tracking-[0.1em] outline-none" aria-label="Sort products"><option value="featured">Featured</option><option value="newest">Newest</option></select></label><button type="submit" className="motion-press rounded-full bg-[#111111] px-6 py-3.5 text-[0.58rem] font-bold uppercase tracking-[0.15em] text-white transition-colors hover:bg-[#0057C8] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057C8] focus-visible:ring-offset-2">Apply</button>{hasFilters && <MotionLink href={clearHref} className="text-[0.56rem] font-bold uppercase tracking-[0.15em] text-[#0057C8] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057C8] focus-visible:ring-offset-2">Clear</MotionLink>}</div>
          </form>
          <MobileFilterSheet open={filterOpen} onClose={closeFilters} facets={facets} filters={filters} sort={sort} clearHref={clearHref} hasFilters={hasFilters} onSubmit={submitFilters} />
        </section>

        <section id="catalogue" className="mx-auto max-w-[1440px] px-5 pb-5 sm:px-8 lg:px-12 xl:px-14" aria-labelledby="catalogue-heading">
          <div className="mb-4 flex items-end justify-between pb-1"><div><h2 id="catalogue-heading" className="font-headline text-[1.85rem] font-black uppercase leading-[0.82] tracking-[-0.06em]">All frames</h2></div><p className="pb-1 text-[0.58rem] font-bold uppercase tracking-[0.16em] text-[#111111]/52">{initialProducts.length} initially shown / {pagination.total} total</p></div>
          {products.length === 0 ? (
            <div className="flex min-h-[28rem] flex-col items-start justify-center bg-[#f4f4f1] p-8"><h3 className="font-headline text-[clamp(2.4rem,4vw,4.5rem)] font-black uppercase leading-[0.84] tracking-[-0.055em]">No frames match<br />this view.</h3><MotionLink href={clearHref} className="motion-press mt-8 rounded-full bg-[#0057C8] px-5 py-3 text-[0.6rem] font-bold uppercase tracking-[0.16em] text-white">Clear filters</MotionLink></div>
          ) : (
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,36fr)_minmax(0,64fr)]"><div data-motion-grid-card><StorefrontProductCard product={initialProducts[0]} kind="anchor" eager /></div><div className="grid grid-cols-1 gap-4 sm:grid-cols-2">{initialProducts.slice(1).map((product, index) => <div data-motion-grid-card key={product.id}><StorefrontProductCard product={product} eager={index < 2} /></div>)}</div></div>
          )}
          <RemainingProducts key={products.map((product) => product.id).join(",")} products={remainderProducts} />
          {pagination.last_page > 1 && <nav className="mt-4 flex items-center gap-3 pt-2" aria-label="Catalogue pagination">{previous?.url && <MotionLink href={previous.url} className="motion-press inline-flex h-10 w-10 items-center justify-center rounded-full border border-[#111111]/18 hover:border-[#0057C8] hover:text-[#0057C8]" aria-label="Previous catalogue page"><ArrowLeft aria-hidden="true" size={16} /></MotionLink>}{pageLinks.map((link) => <MotionLink key={link.label} href={link.url || "#"} aria-current={link.active ? "page" : undefined} className={`motion-press inline-flex h-10 min-w-10 items-center justify-center rounded-full px-3 text-[0.62rem] font-bold tracking-[0.12em] ${link.active ? "bg-[#0057C8] text-white" : "border border-[#111111]/18 hover:border-[#0057C8] hover:text-[#0057C8]"}`}>{link.label.padStart(2, "0")}</MotionLink>)}{next?.url && <MotionLink href={next.url} className="motion-press ml-auto inline-flex items-center gap-3 text-[0.6rem] font-bold uppercase tracking-[0.16em] hover:text-[#0057C8]">Next page <ArrowRight aria-hidden="true" size={16} /></MotionLink>}</nav>}
        </section>

        <section data-shop-scroll-scene className="border-y border-[#111111]/12 bg-[#f1f1ee] py-4" aria-labelledby="collection-heading">
          <div className="mx-auto grid max-w-[1440px] grid-cols-1 gap-5 px-5 sm:px-8 lg:grid-cols-[minmax(0,22fr)_minmax(0,60fr)_minmax(0,18fr)] lg:px-12 xl:px-14">
            <div className="relative z-10 flex min-h-[18rem] flex-col justify-between py-4 lg:min-h-[320px]"><div><p className="text-[0.58rem] font-bold uppercase tracking-[0.18em] text-[#0057C8]">{SHOP_PRESENTATION_CONTENT.collection.eyebrow}</p><h2 id="collection-heading" className="mt-4 font-headline text-[3rem] font-black uppercase leading-[0.76] tracking-[-0.07em]">{SHOP_PRESENTATION_CONTENT.collection.heading[0]}<br />{SHOP_PRESENTATION_CONTENT.collection.heading[1]}</h2><p className="mt-4 max-w-[13rem] text-[0.7rem] leading-[1.7] text-[#111111]/62">{SHOP_PRESENTATION_CONTENT.collection.copy}</p></div><MotionLink href={SHOP_PRESENTATION_CONTENT.collection.cta.href} className="motion-press inline-flex w-fit items-center gap-4 rounded-full bg-[#0057C8] px-5 py-3 text-[0.59rem] font-bold uppercase tracking-[0.15em] text-white">{SHOP_PRESENTATION_CONTENT.collection.cta.label} <ArrowUpRight aria-hidden="true" size={14} /></MotionLink></div>
            <div className="relative min-h-[20rem] overflow-hidden bg-[#cecdc9]"><span className="absolute left-6 top-6 z-10 text-[0.57rem] font-bold uppercase tracking-[0.17em] text-white/80">{SHOP_PRESENTATION_CONTENT.collection.imageLabel}</span><CampaignImage slot={SHOP_PRESENTATION_SLOTS.featuredCollection} imageClassName="absolute inset-0 h-full w-full object-cover object-[52%_center]" /></div>
            <div className="grid min-h-[20rem] grid-rows-2 gap-2"><div className="relative overflow-hidden bg-[#d9d8d5]"><span className="absolute left-5 top-5 z-10 text-[0.55rem] font-bold uppercase tracking-[0.16em] text-white/75">{SHOP_PRESENTATION_CONTENT.collection.detailLabel}</span><CampaignImage slot={SHOP_PRESENTATION_SLOTS.detail[0]} imageClassName="absolute inset-0 h-full w-full object-cover" /></div><div className="flex flex-col justify-between bg-[#111111] p-5 text-white"><div><p className="text-[0.56rem] font-bold uppercase tracking-[0.17em] text-white/52">{SHOP_PRESENTATION_CONTENT.collection.panelEyebrow}</p><p className="mt-3 max-w-[15rem] font-headline text-[1.5rem] font-black uppercase leading-[0.82] tracking-[-0.055em]">{SHOP_PRESENTATION_CONTENT.collection.panelHeading[0]}<br />{SHOP_PRESENTATION_CONTENT.collection.panelHeading[1]}</p></div><TextLink inverted href={SHOP_PRESENTATION_CONTENT.collection.panelCta.href}>{SHOP_PRESENTATION_CONTENT.collection.panelCta.label}</TextLink></div></div>
          </div>
        </section>

        <section data-shop-scroll-scene className="mx-auto max-w-[1440px] px-5 py-4 sm:px-8 lg:px-12 xl:px-14" aria-labelledby="detail-heading">
          <div className="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,22fr)_minmax(0,54fr)_minmax(0,24fr)]">
            <div className="py-4"><p className="text-[0.58rem] font-bold uppercase tracking-[0.18em] text-[#0057C8]">{SHOP_PRESENTATION_CONTENT.detail.eyebrow}</p><h2 id="detail-heading" className="mt-4 font-headline text-[2.25rem] font-black uppercase leading-[0.76] tracking-[-0.07em]">{SHOP_PRESENTATION_CONTENT.detail.heading[0]}<br />{SHOP_PRESENTATION_CONTENT.detail.heading[1]}</h2><p className="mt-7 max-w-[13rem] text-[0.68rem] leading-[1.7] text-[#111111]/62">{SHOP_PRESENTATION_CONTENT.detail.copy}</p></div>
            <div className="grid min-h-[12rem] gap-4 sm:grid-cols-3">{SHOP_PRESENTATION_SLOTS.detail.map((slot, index) => <div key={slot.src} className="relative overflow-hidden bg-[#222]"><span className="absolute left-4 top-4 z-10 text-[0.53rem] font-bold uppercase tracking-[0.16em] text-white/65">{SHOP_PRESENTATION_CONTENT.detail.imageLabels[index]}</span><CampaignImage slot={slot} imageClassName="absolute inset-0 h-full w-full object-cover" /></div>)}</div>
            <div className="group relative min-h-[12rem] overflow-hidden bg-[#111] text-white"><CampaignImage slot={SHOP_PRESENTATION_SLOTS.detailAction} imageClassName="absolute inset-0 h-full w-full object-cover opacity-80 transition-transform duration-500 group-hover:scale-105" /><div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/10 to-transparent" /><div className="relative z-10 flex h-full flex-col justify-between p-7"><div><p className="text-[0.57rem] font-bold uppercase tracking-[0.17em] text-white/70">{SHOP_PRESENTATION_CONTENT.detail.actionEyebrow}</p><h3 className="mt-3 font-headline text-[1.6rem] font-black uppercase leading-[0.8] tracking-[-0.065em]">{SHOP_PRESENTATION_CONTENT.detail.actionHeading[0]}<br />{SHOP_PRESENTATION_CONTENT.detail.actionHeading[1]}</h3></div><TextLink inverted href={SHOP_PRESENTATION_CONTENT.detail.cta.href}>{SHOP_PRESENTATION_CONTENT.detail.cta.label}</TextLink></div></div>
          </div>
        </section>

        <section data-shop-scroll-scene className="relative isolate min-h-[200px] overflow-hidden bg-[#111111] text-white" aria-labelledby="editorial-heading"><CampaignImage slot={SHOP_PRESENTATION_SLOTS.editorial} imageClassName="absolute inset-0 -z-20 h-full w-full scale-110 object-cover object-center opacity-65" /><div className="absolute inset-0 -z-10 bg-[linear-gradient(90deg,rgba(17,17,17,0.97)_0%,rgba(17,17,17,0.84)_38%,rgba(17,17,17,0.24)_100%)]" /><div className="mx-auto flex min-h-[200px] max-w-[1440px] flex-col justify-between px-5 py-6 sm:px-8 lg:px-12 xl:px-14"><div><p className="text-[0.58rem] font-bold uppercase tracking-[0.18em] text-[#78a9f7]">{SHOP_PRESENTATION_CONTENT.editorial.eyebrow}</p><h2 id="editorial-heading" className="mt-3 max-w-[39rem] font-headline text-[2.5rem] font-black uppercase leading-[0.75] tracking-[-0.075em]">{SHOP_PRESENTATION_CONTENT.editorial.heading[0]}<br />{SHOP_PRESENTATION_CONTENT.editorial.heading[1]}</h2><p className="mt-3 max-w-[21rem] text-[0.72rem] leading-[1.75] text-white/70">{SHOP_PRESENTATION_CONTENT.editorial.copy}</p></div><div className="flex items-end justify-between"><MotionLink href={SHOP_PRESENTATION_CONTENT.editorial.cta.href} className="motion-press inline-flex w-fit items-center gap-4 rounded-full border border-white/35 px-5 py-3 text-[0.59rem] font-bold uppercase tracking-[0.15em] transition-colors hover:border-white hover:bg-white hover:text-[#111111]">{SHOP_PRESENTATION_CONTENT.editorial.cta.label} <ArrowUpRight aria-hidden="true" size={14} /></MotionLink></div></div></section>

      </main>
    </>
  );
}
