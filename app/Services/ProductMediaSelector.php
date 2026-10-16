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
        $productMedia = $this->mediaFor($product)
            ->whereNull('product_variant_id')
            ->values();

        if ($variant === null) {
            return $productMedia;
        }

        $variantMedia = $this->mediaFor($product)
            ->where('product_variant_id', $variant->id)
            ->values();

        $variantRoles = $variantMedia->pluck('role')->unique();

        return $productMedia
            ->reject(fn (ProductMedia $media): bool => $variantRoles->contains($media->role))
            ->concat($variantMedia)
            ->sortBy(fn (ProductMedia $media): array => [$media->display_order, $media->id])
            ->values();
    }

    /** @return Collection<int, ProductMedia> */
    private function mediaFor(Product $product): Collection
    {
        if ($product->relationLoaded('media')) {
            return $product->media
                ->sortBy(fn (ProductMedia $media): array => [$media->display_order, $media->id])
                ->values();
        }

        return ProductMedia::query()
            ->where('product_id', $product->id)
            ->with('mediaAsset.derivatives')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }
}
