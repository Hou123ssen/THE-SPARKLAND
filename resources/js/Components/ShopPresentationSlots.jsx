// Temporary presentation slots. CMS/editorial data can replace these paths without
// becoming product catalogue data.
export const SHOP_PRESENTATION_SLOTS = {
  hero: {
    src: "/images/shop-demo/hero-main.png",
    alt: "",
  },
  heroEditorial: {
    src: "/images/shop-demo/hero-side.png",
    alt: "",
  },
  featuredCollection: {
    src: "/images/shop-demo/featured-collection.png",
    alt: "",
  },
  detail: [
    { src: "/images/shop-demo/detail-01.png", alt: "" },
    { src: "/images/shop-demo/detail-02.png", alt: "" },
    { src: "/images/shop-demo/detail-03.png", alt: "" },
  ],
  detailAction: {
    src: "/images/shop-demo/detail-action.png",
    alt: "",
  },
  editorial: {
    src: "/images/shop-demo/editorial-dark.png",
    alt: "",
  },
};

// Temporary editorial content, kept separate from catalogue facts.
export const SHOP_PRESENTATION_CONTENT = {
  hero: {
    eyebrow: "Shop / All frames",
    heading: ["Find", "Your", "Frame."],
    copy: "A considered edit of the current catalogue.",
    cta: { label: "Explore frames", href: "#catalogue" },
  },
  heroEditorial: {
    eyebrow: "Campaign / 01",
    heading: ["New angles", "of view."],
    copy: "A visual space for the current campaign.",
    cta: { label: "Browse frames", href: "#catalogue" },
  },
  collection: {
    eyebrow: "Featured collection",
    heading: ["The everyday", "edit."],
    copy: "A neutral collection space ready for the next campaign release.",
    cta: { label: "Browse frames", href: "#catalogue" },
    imageLabel: "Collection / 02",
    detailLabel: "Detail / 01",
    panelEyebrow: "Edit notes",
    panelHeading: ["A clear point", "of view."],
    panelCta: { label: "Browse frames", href: "#catalogue" },
  },
  detail: {
    eyebrow: "Detail / material story",
    heading: ["Look", "closer."],
    copy: "A visual system for form, finish and the way a frame meets the light.",
    imageLabels: ["01", "02", "03"],
    actionEyebrow: "Your frame",
    actionHeading: ["Find the", "right view."],
    cta: { label: "Browse all frames", href: "#catalogue" },
  },
  editorial: {
    eyebrow: "The Sparkland / editorial",
    heading: ["The world", "in focus."],
    copy: "A flexible editorial space for future stories, ideas and campaign releases.",
    cta: { label: "Explore frames", href: "#catalogue" },
  },
};
