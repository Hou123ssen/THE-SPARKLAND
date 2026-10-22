import { Menu, Search, ShoppingBag } from "lucide-react";

const navItems = [
  { label: "Shop", href: "/shop" },
  { label: "Collections", href: "/" },
  { label: "Journal", href: "/" },
  { label: "About", href: "/" },
];

function Navbar() {
  return (
    <nav
      className="fixed inset-x-0 top-0 z-50 bg-white"
      aria-label="Primary navigation"
    >
      <div className="relative mx-auto flex h-[76px] max-w-[1440px] items-center justify-between border-b border-[#111111]/10 px-6 sm:px-8 lg:px-12 xl:px-14">
        <a
          href="/"
          className="absolute left-1/2 inline-flex -translate-x-1/2 items-center text-[0.68rem] font-semibold uppercase tracking-[0.25em] text-[#111111] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#111111] focus-visible:ring-offset-4 focus-visible:ring-offset-white"
          aria-label="The Sparkland home"
        >
          <img
            src="/images/sparkland-logo.png"
            alt="The Sparkland"
            className="block h-auto w-[100px] object-contain"
          />
        </a>

        <div className="hidden items-center gap-7 md:flex lg:gap-9">
          {navItems.map((item) => (
            <a
              key={item.label}
              href={item.href}
              className="text-[0.65rem] font-medium uppercase tracking-[0.2em] text-[#111111] transition-colors hover:text-neutral-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#111111] focus-visible:ring-offset-4 focus-visible:ring-offset-white"
            >
              {item.label}
            </a>
          ))}
        </div>

        <div className="ml-auto hidden items-center gap-1 md:flex">
          <button
            type="button"
            className="inline-flex h-10 w-10 items-center justify-center text-[#111111] transition-colors hover:text-neutral-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#111111] focus-visible:ring-offset-4 focus-visible:ring-offset-white"
            aria-label="Search"
          >
            <Search aria-hidden="true" size={19} strokeWidth={1.8} />
          </button>
          <button
            type="button"
            className="inline-flex h-10 w-10 items-center justify-center text-[#111111] transition-colors hover:text-neutral-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#111111] focus-visible:ring-offset-4 focus-visible:ring-offset-white"
            aria-label="Shopping bag"
          >
            <ShoppingBag aria-hidden="true" size={19} strokeWidth={1.8} />
          </button>
        </div>

        <button
          type="button"
          className="inline-flex h-10 w-10 items-center justify-center text-[#111111] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#111111] focus-visible:ring-offset-4 focus-visible:ring-offset-white md:hidden"
          aria-label="Open menu"
        >
          <Menu aria-hidden="true" size={24} strokeWidth={1.7} />
        </button>
      </div>
    </nav>
  );
}

export default Navbar;
