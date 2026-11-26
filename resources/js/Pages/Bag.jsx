import { Head } from "@inertiajs/react";
import { useEffect, useState } from "react";
import Navbar from "../Components/Navbar.jsx";
import MotionLink from "../Components/MotionLink.jsx";
import CartLineItem from "../Components/Commerce/CartLineItem.jsx";
import OrderSummary from "../Components/Commerce/OrderSummary.jsx";
import CouponControl from "../Components/Commerce/CouponControl.jsx";
import { useCommerce } from "../Components/Commerce/CommerceProvider.jsx";

export default function Bag() {
  const { cart, quote, initialized, loading, error, appliedCoupon, updateItem, removeItem, applyCoupon } = useCommerce();
  const [couponDraft, setCouponDraft] = useState(appliedCoupon || ""), [couponError, setCouponError] = useState("");
  useEffect(() => setCouponDraft(appliedCoupon || ""), [appliedCoupon]);
  const apply = async () => { setCouponError(""); try { await applyCoupon(couponDraft); } catch (e) { setCouponError(e.message); } };
  const mutate = (action) => action().catch(() => {});
  const content = !initialized ? <p className="mt-12 border-y border-[#111111]/12 py-10 text-sm text-[#111111]/55">Chargement du panier...</p> : cart?.items?.length ? <div className="mt-10 grid gap-10 lg:grid-cols-[1fr_22rem]"><section>{cart.items.map((item) => <CartLineItem key={item.id} item={item} loading={loading} onIncrement={(line) => mutate(() => updateItem(line.id, line.quantity + 1))} onDecrement={(line) => mutate(() => updateItem(line.id, line.quantity - 1))} onRemove={(line) => mutate(() => removeItem(line.id))} />)}<div className="mt-8"><CouponControl value={couponDraft} onChange={(value) => { setCouponDraft(value); setCouponError(""); }} onApply={apply} loading={loading} error={couponError} /></div></section><OrderSummary quote={quote} itemCount={cart.item_count} disabled={!quote || cart.items.some((item) => !item.can_purchase)} onCheckout={() => { window.location.href = "/checkout"; }} actionLabel="Passer la commande" /></div> : <section className="mt-12 border-y border-[#111111]/12 py-12"><p className="text-lg">Votre panier est vide.</p><MotionLink href="/shop" className="mt-6 inline-flex min-h-12 items-center border border-[#0057c8] px-5 text-[.65rem] font-bold uppercase tracking-[.16em] text-[#0057c8]">Explorer les montures -&gt;</MotionLink></section>;
  return <><Head title="Bag panier | The Sparkland" /><Navbar /><main className="min-h-screen bg-white px-5 pb-20 pt-[106px] text-[#111111] sm:px-8 lg:px-12 xl:px-14"><div className="mx-auto max-w-[1280px]"><p className="text-[.62rem] font-bold uppercase tracking-[.18em] text-[#0057c8]">Bag panier</p><div className="mt-3 flex items-end justify-between gap-4"><h1 className="font-headline text-5xl uppercase leading-[.84] sm:text-7xl">Mon panier</h1>{cart ? <span className="text-xs uppercase tracking-[.14em] text-[#111111]/50">{cart.item_count} articles</span> : null}</div>{error ? <p role="alert" className="mt-5 text-sm text-[#9e3030]">{error}</p> : null}{content}</div></main></>;
}
