<?php

namespace App\Services;

use App\Models\Price;
use App\Models\Product;
use App\Models\Sku;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Shared source of truth for public storefront product cards.
 *
 * Shop owns its facets and page contract; future collection listings can reuse
 * these rules while applying their own fixed collection scope and ordering.
 */
class StorefrontCatalog
{
    protected const VARIANT_FILTERS = [
        'shape' => 'frame_shape',
        'colour' => 'colour_name',
        'material' => 'material',
    ];

    public function __construct(protected readonly ProductMediaManifest $mediaManifest) {}

    protected function visibleProducts(): Builder
    {
        return Product::query()->tap(fn (Builder $query) => $this->applyPublicVisibility($query));
    }

    protected function applyPublicVisibility(Builder $query): void
    {
        $query
            ->where('products.is_active', true)
            ->where('products.status', Product::STATUS_ACTIVE)
            ->where(fn (Builder $published) => $published->whereNull('products.published_at')->orWhere('products.published_at', '<=', now()))
            ->whereHas('variants', fn (Builder $variants) => $this->applyVariantConstraints($variants));
    }

    /** @param array<string, string> $filters */
    protected function applyVariantConstraints($variants, array $filters = []): void
    {
        $variants
            ->where('is_active', true)
            ->whereHas('skus', fn (Builder $skus) => $skus->where('status', Sku::STATUS_ACTIVE));

        foreach (self::VARIANT_FILTERS as $filter => $column) {
            if (isset($filters[$filter])) {
                $variants->where($column, $filters[$filter]);
            }
        }
    }

    /** @param array<string, string> $filters */
    protected function applyVariantFilters(Builder $query, array $filters): void
    {
        if (array_intersect_key($filters, self::VARIANT_FILTERS) !== []) {
            $query->whereHas('variants', fn (Builder $variants) => $this->applyVariantConstraints($variants, $filters));
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, list<array{value: string, label: string}>>  $facets
     * @param  list<string>  $filterNames
     * @return array<string, string>
     */
    protected function validFilters(array $input, array $facets, array $filterNames): array
    {
        $filters = [];
        foreach ($filterNames as $name) {
            $options = $facets[$name] ?? [];
            $value = $input[$name] ?? null;
            if (is_string($value) && in_array($value, array_column($options, 'value'), true)) {
                $filters[$name] = $value;
            }
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, list<array{value: string, label: string}>>  $facets
     * @param  list<string>  $filterNames
     */
    protected function hasInvalidFacetInput(array $input, array $facets, array $filterNames): bool
    {
        foreach ($filterNames as $name) {
            if (! array_key_exists($name, $input) || $input[$name] === '') {
                continue;
            }

            if (! is_string($input[$name]) || ! in_array($input[$name], array_column($facets[$name] ?? [], 'value'), true)) {
                return true;
            }
        }

        return false;
    }

    protected function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'newest' => $query->orderByDesc('products.created_at')->orderByDesc('products.id'),
            default => $query->orderBy('products.display_order')->orderBy('products.id'),
        };
    }

    /** @param array<string, string> $filters */
    protected function catalogRelations(array $filters): array
    {
        return [
            'collections' => fn ($collections) => $collections->where('is_active', true)->orderBy('display_order')->orderBy('id'),
            'variants' => function ($variants) use ($filters): void {
                $this->applyVariantConstraints($variants, $filters);
                $variants
                    ->orderBy('display_order')
                    ->orderBy('id')
                    ->with(['skus' => function ($skus): void {
                        $skus
                            ->where('status', Sku::STATUS_ACTIVE)
                            ->orderBy('id')
                            ->with(['prices' => fn ($prices) => $this->currentPrices($prices)]);
                    }]);
            },
            'media' => fn ($media) => $media->with('mediaAsset.derivatives')->orderBy('display_order')->orderBy('id'),
        ];
    }

    protected function currentPrices($prices): void
    {
        $prices->current()->orderBy('id');
    }

    /** @return array<string, mixed> */
    protected function serializeProduct(Product $product): array
    {
        // A catalogue card represents one ordered sellable variant and SKU. We
        // intentionally never aggregate prices across variants or currencies.
        $variant = $product->variants->first();
        $sku = $variant?->skus->first();
        $price = $this->unambiguousCurrentPrice($sku);
        $media = collect($this->mediaManifest->forProduct($product, $variant));
        $primaryMedia = $media->firstWhere('role', 'front') ?? $media->first();
        $colour = $variant?->colour_name ?: $product->color;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $price === null ? null : [
                'amount_minor' => $price->amount_minor,
                'compare_at_minor' => $price->compare_at_minor !== null && $price->compare_at_minor > $price->amount_minor ? $price->compare_at_minor : null,
                'currency' => $price->currency,
            ],
            'attributes' => array_filter([
                'shape' => $variant?->frame_shape ?: $product->frame,
                'colour' => $colour,
                'material' => $variant?->material ?: $product->material,
            ], fn (?string $value): bool => $value !== null && $value !== ''),
            'media' => $primaryMedia === null ? null : [
                'alt' => $primaryMedia['alt'] ?: trim($product->name.($colour ? ' in '.$colour : '')),
                'responsive' => $primaryMedia['responsive'],
            ],
        ];
    }

    protected function unambiguousCurrentPrice(?Sku $sku): ?Price
    {
        if ($sku === null || $sku->prices->count() !== 1) {
            return null;
        }

        return $sku->prices->first();
    }

    /** @return array<string, mixed> */
    protected function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'links' => $paginator->linkCollection()
                ->map(fn (array $link): array => ['label' => strip_tags($link['label']), 'url' => $link['url'], 'active' => $link['active']])
                ->all(),
        ];
    }
}
