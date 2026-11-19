import { Head } from "@inertiajs/react";

export default function ProductShow({ product }) {
  return <><Head title={product ? `${product.name} | The Sparkland` : "Product | The Sparkland"} /><main><h1>{product?.name}</h1></main></>;
}
