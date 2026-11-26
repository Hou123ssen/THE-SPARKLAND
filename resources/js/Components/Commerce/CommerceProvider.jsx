import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from "react";
import { addCartItem, fetchCart, quoteCart, removeCartItem, updateCartItem } from "../../lib/commerceApi.js";

const CommerceContext = createContext(null);
const couponKey = "sparkland.appliedCoupon";
const readCoupon = () => typeof sessionStorage === "undefined" ? "" : sessionStorage.getItem(couponKey) || "";
const writeCoupon = (code) => { if (typeof sessionStorage === "undefined") return; code ? sessionStorage.setItem(couponKey, code) : sessionStorage.removeItem(couponKey); };

export function CommerceProvider({ children }) {
  const [cart, setCart] = useState(null), [quote, setQuote] = useState(null), [appliedCoupon, setAppliedCoupon] = useState(readCoupon), [loading, setLoading] = useState(false), [error, setError] = useState(""), [bagOpen, setBagOpen] = useState(false), [initialized, setInitialized] = useState(false);
  const couponRef = useRef(appliedCoupon);
  const refresh = useCallback(async (coupon = couponRef.current) => { const result = await fetchCart(); setCart(result.cart); if (result.cart.items.length && result.cart.items.every((item) => item.can_purchase)) { try { const resultQuote = await quoteCart({ country_code: "MA", ...(coupon ? { coupon_code: coupon } : {}) }); setQuote(resultQuote.quote); } catch (err) { if (coupon) { couponRef.current = ""; writeCoupon(""); setAppliedCoupon(""); const fallback = await quoteCart({ country_code: "MA" }); setQuote(fallback.quote); } else throw err; } } else setQuote(null); setInitialized(true); return result.cart; }, []);
  useEffect(() => { refresh().catch((err) => { setError(err.message); setInitialized(true); }); }, [refresh]);
  const run = useCallback(async (action) => { setLoading(true); setError(""); try { const result = await action(); setCart(result.cart); await refresh(); return result.cart; } catch (err) { setError(err.message); throw err; } finally { setLoading(false); } }, [refresh]);
  const value = useMemo(() => ({ cart, quote, appliedCoupon, initialized, loading, error, bagOpen, setBagOpen, refresh, clearError: () => setError(""), addItem: (sku, qty) => run(() => addCartItem(sku, qty)), updateItem: (id, qty) => run(() => updateCartItem(id, qty)), removeItem: (id) => run(() => removeCartItem(id)), applyCoupon: async (draft) => { setLoading(true); setError(""); const code = draft.trim().toUpperCase(); try { const result = await quoteCart({ country_code: "MA", coupon_code: code }); setQuote(result.quote); couponRef.current = code; setAppliedCoupon(code); writeCoupon(code); return result.quote; } finally { setLoading(false); } }, clearCoupon: async () => { couponRef.current = ""; writeCoupon(""); setAppliedCoupon(""); try { await refresh(""); } catch (err) { setQuote(null); throw err; } }, resetAfterOrder: () => { couponRef.current = ""; writeCoupon(""); setAppliedCoupon(""); setCart({ item_count: 0, merchandise_subtotal: null, items: [] }); setQuote(null); setBagOpen(false); setInitialized(true); } }), [cart, quote, appliedCoupon, initialized, loading, error, bagOpen, refresh, run]);
  return <CommerceContext.Provider value={value}>{children}</CommerceContext.Provider>;
}
export function useCommerce() { const value = useContext(CommerceContext); if (!value) throw new Error("useCommerce must be used inside CommerceProvider"); return value; }
