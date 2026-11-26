import { Head } from "@inertiajs/react";
import gsap from "gsap";
import {
  ChevronLeft,
  ChevronRight,
  Minus,
  Plus,
  ShoppingBag,
} from "lucide-react";
import { useEffect, useLayoutEffect, useMemo, useRef, useState } from "react";
import Navbar from "../Components/Navbar.jsx";
import { useCommerce } from "../Components/Commerce/CommerceProvider.jsx";
import { consumeProductTransition, motionIsReduced, productTransitionName } from "../Components/ProductMotion.js";
import { MOTION, addScrollTrack, scrollScene, useScrubScenes } from "../Components/StorefrontMotion.js";

const display =
  "font-[Impact,Haettenschweiler,'Arial_Narrow_Bold',sans-serif] font-normal uppercase leading-[.84] tracking-[-.045em]";
const labels = {
  colour: "Colour",
  material: "Material",
  frame_shape: "Frame shape",
  fit: "Fit",
  frame_width_mm: "Frame width",
  lens_width_mm: "Lens width",
  lens_height_mm: "Lens height",
  bridge_width_mm: "Bridge width",
  temple_length_mm: "Temple length",
  lens_type: "Lens type",
};
const dimensions = new Set([
  "frame_width_mm",
  "lens_width_mm",
  "lens_height_mm",
  "bridge_width_mm",
  "temple_length_mm",
]);
const priceText = (price) =>
  price
    ? `${price.currency} ${new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(price.amount_minor / 100)}`
    : null;

function resolveSku(variant, options, selections) {
  if (
    !variant ||
    !options
      .filter((option) => option.required)
      .every((option) => Object.hasOwn(selections, option.id))
  ) {
    return null;
  }

  const candidates = variant.skus.filter((sku) =>
    Object.entries(selections).every(([optionId, valueId]) =>
      sku.option_values.some(
        (value) => value.option_id === Number(optionId) && value.id === valueId,
      ),
    ),
  );

  return candidates.length === 1 ? candidates[0] : null;
}

function Media({ item, name, eager = false }) {
  const responsive = item?.responsive,
    fallback = responsive?.fallback ?? [],
    source = fallback.at(-1);
  if (!source) return <div className="h-full w-full bg-[#f1f1ee]" />;
  const srcset = (items) =>
    items.map((image) => `${image.url} ${image.width}w`).join(", ");
  return (
    <picture className="block h-full w-full">
      {responsive?.avif?.length ? (
        <source
          type="image/avif"
          srcSet={srcset(responsive.avif)}
          sizes="(min-width:1024px) 52vw, 100vw"
        />
      ) : null}
      {responsive?.webp?.length ? (
        <source
          type="image/webp"
          srcSet={srcset(responsive.webp)}
          sizes="(min-width:1024px) 52vw, 100vw"
        />
      ) : null}
      <img
        src={source.url}
        srcSet={srcset(fallback)}
        sizes="(min-width:1024px) 52vw, 100vw"
        alt={item.alt || name}
        loading={eager ? "eager" : "lazy"}
        fetchPriority={eager ? "high" : "auto"}
        className="h-full w-full object-contain mix-blend-multiply transition-transform duration-500 lg:scale-[1.45]"
      />
    </picture>
  );
}

function Gallery({ media, name, product }) {
  const [index, setIndex] = useState(0);
  const stageRef = useRef(null);
  const mediaRef = useRef(null);
  const outgoingRef = useRef(null);
  const incomingDirection = useRef(0);
  useLayoutEffect(() => {
    return () => {
      outgoingRef.current?.kill();
      gsap.killTweensOf(mediaRef.current);
    };
  }, []);
  useLayoutEffect(() => {
    if (!incomingDirection.current || motionIsReduced()) return;
    const direction = incomingDirection.current;
    incomingDirection.current = 0;
    const context = gsap.context(() => {
      gsap.fromTo(mediaRef.current, { opacity: 0, x: direction * 24, scale: 1.025 }, {
        opacity: 1, x: 0, scale: 1, duration: 0.42, ease: "power3.out", clearProps: "opacity,transform",
      });
    }, stageRef);
    return () => context.revert();
  }, [index]);
  useEffect(() => setIndex(0), [media]);
  const current = media[index] ?? media[0];
  const changeImage = (nextIndex, direction) => {
    if (nextIndex === index || !media.length) return;
    if (motionIsReduced()) {
      setIndex(nextIndex);
      return;
    }
    outgoingRef.current?.kill();
    outgoingRef.current = gsap.to(mediaRef.current, {
      opacity: 0, x: -direction * 20, scale: 0.975, duration: 0.18, ease: "power2.in",
      onComplete: () => {
        incomingDirection.current = direction;
        setIndex(nextIndex);
      },
    });
  };
  const move = (amount) => changeImage((index + amount + media.length) % media.length, amount);
  return (
    <section data-pdp-gallery aria-label={`${name} gallery`}>
      <div ref={stageRef} className="relative h-[clamp(16rem,75vw,19rem)] overflow-hidden bg-[#f1f2ee] lg:h-[min(52vw,44rem)]">
        <div ref={mediaRef} className="h-full w-full bg-[#f1f2ee]" style={{ viewTransitionName: productTransitionName(product) }}>
          <Media item={current} name={name} eager />
        </div>
        {media.length > 1 ? (
          <>
            <button
              onClick={() => move(-1)}
              className="motion-press motion-no-scale absolute left-3 top-1/2 inline-flex h-10 w-10 -translate-y-1/2 items-center justify-center border border-[#111111]/15 bg-white/90 transition-colors hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057c8]"
              aria-label="Previous product image"
            >
              <ChevronLeft size={19} />
            </button>
            <button
              onClick={() => move(1)}
              className="motion-press motion-no-scale absolute right-3 top-1/2 inline-flex h-10 w-10 -translate-y-1/2 items-center justify-center border border-[#111111]/15 bg-white/90 transition-colors hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057c8]"
              aria-label="Next product image"
            >
              <ChevronRight size={19} />
            </button>
          </>
        ) : null}
      </div>
      {media.length > 1 ? (
        <>
          <div className="mt-2 flex justify-center gap-1.5 lg:hidden">
            {media.map((item, itemIndex) => (
              <button
                key={item.id}
                onClick={() => changeImage(itemIndex, itemIndex > index ? 1 : -1)}
                aria-label={`View image ${itemIndex + 1}`}
                aria-current={itemIndex === index ? "true" : undefined}
                className="motion-press inline-flex h-6 w-5 items-center justify-center focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057c8]"
              >
                <span className={`h-2 w-2 rounded-full transition-colors duration-[180ms] ${itemIndex === index ? "bg-[#0057c8]" : "bg-[#111111]/28"}`} />
              </button>
            ))}
          </div>
          <div className="mt-3 hidden gap-2 overflow-x-auto pb-1 lg:flex">
          {media.map((item, itemIndex) => (
            <button
              key={item.id}
              onClick={() => changeImage(itemIndex, itemIndex > index ? 1 : -1)}
              aria-label={`View image ${itemIndex + 1}`}
              aria-current={itemIndex === index ? "true" : undefined}
              className={`motion-press h-16 w-20 shrink-0 overflow-hidden border bg-[#f1f2ee] transition-[transform,border-color] duration-[180ms] hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057c8] sm:h-20 sm:w-28 lg:h-28 lg:w-40 ${itemIndex === index ? "border-2 border-[#0057c8]" : "border-[#111111]/15"}`}
            >
              <Media item={item} name="" />
            </button>
          ))}
        </div>
        </>
      ) : null}
    </section>
  );
}

function AddToCart({ disabled, className = "", onClick }) {
  return (
    <button
      type="button"
      disabled={disabled}
      onClick={onClick}
      className={`motion-press flex min-h-14 items-center justify-between bg-[#0057c8] px-5 text-[.65rem] font-bold uppercase tracking-[.16em] text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057c8] focus-visible:ring-offset-4 enabled:hover:bg-[#111111] disabled:cursor-not-allowed disabled:bg-[#d6d6d3] disabled:text-[#111111]/55 ${className}`}
    >
      <span>Add to cart</span>
      <ShoppingBag size={18} />
    </button>
  );
}

export default function ProductShow({
  product,
  options = [],
  variants = [],
  initial_selection: initialSelection,
}) {
  const pageRef = useRef(null);
  const technicalRef = useRef(null);
  const sharedEntranceRef = useRef(null);
  const previousCommerceRef = useRef(null);
  const initialVariant = initialSelection
    ? variants.find((variant) => variant.id === initialSelection.variant_id)
    : null;
  const [variantId, setVariantId] = useState(
    initialVariant?.id ?? (variants.length === 1 ? variants[0].id : null),
  );
  const [selections, setSelections] = useState(() =>
    Object.fromEntries(
      (
        initialVariant?.skus.find((sku) => sku.id === initialSelection?.sku_id)
          ?.option_values ?? []
      ).map((value) => [value.option_id, value.id]),
    ),
  );
  const [quantity, setQuantity] = useState(1);
  const { addItem, loading: bagLoading, setBagOpen } = useCommerce();
  const quantityRef = useRef(null);
  const previousQuantityRef = useRef(1);
  const variant = variants.find((item) => item.id === variantId) ?? null;
  const selectedSku = useMemo(
    () => resolveSku(variant, options, selections),
    [variant, options, selections],
  );
  const viable = (next) =>
    variant?.skus.some((sku) =>
      Object.entries(next).every(([optionId, valueId]) =>
        sku.option_values.some(
          (value) =>
            value.option_id === Number(optionId) && value.id === valueId,
        ),
      ),
    ) ?? false;
  const selectVariant = (id) => {
    if (id === variantId) return;

    setVariantId(id);
    setSelections({});
    setQuantity(1);
  };
  const gallery = variant?.media?.length
    ? variant.media
    : (product?.media ?? []);
  const availability = selectedSku
    ? {
        in_stock: "In stock",
        backorder: "Backorder",
        out_of_stock: "Out of stock",
      }[selectedSku.availability]
    : null;
  const availabilityTone = selectedSku
    ? {
        in_stock: "text-[#287a48]",
        backorder: "text-[#a55b00]",
        out_of_stock: "text-[#9e3030]",
      }[selectedSku.availability]
    : null;
  const specificationEntries = Object.entries(variant?.specifications ?? {}).filter(
    ([key, value]) => key !== "colour_hex" && labels[key] && value !== null && value !== "",
  );
  const factualSummary = [
    variant?.specifications?.frame_shape,
    variant?.specifications?.material,
  ]
    .filter(Boolean)
    .join(" · ");
  const commerceKey = `${selectedSku?.id ?? "none"}:${selectedSku?.price?.amount_minor ?? "unknown"}:${selectedSku?.availability ?? "unknown"}`;
  const addToBag = async () => {
    if (!selectedSku?.id) return;
    try { await addItem(selectedSku.id, quantity); setBagOpen(true); } catch { setBagOpen(true); }
  };

  useLayoutEffect(() => {
    if (previousQuantityRef.current === quantity) return undefined;
    previousQuantityRef.current = quantity;
    if (motionIsReduced() || !quantityRef.current) return undefined;
    const context = gsap.context(() => {
      gsap.fromTo(quantityRef.current, { opacity: 0.6, y: 4 }, { opacity: 1, y: 0, duration: 0.16, ease: "power3.out", clearProps: "opacity,transform" });
    }, quantityRef);
    return () => context.revert();
  }, [quantity]);

  useLayoutEffect(() => {
    sharedEntranceRef.current ??= consumeProductTransition(product);
    const context = gsap.context(() => {
      if (motionIsReduced()) return;
      const entrance = gsap.timeline({ delay: sharedEntranceRef.current ? 0.46 : 0.06, defaults: { ease: "power3.out" } });
      entrance
        .from("[data-motion-identity]", { autoAlpha: 0, y: 30, duration: 0.46, stagger: 0.07 }, 0)
        .from("[data-motion-summary]", { autoAlpha: 0, y: 24, duration: 0.42, stagger: 0.07 }, 0.13)
        .from("[data-motion-detail]", { autoAlpha: 0, y: 20, duration: 0.4, stagger: 0.07 }, 0.27)
        .from("[data-motion-controls]", { autoAlpha: 0, y: 22, duration: 0.42, stagger: 0.07 }, 0.4)
        .from("[data-motion-sticky]", { autoAlpha: 0, y: 20, duration: 0.4 }, 0.35);
    }, pageRef);
    return () => context.revert();
  }, [product?.id]);

  useLayoutEffect(() => {
    if (previousCommerceRef.current === null) {
      previousCommerceRef.current = commerceKey;
      return;
    }
    if (previousCommerceRef.current === commerceKey) return;
    previousCommerceRef.current = commerceKey;
    if (motionIsReduced()) return;
    const context = gsap.context(() => {
      gsap.fromTo("[data-commerce-state]", { opacity: 0.6, y: 12 }, {
        opacity: 1, y: 0, duration: 0.3, ease: "power3.out", stagger: 0.05, clearProps: "opacity,transform", overwrite: true,
      });
    }, pageRef);
    return () => context.revert();
  }, [commerceKey]);

  useScrubScenes(pageRef, `${product?.id}:${specificationEntries.length}`, (page, mobile) => {
    const gallerySection = page.querySelector("[data-pdp-gallery]");
    if (gallerySection) {
      const timeline = scrollScene(gallerySection, { start: "top 76px", end: "bottom 25%" });
      timeline.to(gallerySection.querySelector("picture"), { y: mobile ? -18 : -40, scale: mobile ? 1.025 : 1.045, ease: "none", duration: 1 }, 0);
    }
    const technical = technicalRef.current;
    if (technical) {
      const timeline = scrollScene(technical, { start: "top 92%", end: "bottom bottom", scrub: MOTION.scrollScrubEditorial });
      addScrollTrack(timeline, technical.querySelector("[data-technical-eyebrow]"), { y: mobile ? 12 : 22, opacity: 0.65 }, { y: mobile ? -25 : -34, opacity: 0.85 }, { enterEnd: 0.2, exitStart: 0.8 });
      addScrollTrack(timeline, technical.querySelector("[data-technical-title]"), { y: mobile ? 22 : 46, opacity: 0.7 }, { y: mobile ? -10 : -14, opacity: 0.88 }, { enterEnd: 0.3, exitStart: 0.76 });
      addScrollTrack(timeline, technical.querySelector("[data-technical-rule]"), { scaleX: 0.2 }, { scaleX: 0.96 }, { enterEnd: 0.35, exitStart: 0.78 });
      technical.querySelectorAll("[data-technical-cell]").forEach((cell, index) => {
        addScrollTrack(timeline, cell, { y: (mobile ? 16 : 32) + index * 3, opacity: 0.7 }, { y: -5, opacity: 0.88 }, { enterEnd: 0.36, exitStart: 0.76 });
      });
    }
  });
  return (
    <>
      <Head title={`${product?.name ?? "Product"} | The Sparkland`} />
      <Navbar />
      <main ref={pageRef} className="bg-white pb-[calc(7rem+env(safe-area-inset-bottom))] pt-[76px] text-[#111111] lg:pb-0">
        <div className="mx-auto max-w-[1440px] px-5 py-4 sm:px-8 lg:px-12 lg:py-7 xl:px-14">
          <p className="mb-4 text-[.62rem] font-bold uppercase tracking-[.18em] text-[#111111]/45 lg:mb-5">
            Shop / {product?.name}
          </p>
          <div className="lg:grid lg:grid-cols-12 lg:items-start lg:gap-8 xl:gap-10">
            <div className="-mx-5 sm:mx-0 lg:col-span-8">
              <Gallery
                key={variant?.id ?? "product"}
                media={gallery}
                name={product?.name ?? "Product"}
                product={product}
              />
            </div>
            <section className="mt-4 lg:col-span-4 lg:mt-0 lg:sticky lg:top-[104px] lg:self-start lg:pt-1">
              <p data-motion-identity className="text-[.58rem] font-bold uppercase tracking-[.18em] text-[#0057c8]">
                {product?.product_type}
              </p>
              <div className="mt-2 flex items-end justify-between gap-3 lg:block">
                <div>
                  <h1 data-motion-identity className={`${display} text-[clamp(2.5rem,12vw,3rem)] lg:text-[clamp(3.6rem,5.4vw,6rem)]`}>
                    {product?.name}
                  </h1>
                  {factualSummary ? (
                    <p data-motion-summary className="mt-1 text-sm font-medium leading-5 text-[#111111]/65">
                      {factualSummary}
                    </p>
                  ) : null}
                </div>
                {priceText(selectedSku?.price) ? (
                  <div data-motion-summary data-commerce-state className="shrink-0 text-right lg:hidden">
                    <p className="text-[1.5rem] font-bold leading-none text-[#0057c8]">
                      {priceText(selectedSku.price)}
                    </p>
                    {selectedSku.sale ? (
                      <div className="mt-1 flex items-center justify-end gap-2 text-[.62rem]">
                        <s className="text-[#111111]/45">
                          {selectedSku.price.currency}{" "}
                          {(selectedSku.sale.compare_at_minor / 100).toFixed(2)}
                        </s>
                        <span className="bg-[#0057c8] px-1.5 py-0.5 text-[.52rem] font-bold text-white">
                          -{selectedSku.sale.discount_percentage}%
                        </span>
                      </div>
                    ) : null}
                  </div>
                ) : null}
              </div>
              <div className="mt-2 border-b border-[#111111]/15 pb-3 lg:mt-4 lg:pb-4">
                {priceText(selectedSku?.price) ? (
                  <div data-motion-summary data-commerce-state className="hidden flex-wrap items-center gap-3 lg:flex">
                    <p className="text-[1.55rem] font-bold leading-none text-[#0057c8] lg:text-[1.75rem]">
                      {priceText(selectedSku.price)}
                    </p>
                    {selectedSku.sale ? (
                      <>
                        <s className="text-sm text-[#111111]/45">
                          {selectedSku.price.currency}{" "}
                          {(selectedSku.sale.compare_at_minor / 100).toFixed(2)}
                        </s>
                        <span className="bg-[#0057c8] px-2 py-1 text-[.55rem] font-bold text-white">
                          -{selectedSku.sale.discount_percentage}%
                        </span>
                      </>
                    ) : null}
                  </div>
                ) : (
                  <p data-motion-summary data-commerce-state className="text-sm text-[#111111]/55">Select options</p>
                )}
                {availability ? (
                  <p data-motion-detail data-commerce-state className={`mt-2 flex items-center gap-2 text-[.62rem] font-bold uppercase tracking-[.14em] ${availabilityTone}`}>
                    <span className="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true" />
                    {availability}
                  </p>
                ) : null}
              </div>
              {product?.description ? (
                <p data-motion-detail className="mt-3 max-w-[34rem] text-[.84rem] leading-[1.55] text-[#111111]/70 lg:mt-4 lg:text-sm lg:leading-6">
                  {product.description}
                </p>
              ) : null}
              {variants.length > 1 ? (
                <fieldset data-motion-controls className="mt-5">
                  <legend className="text-[.6rem] font-bold uppercase tracking-[.16em]">
                    Variant
                  </legend>
                  <div className="mt-3 flex flex-wrap gap-2">
                    {variants.map((item) => (
                      <button
                        key={item.id}
                        onClick={() => selectVariant(item.id)}
                        aria-pressed={item.id === variantId}
                        className={`motion-press min-h-11 border px-3 text-[.62rem] font-bold uppercase tracking-[.12em] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057c8] ${item.id === variantId ? "border-[#0057c8] bg-[#0057c8] text-white" : "border-[#111111]/25"}`}
                      >
                        {item.specifications?.colour_hex ? (
                          <span
                            className="mr-2 inline-block h-3 w-3 rounded-full border"
                            style={{
                              backgroundColor: item.specifications.colour_hex,
                            }}
                          />
                        ) : null}
                        {item.name}
                      </button>
                    ))}
                  </div>
                </fieldset>
              ) : null}
              {variant
                ? options.map((option) => (
                    <fieldset key={option.id} data-motion-controls className="mt-5">
                      <legend className="text-[.6rem] font-bold uppercase tracking-[.16em]">
                        {option.name}
                        {option.required ? " (required)" : ""}
                      </legend>
                      <div className="mt-3 flex flex-wrap gap-2">
                        {option.values.map((value) => {
                          const next = { ...selections, [option.id]: value.id };
                          return (
                            <button
                              key={value.id}
                              disabled={!viable(next)}
                              onClick={() => {
                                if (selections[option.id] === value.id) return;

                                const nextSku = resolveSku(
                                  variant,
                                  options,
                                  next,
                                );
                                setSelections(next);
                                if (nextSku?.id !== selectedSku?.id) {
                                  setQuantity(1);
                                }
                              }}
                              aria-pressed={selections[option.id] === value.id}
                              className={`motion-press min-h-11 border px-3 text-[.62rem] font-bold uppercase tracking-[.1em] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057c8] disabled:opacity-30 ${selections[option.id] === value.id ? "border-[#0057c8] bg-[#0057c8] text-white" : "border-[#111111]/25"}`}
                            >
                              {value.label}
                            </button>
                          );
                        })}
                      </div>
                    </fieldset>
                  ))
                : null}
              <div data-motion-controls className="mt-[18px] flex flex-col items-start gap-2 lg:mt-5">
                <span className="text-[.6rem] font-bold uppercase tracking-[.16em]">
                  Quantity
                </span>
                <div className="flex h-11 w-32 items-center border border-[#111111]/20">
                  <button
                    onClick={() =>
                      setQuantity((value) => Math.max(1, value - 1))
                    }
                    className="motion-press h-full w-11 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057c8]"
                    aria-label="Decrease quantity"
                  >
                    <Minus size={16} className="mx-auto" />
                  </button>
                  <output ref={quantityRef} className="min-w-8 text-center">{quantity}</output>
                  <button
                    onClick={() => setQuantity((value) => value + 1)}
                    className="motion-press h-full w-11 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057c8]"
                    aria-label="Increase quantity"
                  >
                    <Plus size={16} className="mx-auto" />
                  </button>
                </div>
              </div>
              <div data-motion-controls className="hidden lg:block">
                <AddToCart
                disabled={!selectedSku?.can_purchase || bagLoading}
                className="mt-4 w-full"
                onClick={addToBag}
                />
              </div>
            </section>
          </div>
          {specificationEntries.length ? (
            <section ref={technicalRef} className="mt-8 overflow-hidden border-y border-[#111111]/15 py-5 lg:mt-12 lg:py-8">
              <div className="flex items-end justify-between gap-6">
                <div>
                  <p data-technical-eyebrow className="text-[.58rem] font-bold uppercase tracking-[.18em] text-[#0057c8]">
                    Frame
                  </p>
                  <h2 data-technical-title className={`${display} mt-2 text-[clamp(1.7rem,7.5vw,2rem)] lg:text-[clamp(2rem,3vw,3.25rem)]`}>
                    Technical data
                  </h2>
                </div>
                <p className="max-w-[18ch] text-right text-[.62rem] leading-5 text-[#111111]/48">
                  Factual frame specifications.
                </p>
              </div>
              <div data-technical-rule aria-hidden="true" className="mt-5 origin-left border-t border-[#111111]/12 lg:mt-7" />
              <dl className="grid grid-cols-1 gap-x-6 min-[360px]:grid-cols-2 lg:grid-cols-4 lg:gap-x-8">
                {specificationEntries.map(([key, value]) => (
                  <div
                    key={key}
                    data-technical-cell className="border-b border-[#111111]/12 py-4"
                  >
                    <dt className="text-[.58rem] font-bold uppercase tracking-[.15em] text-[#111111]/48">
                      {labels[key]}
                    </dt>
                    <dd className="mt-2 text-sm font-medium leading-5 text-[#111111]">
                      {dimensions.has(key) ? `${value} mm` : value}
                    </dd>
                  </div>
                ))}
              </dl>
            </section>
          ) : null}
        </div>
        <div data-motion-sticky className="fixed inset-x-0 bottom-0 z-40 border-t border-[#111111]/15 bg-white px-4 pb-[calc(0.75rem+env(safe-area-inset-bottom))] pt-3 lg:hidden">
          <AddToCart disabled={!selectedSku?.can_purchase || bagLoading} className="w-full" onClick={addToBag} />
        </div>
      </main>
    </>
  );
}
