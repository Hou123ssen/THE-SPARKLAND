<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\MediaAsset;
use App\Models\MediaDerivative;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\Sku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CollectionCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_collections_index_returns_only_active_collections_in_curation_order(): void
    {
        $late = $this->collection('late-forms', 20);
        $early = $this->collection('early-forms', 10);
        $this->collection('hidden-forms', 0, false);

        $this->get('/collections')->assertInertia(fn (Assert $page) => $page
            ->component('Collections')
            ->has('collections', 2)
            ->where('collections.0.id', $early->id)
            ->where('collections.0.href', '/collections/early-forms')
            ->where('collections.1.id', $late->id)
            ->missing('collections.0.products')
            ->missing('products')
        );
    }

    public function test_collection_show_resolves_only_active_collections(): void
    {
        $collection = $this->collection('city-views');

        $this->get('/collections/city-views')->assertInertia(fn (Assert $page) => $page
            ->component('CollectionShow')
            ->where('collection.id', $collection->id)
            ->where('collection.name', $collection->name)
            ->where('collection.slug', 'city-views')
            ->missing('collection.display_order')
            ->missing('facets.collection')
        );

        $this->get('/collections/unknown')->assertNotFound();
        $this->collection('inactive-views', 0, false);
        $this->get('/collections/inactive-views')->assertNotFound();
    }

    public function test_collection_catalogue_is_scoped_to_public_sellable_products(): void
    {
        $city = $this->collection('city-views');
        $other = $this->collection('other-views');
        $visible = $this->product($city, 'city-visible', 'Oval', 'Black', 'Acetate', 24500);
        $this->product($other, 'other-visible', 'Square', 'Red', 'Titanium', 26500);
        $this->product($city, 'inactive-product', 'Round', 'Grey', 'Acetate', 27500, ['is_active' => false]);
        $this->product($city, 'draft-product', 'Round', 'Grey', 'Acetate', 27500, ['status' => Product::STATUS_DRAFT]);
        $this->product($city, 'future-product', 'Round', 'Grey', 'Acetate', 27500, ['published_at' => now()->addDay()]);
        $this->product($city, 'inactive-sku', 'Round', 'Grey', 'Acetate', 27500, [], [], Sku::STATUS_INACTIVE);
        $this->product($city, 'no-sku', 'Round', 'Grey', 'Acetate', 27500, [], [], null, [], false);

        $this->get('/collections/city-views')->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.slug', $visible->slug)
        );
    }

    public function test_collection_facets_are_scoped_to_its_sellable_products(): void
    {
        $city = $this->collection('city-views');
        $other = $this->collection('other-views');
        $this->product($city, 'city-frame', 'Oval', 'Black', 'Acetate', 24500);
        $this->product($other, 'other-frame', 'Square', 'Red', 'Titanium', 26500);
        $this->product($city, 'inactive-sku', 'Panto', 'Green', 'Nylon', 27500, [], [], Sku::STATUS_INACTIVE);

        $this->get('/collections/city-views')->assertInertia(fn (Assert $page) => $page
            ->where('facets.shape.0.value', 'Oval')
            ->where('facets.colour.0.value', 'Black')
            ->where('facets.material.0.value', 'Acetate')
            ->missing('facets.collection')
            ->where('facets.shape', fn ($values): bool => ! $values->contains('value', 'Square'))
            ->where('facets.material', fn ($values): bool => ! $values->contains('value', 'Titanium'))
        );
    }

    public function test_collection_filters_select_one_matching_sellable_variant_for_serialization(): void
    {
        $city = $this->collection('city-views');
        $product = $this->product($city, 'city-frame', 'Oval', 'Black', 'Acetate', 50000);
        $black = ProductVariant::query()->where('product_id', $product->id)->firstOrFail();
        $red = $this->additionalVariant($product, 'city-frame-red', 'Square', 'Red', 'Titanium', 10000, 1);
        $this->variantMedia($product, $black, 'city-frame-black');
        $this->variantMedia($product, $red, 'city-frame-red');

        $this->get('/collections/city-views?colour=Red')->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.slug', $product->slug)
            ->where('products.0.attributes.shape', 'Square')
            ->where('products.0.attributes.colour', 'Red')
            ->where('products.0.attributes.material', 'Titanium')
            ->where('products.0.price.amount_minor', 10000)
            ->where('products.0.media.responsive.fallback.0.url', fn (string $url) => str_ends_with($url, '/storage/products/city-frame-red-960.png'))
        );

        $this->get('/collections/city-views?shape=Oval&colour=Red')->assertInertia(fn (Assert $page) => $page
            ->where('collection.slug', 'city-views')
            ->has('products', 0)
        );
    }

    public function test_invalid_collection_facet_input_returns_no_products_but_keeps_collection_identity(): void
    {
        $city = $this->collection('city-views');
        $this->product($city, 'city-frame', 'Oval', 'Black', 'Acetate', 24500);

        $this->get('/collections/city-views?shape=not-a-real-shape')->assertInertia(fn (Assert $page) => $page
            ->where('collection.slug', 'city-views')
            ->missing('filters.shape')
            ->has('products', 0)
        );
    }

    public function test_featured_sort_uses_collection_pivot_curation_then_product_order_and_id(): void
    {
        $city = $this->collection('city-views');
        $late = $this->product($city, 'late', 'Oval', 'Black', 'Acetate', 24500, ['display_order' => 1], ['display_order' => 20]);
        $second = $this->product($city, 'second', 'Square', 'Red', 'Titanium', 25500, ['display_order' => 5], ['display_order' => 10]);
        $first = $this->product($city, 'first', 'Round', 'Grey', 'Acetate', 26500, ['display_order' => 2], ['display_order' => 10]);

        $this->get('/collections/city-views')->assertInertia(fn (Assert $page) => $page
            ->where('sort', 'featured')
            ->where('products.0.id', $first->id)
            ->where('products.1.id', $second->id)
            ->where('products.2.id', $late->id)
        );
    }

    public function test_newest_sort_stays_within_the_resolved_collection(): void
    {
        $city = $this->collection('city-views');
        $other = $this->collection('other-views');
        $older = $this->product($city, 'older', 'Oval', 'Black', 'Acetate', 24500);
        $newer = $this->product($city, 'newer', 'Square', 'Red', 'Titanium', 25500);
        $outside = $this->product($other, 'outside', 'Round', 'Grey', 'Acetate', 26500);
        $older->forceFill(['created_at' => now()->subDays(2)])->saveQuietly();
        $newer->forceFill(['created_at' => now()->subDay()])->saveQuietly();
        $outside->forceFill(['created_at' => now()])->saveQuietly();

        $this->get('/collections/city-views?sort=newest')->assertInertia(fn (Assert $page) => $page
            ->where('sort', 'newest')
            ->where('products.0.id', $newer->id)
            ->where('products.1.id', $older->id)
            ->where('products', fn ($products): bool => ! $products->contains('id', $outside->id))
        );
    }

    public function test_collection_catalogue_preserves_current_price_sale_and_media_contracts(): void
    {
        $city = $this->collection('city-views');
        $normal = $this->product($city, 'normal', 'Oval', 'Black', 'Acetate', 32000, [], ['display_order' => 1]);
        $sale = $this->product($city, 'sale', 'Square', 'Red', 'Titanium', 32000, [], ['display_order' => 2], Sku::STATUS_ACTIVE, ['compare_at_minor' => 40000]);
        $invalidSale = $this->product($city, 'invalid-sale', 'Round', 'Grey', 'Acetate', 32000, [], ['display_order' => 3], Sku::STATUS_ACTIVE, ['compare_at_minor' => 32000]);
        $ambiguous = $this->product($city, 'ambiguous', 'Panto', 'Brown', 'Acetate', 32000, [], ['display_order' => 4]);
        $future = $this->product($city, 'future-price', 'Oval', 'Green', 'Acetate', 32000, [], ['display_order' => 5], Sku::STATUS_ACTIVE, ['valid_from' => now()->addDay()]);
        $expired = $this->product($city, 'expired-price', 'Square', 'Blue', 'Titanium', 32000, [], ['display_order' => 6], Sku::STATUS_ACTIVE, ['valid_to' => now()->subDay()]);
        $ambiguousSku = Sku::query()->where('sku', 'SPK-ambiguous')->firstOrFail();
        Price::create(['sku_id' => $ambiguousSku->id, 'currency' => 'USD', 'amount_minor' => 20000]);

        $this->get('/collections/city-views')->assertInertia(fn (Assert $page) => $page
            ->where('products.0.id', $normal->id)
            ->where('products.0.price.compare_at_minor', null)
            ->where('products.0.media.responsive.fallback.0.url', fn (string $url) => str_ends_with($url, '/storage/products/normal-960.png'))
            ->where('products.1.id', $sale->id)
            ->where('products.1.price.compare_at_minor', 40000)
            ->where('products.2.id', $invalidSale->id)
            ->where('products.2.price.compare_at_minor', null)
            ->where('products.3.id', $ambiguous->id)
            ->where('products.3.price', null)
            ->where('products.4.id', $future->id)
            ->where('products.4.price', null)
            ->where('products.5.id', $expired->id)
            ->where('products.5.price', null)
        );
    }

    public function test_collection_catalogue_paginates_nine_products_and_preserves_query_parameters(): void
    {
        $city = $this->collection('city-views');
        foreach (range(1, 10) as $index) {
            $this->product($city, "oval-{$index}", 'Oval', "Colour {$index}", 'Acetate', 20000 + $index, [], ['display_order' => $index]);
        }

        $this->get('/collections/city-views?shape=Oval&page=2')->assertInertia(fn (Assert $page) => $page
            ->where('filters.shape', 'Oval')
            ->where('pagination.current_page', 2)
            ->where('pagination.last_page', 2)
            ->where('pagination.total', 10)
            ->has('products', 1)
            ->where('pagination.links.1.url', fn (?string $url) => str_contains($url ?? '', 'shape=Oval'))
        );
    }

    private function collection(string $slug, int $displayOrder = 0, bool $isActive = true): Collection
    {
        return Collection::create([
            'name' => ucwords(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'description' => "Description for {$slug}.",
            'display_order' => $displayOrder,
            'is_active' => $isActive,
        ]);
    }

    private function product(Collection $collection, string $slug, string $shape, string $colour, string $material, int $amount, array $attributes = [], array $pivot = [], ?string $skuStatus = Sku::STATUS_ACTIVE, array $priceAttributes = [], bool $withSku = true): Product
    {
        $product = Product::create([
            'collection_id' => $collection->id,
            'name' => strtoupper(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'display_order' => Product::query()->count() + 1,
            ...$attributes,
        ]);
        $collection->products()->attach($product, [
            'display_order' => $pivot['display_order'] ?? $product->display_order,
            'is_featured' => false,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'slug' => "{$slug}-default",
            'name' => 'Default',
            'frame_shape' => $shape,
            'colour_name' => $colour,
            'material' => $material,
            'display_order' => 0,
        ]);

        if ($withSku) {
            $sku = Sku::create(['product_variant_id' => $variant->id, 'sku' => "SPK-{$slug}", 'status' => $skuStatus]);
            Price::create(['sku_id' => $sku->id, 'currency' => 'EUR', 'amount_minor' => $amount, ...$priceAttributes]);
        }

        $this->variantMedia($product, $variant, $slug);

        return $product;
    }

    private function additionalVariant(Product $product, string $slug, string $shape, string $colour, string $material, int $amount, int $displayOrder): ProductVariant
    {
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'slug' => $slug,
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'frame_shape' => $shape,
            'colour_name' => $colour,
            'material' => $material,
            'display_order' => $displayOrder,
        ]);
        $sku = Sku::create(['product_variant_id' => $variant->id, 'sku' => "SPK-{$slug}"]);
        Price::create(['sku_id' => $sku->id, 'currency' => 'EUR', 'amount_minor' => $amount]);

        return $variant;
    }

    private function variantMedia(Product $product, ProductVariant $variant, string $slug): void
    {
        $asset = MediaAsset::create([
            'disk' => 'local',
            'path' => "products/{$product->id}/masters/private-{$slug}.png",
            'mime_type' => 'image/png',
            'width' => 960,
            'height' => 720,
            'bytes' => 1,
            'checksum_sha256' => hash('sha256', $slug),
            'processing_status' => 'ready',
        ]);
        ProductMedia::create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'media_asset_id' => $asset->id, 'kind' => 'image', 'role' => 'front', 'is_primary' => true]);
        MediaDerivative::create([
            'media_asset_id' => $asset->id,
            'preset' => 'responsive_960',
            'format' => 'png',
            'disk' => 'public',
            'path' => "products/{$slug}-960.png",
            'mime_type' => 'image/png',
            'width' => 960,
            'height' => 720,
            'bytes' => 1,
            'checksum_sha256' => hash('sha256', "{$slug}-derivative"),
        ]);
    }
}
