<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

class ProductMediaSelector
{
    /** @return Collection<int, ProductMedia> */
    public function forProduct(Product $product, ?ProductVariant $variant = null): Collection
    {
        $productMedia = ProductMedia::query()
            ->where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->with('mediaAsset.derivatives')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        if ($variant === null) {
            return $productMedia;
        }

        $variantMedia = ProductMedia::query()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant->id)
            ->with('mediaAsset.derivatives')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        $variantRoles = $variantMedia->pluck('role')->unique();

        return $productMedia
            ->reject(fn (ProductMedia $media): bool => $variantRoles->contains($media->role))
            ->concat($variantMedia)
            ->sortBy(fn (ProductMedia $media): array => [$media->display_order, $media->id])
            ->values();
    }
}
