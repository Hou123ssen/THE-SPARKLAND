<?php

namespace App\Services;

use App\Models\Collection;

class PublicCatalog
{
    /**
     * Return the active, ordered catalog in the shape used by the public site.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return Collection::query()
            ->select(['id', 'name', 'slug', 'description', 'display_order'])
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->with(['products' => function ($query): void {
                $query
                    ->select([
                        'products.id',
                        'products.collection_id',
                        'products.name',
                        'products.slug',
                        'products.description',
                        'products.price',
                        'products.color',
                        'products.material',
                        'products.lens',
                        'products.frame',
                        'products.dimensions',
                    ])
                    ->where('products.is_active', true)
                    ->where('products.status', 'active')
                    ->where(function ($query): void {
                        $query->whereNull('products.published_at')->orWhere('products.published_at', '<=', now());
                    })
                    ->orderBy('collection_product.display_order')
                    ->orderBy('products.display_order')
                    ->orderBy('products.id')
                    ->with(['images' => function ($query): void {
                        $query
                            ->select(['id', 'product_id', 'image_path', 'type', 'display_order'])
                            ->orderBy('display_order')
                            ->orderBy('id');
                    }]);
            }])
            ->get()
            ->map(fn (Collection $collection): array => [
                'id' => $collection->id,
                'name' => $collection->name,
                'slug' => $collection->slug,
                'description' => $collection->description,
                'products' => $collection->products->map(fn ($product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'description' => $product->description,
                    'price' => $product->price,
                    'color' => $product->color,
                    'material' => $product->material,
                    'lens' => $product->lens,
                    'frame' => $product->frame,
                    'dimensions' => $product->dimensions,
                    'images' => $product->images->map(fn ($image): array => [
                        'id' => $image->id,
                        'image_path' => $image->image_path,
                        'type' => $image->type,
                    ])->values()->all(),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
