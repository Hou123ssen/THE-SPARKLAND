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

    public function test_filters_are_server_backed_and_invalid_values_are_ignored(): void
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
            ->has('products', 2)
        );
    }

    public function test_server_side_sorting_uses_current_prices(): void
    {
        $higher = $this->product('high-01', 'Oval', 'Black', 'Acetate', 40000);
        $lower = $this->product('low-02', 'Oval', 'Grey', 'Acetate', 21000);

        $this->get('/shop?sort=price_asc')->assertInertia(fn (Assert $page) => $page
            ->where('sort', 'price_asc')
            ->where('products.0.slug', $lower->slug)
            ->where('products.1.slug', $higher->slug)
        );
        $this->get('/shop?sort=price_desc')->assertInertia(fn (Assert $page) => $page
            ->where('products.0.slug', $higher->slug)
            ->where('products.1.slug', $lower->slug)
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

    private function product(string $slug, string $shape, string $colour, string $material, int $amount, array $attributes = []): Product
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
        Price::create(['sku_id' => $sku->id, 'currency' => 'EUR', 'amount_minor' => $amount]);
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
}
