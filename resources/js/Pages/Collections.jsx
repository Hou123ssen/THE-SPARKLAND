import { Link } from "@inertiajs/react";
import { useRef } from "react";
import { ArrowRight } from "lucide-react";
import Navbar from "../Components/Navbar.jsx";
import { COLLECTION_PRESENTATION, collectionPresentation } from "../Components/CollectionPresentationSlots";

const displayFace = "font-[Impact,Haettenschweiler,'Arial_Narrow_Bold',sans-serif] font-normal uppercase leading-[0.84] tracking-[-0.045em]";
const mastheadFace = "font-[Impact,Haettenschweiler,'Arial_Narrow_Bold',sans-serif] font-black uppercase leading-[0.74] tracking-[-0.07em] whitespace-nowrap";

function CollectionRow({ collection, index }) {
  const presentation = collectionPresentation(index);
  const number = String(index + 1).padStart(2, "0");

  return (
    <article className="grid grid-cols-1 border-t border-[#111111]/20 py-3 sm:grid-cols-[3rem_minmax(0,1fr)] sm:gap-x-5 sm:py-2 md:grid-cols-[3.5rem_minmax(0,1.55fr)_minmax(12rem,.75fr)] md:gap-x-6 lg:grid-cols-[4.25rem_minmax(0,1.55fr)_minmax(15rem,.75fr)] lg:gap-x-8 lg:py-3.5">
      <p className="hidden pt-1 text-[.95rem] font-bold leading-none tracking-[-.06em] sm:block sm:text-[1.08rem]">{number}</p>
      <Link href={collection.href} aria-label={`View ${collection.name}`} className="group relative block aspect-[1.66/1] overflow-hidden bg-[#e8e8e4] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057c8] focus-visible:ring-offset-4 md:aspect-[2.35/1] lg:aspect-[2.7/1]">
        <span className="absolute left-3 top-3 z-10 border border-white/70 bg-[#111111]/75 px-2 py-1 text-[.58rem] font-semibold leading-none tracking-[.12em] text-white sm:hidden">{number}</span>
        {presentation ? <img src={presentation.src} alt={collection.name} loading="lazy" className="h-full w-full object-cover transition-transform duration-500 motion-reduce:transition-none group-hover:scale-[1.025]" style={{ objectPosition: presentation.position }} /> : <div className="flex h-full items-end bg-[#e9e9e5] p-4 text-[.58rem] font-semibold uppercase tracking-[.16em] text-[#111111]/60">Collection / {number}</div>}
      </Link>
      <div className="col-start-auto mt-1.5 flex min-w-0 flex-col border-b border-[#111111]/20 pb-3 sm:col-start-2 sm:mt-3 sm:pb-3 md:col-start-3 md:mt-0 md:border-b-0 md:pb-0">
        <p className="mb-0.5 text-[.53rem] font-semibold uppercase tracking-[.16em] text-[#111111]/75 sm:mb-1">Collection {number}</p>
        <Link href={collection.href} className={`${displayFace} inline-flex min-h-10 w-fit max-w-full items-center text-[clamp(2rem,9vw,2.25rem)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057c8] focus-visible:ring-offset-4 sm:min-h-11 sm:text-[clamp(2.25rem,4.9vw,4.55rem)] lg:max-w-[18rem]`}>{collection.name}</Link>
        {collection.description ? <p className="mt-0 max-w-[16rem] text-[.69rem] leading-[1.45] text-[#111111]/80 sm:mt-1.5">{collection.description}</p> : null}
        <Link href={collection.href} aria-label={`Explore ${collection.name}`} className="mt-1.5 flex min-h-11 w-full items-center justify-between gap-2 rounded-[3px] border border-[#0057c8]/75 px-3 text-[.62rem] font-semibold uppercase tracking-[.13em] text-[#111111] transition-colors hover:bg-[#0057c8] hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057c8] focus-visible:ring-offset-4 sm:mt-auto sm:rounded-none sm:border-0 sm:px-0 sm:pt-3"><span className="min-w-0">Explore</span><ArrowRight className="shrink-0" size={18} strokeWidth={1.6} aria-hidden="true" /></Link>
      </div>
    </article>
  );
}

function EditorialEnding() {
  return <section className="grid grid-cols-2 bg-[#111111] text-white md:h-[18rem] md:grid-cols-[25%_36%_39%]" aria-label="Sparkland editorial">
    <div className="flex min-h-[12rem] flex-col justify-between bg-[#0057c8] p-6 sm:min-h-[14rem] sm:p-8 md:min-h-0 md:p-7"><p className={`${displayFace} max-w-[15rem] text-[clamp(1.55rem,2.15vw,2.15rem)]`}>More than<br />sunglasses<br />a different<br />point of view.</p><p className="text-[.52rem] font-semibold uppercase tracking-[.17em]">The Sparkland</p></div>
    <div className="relative min-h-[12rem] overflow-hidden sm:min-h-[14rem] md:min-h-0"><img src={COLLECTION_PRESENTATION.ending.monochrome} alt="" loading="lazy" className="absolute inset-0 h-full w-full object-cover" /></div>
    <div className="col-span-2 flex min-h-[12rem] flex-col justify-between p-6 sm:min-h-[14rem] sm:p-8 md:col-span-1 md:min-h-0 md:p-7"><p className="text-[.52rem] font-semibold uppercase tracking-[.17em] text-white/70">Join the movement</p><img src={COLLECTION_PRESENTATION.ending.architecture} alt="" loading="lazy" className="h-16 w-full max-w-[14rem] object-cover md:h-20" /><p className="max-w-[7rem] self-end text-[.55rem] font-semibold uppercase leading-[1.7] tracking-[.15em] text-white/65">People Places Ideas Style Culture</p></div>
  </section>;
}

export default function Collections({ collections = [] }) {
  const rosterRef = useRef(null);
  const scrollToRoster = () => {
    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    rosterRef.current?.scrollIntoView({ behavior: reducedMotion ? "auto" : "smooth", block: "start" });
  };

  return <>
    <Navbar />
    <main className="mx-auto max-w-[1440px] bg-white pt-[76px] text-[#111111]">
    <section className="relative isolate" aria-labelledby="collections-title">
      <div className="relative h-[21.5rem] overflow-hidden bg-[#bcd4e9] sm:h-[30rem] lg:h-[41rem]">
        <img src={COLLECTION_PRESENTATION.hero.src} alt="" className="absolute inset-0 h-full w-full object-cover object-[center_10%] lg:object-[center_18%]" aria-hidden="true" />
        <img src={COLLECTION_PRESENTATION.hero.src} alt="" fetchPriority="high" className="absolute inset-0 h-full w-full object-cover object-[center_10%] lg:scale-x-[.92] lg:object-[center_18%] lg:[mask-image:linear-gradient(to_right,transparent_0%,black_6%,black_94%,transparent_100%)]" />
        <div className="pointer-events-none absolute inset-x-0 bottom-0 h-[5.5rem] bg-[linear-gradient(to_bottom,transparent_0%,rgba(255,255,255,0.08)_9%,rgba(255,255,255,0.2)_22%,rgba(255,255,255,0.5)_52%,rgba(255,255,255,0.8)_73%,rgba(255,255,255,0.97)_92%,white_100%)] sm:h-[7rem] lg:h-[26rem]" aria-hidden="true" />
        <div className="absolute inset-x-0 top-0 flex justify-between p-6 text-[.5rem] font-semibold uppercase leading-[1.55] tracking-[.18em] sm:p-8 lg:pl-[4.2rem] lg:pr-12 lg:pt-[4.2rem] lg:text-[min(1vw,.9rem)]"><p>Vision<br />People<br />Culture<br />Everyday</p><p className="text-right">The Sparkland<br />Eyewear<br />Casablanca<br />Est. 2024</p></div>
      </div>
      <div className="relative flex min-h-[5.5rem] items-end justify-between bg-white px-6 pb-4 pt-10 text-[.55rem] font-semibold uppercase tracking-[.17em] sm:min-h-[6.25rem] sm:px-8 sm:pb-5 sm:pt-12 lg:min-h-[7.5rem] lg:items-start lg:px-12 lg:pb-0 lg:pt-[1.75rem] lg:text-[min(1.3vw,1.15rem)] lg:leading-[1.1] xl:pl-[4.5%] xl:pr-14">
        <p id="collections-roster-title">Different perspectives<br />Same vision</p>
        <p className="text-right">Sunglasses for<br />a brighter tomorrow</p>
        <span className="absolute bottom-0 left-[4.5%] w-[6%] border-b border-[#111111]/20" aria-hidden="true" />
        <span className="absolute bottom-0 left-[69%] w-[6%] border-b border-[#111111]/20" aria-hidden="true" />
      </div>
      <h1 id="collections-title" className={`${mastheadFace} pointer-events-none absolute bottom-[3.75rem] left-[3.8%] z-10 text-[clamp(2.8rem,14.5vw,5.5rem)] tracking-[-0.08em] sm:bottom-[4.25rem] sm:text-[clamp(4.4rem,18.25vw,16.5rem)] sm:tracking-[-0.07em] lg:bottom-[8.875rem] lg:origin-bottom-left lg:scale-x-[.975] lg:scale-y-[1.12] lg:tracking-[-0.06em]`}>Collections</h1>
      <button type="button" onClick={scrollToRoster} className="absolute bottom-[3.125rem] right-[3.2%] z-20 flex h-12 w-12 items-center justify-center rounded-full bg-[#0057c8] text-white transition-transform hover:scale-105 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057c8] focus-visible:ring-offset-4 sm:bottom-[3.625rem] sm:h-16 sm:w-16 lg:bottom-[8.125rem] lg:h-[6.25rem] lg:w-[6.25rem] lg:scale-[.93]" aria-label="Scroll to collections"><ArrowRight size={28} strokeWidth={1.4} aria-hidden="true" /></button>
    </section>
    <section ref={rosterRef} className="scroll-mt-[76px] px-6 pb-4 pt-1 sm:px-8 sm:pb-7 sm:pt-2 md:pb-9 lg:px-12 xl:px-14 [&>div>article:first-child]:border-t-0" aria-label="Collections roster">
      {collections.length ? <div>{collections.map((collection, index) => <CollectionRow key={collection.id ?? collection.slug ?? index} collection={collection} index={index} />)}</div> : <div className="border-y border-[#111111]/20 py-20 text-center sm:py-28"><p className="text-[.58rem] font-semibold uppercase tracking-[.18em] text-[#111111]/60">The Sparkland</p><h2 className={`${displayFace} mt-4 text-[clamp(2.8rem,7vw,6rem)]`}>New perspectives soon</h2></div>}
    </section>
    <EditorialEnding />
    </main>
  </>;
}
