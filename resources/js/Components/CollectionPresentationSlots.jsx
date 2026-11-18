// Temporary campaign presentation slots, isolated from product/catalogue media.
// A future CMS source can replace this module without changing the page composition.
export const COLLECTION_PRESENTATION = {
  hero: { src: "/images/collections-demo/hero-main.png", alt: "" },
  rows: [
    { src: "/images/collections-demo/collection-01.png", position: "center 43%" },
    { src: "/images/collections-demo/collection-02.png", position: "center" },
    { src: "/images/collections-demo/collection-03.png", position: "center" },
    { src: "/images/collections-demo/collection-04.png", position: "center" },
  ],
  ending: {
    monochrome: "/images/collections-demo/editorial-blackwhite.png",
    architecture: "/images/collections-demo/editorial-architecture.png",
  },
};

export const collectionPresentation = (index) => COLLECTION_PRESENTATION.rows[index] ?? null;
