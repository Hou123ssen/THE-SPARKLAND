<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sku;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ShopCatalog
{
    private const PER_PAGE = 9;
    private const SORTS = ['featured', 'newest'];
    private const FACETS = ['shape', 'colour', 'material', 'collection'];

    public function __construct(private readonly ProductMediaManifest $mediaManifest)
    {
    }

    /** @param array<string, mixed> $input */
    public function page(array $input): array
    {
        $facets = $this->facets();
        $filters = $this->validFilters($input, $facets);
        $hasInvalidFacetInput = $this->hasInvalidFacetInput($input, $facets);
        $sort = in_array($input['sort'] ?? null, self::SORTS, true) ? $input['sort'] : 'featured';

        $products = $this->visibleProducts();
        $this->applyFilters($products, $filters);
        if ($hasInvalidFacetInput) {
            $products->whereRaw('1 = 0');
        }
        $this->applySort($products, $sort);

        $paginator = $products
            ->with($this->catalogRelations())
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return [
            'products' => array_map(fn (Product $product): array => $this->serializeProduct($product), $paginator->items()),
            'facets' => $facets,
            'filters' => $filters,
            'sort' => $sort,
            'pagination' => $this->pagination($paginator),
        ];
    }

    /** @return array<string, list<array{value: string, label: string}>> */
    private function facets(): array
    {
        $variantFacet = fn (string $column): array => ProductVariant::query()
            ->tap(fn (Builder $variants) => $this->applySellableVariant($variants))
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->whereHas('product', fn (Builder $query) => $this->applyPublicVisibility($query))
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(fn (string $value): array => ['value' => $value, 'label' => $value])
            ->values()
            ->all();

        return array_filter([
            'shape' => $variantFacet('frame_shape'),
            'colour' => $variantFacet('colour_name'),
            'material' => $variantFacet('material'),
            'collection' => Collection::query()
                ->where('is_active', true)
                ->whereHas('products', fn (Builder $query) => $this->applyPublicVisibility($query))
                ->orderBy('display_order')
                ->orderBy('name')
                ->get(['slug', 'name'])
                ->map(fn (Collection $collection): array => ['value' => $collection->slug, 'label' => $collection->name])
                ->all(),
        ], fn (array $values): bool => $values !== []);
    }

    /** @param array<string, mixed> $input @param array<string, list<array{value: string, label: string}>> $facets */
    private function validFilters(array $input, array $facets): array
    {
        $filters = [];
        foreach (self::FACETS as $name) {
            $options = $facets[$name] ?? [];
            $value = $input[$name] ?? null;
            if (! is_string($value) || ! in_array($value, array_column($options, 'value'), true)) {
                continue;
            }
            $filters[$name] = $value;
        }

        return $filters;
    }

    /**
     * Unknown facet values are treated as an empty result rather than being
     * ignored, so a non-sellable variant cannot be surfaced via a crafted URL.
     *
     * @param array<string, mixed> $input
     * @param array<string, list<array{value: string, label: string}>> $facets
     */
    private function hasInvalidFacetInput(array $input, array $facets): bool
    {
        foreach (self::FACETS as $name) {
            if (! array_key_exists($name, $input) || $input[$name] === '') {
                continue;
            }

            if (! is_string($input[$name]) || ! in_array($input[$name], array_column($facets[$name] ?? [], 'value'), true)) {
                return true;
            }
        }

        return false;
    }

    private function visibleProducts(): Builder
    {
        return Product::query()->tap(fn (Builder $query) => $this->applyPublicVisibility($query));
    }

    private function applyPublicVisibility(Builder $query): void
    {
        $query
            ->where('products.is_active', true)
            ->where('products.status', Product::STATUS_ACTIVE)
            ->where(fn (Builder $published) => $published->whereNull('products.published_at')->orWhere('products.published_at', '<=', now()))
            ->whereHas('variants', fn (Builder $variants) => $this->applySellableVariant($variants));
    }

    private function applySellableVariant($variants): void
    {
        $variants
            ->where('is_active', true)
            ->whereHas('skus', fn (Builder $skus) => $skus->where('status', Sku::STATUS_ACTIVE));
    }

    /** @param array<string, string> $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['shape' => 'frame_shape', 'colour' => 'colour_name', 'material' => 'material'] as $filter => $column) {
            if (isset($filters[$filter])) {
                $query->whereHas('variants', function (Builder $variants) use ($column, $filters, $filter): void {
                    $this->applySellableVariant($variants);
                    $variants->where($column, $filters[$filter]);
                });
            }
        }

        if (isset($filters['collection'])) {
            $query->whereHas('collections', fn (Builder $collections) => $collections->where('collections.is_active', true)->where('collections.slug', $filters['collection']));
        }
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'newest' => $query->orderByDesc('products.created_at')->orderByDesc('products.id'),
            default => $query->orderBy('products.display_order')->orderBy('products.id'),
        };
    }

    private function catalogRelations(): array
    {
        return [
            'collections' => fn ($collections) => $collections->where('is_active', true)->orderBy('display_order')->orderBy('id'),
            'variants' => function ($variants): void {
                $this->applySellableVariant($variants);
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

    private function currentPrices($prices): void
    {
        $prices
            ->where(fn (Builder $current) => $current->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn (Builder $current) => $current->whereNull('valid_to')->orWhere('valid_to', '>=', now()))
            ->orderBy('id');
    }

    /** @return array<string, mixed> */
    private function serializeProduct(Product $product): array
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
            'price' => $price === null ? null : ['amount_minor' => $price->amount_minor, 'currency' => $price->currency],
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

    private function unambiguousCurrentPrice(?Sku $sku): ?Price
    {
        if ($sku === null || $sku->prices->count() !== 1) {
            return null;
        }

        return $sku->prices->first();
    }

    /** @return array<string, mixed> */
    private function pagination(LengthAwarePaginator $paginator): array
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
