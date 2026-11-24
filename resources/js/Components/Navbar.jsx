import { Menu, Search, ShoppingBag, X } from "lucide-react";
import { useEffect, useLayoutEffect, useRef, useState } from "react";
import gsap from "gsap";
import MotionLink from "./MotionLink.jsx";
import { MOTION, reducedMotion } from "./StorefrontMotion.js";

const navItems = [
  { label: "Shop", href: "/shop" },
  { label: "Collections", href: "/collections" },
  { label: "Journal" },
  { label: "About" },
];

export default function Navbar() {
  const [open, setOpen] = useState(false);
  const [mounted, setMounted] = useState(false);
  const buttonRef = useRef(null);
  const panelRef = useRef(null);
  const backdropRef = useRef(null);
  const firstLinkRef = useRef(null);

  useLayoutEffect(() => {
    if (!mounted) return undefined;
    if (reducedMotion()) {
      if (!open) setMounted(false);
      return undefined;
    }
    const context = gsap.context(() => {
      gsap.fromTo(backdropRef.current, { opacity: open ? 0 : 1 }, { opacity: open ? 1 : 0, duration: MOTION.control, ease: MOTION.ease });
      gsap.fromTo(panelRef.current, { yPercent: open ? -100 : 0, opacity: open ? 0 : 1 }, {
        yPercent: open ? 0 : -100, opacity: open ? 1 : 0, duration: MOTION.panel, ease: MOTION.ease,
        onComplete: () => { if (!open) setMounted(false); },
      });
      if (open) gsap.fromTo("[data-menu-link]", { y: -24, opacity: 0 }, { y: 0, opacity: 1, stagger: 0.075, duration: MOTION.control, delay: 0.16, ease: MOTION.ease });
    }, panelRef.current);
    return () => context.revert();
  }, [mounted, open]);

  useEffect(() => {
    if (!open) return undefined;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    firstLinkRef.current?.focus();
    const onKeyDown = (event) => {
      if (event.key === "Escape") { setOpen(false); buttonRef.current?.focus(); }
      if (event.key === "Tab" && panelRef.current) {
        const links = [...panelRef.current.querySelectorAll("a")];
        if (event.shiftKey && document.activeElement === links[0]) { event.preventDefault(); links.at(-1)?.focus(); }
        else if (!event.shiftKey && document.activeElement === links.at(-1)) { event.preventDefault(); links[0]?.focus(); }
      }
    };
    document.addEventListener("keydown", onKeyDown);
    return () => { document.body.style.overflow = previousOverflow; document.removeEventListener("keydown", onKeyDown); };
  }, [open]);

  const close = () => { setOpen(false); buttonRef.current?.focus(); };

  return <nav className="fixed inset-x-0 top-0 z-50 bg-white" aria-label="Primary navigation" style={{ viewTransitionName: "sparkland-navbar" }}>
    <div className="relative mx-auto flex h-[76px] max-w-[1440px] items-center justify-between border-b border-[#111111]/10 px-6 sm:px-8 lg:px-12 xl:px-14">
      <MotionLink href="/" className="absolute left-1/2 inline-flex -translate-x-1/2 items-center focus:outline-none focus-visible:ring-2 focus-visible:ring-[#111111] focus-visible:ring-offset-4" aria-label="The Sparkland home"><img src="/images/sparkland-logo.png" alt="The Sparkland" className="block h-auto w-[100px] object-contain" /></MotionLink>
      <div className="hidden items-center gap-7 md:flex lg:gap-9">{navItems.map((item) => item.href
        ? <MotionLink key={item.label} href={item.href} className="motion-nav-link text-[0.65rem] font-medium uppercase tracking-[0.2em] text-[#111111] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#111111] focus-visible:ring-offset-4">{item.label}</MotionLink>
        : <span key={item.label} className="text-[0.65rem] font-medium uppercase tracking-[0.2em] text-[#111111]/45">{item.label}</span>)}</div>
      <div className="ml-auto hidden items-center gap-1 md:flex">
        <span className="inline-flex h-10 w-10 items-center justify-center text-[#111111]/45" aria-label="Search unavailable"><Search aria-hidden="true" size={19} strokeWidth={1.8} /></span>
        <span className="inline-flex h-10 w-10 items-center justify-center text-[#111111]/45" aria-label="Shopping bag unavailable"><ShoppingBag aria-hidden="true" size={19} strokeWidth={1.8} /></span>
      </div>
      <button ref={buttonRef} type="button" onClick={() => { if (!mounted) setMounted(true); setOpen(!open); }} aria-expanded={open} aria-controls="sparkland-mobile-menu" aria-label={open ? "Close menu" : "Open menu"} className="motion-press inline-flex h-10 w-10 items-center justify-center text-[#111111] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#111111] focus-visible:ring-offset-4 md:hidden">{open ? <X aria-hidden="true" size={24} strokeWidth={1.7} /> : <Menu aria-hidden="true" size={24} strokeWidth={1.7} />}</button>
    </div>
    {mounted && <div id="sparkland-mobile-menu" className="fixed inset-x-0 bottom-0 top-[76px] md:hidden" aria-hidden={!open} inert={!open ? "" : undefined}>
      <button ref={backdropRef} type="button" onClick={close} tabIndex={open ? 0 : -1} aria-label="Close menu" className="absolute inset-0 w-full bg-[#111111]/35" />
      <div ref={panelRef} role="dialog" aria-modal="true" aria-label="Mobile navigation" className="relative bg-white px-6 pb-8 pt-5 shadow-lg">
        {navItems.map((item, index) => item.href
          ? <MotionLink ref={index === 0 ? firstLinkRef : undefined} data-menu-link key={item.label} href={item.href} onClick={() => setOpen(false)} className="motion-nav-link block border-b border-[#111111]/10 py-4 font-headline text-[2rem] font-black uppercase leading-none focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0057c8]">{item.label}</MotionLink>
          : <span data-menu-link key={item.label} className="block border-b border-[#111111]/10 py-4 font-headline text-[2rem] font-black uppercase leading-none text-[#111111]/35">{item.label}</span>)}
      </div>
    </div>}
  </nav>;
}
