import { Minus, Plus, Trash2 } from "lucide-react";
import CommerceMoney from "./CommerceMoney.jsx";

export default function CartLineItem({ item, onIncrement, onDecrement, onRemove, loading = false }) {
  const source = item.media?.responsive?.fallback?.at(-1);
  return <article className={`flex gap-4 border-b border-[#111111]/12 py-5 ${item.can_purchase ? "" : "bg-[#f1f2ee]/60"}`}>
    <div className="h-28 w-28 shrink-0 bg-[#f1f2ee]">{source ? <img src={source.url} alt={item.media?.alt || item.product?.name} className="h-full w-full object-contain mix-blend-multiply" /> : null}</div>
    <div className="min-w-0 flex-1"><div className="flex justify-between gap-3"><div><h3 className="font-headline text-xl uppercase leading-none">{item.product?.name}</h3>{item.selected_options?.length ? <p className="mt-2 text-[.65rem] uppercase tracking-[.12em] text-[#111111]/55">{item.selected_options.map((option) => option.value?.label).join(" · ")}</p> : null}</div><button type="button" onClick={() => onRemove(item)} disabled={loading} aria-label={`Remove ${item.product?.name}`} className="inline-flex h-11 w-11 items-center justify-center text-[#111111]/55 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057c8]"><Trash2 size={16} /></button></div>
      {!item.can_purchase ? <p className="mt-2 text-xs font-bold text-[#9e3030]">Unavailable — review this item before checkout.</p> : null}
      <div className="mt-4 flex items-center justify-between"><div className="flex h-11 items-center border border-[#111111]/20"><button type="button" onClick={() => onDecrement(item)} disabled={loading || item.quantity <= 1} aria-label="Decrease quantity" className="h-full w-11 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057c8]"><Minus size={14} className="mx-auto" /></button><output className="w-9 text-center text-sm">{item.quantity}</output><button type="button" onClick={() => onIncrement(item)} disabled={loading} aria-label="Increase quantity" className="h-full w-11 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057c8]"><Plus size={14} className="mx-auto" /></button></div><CommerceMoney amount={item.line_total?.amount_minor} currency={item.line_total?.currency} className="text-sm font-bold" /></div>
    </div>
  </article>;
}
