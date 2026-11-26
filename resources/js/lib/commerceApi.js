const csrf = () => typeof document === "undefined" ? "" : document.querySelector('meta[name="csrf-token"]')?.content ?? "";

export async function commerceRequest(path, { method = "GET", body, signal } = {}) {
  const response = await fetch(path, {
    method, credentials: "same-origin", signal,
    headers: { Accept: "application/json", "Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest", ...(method !== "GET" ? { "X-CSRF-TOKEN": csrf() } : {}) },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    const error = new Error(data.message || "Unable to complete the request.");
    error.kind = response.status === 422 ? "validation" : "server";
    error.status = response.status; error.errors = data.errors ?? {}; error.data = data;
    throw error;
  }
  return data;
}
export const fetchCart = () => commerceRequest("/cart");
export const addCartItem = (sku_id, quantity) => commerceRequest("/cart/items", { method: "POST", body: { sku_id, quantity } });
export const updateCartItem = (id, quantity) => commerceRequest(`/cart/items/${id}`, { method: "PATCH", body: { quantity } });
export const removeCartItem = (id) => commerceRequest(`/cart/items/${id}`, { method: "DELETE" });
export const quoteCart = (body = {}) => commerceRequest("/cart/quote", { method: "POST", body });
export const placeOrder = (body) => commerceRequest("/checkout/orders", { method: "POST", body });
