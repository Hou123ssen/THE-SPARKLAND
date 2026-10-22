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
use App\Services\ProductMediaManifest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShopCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_only_returns_public_products_and_uses_canonical_slugs(): void
    {
        $visible = $this->product('axis-01', 'Oval', 'Ink Black', 'Acetate', 24500);
        $this->product('hidden-02', 'Square', 'Black', 'Acetate', 26500, ['is_active' => false]);
        $this->product('draft-03', 'Round', 'Grey', 'Titanium', 27500, ['status' => Product::STATUS_DRAFT]);
        $this->product('future-04', 'Panto', 'Brown', 'Acetate', 28500, ['published_at' => now()->addDay()]);

        $this->get('/shop')->assertInertia(fn (Assert $page) => $page
            ->component('Shop')
            ->has('products', 1)
            ->where('products.0.slug', $visible->slug)
            ->where('products.0.price.currency', 'EUR')
            ->where('products.0.media.responsive.fallback.0.url', fn (string $url) => str_ends_with($url, '/storage/products/axis-01-960.png'))
        );
    }

    public function test_filters_are_server_backed_and_invalid_values_return_no_catalogue_results(): void
    {
        $oval = $this->product('axis-01', 'Oval', 'Ink Black', 'Acetate', 24500);
        $this->product('line-02', 'Square', 'Warm Tortoise', 'Titanium', 26500);

        $this->get('/shop?shape=Oval&colour=Ink%20Black')->assertInertia(fn (Assert $page) => $page
            ->where('filters.shape', 'Oval')
            ->where('filters.colour', 'Ink Black')
            ->has('products', 1)
            ->where('products.0.slug', $oval->slug)
        );

        $this->get('/shop?shape=not-a-real-shape')->assertInertia(fn (Assert $page) => $page
            ->missing('filters.shape')
            ->has('products', 0)
        );
    }

    public function test_price_sorts_are_unavailable_until_a_storefront_currency_is_configured(): void
    {
        $usd = $this->product('usd-01', 'Oval', 'Black', 'Acetate', 10000, [], 'USD');
        $eur = $this->product('eur-02', 'Oval', 'Grey', 'Titanium', 5000, [], 'EUR');

        $this->get('/shop?sort=price_asc')->assertInertia(fn (Assert $page) => $page
            ->where('sort', 'featured')
            ->where('products.0.slug', $usd->slug)
            ->where('products.1.slug', $eur->slug)
            ->where('products.0.price.currency', 'USD')
            ->where('products.1.price.currency', 'EUR')
        );
        $this->get('/shop?sort=price_desc')->assertInertia(fn (Assert $page) => $page
            ->where('sort', 'featured')
            ->where('products.0.slug', $usd->slug)
            ->where('products.1.slug', $eur->slug)
        );
    }

    public function test_pagination_preserves_valid_active_filters(): void
    {
        foreach (range(1, 10) as $index) {
            $this->product("oval-{$index}", 'Oval', "Colour {$index}", 'Acetate', 20000 + $index);
        }

        $this->get('/shop?shape=Oval&page=2')->assertInertia(fn (Assert $page) => $page
            ->where('filters.shape', 'Oval')
            ->where('pagination.current_page', 2)
            ->where('pagination.last_page', 2)
            ->has('products', 1)
            ->where('pagination.links.1.url', fn (?string $url) => str_contains($url ?? '', 'shape=Oval'))
        );
    }

    public function test_empty_results_are_safe_when_valid_filters_do_not_intersect(): void
    {
        $this->product('oval-01', 'Oval', 'Black', 'Acetate', 24500);
        $this->product('square-02', 'Square', 'Grey', 'Titanium', 26500);

        $this->get('/shop?shape=Oval&material=Titanium')->assertInertia(fn (Assert $page) => $page
            ->where('filters.shape', 'Oval')
            ->where('filters.material', 'Titanium')
            ->has('products', 0)
        );
    }

    public function test_shop_media_serialization_never_leaks_private_master_storage_paths(): void
    {
        $this->product('axis-01', 'Oval', 'Ink Black', 'Acetate', 24500);

        $response = $this->get('/shop');

        $response->assertDontSee('masters/private-axis-01.png');
        $response->assertInertia(fn (Assert $page) => $page
            ->has('products.0.media.responsive.fallback', 1)
            ->where('products.0.media.responsive.fallback.0.url', fn (string $url) => str_ends_with($url, '/storage/products/axis-01-960.png'))
        );
    }

    public function test_catalogue_card_uses_one_representative_sellable_variant_for_media_attributes_and_price(): void
    {
        $product = $this->product('axis-01', 'Oval', 'Ink Black', 'Acetate', 50000);
        $black = ProductVariant::query()->where('product_id', $product->id)->firstOrFail();
        $red = $this->additionalVariant($product, 'axis-01-red', 'Square', 'Signal Red', 'Titanium', 10000, 1);
        $this->variantMedia($product, $black, 'axis-01-black');
        $this->variantMedia($product, $red, 'axis-01-red');

        $this->get('/shop')->assertInertia(fn (Assert $page) => $page
            ->where('products.0.attributes.shape', 'Oval')
            ->where('products.0.attributes.colour', 'Ink Black')
            ->where('products.0.attributes.material', 'Acetate')
            ->where('products.0.price.amount_minor', 50000)
            ->where('products.0.price.currency', 'EUR')
            ->where('products.0.media.responsive.fallback.0.url', fn (string $url) => str_ends_with($url, '/storage/products/axis-01-black-960.png'))
        );
    }

    public function test_variant_filter_selects_the_matching_variant_for_catalogue_serialization(): void
    {
        $product = $this->product('axis-01', 'Oval', 'Black', 'Acetate', 50000);
        $black = ProductVariant::query()->where('product_id', $product->id)->firstOrFail();
        $red = $this->additionalVariant($product, 'axis-01-red', 'Square', 'Red', 'Titanium', 10000, 1);
        $this->variantMedia($product, $black, 'axis-01-black');
        $this->variantMedia($product, $red, 'axis-01-red');

        $this->get('/shop?colour=Red')->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.slug', $product->slug)
            ->where('products.0.attributes.colour', 'Red')
            ->where('products.0.attributes.shape', 'Square')
            ->where('products.0.attributes.material', 'Titanium')
            ->where('products.0.price.amount_minor', 10000)
            ->where('products.0.media.responsive.fallback.0.url', fn (string $url) => str_ends_with($url, '/storage/products/axis-01-red-960.png'))
        );
    }

    public function test_variant_filters_must_match_one_sellable_variant(): void
    {
        $product = $this->product('axis-01', 'Oval', 'Black', 'Acetate', 50000);
        $this->additionalVariant($product, 'axis-01-red', 'Square', 'Red', 'Titanium', 10000, 1);

        $this->get('/shop?shape=Oval&colour=Red')->assertInertia(fn (Assert $page) => $page
            ->where('filters.shape', 'Oval')
            ->where('filters.colour', 'Red')
            ->has('products', 0)
        );
    }

    public function test_valid_multi_filter_selects_its_matching_sellable_variant(): void
    {
        $product = $this->product('axis-01', 'Oval', 'Black', 'Acetate', 50000);
        $this->additionalVariant($product, 'axis-01-red', 'Square', 'Red', 'Titanium', 10000, 1);

        $this->get('/shop?shape=Square&colour=Red&material=Titanium')->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.slug', $product->slug)
            ->where('products.0.attributes.shape', 'Square')
            ->where('products.0.attributes.colour', 'Red')
            ->where('products.0.attributes.material', 'Titanium')
            ->where('products.0.price.amount_minor', 10000)
        );
    }

    public function test_ambiguous_current_prices_are_not_serialized(): void
    {
        $product = $this->product('axis-01', 'Oval', 'Ink Black', 'Acetate', 24500);
        $sku = Sku::query()->where('sku', 'SPK-axis-01')->firstOrFail();
        Price::create(['sku_id' => $sku->id, 'currency' => 'USD', 'amount_minor' => 20000]);

        $this->get('/shop')->assertInertia(fn (Assert $page) => $page
            ->where('products.0.slug', $product->slug)
            ->where('products.0.price', null)
        );
    }

    public function test_facets_and_variant_filters_require_the_same_sellable_variant(): void
    {
        $black = $this->product('black-01', 'Oval', 'Black', 'Acetate', 24500);
        $this->additionalVariant($black, 'black-01-red', 'Oval', 'Red', 'Acetate', 26000, 1, 'EUR', Sku::STATUS_INACTIVE);

        $this->get('/shop')->assertInertia(fn (Assert $page) => $page
            ->where('facets.colour', fn ($values): bool => ! $values->contains('value', 'Red'))
        );

        $this->get('/shop?colour=Red')->assertInertia(fn (Assert $page) => $page
            ->missing('filters.colour')
            ->has('products', 0)
        );
    }

    public function test_soft_deleted_variants_cannot_supply_catalogue_prices_or_attributes(): void
    {
        $product = $this->product('axis-01', 'Oval', 'Ink Black', 'Acetate', 50000);
        $deleted = $this->additionalVariant($product, 'axis-01-deleted', 'Square', 'Red', 'Titanium', 10000, -1);
        $deleted->delete();

        $this->get('/shop')->assertInertia(fn (Assert $page) => $page
            ->where('products.0.attributes.colour', 'Ink Black')
            ->where('products.0.price.amount_minor', 50000)
        );
    }

    public function test_media_manifest_uses_only_the_narrow_legacy_public_path_fallback_and_prefers_derivatives(): void
    {
        $product = $this->product('axis-01', 'Oval', 'Ink Black', 'Acetate', 24500);
        $manifest = app(ProductMediaManifest::class);

        $legacy = $this->media($product, 'public', '/images/legacy-frame.png', 'legacy-frame');
        $legacyData = $manifest->forMedia($legacy->load('mediaAsset.derivatives'));
        $this->assertSame('/images/legacy-frame.png', $legacyData['responsive']['fallback'][0]['url']);

        $private = $this->media($product, 'local', 'products/private-master.png', 'private-master');
        $privateData = $manifest->forMedia($private->load('mediaAsset.derivatives'));
        $this->assertSame([], $privateData['responsive']['fallback']);
        $this->assertStringNotContainsString('products/private-master.png', json_encode($privateData, JSON_THROW_ON_ERROR));

        $withDerivative = $this->media($product, 'public', '/images/legacy-with-derivative.png', 'legacy-with-derivative');
        MediaDerivative::create([
            'media_asset_id' => $withDerivative->media_asset_id,
            'preset' => 'responsive_960',
            'format' => 'png',
            'disk' => 'public',
            'path' => 'products/derivative-first-960.png',
            'mime_type' => 'image/png',
            'width' => 960,
            'height' => 720,
            'bytes' => 1,
            'checksum_sha256' => hash('sha256', 'derivative-first'),
        ]);
        $derivativeData = $manifest->forMedia($withDerivative->fresh()->load('mediaAsset.derivatives'));
        $this->assertCount(1, $derivativeData['responsive']['fallback']);
        $this->assertStringEndsWith('/storage/products/derivative-first-960.png', $derivativeData['responsive']['fallback'][0]['url']);
    }

    private function product(string $slug, string $shape, string $colour, string $material, int $amount, array $attributes = [], string $currency = 'EUR'): Product
    {
        $collection = Collection::firstOrCreate(['slug' => 'essential-forms'], ['name' => 'Essential Forms', 'display_order' => 1]);
        $product = Product::create([
            'collection_id' => $collection->id,
            'name' => strtoupper(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'display_order' => Product::query()->count() + 1,
            ...$attributes,
        ]);
        $collection->products()->attach($product, ['display_order' => $product->display_order, 'is_featured' => false]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'slug' => "{$slug}-default",
            'name' => 'Default',
            'frame_shape' => $shape,
            'colour_name' => $colour,
            'material' => $material,
            'display_order' => 0,
        ]);
        $sku = Sku::create(['product_variant_id' => $variant->id, 'sku' => "SPK-{$slug}"]);
        Price::create(['sku_id' => $sku->id, 'currency' => $currency, 'amount_minor' => $amount]);
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
        ProductMedia::create(['product_id' => $product->id, 'media_asset_id' => $asset->id, 'kind' => 'image', 'role' => 'front', 'is_primary' => true]);
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

        return $product;
    }

    private function additionalVariant(Product $product, string $slug, string $shape, string $colour, string $material, int $amount, int $displayOrder, string $currency = 'EUR', string $skuStatus = Sku::STATUS_ACTIVE): ProductVariant
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
        $sku = Sku::create(['product_variant_id' => $variant->id, 'sku' => "SPK-{$slug}", 'status' => $skuStatus]);
        Price::create(['sku_id' => $sku->id, 'currency' => $currency, 'amount_minor' => $amount]);

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

    private function media(Product $product, string $disk, string $path, string $key): ProductMedia
    {
        $asset = MediaAsset::create([
            'disk' => $disk,
            'path' => $path,
            'mime_type' => 'image/png',
            'width' => 960,
            'height' => 720,
            'bytes' => 1,
            'checksum_sha256' => hash('sha256', $key),
            'processing_status' => 'ready',
        ]);

        return ProductMedia::create(['product_id' => $product->id, 'media_asset_id' => $asset->id, 'kind' => 'image', 'role' => 'detail']);
    }
}
