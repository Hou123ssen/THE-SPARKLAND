import { Head, Link } from "@inertiajs/react";
import { useState } from "react";
import { ArrowLeft, ArrowRight, ArrowUpRight, SlidersHorizontal } from "lucide-react";
import Navbar from "../Components/Navbar.jsx";
import { SHOP_PRESENTATION_CONTENT, SHOP_PRESENTATION_SLOTS } from "../Components/ShopPresentationSlots.jsx";

function priceLabel(price) {
  if (!price) return null;

  return `${price.currency} ${new Intl.NumberFormat(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(price.amount_minor / 100)}`;
}

function ProductMedia({ media, name, eager = false, className = "" }) {
  const responsive = media?.responsive;
  const fallback = responsive?.fallback ?? [];
  const fallbackImage = fallback.at(-1);

  if (!fallbackImage) {
    return <div className={`h-full w-full bg-[#f4f4f1] ${className}`} aria-hidden="true" />;
  }

  return (
    <picture className="block h-full w-full overflow-hidden">
      {responsive?.avif?.length > 0 && (
        <source type="image/avif" srcSet={responsive.avif.map((image) => `${image.url} ${image.width}w`).join(", ")} sizes="(min-width: 1024px) 32vw, 100vw" />
      )}
      {responsive?.webp?.length > 0 && (
        <source type="image/webp" srcSet={responsive.webp.map((image) => `${image.url} ${image.width}w`).join(", ")} sizes="(min-width: 1024px) 32vw, 100vw" />
      )}
      <img
        src={fallbackImage.url}
        srcSet={fallback.map((image) => `${image.url} ${image.width ?? 960}w`).join(", ")}
        sizes="(min-width: 1024px) 32vw, 100vw"
        alt={media.alt || name}
        loading={eager ? "eager" : "lazy"}
        fetchPriority={eager ? "high" : "auto"}
        decoding="async"
        className={`h-full w-full object-contain transition-transform duration-500 ease-out group-hover:scale-[1.025] group-focus-visible:scale-[1.025] motion-reduce:transform-none ${className}`}
      />
    </picture>
  );
}

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

function ProductTile({ product, kind = "small", eager = false }) {
  if (!product) return null;

  const metadata = [product.attributes.shape, product.attributes.colour].filter(Boolean).join(" / ");
  const large = kind === "anchor";

  return (
    <article className={`group min-w-0 ${large ? "h-full" : ""}`}>
      <Link href={`/products/${product.slug}`} className={`flex h-full flex-col overflow-hidden bg-[#f1f1ee] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057C8] focus-visible:ring-offset-4 ${large ? "lg:h-[490px]" : "lg:h-[237px]"}`} aria-label={`View ${product.name}`}>
        <div className="relative min-h-[14rem] flex-1 overflow-hidden lg:min-h-0">
          <div className={`h-full w-full mix-blend-multiply ${large ? "scale-[1.7]" : "scale-[2.2]"}`}><ProductMedia media={product.media} name={product.name} eager={eager} /></div>
        </div>
        <div className="flex shrink-0 items-end justify-between gap-4 px-5 pb-4 pt-2">
          <div>
            <h3 className={`${large ? "text-[1.35rem]" : "text-[1.05rem]"} font-headline font-black uppercase leading-[0.95] transition-colors group-hover:text-[#0057C8]`}>{product.name}</h3>
            {metadata && <p className="mt-2 text-[0.55rem] font-medium uppercase tracking-[0.14em] text-[#111111]/55">{metadata}</p>}
          </div>
          <div className="flex shrink-0 items-center gap-3">{priceLabel(product.price) && <span className="text-[0.58rem] font-bold uppercase tracking-[0.09em] text-[#111111]/75">{priceLabel(product.price)}</span>}<span className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[#111111]/50"><ArrowUpRight aria-hidden="true" size={16} /></span></div>
        </div>
      </Link>
    </article>
  );
}

function TextLink({ children, href = "#catalogue", inverted = false }) {
  return (
    <a href={href} className={`inline-flex items-center gap-3 text-[0.61rem] font-bold uppercase tracking-[0.16em] transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057C8] focus-visible:ring-offset-4 ${inverted ? "text-white hover:text-[#78a9f7]" : "text-[#111111] hover:text-[#0057C8]"}`}>
      {children}<ArrowUpRight aria-hidden="true" size={15} strokeWidth={1.7} />
    </a>
  );
}

function RemainingProducts({ products }) {
  const [expanded, setExpanded] = useState(false);

  if (products.length === 0) return null;

  return (
    <div className="mt-4">
      {expanded && <div id="remaining-page-products" className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">{products.map((product) => <ProductTile key={product.id} product={product} />)}</div>}
      <button type="button" aria-expanded={expanded} aria-controls={expanded ? "remaining-page-products" : undefined} onClick={() => setExpanded(!expanded)} className="text-[0.6rem] font-bold uppercase tracking-[0.14em] text-[#0057C8] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057C8] focus-visible:ring-offset-2">{expanded ? "View less" : `View ${products.length} more on this page`}</button>
    </div>
  );
}

export default function Shop({ products, facets, filters, sort, pagination }) {
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

      <main className="overflow-hidden bg-white pt-[76px] text-[#111111]">
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
                <div><p className="max-w-[19rem] text-[0.75rem] leading-[1.5] text-[#111111]/68">{SHOP_PRESENTATION_CONTENT.hero.copy}</p><a href={SHOP_PRESENTATION_CONTENT.hero.cta.href} className="mt-4 inline-flex min-w-[11rem] items-center justify-between gap-7 rounded-full bg-[#0057C8] px-6 py-3.5 text-[0.61rem] font-bold uppercase tracking-[0.16em] text-white transition-colors hover:bg-[#0047a4] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057C8] focus-visible:ring-offset-4">{SHOP_PRESENTATION_CONTENT.hero.cta.label} <ArrowRight aria-hidden="true" size={17} strokeWidth={1.8} /></a></div>
              </div>
            </div>
            <aside className="grid grid-cols-[1fr_1fr] bg-[#f4f4f1] lg:grid-cols-1 lg:grid-rows-[65%_35%]" aria-label="Campaign feature">
              <div className="relative min-h-[15rem] overflow-hidden bg-[#bebebb] lg:min-h-0"><CampaignImage slot={SHOP_PRESENTATION_SLOTS.heroEditorial} eager imageClassName="absolute inset-0 h-full w-full object-cover object-[54%_center]" /></div>
              <div className="flex flex-col justify-center p-5"><p className="text-[0.55rem] font-bold uppercase tracking-[0.18em] text-[#0057C8]">{SHOP_PRESENTATION_CONTENT.heroEditorial.eyebrow}</p><h2 className="mt-3 font-headline text-[1.4rem] font-black uppercase leading-[0.95]">{SHOP_PRESENTATION_CONTENT.heroEditorial.heading[0]}<br />{SHOP_PRESENTATION_CONTENT.heroEditorial.heading[1]}</h2><p className="mt-2 max-w-[12rem] text-[0.68rem] leading-[1.5] text-[#111111]/58">{SHOP_PRESENTATION_CONTENT.heroEditorial.copy}</p><div className="mt-3"><TextLink href={SHOP_PRESENTATION_CONTENT.heroEditorial.cta.href}>{SHOP_PRESENTATION_CONTENT.heroEditorial.cta.label}</TextLink></div></div>
            </aside>
          </div>
        </section>

        <section className="mx-auto max-w-[1440px] px-5 pb-7 pt-1 sm:px-8 lg:px-12 xl:px-14" aria-label="Catalogue controls">
          <form action="/shop" method="get" className="rounded-[18px] bg-[#f0f0ed] p-4 lg:flex lg:items-center lg:gap-3 lg:p-3">
            <div className="flex items-center gap-3 px-1 lg:w-[8rem] lg:shrink-0"><span className="inline-flex h-10 w-10 items-center justify-center rounded-full bg-[#0057C8] text-white"><SlidersHorizontal aria-hidden="true" size={16} strokeWidth={1.8} /></span><p className="text-[0.62rem] font-bold uppercase tracking-[0.17em]">Filter by</p></div>
            <div className="mt-4 flex flex-wrap gap-3 lg:mt-0 lg:flex-1"><FilterSelect name="shape" label="Shape" options={facets.shape} value={filters.shape} /><FilterSelect name="colour" label="Colour" options={facets.colour} value={filters.colour} /><FilterSelect name="material" label="Material" options={facets.material} value={filters.material} /><FilterSelect name="collection" label="Collection" options={facets.collection} value={filters.collection} /></div>
            <div className="mt-4 flex items-center gap-3 lg:mt-0 lg:shrink-0"><label className="flex min-w-[12rem] flex-1 items-center gap-2 rounded-full bg-white px-5 py-3.5 text-[0.59rem] font-semibold uppercase tracking-[0.14em] ring-1 ring-[#111111]/10 focus-within:ring-[#0057C8] lg:flex-none"><span className="text-[#111111]/62">Sort by</span><select name="sort" defaultValue={sort} className="min-w-0 flex-1 appearance-none bg-transparent text-right text-[0.58rem] font-bold uppercase tracking-[0.1em] outline-none" aria-label="Sort products"><option value="featured">Featured</option><option value="newest">Newest</option></select></label><button type="submit" className="rounded-full bg-[#111111] px-6 py-3.5 text-[0.58rem] font-bold uppercase tracking-[0.15em] text-white transition-colors hover:bg-[#0057C8] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057C8] focus-visible:ring-offset-2">Apply</button>{hasFilters && <Link href={clearHref} className="text-[0.56rem] font-bold uppercase tracking-[0.15em] text-[#0057C8] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057C8] focus-visible:ring-offset-2">Clear</Link>}</div>
          </form>
        </section>

        <section id="catalogue" className="mx-auto max-w-[1440px] px-5 pb-5 sm:px-8 lg:px-12 xl:px-14" aria-labelledby="catalogue-heading">
          <div className="mb-4 flex items-end justify-between pb-1"><div><h2 id="catalogue-heading" className="font-headline text-[1.85rem] font-black uppercase leading-[0.82] tracking-[-0.06em]">All frames</h2></div><p className="pb-1 text-[0.58rem] font-bold uppercase tracking-[0.16em] text-[#111111]/52">{initialProducts.length} initially shown / {pagination.total} total</p></div>
          {products.length === 0 ? (
            <div className="flex min-h-[28rem] flex-col items-start justify-center bg-[#f4f4f1] p-8"><h3 className="font-headline text-[clamp(2.4rem,4vw,4.5rem)] font-black uppercase leading-[0.84] tracking-[-0.055em]">No frames match<br />this view.</h3><Link href={clearHref} className="mt-8 rounded-full bg-[#0057C8] px-5 py-3 text-[0.6rem] font-bold uppercase tracking-[0.16em] text-white">Clear filters</Link></div>
          ) : (
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,36fr)_minmax(0,64fr)]"><div><ProductTile product={initialProducts[0]} kind="anchor" eager /></div><div className="grid grid-cols-1 gap-4 sm:grid-cols-2">{initialProducts.slice(1).map((product, index) => <ProductTile key={product.id} product={product} eager={index < 2} />)}</div></div>
          )}
          <RemainingProducts key={products.map((product) => product.id).join(",")} products={remainderProducts} />
          {pagination.last_page > 1 && <nav className="mt-4 flex items-center gap-3 pt-2" aria-label="Catalogue pagination">{previous?.url && <Link href={previous.url} className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[#111111]/18 hover:border-[#0057C8] hover:text-[#0057C8]" aria-label="Previous catalogue page"><ArrowLeft aria-hidden="true" size={16} /></Link>}{pageLinks.map((link) => <Link key={link.label} href={link.url || "#"} aria-current={link.active ? "page" : undefined} className={`inline-flex h-10 min-w-10 items-center justify-center rounded-full px-3 text-[0.62rem] font-bold tracking-[0.12em] ${link.active ? "bg-[#0057C8] text-white" : "border border-[#111111]/18 hover:border-[#0057C8] hover:text-[#0057C8]"}`}>{link.label.padStart(2, "0")}</Link>)}{next?.url && <Link href={next.url} className="ml-auto inline-flex items-center gap-3 text-[0.6rem] font-bold uppercase tracking-[0.16em] hover:text-[#0057C8]">Next page <ArrowRight aria-hidden="true" size={16} /></Link>}</nav>}
        </section>

        <section className="border-y border-[#111111]/12 bg-[#f1f1ee] py-4" aria-labelledby="collection-heading">
          <div className="mx-auto grid max-w-[1440px] grid-cols-1 gap-5 px-5 sm:px-8 lg:grid-cols-[minmax(0,22fr)_minmax(0,60fr)_minmax(0,18fr)] lg:px-12 xl:px-14">
            <div className="relative z-10 flex min-h-[18rem] flex-col justify-between py-4 lg:min-h-[320px]"><div><p className="text-[0.58rem] font-bold uppercase tracking-[0.18em] text-[#0057C8]">{SHOP_PRESENTATION_CONTENT.collection.eyebrow}</p><h2 id="collection-heading" className="mt-4 font-headline text-[3rem] font-black uppercase leading-[0.76] tracking-[-0.07em]">{SHOP_PRESENTATION_CONTENT.collection.heading[0]}<br />{SHOP_PRESENTATION_CONTENT.collection.heading[1]}</h2><p className="mt-4 max-w-[13rem] text-[0.7rem] leading-[1.7] text-[#111111]/62">{SHOP_PRESENTATION_CONTENT.collection.copy}</p></div><a href={SHOP_PRESENTATION_CONTENT.collection.cta.href} className="inline-flex w-fit items-center gap-4 rounded-full bg-[#0057C8] px-5 py-3 text-[0.59rem] font-bold uppercase tracking-[0.15em] text-white">{SHOP_PRESENTATION_CONTENT.collection.cta.label} <ArrowUpRight aria-hidden="true" size={14} /></a></div>
            <div className="relative min-h-[20rem] overflow-hidden bg-[#cecdc9]"><span className="absolute left-6 top-6 z-10 text-[0.57rem] font-bold uppercase tracking-[0.17em] text-white/80">{SHOP_PRESENTATION_CONTENT.collection.imageLabel}</span><CampaignImage slot={SHOP_PRESENTATION_SLOTS.featuredCollection} imageClassName="absolute inset-0 h-full w-full object-cover object-[52%_center]" /></div>
            <div className="grid min-h-[20rem] grid-rows-2 gap-2"><div className="relative overflow-hidden bg-[#d9d8d5]"><span className="absolute left-5 top-5 z-10 text-[0.55rem] font-bold uppercase tracking-[0.16em] text-white/75">{SHOP_PRESENTATION_CONTENT.collection.detailLabel}</span><CampaignImage slot={SHOP_PRESENTATION_SLOTS.detail[0]} imageClassName="absolute inset-0 h-full w-full object-cover" /></div><div className="flex flex-col justify-between bg-[#111111] p-5 text-white"><div><p className="text-[0.56rem] font-bold uppercase tracking-[0.17em] text-white/52">{SHOP_PRESENTATION_CONTENT.collection.panelEyebrow}</p><p className="mt-3 max-w-[15rem] font-headline text-[1.5rem] font-black uppercase leading-[0.82] tracking-[-0.055em]">{SHOP_PRESENTATION_CONTENT.collection.panelHeading[0]}<br />{SHOP_PRESENTATION_CONTENT.collection.panelHeading[1]}</p></div><TextLink inverted href={SHOP_PRESENTATION_CONTENT.collection.panelCta.href}>{SHOP_PRESENTATION_CONTENT.collection.panelCta.label}</TextLink></div></div>
          </div>
        </section>

        <section className="mx-auto max-w-[1440px] px-5 py-4 sm:px-8 lg:px-12 xl:px-14" aria-labelledby="detail-heading">
          <div className="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,22fr)_minmax(0,54fr)_minmax(0,24fr)]">
            <div className="py-4"><p className="text-[0.58rem] font-bold uppercase tracking-[0.18em] text-[#0057C8]">{SHOP_PRESENTATION_CONTENT.detail.eyebrow}</p><h2 id="detail-heading" className="mt-4 font-headline text-[2.25rem] font-black uppercase leading-[0.76] tracking-[-0.07em]">{SHOP_PRESENTATION_CONTENT.detail.heading[0]}<br />{SHOP_PRESENTATION_CONTENT.detail.heading[1]}</h2><p className="mt-7 max-w-[13rem] text-[0.68rem] leading-[1.7] text-[#111111]/62">{SHOP_PRESENTATION_CONTENT.detail.copy}</p></div>
            <div className="grid min-h-[12rem] gap-4 sm:grid-cols-3">{SHOP_PRESENTATION_SLOTS.detail.map((slot, index) => <div key={slot.src} className="relative overflow-hidden bg-[#222]"><span className="absolute left-4 top-4 z-10 text-[0.53rem] font-bold uppercase tracking-[0.16em] text-white/65">{SHOP_PRESENTATION_CONTENT.detail.imageLabels[index]}</span><CampaignImage slot={slot} imageClassName="absolute inset-0 h-full w-full object-cover" /></div>)}</div>
            <div className="group relative min-h-[12rem] overflow-hidden bg-[#111] text-white"><CampaignImage slot={SHOP_PRESENTATION_SLOTS.detailAction} imageClassName="absolute inset-0 h-full w-full object-cover opacity-80 transition-transform duration-500 group-hover:scale-105" /><div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/10 to-transparent" /><div className="relative z-10 flex h-full flex-col justify-between p-7"><div><p className="text-[0.57rem] font-bold uppercase tracking-[0.17em] text-white/70">{SHOP_PRESENTATION_CONTENT.detail.actionEyebrow}</p><h3 className="mt-3 font-headline text-[1.6rem] font-black uppercase leading-[0.8] tracking-[-0.065em]">{SHOP_PRESENTATION_CONTENT.detail.actionHeading[0]}<br />{SHOP_PRESENTATION_CONTENT.detail.actionHeading[1]}</h3></div><TextLink inverted href={SHOP_PRESENTATION_CONTENT.detail.cta.href}>{SHOP_PRESENTATION_CONTENT.detail.cta.label}</TextLink></div></div>
          </div>
        </section>

        <section className="relative isolate min-h-[200px] overflow-hidden bg-[#111111] text-white" aria-labelledby="editorial-heading"><CampaignImage slot={SHOP_PRESENTATION_SLOTS.editorial} imageClassName="absolute inset-0 -z-20 h-full w-full scale-110 object-cover object-center opacity-65" /><div className="absolute inset-0 -z-10 bg-[linear-gradient(90deg,rgba(17,17,17,0.97)_0%,rgba(17,17,17,0.84)_38%,rgba(17,17,17,0.24)_100%)]" /><div className="mx-auto flex min-h-[200px] max-w-[1440px] flex-col justify-between px-5 py-6 sm:px-8 lg:px-12 xl:px-14"><div><p className="text-[0.58rem] font-bold uppercase tracking-[0.18em] text-[#78a9f7]">{SHOP_PRESENTATION_CONTENT.editorial.eyebrow}</p><h2 id="editorial-heading" className="mt-3 max-w-[39rem] font-headline text-[2.5rem] font-black uppercase leading-[0.75] tracking-[-0.075em]">{SHOP_PRESENTATION_CONTENT.editorial.heading[0]}<br />{SHOP_PRESENTATION_CONTENT.editorial.heading[1]}</h2><p className="mt-3 max-w-[21rem] text-[0.72rem] leading-[1.75] text-white/70">{SHOP_PRESENTATION_CONTENT.editorial.copy}</p></div><div className="flex items-end justify-between"><a href={SHOP_PRESENTATION_CONTENT.editorial.cta.href} className="inline-flex w-fit items-center gap-4 rounded-full border border-white/35 px-5 py-3 text-[0.59rem] font-bold uppercase tracking-[0.15em] transition-colors hover:border-white hover:bg-white hover:text-[#111111]">{SHOP_PRESENTATION_CONTENT.editorial.cta.label} <ArrowUpRight aria-hidden="true" size={14} /></a></div></div></section>

      </main>
    </>
  );
}
