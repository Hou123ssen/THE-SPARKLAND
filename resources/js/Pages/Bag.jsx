import { Head } from "@inertiajs/react";
import { useCallback, useEffect, useState } from "react";
import Navbar from "../Components/Navbar.jsx";
import MotionLink from "../Components/MotionLink.jsx";
import CartLineItem from "../Components/Commerce/CartLineItem.jsx";
import OrderSummary from "../Components/Commerce/OrderSummary.jsx";
import CouponControl from "../Components/Commerce/CouponControl.jsx";
import { fetchCart, quoteCart, updateCartItem, removeCartItem } from "../lib/commerceApi.js";

export default function Bag() {
  const [cart, setCart] = useState(null), [quote, setQuote] = useState(null), [coupon, setCoupon] = useState(""), [error, setError] = useState(""), [loading, setLoading] = useState(false);
  const refresh = useCallback(async (code = coupon) => { const result = await fetchCart(); setCart(result.cart); if (result.cart.items.length && result.cart.items.every((item) => item.can_purchase)) { const quoted = await quoteCart({ country_code: "MA", ...(code ? { coupon_code: code } : {}) }); setQuote(quoted.quote); } else setQuote(null); return result.cart; }, [coupon]);
  useEffect(() => { refresh().catch((e) => setError(e.message)); }, [refresh]);
  const mutate = async (fn) => { setLoading(true); setError(""); try { await fn(); await refresh(); } catch (e) { setError(e.message); } finally { setLoading(false); } };
  const applyCoupon = () => mutate(() => refresh(coupon));
  return <><Head title="Your bag | The Sparkland" /><Navbar /><main className="min-h-screen bg-white px-5 pb-20 pt-[106px] text-[#111111] sm:px-8 lg:px-12 xl:px-14"><div className="mx-auto max-w-[1280px]"><p className="text-[.62rem] font-bold uppercase tracking-[.18em] text-[#0057c8]">The Sparkland / Commerce</p><div className="mt-3 flex items-end justify-between gap-4"><h1 className="font-headline text-6xl uppercase leading-[.84] sm:text-8xl">Your bag</h1>{cart ? <span className="text-xs uppercase tracking-[.14em] text-[#111111]/50">{cart.item_count} items</span> : null}</div>{error ? <p role="alert" className="mt-5 text-sm text-[#9e3030]">{error}</p> : null}{cart?.items?.length ? <div className="mt-12 grid gap-10 lg:grid-cols-[1fr_22rem]"><section>{cart.items.map((item) => <CartLineItem key={item.id} item={item} loading={loading} onIncrement={(line) => mutate(() => updateCartItem(line.id, line.quantity + 1))} onDecrement={(line) => mutate(() => updateCartItem(line.id, line.quantity - 1))} onRemove={(line) => mutate(() => removeCartItem(line.id))} />)}<div className="mt-8"><CouponControl value={coupon} onChange={setCoupon} onApply={applyCoupon} loading={loading} error={error} /></div></section><OrderSummary quote={quote} itemCount={cart.item_count} disabled={!quote || cart.items.some((item) => !item.can_purchase)} onCheckout={() => { window.location.href = "/checkout"; }} /></div> : <section className="mt-16 border-y border-[#111111]/12 py-12"><p className="text-lg">Your bag is empty.</p><MotionLink href="/shop" className="mt-6 inline-flex min-h-12 items-center border border-[#0057c8] px-5 text-[.65rem] font-bold uppercase tracking-[.16em] text-[#0057c8]">Explore frames <span className="ml-8">→</span></MotionLink></section>}</div></main></>;
}
