<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\InventoryLevel;
use App\Models\InventoryLocation;
use App\Models\MediaAsset;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\Sku;
use App\Services\ProductDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_active_product_resolves_by_slug_and_renders_product_show(): void
    {
        [$product] = $this->product();
        $this->get('/products/'.$product->slug)->assertOk()->assertSee('ProductShow')->assertSee($product->slug);
    }

    public function test_inactive_draft_and_future_products_are_not_public(): void
    {
        foreach ([['is_active' => false], ['status' => Product::STATUS_DRAFT], ['published_at' => now()->addDay()]] as $attributes) {
            [$product] = $this->product($attributes);
            $this->get('/products/'.$product->slug)->assertNotFound();
        }
    }

    public function test_only_active_variants_and_active_skus_serialize(): void
    {
        [$product, $variant, $sku] = $this->product();
        $inactiveVariant = ProductVariant::create(['product_id' => $product->id, 'slug' => 'hidden', 'name' => 'Hidden', 'is_active' => false]);
        Sku::create(['product_variant_id' => $inactiveVariant->id, 'sku' => 'HIDDEN']);
        $inactiveSku = Sku::create(['product_variant_id' => $variant->id, 'sku' => 'INACTIVE', 'status' => Sku::STATUS_INACTIVE]);

        $detail = app(ProductDetail::class)->forSlug($product->slug);
        $this->assertCount(1, $detail['variants']);
        $this->assertSame([$sku->id], array_column($detail['variants'][0]['skus'], 'id'));
        $this->assertNotSame($inactiveSku->id, $detail['variants'][0]['skus'][0]['id']);
    }

    public function test_options_and_active_values_serialize_in_display_order(): void
    {
        [$product] = $this->product();
        $late = $this->option($product, 'Late', 'late', 2);
        $early = $this->option($product, 'Early', 'early', 1);
        $this->value($late, 'Hidden', 'hidden', 1, false, false);
        $this->value($late, 'Second', 'second', 2);
        $this->value($late, 'First', 'first', 1);
        $this->value($early, 'Only', 'only', 1);

        $options = app(ProductDetail::class)->forSlug($product->slug)['options'];
        $this->assertSame(['early', 'late'], array_column($options, 'code'));
        $this->assertSame(['first', 'second'], array_column($options[1]['values'], 'code'));
    }

    public function test_existing_sku_without_options_remains_valid_and_is_initially_selected(): void
    {
        [$product, $variant, $sku] = $this->product();
        $detail = app(ProductDetail::class)->forSlug($product->slug);
        $this->assertSame([], $detail['options']);
        $this->assertSame($sku->id, $detail['initial_selection']['sku_id']);
    }

    public function test_sku_omitting_an_active_required_option_is_not_public_or_initially_selected(): void
    {
        [$product] = $this->product();
        $this->option($product, 'Required', 'required', 0, true);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        app(ProductDetail::class)->forSlug($product->slug);
    }

    public function test_multiple_skus_without_options_do_not_guess_an_initial_sku(): void
    {
        [$product, $variant] = $this->product();
        Sku::create(['product_variant_id' => $variant->id, 'sku' => 'SECOND-'.uniqid(), 'status' => Sku::STATUS_ACTIVE]);
        $this->assertNull(app(ProductDetail::class)->forSlug($product->slug)['initial_selection']);
    }

    public function test_sku_option_values_serialize_the_correct_combination(): void
    {
        [$product, , $sku] = $this->product();
        $option = $this->option($product, 'Finish', 'finish');
        $value = $this->value($option, 'Case', 'case');
        $sku->syncOptionValues([$value->id]);

        $serialized = app(ProductDetail::class)->forSlug($product->slug)['variants'][0]['skus'][0];
        $this->assertSame([['id' => $value->id, 'option_id' => $option->id, 'code' => 'case']], $serialized['option_values']);
    }

    public function test_cross_product_option_values_cannot_be_assigned_or_resolved(): void
    {
        [$product, , $sku] = $this->product();
        [$other] = $this->product(['slug' => 'other']);
        $foreign = $this->value($this->option($other, 'Foreign', 'foreign'), 'Wrong', 'wrong');
        $this->expectException(\InvalidArgumentException::class);
        $sku->syncOptionValues([$foreign->id]);
    }

    public function test_service_omits_a_manually_corrupted_cross_product_combination(): void
    {
        [$product, , $sku] = $this->product();
        [$other] = $this->product(['slug' => 'other']);
        $foreign = $this->value($this->option($other, 'Foreign', 'foreign'), 'Wrong', 'wrong');
        $sku->optionValues()->attach($foreign->id, ['product_option_id' => $foreign->product_option_id]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        app(ProductDetail::class)->forSlug($product->slug);
    }

    public function test_two_values_from_the_same_option_cannot_form_a_sku_combination(): void
    {
        [$product, , $sku] = $this->product();
        $option = $this->option($product, 'Finish', 'finish');
        $first = $this->value($option, 'First', 'first');
        $second = $this->value($option, 'Second', 'second');
        $this->expectException(\InvalidArgumentException::class);
        $sku->syncOptionValues([$first->id, $second->id]);
    }

    public function test_database_rejects_a_value_claiming_a_different_option(): void
    {
        [$product, , $sku] = $this->product();
        $first = $this->option($product, 'First', 'first');
        $second = $this->option($product, 'Second', 'second');
        $value = $this->value($first, 'Value', 'value');
        $this->expectException(\Illuminate\Database\QueryException::class);
        $sku->optionValues()->attach($value->id, ['product_option_id' => $second->id]);
    }

    public function test_invalid_assignment_rolls_back_and_preserves_existing_values(): void
    {
        [$product, , $sku] = $this->product();
        $valid = $this->value($this->option($product, 'Optional', 'optional'), 'Valid', 'valid');
        $sku->syncOptionValues([$valid->id]);
        [$other] = $this->product(['slug' => 'other-atomic']);
        $invalid = $this->value($this->option($other, 'Other', 'other'), 'Invalid', 'invalid');
        try { $sku->syncOptionValues([$invalid->id]); $this->fail('Expected invalid assignment.'); } catch (\InvalidArgumentException) {}
        $this->assertSame([$valid->id], $sku->fresh()->optionValues()->pluck('product_option_values.id')->all());
    }

    public function test_duplicate_option_value_input_is_rejected(): void
    {
        [$product, , $sku] = $this->product();
        $value = $this->value($this->option($product, 'Optional', 'optional'), 'Value', 'value');
        $this->expectException(\InvalidArgumentException::class);
        $sku->syncOptionValues([$value->id, $value->id]);
    }

    public function test_current_price_serializes_in_minor_units_without_inventing_currency(): void
    {
        [$product, , $sku] = $this->product();
        Price::create(['sku_id' => $sku->id, 'currency' => 'XXX', 'amount_minor' => 12345]);
        $price = app(ProductDetail::class)->forSlug($product->slug)['variants'][0]['skus'][0]['price'];
        $this->assertSame(['amount_minor' => 12345, 'currency' => 'XXX'], $price);
    }

    public function test_valid_compare_at_serializes_sale_and_discount(): void
    {
        [$product, , $sku] = $this->product();
        Price::create(['sku_id' => $sku->id, 'currency' => 'XXX', 'amount_minor' => 8000, 'compare_at_minor' => 10000]);
        $sale = app(ProductDetail::class)->forSlug($product->slug)['variants'][0]['skus'][0]['sale'];
        $this->assertSame(['compare_at_minor' => 10000, 'discount_percentage' => 20], $sale);
    }

    public function test_invalid_compare_at_does_not_create_sale_state(): void
    {
        foreach ([8000, 7000] as $compareAt) {
            [$product, , $sku] = $this->product(['slug' => 'price-'.$compareAt]);
            Price::create(['sku_id' => $sku->id, 'currency' => 'XXX', 'amount_minor' => 8000, 'compare_at_minor' => $compareAt]);
            $this->assertNull(app(ProductDetail::class)->forSlug($product->slug)['variants'][0]['skus'][0]['sale']);
        }
    }

    public function test_ambiguous_current_prices_do_not_invent_a_price(): void
    {
        [$product, , $sku] = $this->product();
        Price::create(['sku_id' => $sku->id, 'currency' => 'XXX', 'amount_minor' => 8000]);
        Price::create(['sku_id' => $sku->id, 'currency' => 'USD', 'amount_minor' => 9000]);
        $this->assertNull(app(ProductDetail::class)->forSlug($product->slug)['variants'][0]['skus'][0]['price']);
    }

    public function test_positive_inventory_is_in_stock_and_can_purchase(): void
    {
        [$product, , $sku] = $this->product();
        $this->stock($sku, 3, 0);
        $skuData = app(ProductDetail::class)->forSlug($product->slug)['variants'][0]['skus'][0];
        $this->assertSame('in_stock', $skuData['availability']); $this->assertTrue($skuData['can_purchase']);
    }

    public function test_reserved_inventory_reduces_availability(): void
    {
        [$product, , $sku] = $this->product();
        $this->stock($sku, 3, 3);
        $skuData = app(ProductDetail::class)->forSlug($product->slug)['variants'][0]['skus'][0];
        $this->assertSame('out_of_stock', $skuData['availability']); $this->assertFalse($skuData['can_purchase']);
    }

    public function test_no_inventory_is_out_of_stock(): void
    {
        [$product] = $this->product(['slug' => 'no-stock']);
        $skuData = app(ProductDetail::class)->forSlug($product->slug)['variants'][0]['skus'][0];
        $this->assertSame('out_of_stock', $skuData['availability']);
        $this->assertFalse($skuData['can_purchase']);
    }

    public function test_zero_stock_backorderable_sku_is_backorder(): void
    {
        [$product, , $sku] = $this->product(['slug' => 'backorder'], ['is_backorderable' => true]);
        $this->assertSame('backorder', app(ProductDetail::class)->forSlug($product->slug)['variants'][0]['skus'][0]['availability']);
    }

    public function test_inactive_location_stock_is_not_available_and_active_locations_aggregate(): void
    {
        [$product, , $sku] = $this->product();
        $inactive = InventoryLocation::create(['code' => 'OFF-'.uniqid(), 'name' => 'Inactive', 'is_active' => false]);
        InventoryLevel::create(['sku_id' => $sku->id, 'inventory_location_id' => $inactive->id, 'on_hand' => 9]);
        $this->assertSame('out_of_stock', app(ProductDetail::class)->forSlug($product->slug)['variants'][0]['skus'][0]['availability']);
        $this->stock($sku, 1, 1); $this->stock($sku, 2, 0);
        $this->assertSame('in_stock', app(ProductDetail::class)->forSlug($product->slug)['variants'][0]['skus'][0]['availability']);
    }

    public function test_variant_media_uses_the_existing_variant_aware_manifest(): void
    {
        [$product, $variant] = $this->product();
        $this->media($product, null, 'front', '/product-front.png');
        $this->media($product, $variant, 'front', '/variant-front.png');
        $this->media($product, null, 'side', '/product-side.png');
        $media = app(ProductDetail::class)->forSlug($product->slug)['variants'][0]['media'];
        $this->assertSame(['/variant-front.png', '/product-side.png'], array_map(fn (array $item): string => $item['responsive']['fallback'][0]['url'], $media));
    }

    public function test_missing_optional_specifications_are_omitted(): void
    {
        [$product] = $this->product();
        $specifications = app(ProductDetail::class)->forSlug($product->slug)['variants'][0]['specifications'];
        $this->assertArrayNotHasKey('material', $specifications);
        $this->assertArrayNotHasKey('fit', $specifications);
    }

    public function test_required_defaults_resolve_exactly_one_initial_sku(): void
    {
        [$product, $variant, $sku] = $this->product();
        $option = $this->option($product, 'Finish', 'finish', 0, true);
        $default = $this->value($option, 'Standard', 'standard', 0, true);
        $sku->syncOptionValues([$default->id]);
        $this->assertSame($sku->id, app(ProductDetail::class)->forSlug($product->slug)['initial_selection']['sku_id']);
    }

    public function test_unresolved_required_options_do_not_invent_an_initial_sku_or_price(): void
    {
        [$product, , $sku] = $this->product();
        $option = $this->option($product, 'Finish', 'finish', 0, true);
        $first = $this->value($option, 'First', 'first', 0, true);
        $this->value($option, 'Second', 'second', 1, true);
        $sku->syncOptionValues([$first->id]);
        $detail = app(ProductDetail::class)->forSlug($product->slug);
        $this->assertNull($detail['initial_selection']);
    }

    public function test_options_require_unique_codes_per_product(): void
    {
        [$product] = $this->product();
        $this->option($product, 'Finish', 'finish');
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->option($product, 'Duplicate', 'finish');
    }

    public function test_values_require_unique_codes_per_option(): void
    {
        [$product] = $this->product();
        $option = $this->option($product, 'Finish', 'finish');
        $this->value($option, 'First', 'same');
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->value($option, 'Second', 'same');
    }

    /** @return array{Product, ProductVariant, Sku} */
    private function product(array $productAttributes = [], array $skuAttributes = []): array
    {
        $suffix = uniqid();
        $collection = Collection::create(['name' => 'Core '.$suffix, 'slug' => 'core-'.$suffix]);
        $product = Product::create(['collection_id' => $collection->id, 'name' => 'Axis', 'slug' => 'axis-'.$suffix, 'status' => Product::STATUS_ACTIVE, 'is_active' => true, ...$productAttributes]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'slug' => 'black', 'name' => 'Black', 'is_active' => true, 'display_order' => 0]);
        $sku = Sku::create(['product_variant_id' => $variant->id, 'sku' => 'SKU-'.$suffix, 'status' => Sku::STATUS_ACTIVE, ...$skuAttributes]);
        return [$product, $variant, $sku];
    }

    private function option(Product $product, string $name, string $code, int $order = 0, bool $required = false): ProductOption
    {
        return ProductOption::create(['product_id' => $product->id, 'name' => $name, 'code' => $code, 'display_order' => $order, 'is_required' => $required]);
    }

    private function value(ProductOption $option, string $label, string $code, int $order = 0, bool $default = false, bool $active = true): ProductOptionValue
    {
        return ProductOptionValue::create(['product_option_id' => $option->id, 'label' => $label, 'code' => $code, 'display_order' => $order, 'is_default' => $default, 'is_active' => $active]);
    }

    private function stock(Sku $sku, int $onHand, int $reserved): void
    {
        $location = InventoryLocation::create(['code' => 'WH-'.uniqid(), 'name' => 'Warehouse', 'is_active' => true]);
        InventoryLevel::create(['sku_id' => $sku->id, 'inventory_location_id' => $location->id, 'on_hand' => $onHand, 'reserved' => $reserved]);
    }

    private function media(Product $product, ?ProductVariant $variant, string $role, string $path): void
    {
        $asset = MediaAsset::create(['disk' => 'public', 'path' => $path, 'mime_type' => 'image/png', 'width' => 100, 'height' => 100]);
        ProductMedia::create(['product_id' => $product->id, 'product_variant_id' => $variant?->id, 'media_asset_id' => $asset->id, 'kind' => 'image', 'role' => $role]);
    }
}
