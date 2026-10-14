import { useRef, useState } from "react";
import ProductGrid from "./ProductGrid.jsx";

const screenGeometry = {
  clipPath: "polygon(0.8% 4%, 50% 6%, 99.2% 4%, 99.2% 96%, 50% 94%, 0.8% 96%)",
};

function DeviceBrowser({ catalog, browserRef, screenCoverRef, onProductSelect }) {
  const [collectionIndex, setCollectionIndex] = useState(0);
  const swipeStartX = useRef(null);
  const collections = catalog.filter((collection) => collection.products?.length).map((collection) => ({
    ...collection,
    products: collection.products.slice(0, 6),
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
        className="pointer-events-none absolute left-[20.25%] top-[19.25%] z-[11] h-[61.5%] w-[62.5%] bg-white opacity-0"
        style={screenGeometry}
        aria-hidden="true"
      />

      <div
        ref={browserRef}
        className="pointer-events-none absolute inset-0 z-[12] opacity-0"
        aria-label="Collection product browser"
      >
        <div
          className="absolute left-[20.25%] top-[19.25%] h-[61.5%] w-[62.5%] overflow-hidden rounded-[4%] bg-transparent"
          style={screenGeometry}
          onPointerDown={handlePointerDown}
          onPointerUp={handlePointerUp}
          onPointerCancel={() => { swipeStartX.current = null; }}
        >
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

          <div className="pointer-events-none absolute bottom-[2.5%] left-1/2 -translate-x-1/2 whitespace-nowrap font-sans text-[clamp(0.42rem,0.75vw,0.62rem)] font-semibold uppercase tracking-[0.16em] text-[#111111]/60">
            {String(collectionIndex + 1).padStart(2, "0")} / {String(collections.length).padStart(2, "0")} &nbsp; {collection.name}
          </div>
        </div>
      </div>
    </>
  );
}

export default DeviceBrowser;
