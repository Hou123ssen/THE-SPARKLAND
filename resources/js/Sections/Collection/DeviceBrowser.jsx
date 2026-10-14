import { useRef, useState } from "react";
import ProductGrid from "./ProductGrid.jsx";

const screenGeometry = {
  clipPath: "polygon(1.2% 2.2%, 50% 4.8%, 98.8% 2.2%, 98.8% 97.8%, 50% 95.2%, 1.2% 97.8%)",
};

function DeviceBrowser({ catalog, browserRef, screenCoverRef, onProductSelect }) {
  const [collectionIndex, setCollectionIndex] = useState(0);
  const swipeStartX = useRef(null);
  const collections = catalog.filter((collection) => collection.products?.length).map((collection) => ({
    ...collection,
    products: collection.products.slice(0, 16),
  }));
  const collection = collections[collectionIndex] || collections[0];

  const showPrevious = () => {
    setCollectionIndex((index) => (index - 1 + collections.length) % collections.length);
  };

  const showNext = () => {
    setCollectionIndex((index) => (index + 1) % collections.length);
  };

  const handlePointerDown = (event) => {
    swipeStartX.current = event.clientX;
  };

  const handlePointerUp = (event) => {
    if (swipeStartX.current === null) return;
    const distance = event.clientX - swipeStartX.current;
    swipeStartX.current = null;
    if (Math.abs(distance) > 40) {
      distance < 0 ? showNext() : showPrevious();
    }
  };

  if (!collection) return null;

  return (
    <>
      <div
        ref={screenCoverRef}
        className="pointer-events-none absolute left-[20.70%] top-[20.70%] z-[11] h-[59.31%] w-[61.17%] bg-white opacity-0"
        style={screenGeometry}
        aria-hidden="true"
      >
        <span
          className="pointer-events-none absolute left-1/2 top-[5%] z-10 h-[90%] w-[0.45%] -translate-x-1/2 bg-[#111111]/90"
          aria-hidden="true"
        />
      </div>

      <div
        ref={browserRef}
        className="pointer-events-none absolute inset-0 z-[12] opacity-0"
        aria-label="Collection product browser"
      >
        <div
          className="absolute left-[20.70%] top-[20.70%] h-[59.31%] w-[61.17%] overflow-hidden bg-transparent"
          style={screenGeometry}
          onPointerDown={handlePointerDown}
          onPointerUp={handlePointerUp}
          onPointerCancel={() => { swipeStartX.current = null; }}
        >
          <span
            className="pointer-events-none absolute left-1/2 top-[5%] z-20 h-[90%] w-[0.45%] -translate-x-1/2 bg-[#111111]/90"
            aria-hidden="true"
          />
          <div className="absolute inset-0 transition-opacity duration-300">
            <ProductGrid
              key={collection.id}
              collectionId={collection.id}
              products={collection.products}
              onProductSelect={onProductSelect}
            />
          </div>

          {collections.length > 1 && (
            <div className="pointer-events-none absolute inset-x-[2%] top-1/2 flex -translate-y-1/2 justify-between">
              <button
                type="button"
                className="pointer-events-auto hidden h-7 w-7 items-center justify-center bg-white/70 text-sm text-[#111111] md:flex"
                onClick={showPrevious}
                aria-label="Previous collection"
              >
                ←
              </button>
              <button
                type="button"
                className="pointer-events-auto hidden h-7 w-7 items-center justify-center bg-white/70 text-sm text-[#111111] md:flex"
                onClick={showNext}
                aria-label="Next collection"
              >
                →
              </button>
            </div>
          )}

          <div className="pointer-events-none absolute bottom-[1.25%] left-1/2 -translate-x-1/2 whitespace-nowrap font-sans text-[clamp(0.4rem,0.65vw,0.56rem)] font-semibold uppercase tracking-[0.16em] text-[#111111]/50">
            {String(collectionIndex + 1).padStart(2, "0")} / {String(collections.length).padStart(2, "0")}
          </div>
        </div>
      </div>
    </>
  );
}

export default DeviceBrowser;
