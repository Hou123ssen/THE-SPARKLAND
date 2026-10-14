<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\PublicCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_collection_has_many_products(): void
    {
        $collection = Collection::create([
            'name' => 'Core Forms',
            'slug' => 'core-forms',
        ]);
        $product = Product::create([
            'collection_id' => $collection->id,
            'name' => 'Axis 01',
            'slug' => 'axis-01',
        ]);

        $this->assertTrue($collection->products->contains($product));
    }

    public function test_product_belongs_to_collection(): void
    {
        $collection = Collection::create([
            'name' => 'Core Forms',
            'slug' => 'core-forms',
        ]);
        $product = Product::create([
            'collection_id' => $collection->id,
            'name' => 'Axis 01',
            'slug' => 'axis-01',
        ]);

        $this->assertTrue($product->collection->is($collection));
    }

    public function test_product_has_many_product_images(): void
    {
        $collection = Collection::create([
            'name' => 'Core Forms',
            'slug' => 'core-forms',
        ]);
        $product = Product::create([
            'collection_id' => $collection->id,
            'name' => 'Axis 01',
            'slug' => 'axis-01',
        ]);
        $image = ProductImage::create([
            'product_id' => $product->id,
            'image_path' => '/images/glasses-front.png',
        ]);

        $this->assertTrue($product->images->contains($image));
    }

    public function test_product_image_belongs_to_product(): void
    {
        $collection = Collection::create([
            'name' => 'Core Forms',
            'slug' => 'core-forms',
        ]);
        $product = Product::create([
            'collection_id' => $collection->id,
            'name' => 'Axis 01',
            'slug' => 'axis-01',
        ]);
        $image = ProductImage::create([
            'product_id' => $product->id,
            'image_path' => '/images/glasses-front.png',
        ]);

        $this->assertTrue($image->product->is($product));
    }

    public function test_inactive_collections_are_excluded_from_public_catalog(): void
    {
        Collection::create([
            'name' => 'Visible',
            'slug' => 'visible',
            'is_active' => true,
        ]);
        Collection::create([
            'name' => 'Hidden',
            'slug' => 'hidden',
            'is_active' => false,
        ]);

        $catalog = app(PublicCatalog::class)->all();

        $this->assertSame(['visible'], array_column($catalog, 'slug'));
    }

    public function test_inactive_products_are_excluded_from_public_catalog(): void
    {
        $collection = Collection::create([
            'name' => 'Visible',
            'slug' => 'visible',
        ]);
        Product::create([
            'collection_id' => $collection->id,
            'name' => 'Visible Product',
            'slug' => 'visible-product',
            'is_active' => true,
        ]);
        Product::create([
            'collection_id' => $collection->id,
            'name' => 'Hidden Product',
            'slug' => 'hidden-product',
            'is_active' => false,
        ]);

        $catalog = app(PublicCatalog::class)->all();

        $this->assertSame(['visible-product'], array_column($catalog[0]['products'], 'slug'));
    }

    public function test_collections_respect_display_order(): void
    {
        Collection::create([
            'name' => 'Second',
            'slug' => 'second',
            'display_order' => 2,
        ]);
        Collection::create([
            'name' => 'First',
            'slug' => 'first',
            'display_order' => 1,
        ]);

        $catalog = app(PublicCatalog::class)->all();

        $this->assertSame(['first', 'second'], array_column($catalog, 'slug'));
    }

    public function test_products_respect_display_order(): void
    {
        $collection = Collection::create([
            'name' => 'Visible',
            'slug' => 'visible',
        ]);
        Product::create([
            'collection_id' => $collection->id,
            'name' => 'Second',
            'slug' => 'second',
            'display_order' => 2,
        ]);
        Product::create([
            'collection_id' => $collection->id,
            'name' => 'First',
            'slug' => 'first',
            'display_order' => 1,
        ]);

        $catalog = app(PublicCatalog::class)->all();

        $this->assertSame(['first', 'second'], array_column($catalog[0]['products'], 'slug'));
    }

    public function test_home_inertia_response_contains_the_catalog_structure(): void
    {
        $collection = Collection::create([
            'name' => 'Core Forms',
            'slug' => 'core-forms',
            'description' => 'Everyday clarity.',
        ]);
        $product = Product::create([
            'collection_id' => $collection->id,
            'name' => 'Axis 01',
            'slug' => 'axis-01',
            'price' => 245,
            'color' => 'Ink Black',
            'material' => 'Acetate',
            'lens' => 'Clear CR-39',
            'frame' => 'Soft square',
            'dimensions' => '49-20-145',
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => '/images/glasses-front.png',
            'type' => 'front',
        ]);

        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->has('catalog', 1)
                ->where('catalog.0.name', 'Core Forms')
                ->has('catalog.0.products', 1)
                ->where('catalog.0.products.0.slug', 'axis-01')
                ->where('catalog.0.products.0.price', '245.00')
                ->has('catalog.0.products.0.images', 1)
                ->where('catalog.0.products.0.images.0.type', 'front')
            );
    }
}
