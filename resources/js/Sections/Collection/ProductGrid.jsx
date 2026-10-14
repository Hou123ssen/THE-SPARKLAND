import { useEffect, useRef, useState } from "react";

function ProductGrid({ collectionId, products, onProductSelect }) {
  const dragStartX = useRef(null);
  const [entering, setEntering] = useState(true);

  useEffect(() => {
    const frame = requestAnimationFrame(() => setEntering(false));
    return () => cancelAnimationFrame(frame);
  }, [collectionId]);

  const getImage = (product) => product.images?.find((image) => image.type === "front")?.image_path
    || product.images?.[0]?.image_path;

  const handlePointerDown = (event) => {
    dragStartX.current = event.clientX;
    event.currentTarget.setPointerCapture?.(event.pointerId);
  };

  const handlePointerUp = (event) => {
    if (dragStartX.current === null) return;
    dragStartX.current = null;
    event.currentTarget.releasePointerCapture?.(event.pointerId);
  };

  return (
    <div
      className={`grid h-full w-full grid-cols-4 grid-rows-4 gap-x-[1%] gap-y-0 px-[4%] py-[3%] touch-pan-y transition-[opacity,transform] duration-300 ease-out ${entering ? "translate-x-2 opacity-0" : "translate-x-0 opacity-100"}`}
      onPointerDown={handlePointerDown}
      onPointerUp={handlePointerUp}
      onPointerCancel={handlePointerUp}
    >
      {products.map((product) => (
        <button
          key={product.id}
          type="button"
          className="group flex min-w-0 flex-col items-center justify-center text-center text-[#111111] transition-transform duration-300 hover:z-10 hover:scale-[1.05] focus-visible:outline focus-visible:outline-1 focus-visible:outline-[#111111]"
          onClick={() => onProductSelect?.(product)}
          aria-label={`Select ${product.name}`}
        >
          <span className="flex min-h-0 w-full flex-1 items-center justify-center overflow-hidden">
            <img
              src={getImage(product)}
              alt=""
              className="max-h-[94%] w-full object-contain mix-blend-multiply transition-opacity duration-300 group-hover:opacity-75"
              draggable="false"
            />
          </span>
        </button>
      ))}
    </div>
  );
}

export default ProductGrid;
