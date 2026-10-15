<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\InventoryLevel;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\MediaAsset;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\Sku;
use App\Services\LegacyCatalogBackfill;
use App\Services\PublicCatalog;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_collection_has_ordered_product_memberships_without_removing_legacy_collection(): void
    {
        [$collection, $product] = $this->legacyProduct();
        $collection->products()->attach($product, ['display_order' => 3, 'is_featured' => true]);

        $this->assertTrue($collection->products->contains($product));
        $this->assertTrue($collection->legacyProducts->contains($product));
        $this->assertTrue($product->collection->is($collection));
        $this->assertSame(3, $collection->products->first()->pivot->display_order);
    }

    public function test_duplicate_collection_product_membership_is_prohibited(): void
    {
        [$collection, $product] = $this->legacyProduct();
        $collection->products()->attach($product);

        $this->expectException(QueryException::class);
        $collection->products()->attach($product);
    }

    public function test_product_has_variants_and_variant_has_skus(): void
    {
        [, $product] = $this->legacyProduct();
        $variant = ProductVariant::create(['product_id' => $product->id, 'slug' => 'axis-black', 'name' => 'Ink Black']);
        $sku = Sku::create(['product_variant_id' => $variant->id, 'sku' => 'SPK-AXIS-BLK']);

        $this->assertTrue($product->variants->contains($variant));
        $this->assertTrue($variant->skus->contains($sku));
        $this->assertTrue($sku->variant->is($variant));
    }

    public function test_sku_codes_are_unique(): void
    {
        [, $product] = $this->legacyProduct();
        $first = ProductVariant::create(['product_id' => $product->id, 'slug' => 'axis-black', 'name' => 'Ink Black']);
        $second = ProductVariant::create(['product_id' => $product->id, 'slug' => 'axis-tortoise', 'name' => 'Tortoise']);
        Sku::create(['product_variant_id' => $first->id, 'sku' => 'SPK-AXIS-001']);

        $this->expectException(QueryException::class);
        Sku::create(['product_variant_id' => $second->id, 'sku' => 'SPK-AXIS-001']);
    }

    public function test_sku_has_integer_minor_unit_prices(): void
    {
        [, $product] = $this->legacyProduct();
        $variant = ProductVariant::create(['product_id' => $product->id, 'slug' => 'axis-black', 'name' => 'Ink Black']);
        $sku = Sku::create(['product_variant_id' => $variant->id, 'sku' => 'SPK-AXIS-BLK']);
        $price = Price::create(['sku_id' => $sku->id, 'currency' => 'MAD', 'amount_minor' => 24500]);

        $this->assertTrue($sku->prices->contains($price));
        $this->assertSame(24500, $price->amount_minor);
        $this->assertIsInt($price->amount_minor);
    }

    public function test_inventory_level_is_unique_per_sku_and_location(): void
    {
        [, $product] = $this->legacyProduct();
        $variant = ProductVariant::create(['product_id' => $product->id, 'slug' => 'axis-black', 'name' => 'Ink Black']);
        $sku = Sku::create(['product_variant_id' => $variant->id, 'sku' => 'SPK-AXIS-BLK']);
        $location = InventoryLocation::create(['code' => 'WH-01', 'name' => 'Primary warehouse']);
        InventoryLevel::create(['sku_id' => $sku->id, 'inventory_location_id' => $location->id, 'on_hand' => 4]);

        $this->expectException(QueryException::class);
        InventoryLevel::create(['sku_id' => $sku->id, 'inventory_location_id' => $location->id, 'on_hand' => 2]);
    }

    public function test_inventory_levels_prevent_deleting_an_operational_sku(): void
    {
        [$sku, $location] = $this->stockedSku();
        InventoryLevel::create(['sku_id' => $sku->id, 'inventory_location_id' => $location->id, 'on_hand' => 4]);

        $this->expectException(QueryException::class);
        $sku->delete();
    }

    public function test_inventory_movement_history_prevents_deleting_a_location(): void
    {
        [$sku, $location] = $this->stockedSku();
        $level = InventoryLevel::create(['sku_id' => $sku->id, 'inventory_location_id' => $location->id, 'on_hand' => 4]);
        InventoryMovement::create([
            'sku_id' => $sku->id,
            'inventory_location_id' => $location->id,
            'type' => 'adjustment',
            'quantity_delta' => 4,
        ]);
        $level->delete();

        $this->expectException(QueryException::class);
        $location->delete();
    }

    public function test_product_and_variant_media_resolve_to_a_normalized_asset(): void
    {
        [, $product] = $this->legacyProduct();
        $variant = ProductVariant::create(['product_id' => $product->id, 'slug' => 'axis-black', 'name' => 'Ink Black']);
        $asset = MediaAsset::create(['disk' => 'public', 'path' => '/images/axis.png']);
        $media = ProductMedia::create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'media_asset_id' => $asset->id, 'kind' => 'image', 'role' => 'front']);

        $this->assertTrue($product->media->contains($media));
        $this->assertTrue($variant->media->contains($media));
        $this->assertTrue($media->mediaAsset->is($asset));
    }

    public function test_backfill_preserves_existing_catalog_records_and_legacy_images(): void
    {
        [$collection, $product] = $this->legacyProduct(['price' => '245.00']);
        ProductImage::create(['product_id' => $product->id, 'image_path' => '/images/glasses-front.png', 'type' => 'front', 'display_order' => 1]);

        app(LegacyCatalogBackfill::class)->run();

        $this->assertDatabaseHas('collection_product', ['collection_id' => $collection->id, 'product_id' => $product->id]);
        $variant = $product->fresh()->variants()->sole();
        $sku = $variant->skus()->sole();
        $this->assertSame('LEGACY-'.str_pad((string) $product->id, 6, '0', STR_PAD_LEFT).'-DEFAULT', $sku->sku);
        $this->assertDatabaseHas('prices', ['sku_id' => $sku->id, 'currency' => 'XXX', 'amount_minor' => 24500]);
        $this->assertDatabaseHas('product_media', ['product_id' => $product->id, 'role' => 'front']);
        $this->assertDatabaseHas('product_images', ['product_id' => $product->id, 'image_path' => '/images/glasses-front.png']);
    }

    public function test_deleting_a_product_cascades_variants_and_product_media_but_preserves_the_asset(): void
    {
        [, $product] = $this->legacyProduct();
        $variant = ProductVariant::create(['product_id' => $product->id, 'slug' => 'axis-black', 'name' => 'Ink Black']);
        $asset = MediaAsset::create(['disk' => 'public', 'path' => '/images/axis.png']);
        ProductMedia::create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'media_asset_id' => $asset->id, 'kind' => 'image', 'role' => 'front']);

        $product->forceDelete();

        $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);
        $this->assertDatabaseMissing('product_media', ['product_id' => $product->id]);
        $this->assertDatabaseHas('media_assets', ['id' => $asset->id]);
    }

    public function test_a_referenced_media_asset_cannot_be_deleted(): void
    {
        [, $product] = $this->legacyProduct();
        $asset = MediaAsset::create(['disk' => 'public', 'path' => '/images/axis.png']);
        ProductMedia::create(['product_id' => $product->id, 'media_asset_id' => $asset->id, 'kind' => 'image', 'role' => 'front']);

        $this->expectException(QueryException::class);
        $asset->delete();
    }

    public function test_database_seeder_does_not_seed_demo_catalog_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        app(DatabaseSeeder::class)->run();

        $this->assertDatabaseCount('products', 0);
    }

    public function test_negative_legacy_prices_are_rejected_before_unsigned_price_insertion(): void
    {
        [, $product] = $this->legacyProduct(['price' => '-1.00']);

        $this->expectException(\InvalidArgumentException::class);
        app(LegacyCatalogBackfill::class)->run();
    }

    public function test_public_catalog_excludes_inactive_and_unpublished_products_and_uses_membership_order(): void
    {
        $collection = Collection::create(['name' => 'Visible', 'slug' => 'visible']);
        $first = Product::create(['collection_id' => $collection->id, 'name' => 'First', 'slug' => 'first', 'display_order' => 2]);
        $second = Product::create(['collection_id' => $collection->id, 'name' => 'Second', 'slug' => 'second', 'display_order' => 1]);
        $inactive = Product::create(['collection_id' => $collection->id, 'name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);
        $draft = Product::create(['collection_id' => $collection->id, 'name' => 'Draft', 'slug' => 'draft', 'status' => Product::STATUS_DRAFT]);
        $future = Product::create(['collection_id' => $collection->id, 'name' => 'Future', 'slug' => 'future', 'published_at' => now()->addDay()]);
        $collection->products()->attach([
            $first->id => ['display_order' => 2], $second->id => ['display_order' => 1],
            $inactive->id => ['display_order' => 3], $draft->id => ['display_order' => 4],
            $future->id => ['display_order' => 5],
        ]);

        $catalog = app(PublicCatalog::class)->all();
        $this->assertSame(['second', 'first'], array_column($catalog[0]['products'], 'slug'));
    }

    public function test_inactive_collections_are_excluded_from_public_catalog(): void
    {
        Collection::create(['name' => 'Visible', 'slug' => 'visible']);
        Collection::create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);
        $this->assertSame(['visible'], array_column(app(PublicCatalog::class)->all(), 'slug'));
    }

    public function test_home_inertia_response_contains_the_legacy_compatible_catalog_shape(): void
    {
        [$collection, $product] = $this->legacyProduct(['price' => 245]);
        $collection->products()->attach($product, ['display_order' => 1]);
        ProductImage::create(['product_id' => $product->id, 'image_path' => '/images/glasses-front.png', 'type' => 'front']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('Home')->has('catalog', 1)->where('catalog.0.name', $collection->name)
            ->has('catalog.0.products', 1)->where('catalog.0.products.0.slug', $product->slug)
            ->where('catalog.0.products.0.price', '245.00')->has('catalog.0.products.0.images', 1)
            ->where('catalog.0.products.0.images.0.type', 'front')
        );
    }

    /** @return array{Collection, Product} */
    private function legacyProduct(array $attributes = []): array
    {
        $collection = Collection::create(['name' => 'Core Forms', 'slug' => 'core-forms']);
        $product = Product::create(['collection_id' => $collection->id, 'name' => 'Axis 01', 'slug' => 'axis-01', ...$attributes]);
        return [$collection, $product];
    }

    /** @return array{Sku, InventoryLocation} */
    private function stockedSku(): array
    {
        [, $product] = $this->legacyProduct();
        $variant = ProductVariant::create(['product_id' => $product->id, 'slug' => 'axis-black', 'name' => 'Ink Black']);
        $sku = Sku::create(['product_variant_id' => $variant->id, 'sku' => 'SPK-AXIS-BLK']);
        $location = InventoryLocation::create(['code' => 'WH-01', 'name' => 'Primary warehouse']);

        return [$sku, $location];
    }
}
