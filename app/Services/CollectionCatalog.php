<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

class CollectionCatalog extends StorefrontCatalog
{
    private const PER_PAGE = 9;
    private const SORTS = ['featured', 'newest'];
    private const FACETS = ['shape', 'colour', 'material'];

    /** @param array<string, mixed> $input */
    public function page(string $slug, array $input): array
    {
        $collection = Collection::query()
            ->where('is_active', true)
            ->where('slug', $slug)
            ->firstOrFail();

        $facets = $this->facets($collection);
        $filters = $this->validFilters($input, $facets, self::FACETS);
        $hasInvalidFacetInput = $this->hasInvalidFacetInput($input, $facets, self::FACETS);
        $sort = in_array($input['sort'] ?? null, self::SORTS, true) ? $input['sort'] : 'featured';

        $products = $this->visibleProducts();
        $this->scopeToCollection($products, $collection);
        $this->applyVariantFilters($products, $filters);
        if ($hasInvalidFacetInput) {
            $products->whereRaw('1 = 0');
        }
        $this->applyCollectionSort($products, $collection, $sort);

        $paginator = $products
            ->with($this->catalogRelations($filters))
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return [
            'collection' => $this->serializeCollection($collection),
            'products' => array_map(fn (Product $product): array => $this->serializeProduct($product), $paginator->items()),
            'facets' => $facets,
            'filters' => $filters,
            'sort' => $sort,
            'pagination' => $this->pagination($paginator),
        ];
    }

    /** @return array<string, list<array{value: string, label: string}>> */
    private function facets(Collection $collection): array
    {
        $variantFacet = fn (string $column): array => ProductVariant::query()
            ->tap(fn (Builder $variants) => $this->applyVariantConstraints($variants))
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->whereHas('product', function (Builder $products) use ($collection): void {
                $this->applyPublicVisibility($products);
                $this->scopeToCollection($products, $collection);
            })
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
        ], fn (array $values): bool => $values !== []);
    }

    private function scopeToCollection(Builder $products, Collection $collection): void
    {
        $products->whereHas('collections', fn (Builder $collections) => $collections->whereKey($collection->id));
    }

    private function applyCollectionSort(Builder $products, Collection $collection, string $sort): void
    {
        if ($sort === 'newest') {
            $products->orderByDesc('products.created_at')->orderByDesc('products.id');

            return;
        }

        $products
            ->join('collection_product', function (JoinClause $join) use ($collection): void {
                $join->on('collection_product.product_id', '=', 'products.id')
                    ->where('collection_product.collection_id', $collection->id);
            })
            ->select('products.*')
            ->orderBy('collection_product.display_order')
            ->orderBy('products.display_order')
            ->orderBy('products.id');
    }

    /** @return array{id: int, name: string, slug: string, description: ?string} */
    private function serializeCollection(Collection $collection): array
    {
        return [
            'id' => $collection->id,
            'name' => $collection->name,
            'slug' => $collection->slug,
            'description' => $collection->description,
        ];
    }
}
