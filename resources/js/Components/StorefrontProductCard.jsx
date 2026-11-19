import { Link } from "@inertiajs/react";
import { ArrowUpRight } from "lucide-react";

function priceLabel(price) {
  if (!price) return null;

  return `${price.currency} ${new Intl.NumberFormat(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(price.amount_minor / 100)}`;
}

function ProductMedia({ media, name, eager = false, interactive = false, sizes = "(min-width: 1024px) 32vw, 100vw", className = "" }) {
  const responsive = media?.responsive;
  const fallback = responsive?.fallback ?? [];
  const fallbackImage = fallback.at(-1);

  if (!fallbackImage) {
    return <div className={`h-full w-full bg-[#f4f4f1] ${className}`} aria-hidden="true" />;
  }

  return (
    <picture className="block h-full w-full overflow-hidden">
      {responsive?.avif?.length > 0 && <source type="image/avif" srcSet={responsive.avif.map((image) => `${image.url} ${image.width}w`).join(", ")} sizes={sizes} />}
      {responsive?.webp?.length > 0 && <source type="image/webp" srcSet={responsive.webp.map((image) => `${image.url} ${image.width}w`).join(", ")} sizes={sizes} />}
      <img src={fallbackImage.url} srcSet={fallback.map((image) => `${image.url} ${image.width ?? 960}w`).join(", ")} sizes={sizes} alt={media.alt || name} loading={eager ? "eager" : "lazy"} fetchPriority={eager ? "high" : "auto"} decoding="async" className={`h-full w-full object-contain ${interactive ? "transition-transform duration-500 ease-out group-hover:scale-[1.025] group-focus-visible:scale-[1.025] motion-reduce:transform-none" : ""} ${className}`} />
    </picture>
  );
}

export default function StorefrontProductCard({ product, kind = "small", eager = false, href = undefined, compactMobile = false }) {
  if (!product) return null;

  const metadata = [product.attributes.shape, product.attributes.colour].filter(Boolean).join(" / ");
  const large = kind === "anchor";
  const destination = href === undefined ? `/products/${product.slug}` : href;
  const interactive = Boolean(destination);
  const mediaSizes = compactMobile ? "(max-width: 639px) 50vw, (min-width: 1024px) 25vw, 32vw" : undefined;
  const mediaHeight = compactMobile ? "min-h-[7.75rem] sm:min-h-[14rem]" : "min-h-[14rem]";
  const mediaScale = compactMobile ? (large ? "scale-[1.1] sm:scale-[1.7]" : "scale-[1.1] sm:scale-[2.2]") : (large ? "scale-[1.7]" : "scale-[2.2]");
  const cardPadding = compactMobile ? "px-3 pb-2.5 pt-2 sm:px-5 sm:pb-4" : "px-5 pb-4 pt-2";
  const headingSize = compactMobile ? (large ? "text-[1rem] sm:text-[1.35rem]" : "text-[clamp(.82rem,4.1vw,1rem)] sm:text-[1.05rem]") : (large ? "text-[1.35rem]" : "text-[1.05rem]");
  const content = <>
    <div className={`relative ${mediaHeight} flex-1 overflow-hidden lg:min-h-0`}><div className={`h-full w-full mix-blend-multiply ${mediaScale}`}><ProductMedia media={product.media} name={product.name} eager={eager} interactive={interactive} sizes={mediaSizes} /></div></div>
    <div className={`flex shrink-0 justify-between gap-2 ${compactMobile ? "flex-col items-start sm:flex-row sm:items-end sm:gap-4" : "items-end"} ${cardPadding}`}><div className="min-w-0"><h3 className={`${headingSize} font-headline font-black uppercase leading-[0.95] ${interactive ? "transition-colors group-hover:text-[#0057C8]" : ""}`}>{product.name}</h3>{metadata && <p className={`mt-1.5 font-medium uppercase tracking-[0.14em] text-[#111111]/55 ${compactMobile ? "text-[.48rem] sm:mt-2 sm:text-[.55rem]" : "mt-2 text-[0.55rem]"}`}>{metadata}</p>}</div><div className="flex shrink-0 items-center gap-3">{priceLabel(product.price) && <span className={`font-bold uppercase tracking-[0.09em] text-[#111111]/75 ${compactMobile ? "text-[.52rem] sm:text-[.58rem]" : "text-[0.58rem]"}`}>{priceLabel(product.price)}</span>}{interactive ? <span className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[#111111]/50"><ArrowUpRight aria-hidden="true" size={16} /></span> : null}</div></div>
  </>;

  return <article className={`${interactive ? "group" : ""} min-w-0 ${large ? "h-full" : ""}`}>{destination ? <Link href={destination} className={`flex h-full flex-col overflow-hidden bg-[#f1f1ee] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057C8] focus-visible:ring-offset-4 ${large ? "lg:h-[490px]" : "lg:h-[237px]"}`} aria-label={`View ${product.name}`}>{content}</Link> : <div className={`flex h-full flex-col overflow-hidden bg-[#f1f1ee] ${large ? "lg:h-[490px]" : "lg:h-[237px]"}`}>{content}</div>}</article>;
}
