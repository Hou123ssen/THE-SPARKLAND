import { Head, Link } from "@inertiajs/react";
import { useEffect, useState } from "react";
import Navbar from "../Components/Navbar.jsx";

function priceLabel(price) {
  if (!price) return null;

  return `${price.currency} ${new Intl.NumberFormat(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(price.amount_minor / 100)}`;
}

function ProductMedia({ media, name, eager = false }) {
  const responsive = media?.responsive;
  const fallback = responsive?.fallback ?? [];
  const fallbackImage = fallback.at(-1);

  if (!fallbackImage) {
    return <div className="h-full w-full bg-[#F7F7F5]" aria-hidden="true" />;
  }

  return (
    <picture className="block h-full w-full overflow-hidden">
      {responsive?.avif?.length > 0 && (
        <source
          type="image/avif"
          srcSet={responsive.avif.map((image) => `${image.url} ${image.width}w`).join(", ")}
          sizes="(min-width: 1024px) 29vw, 100vw"
        />
      )}
      {responsive?.webp?.length > 0 && (
        <source
          type="image/webp"
          srcSet={responsive.webp.map((image) => `${image.url} ${image.width}w`).join(", ")}
          sizes="(min-width: 1024px) 29vw, 100vw"
        />
      )}
      <img
        src={fallbackImage.url}
        srcSet={fallback.map((image) => `${image.url} ${image.width ?? 960}w`).join(", ")}
        sizes="(min-width: 1024px) 29vw, 100vw"
        alt={media.alt || name}
        loading={eager ? "eager" : "lazy"}
        fetchPriority={eager ? "high" : "auto"}
        decoding="async"
        className="h-full w-full object-contain transition-transform duration-300 ease-out group-hover:scale-[1.015] group-focus-visible:scale-[1.015] motion-reduce:transform-none"
      />
    </picture>
  );
}

function FilterSelect({ name, label, options, value }) {
  if (!options?.length) return null;

  return (
    <label className="group flex min-w-[10.5rem] flex-1 items-center gap-3 border-b border-[#111111]/25 py-3 text-[0.64rem] font-semibold uppercase tracking-[0.16em] text-[#111111] focus-within:border-[#0057C8]">
      <span className={value ? "text-[#0057C8]" : ""}>{label}</span>
      <select
        name={name}
        defaultValue={value || ""}
        className="min-w-0 flex-1 appearance-none bg-transparent text-right text-[0.61rem] font-medium uppercase tracking-[0.12em] outline-none"
        aria-label={`Filter by ${label.toLowerCase()}`}
      >
        <option value="">All</option>
        {options.map((option) => (
          <option key={option.value} value={option.value}>{option.label}</option>
        ))}
      </select>
      <span aria-hidden="true" className="text-[#0057C8]">+</span>
    </label>
  );
}

export default function Shop({ products, facets, filters, sort, pagination }) {
  const [entered, setEntered] = useState(false);
  const heroProduct = products[0];
  const hasFilters = Object.keys(filters).length > 0;

  useEffect(() => {
    const frame = window.requestAnimationFrame(() => setEntered(true));
    return () => window.cancelAnimationFrame(frame);
  }, []);

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

      <main className="min-h-screen bg-white pt-[76px] text-[#111111]">
        <section className="mx-auto grid max-w-[1440px] grid-cols-12 border-b border-[#111111]/15 px-6 py-14 sm:px-8 lg:px-12 lg:py-16 xl:px-16" aria-labelledby="shop-heading">
          <div className={`col-span-12 flex flex-col justify-between lg:col-span-7 lg:pr-12 ${entered ? "translate-y-0 opacity-100" : "translate-y-4 opacity-0"} transition-all duration-700 ease-out motion-reduce:translate-y-0 motion-reduce:opacity-100`}>
            <div>
              <p className="text-[0.62rem] font-bold uppercase tracking-[0.2em] text-[#0057C8]">Catalogue / 01</p>
              <h1 id="shop-heading" className="mt-7 font-headline text-[clamp(4.6rem,9.4vw,9.2rem)] font-black uppercase leading-[0.82] tracking-[-0.055em]">
                Find Your<br />Frame.
              </h1>
            </div>
            <p className="mt-14 max-w-[21rem] text-[0.7rem] uppercase leading-[1.8] tracking-[0.14em] text-[#111111]/65 lg:mt-20">
              Product catalogue.
            </p>
          </div>

          <div className={`col-span-12 mt-12 border-l border-[#111111]/15 pl-7 lg:col-span-4 lg:col-start-9 lg:mt-0 ${entered ? "translate-y-0 opacity-100" : "translate-y-5 opacity-0"} transition-all delay-150 duration-700 ease-out motion-reduce:translate-y-0 motion-reduce:opacity-100`}>
            <p className="mb-4 text-[0.58rem] font-semibold uppercase tracking-[0.18em] text-[#111111]/55">Current form</p>
            <div className="aspect-[4/3] bg-[#F7F7F5]">
              {heroProduct && <ProductMedia media={heroProduct.media} name={heroProduct.name} eager />}
            </div>
          </div>
        </section>

        <section className="mx-auto max-w-[1440px] px-6 py-9 sm:px-8 lg:px-12 xl:px-16" aria-label="Catalogue controls">
          <form action="/shop" method="get" className="grid grid-cols-12 gap-x-8 gap-y-5 border-y border-[#111111]/15 py-4">
            <div className="col-span-12 flex items-center justify-between lg:col-span-2 lg:block">
              <p className="text-[0.62rem] font-bold uppercase tracking-[0.2em]">Filter by</p>
              {hasFilters && <Link href={clearHref} className="mt-3 inline-block border-b border-[#0057C8] pb-1 text-[0.57rem] font-semibold uppercase tracking-[0.16em] text-[#0057C8] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057C8] focus-visible:ring-offset-4">Clear filters</Link>}
            </div>

            <div className="col-span-12 flex flex-wrap gap-x-5 gap-y-1 lg:col-span-7">
              <FilterSelect name="shape" label="Shape" options={facets.shape} value={filters.shape} />
              <FilterSelect name="colour" label="Colour" options={facets.colour} value={filters.colour} />
              <FilterSelect name="material" label="Material" options={facets.material} value={filters.material} />
              <FilterSelect name="collection" label="Collection" options={facets.collection} value={filters.collection} />
            </div>

            <div className="col-span-12 flex items-end gap-4 lg:col-span-3 lg:justify-end">
              <label className="flex min-w-[11rem] flex-1 items-center gap-3 border-b border-[#111111]/25 py-3 text-[0.64rem] font-semibold uppercase tracking-[0.16em] focus-within:border-[#0057C8] lg:flex-none">
                <span>Sort</span>
                <select name="sort" defaultValue={sort} className="min-w-0 flex-1 appearance-none bg-transparent text-right text-[0.61rem] font-medium uppercase tracking-[0.12em] outline-none" aria-label="Sort products">
                  <option value="featured">Featured</option>
                  <option value="newest">Newest</option>
                </select>
                <span aria-hidden="true" className="text-[#0057C8]">+</span>
              </label>
              <button type="submit" className="border-b border-[#111111] pb-3 text-[0.6rem] font-bold uppercase tracking-[0.16em] transition-colors hover:border-[#0057C8] hover:text-[#0057C8] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057C8] focus-visible:ring-offset-4">Apply</button>
            </div>
          </form>
        </section>

        <section className="mx-auto max-w-[1440px] px-6 pb-20 sm:px-8 lg:px-12 xl:px-16" aria-labelledby="catalogue-heading">
          <div className="mb-8 flex items-baseline justify-between border-b border-[#111111]/15 pb-4">
            <h2 id="catalogue-heading" className="font-headline text-[1.5rem] font-black uppercase tracking-[-0.035em]">Frames</h2>
            <p className="text-[0.6rem] font-semibold uppercase tracking-[0.16em] text-[#111111]/55">{pagination.total} visible</p>
          </div>

          {products.length === 0 ? (
            <div className="flex min-h-[38vh] flex-col items-start justify-center border-b border-[#111111]/15">
              <h2 className="font-headline text-[clamp(2.4rem,4vw,4.5rem)] font-black uppercase leading-[0.9] tracking-[-0.045em]">No frames match<br />this view.</h2>
              <Link href={clearHref} className="mt-8 border-b border-[#0057C8] pb-1 text-[0.62rem] font-bold uppercase tracking-[0.18em] text-[#0057C8] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057C8] focus-visible:ring-offset-4">Clear filters -&gt;</Link>
            </div>
          ) : (
            <div className="grid grid-cols-1 gap-x-8 gap-y-16 lg:grid-cols-3 lg:gap-y-20">
              {products.map((product, index) => {
                const imageHeights = ["aspect-[4/4.5]", "aspect-[4/3.65]", "aspect-[4/4.9]"];
                const metadata = [product.attributes.shape, product.attributes.colour, product.attributes.material].filter(Boolean).join(" / ");

                return (
                  <article key={product.id} className={`group ${entered ? "translate-y-0 opacity-100" : "translate-y-5 opacity-0"} transition-all duration-500 ease-out motion-reduce:translate-y-0 motion-reduce:opacity-100`} style={{ transitionDelay: `${Math.min(index, 5) * 70}ms` }}>
                    <Link href={`/products/${product.slug}`} className="block focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057C8] focus-visible:ring-offset-4" aria-label={`View ${product.name}`}>
                      <div className={`${imageHeights[index % imageHeights.length]} overflow-hidden bg-[#F7F7F5]`}>
                        <ProductMedia media={product.media} name={product.name} eager={index < 3} />
                      </div>
                    </Link>
                    <div className="mt-4 border-t border-[#111111]/20 pt-3">
                      <div className="flex items-start justify-between gap-5">
                        <Link href={`/products/${product.slug}`} className="font-headline text-[1.35rem] font-black uppercase leading-none tracking-[-0.03em] hover:text-[#0057C8] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057C8] focus-visible:ring-offset-4">{product.name}</Link>
                        {priceLabel(product.price) && <span className="shrink-0 pt-0.5 text-[0.67rem] font-semibold uppercase tracking-[0.08em]">{priceLabel(product.price)}</span>}
                      </div>
                      {metadata && <p className="mt-3 text-[0.58rem] uppercase tracking-[0.14em] text-[#111111]/55 transition-opacity duration-200 group-hover:text-[#111111]/80">{metadata}</p>}
                    </div>
                  </article>
                );
              })}
            </div>
          )}

          {pagination.last_page > 1 && (
            <nav className="mt-20 flex items-center gap-5 border-t border-[#111111]/15 pt-6" aria-label="Catalogue pagination">
              {previous?.url && <Link href={previous.url} className="text-[0.62rem] font-bold uppercase tracking-[0.18em] text-[#111111]/55 hover:text-[#0057C8] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057C8] focus-visible:ring-offset-4">Prev</Link>}
              {pageLinks.map((link) => <Link key={link.label} href={link.url || "#"} aria-current={link.active ? "page" : undefined} className={`text-[0.72rem] font-bold tracking-[0.12em] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057C8] focus-visible:ring-offset-4 ${link.active ? "text-[#0057C8]" : "text-[#111111]/55 hover:text-[#0057C8]"}`}>{link.label.padStart(2, "0")}</Link>)}
              {next?.url && <Link href={next.url} className="ml-auto text-[0.62rem] font-bold uppercase tracking-[0.18em] hover:text-[#0057C8] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057C8] focus-visible:ring-offset-4">Next -&gt;</Link>}
            </nav>
          )}
        </section>
      </main>
    </>
  );
}
