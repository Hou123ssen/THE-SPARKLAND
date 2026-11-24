let pendingProduct = null;

export function productTransitionName(product) {
  return `sparkland-product-${String(product?.id ?? product?.slug).replace(/[^a-zA-Z0-9_-]/g, "-")}`;
}

export function motionIsReduced() {
  return typeof window !== "undefined" && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
}

export function markProductTransition(product) {
  pendingProduct = { id: product.id, at: performance.now() };
}

export function consumeProductTransition(product) {
  const match = pendingProduct?.id === product?.id && performance.now() - pendingProduct.at < 4000;
  pendingProduct = null;
  return Boolean(match);
}

export function clearProductTransition() {
  pendingProduct = null;
}
