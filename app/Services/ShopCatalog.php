<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;

class ShopCatalog extends StorefrontCatalog
{
    private const PER_PAGE = 9;
    private const SORTS = ['featured', 'newest'];
    private const FACETS = ['shape', 'colour', 'material', 'collection'];

    /** @param array<string, mixed> $input */
    public function page(array $input): array
    {
        $facets = $this->facets();
        $filters = $this->validFilters($input, $facets, self::FACETS);
        $hasInvalidFacetInput = $this->hasInvalidFacetInput($input, $facets, self::FACETS);
        $sort = in_array($input['sort'] ?? null, self::SORTS, true) ? $input['sort'] : 'featured';

        $products = $this->visibleProducts();
        $this->applyFilters($products, $filters);
        if ($hasInvalidFacetInput) {
            $products->whereRaw('1 = 0');
        }
        $this->applySort($products, $sort);

        $paginator = $products->with($this->catalogRelations($filters))->paginate(self::PER_PAGE)->withQueryString();

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
            ->tap(fn (Builder $variants) => $this->applyVariantConstraints($variants))
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

    /** @param array<string, string> $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        $this->applyVariantFilters($query, $filters);

        if (isset($filters['collection'])) {
            $query->whereHas('collections', fn (Builder $collections) => $collections->where('collections.is_active', true)->where('collections.slug', $filters['collection']));
        }
    }
}
