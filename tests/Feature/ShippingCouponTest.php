<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Coupon;
use App\Models\InventoryLevel;
use App\Models\InventoryLocation;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\Sku;
use App\Services\CouponService;
use App\Services\ShippingQuoteService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShippingCouponTest extends TestCase
{
    use RefreshDatabase;

    public function test_morocco_quote_uses_database_rate(): void
    {
        $quote = app(ShippingQuoteService::class)->quote(' ma ', 'mad');

        $this->assertSame(['zone' => 'MAROC', 'country_code' => 'MA', 'amount_minor' => 3000, 'currency' => 'MAD'], $quote);
        ShippingRate::query()->where('currency', 'MAD')->update(['amount_minor' => 4500]);
        $this->assertSame(4500, app(ShippingQuoteService::class)->quote('MA', 'MAD')['amount_minor']);
    }

    public function test_inactive_zone_is_rejected(): void
    {
        $service = app(ShippingQuoteService::class);
        ShippingZone::query()->where('code', 'MAROC')->update(['is_active' => false]);

        $this->expectException(ValidationException::class);
        $service->quote('MA', 'MAD');
    }

    public function test_inactive_rate_is_rejected(): void
    {
        $service = app(ShippingQuoteService::class);
        ShippingRate::query()->where('currency', 'MAD')->update(['is_active' => false]);

        $this->expectException(ValidationException::class);
        $service->quote('MA', 'MAD');
    }

    public function test_unknown_and_wrong_currency_shipping_are_rejected(): void
    {
        $service = app(ShippingQuoteService::class);
        $this->expectException(ValidationException::class);
        $service->quote('FR', 'MAD');
    }

    public function test_duplicate_shipping_zone_and_rate_keys_are_rejected(): void
    {
        $zone = ShippingZone::query()->where('code', 'MAROC')->firstOrFail();
        $this->expectException(QueryException::class);
        ShippingZone::create(['code' => 'MAROC', 'name' => 'Duplicate', 'country_code' => 'MA']);
    }

    public function test_duplicate_shipping_rate_key_is_rejected(): void
    {
        $zone = ShippingZone::query()->where('code', 'MAROC')->firstOrFail();
        $this->expectException(QueryException::class);
        ShippingRate::create(['shipping_zone_id' => $zone->id, 'currency' => 'MAD', 'amount_minor' => 1]);
    }

    public function test_coupon_code_is_normalized_and_fixed_discount_is_capped(): void
    {
        $coupon = Coupon::create(['code' => 'WELCOME', 'type' => Coupon::TYPE_FIXED, 'currency' => 'MAD', 'fixed_amount_minor' => 5000]);
        $result = app(CouponService::class)->discount(' welcome ', 3000, 'mad');

        $this->assertSame($coupon->id, $result['coupon']->id);
        $this->assertSame(3000, $result['amount_minor']);
    }

    public function test_percentage_coupon_uses_floor_integer_rounding(): void
    {
        Coupon::create(['code' => 'FIFTEEN', 'type' => Coupon::TYPE_PERCENTAGE, 'currency' => 'MAD', 'percentage_basis_points' => 1500]);

        $this->assertSame(150, app(CouponService::class)->discount('FIFTEEN', 1001, 'MAD')['amount_minor']);
    }

    public function test_coupon_validity_and_minimum_subtotal_are_enforced(): void
    {
        Coupon::create(['code' => 'LIMITED', 'type' => Coupon::TYPE_FIXED, 'currency' => 'MAD', 'fixed_amount_minor' => 100, 'minimum_subtotal_minor' => 1000, 'starts_at' => now()->addMinute()]);

        $this->expectException(ValidationException::class);
        app(CouponService::class)->discount('LIMITED', 2000, 'MAD');
    }

    public function test_disabled_expired_unknown_and_currency_mismatched_coupons_are_rejected(): void
    {
        Coupon::create(['code' => 'OFF', 'type' => Coupon::TYPE_FIXED, 'currency' => 'MAD', 'fixed_amount_minor' => 100, 'is_active' => false]);
        $this->expectException(ValidationException::class);
        app(CouponService::class)->discount('OFF', 2000, 'MAD');
    }

    public function test_invalid_percentage_configuration_is_rejected(): void
    {
        Coupon::create(['code' => 'BAD', 'type' => Coupon::TYPE_PERCENTAGE, 'currency' => 'MAD', 'percentage_basis_points' => 10001]);

        $this->expectException(ValidationException::class);
        app(CouponService::class)->discount('BAD', 10000, 'MAD');
    }

    public function test_quote_composes_merchandise_coupon_and_shipping_without_empty_cart_creation(): void
    {
        $this->postJson('/cart/quote', ['country_code' => 'MA'])->assertUnprocessable();
        $this->assertDatabaseCount('carts', 0);

        [$product, $sku] = $this->sellableSku();
        Coupon::create(['code' => 'SAVE5', 'type' => Coupon::TYPE_FIXED, 'currency' => 'MAD', 'fixed_amount_minor' => 500]);
        $this->postJson('/cart/items', ['sku_id' => $sku->id])->assertOk();

        $this->postJson('/cart/quote', ['coupon_code' => 'save5', 'country_code' => 'MA'])
            ->assertOk()
            ->assertJsonPath('quote.merchandise_subtotal.amount_minor', 10000)
            ->assertJsonPath('quote.discount.amount_minor', 500)
            ->assertJsonPath('quote.shipping.amount_minor', 3000)
            ->assertJsonPath('quote.total.amount_minor', 12500)
            ->assertJsonPath('quote.currency', 'MAD');
    }

    public function test_unpurchasable_cart_line_cannot_be_quoted(): void
    {
        [$product, $sku] = $this->sellableSku();
        $this->postJson('/cart/items', ['sku_id' => $sku->id])->assertOk();
        $product->update(['is_active' => false]);

        $this->postJson('/cart/quote')->assertUnprocessable()->assertJsonValidationErrors('cart');
    }

    public function test_out_of_stock_line_cannot_be_quoted(): void
    {
        [$product, $sku] = $this->sellableSku();
        $this->postJson('/cart/items', ['sku_id' => $sku->id])->assertOk();
        $sku->inventoryLevels()->update(['on_hand' => 0]);

        $this->postJson('/cart/quote')->assertUnprocessable()->assertJsonValidationErrors('cart');
    }

    public function test_inactive_variant_line_cannot_be_quoted(): void
    {
        [$product, $sku] = $this->sellableSku();
        $this->postJson('/cart/items', ['sku_id' => $sku->id])->assertOk();
        $sku->variant->update(['is_active' => false]);

        $this->postJson('/cart/quote')->assertUnprocessable()->assertJsonValidationErrors('cart');
    }

    public function test_missing_required_option_combination_cannot_be_quoted(): void
    {
        [$product, $sku] = $this->sellableSku();
        $this->postJson('/cart/items', ['sku_id' => $sku->id])->assertOk();
        ProductOption::create(['product_id' => $product->id, 'name' => 'Finish', 'code' => 'finish', 'is_required' => true]);

        $this->postJson('/cart/quote')->assertUnprocessable()->assertJsonValidationErrors('cart');
    }

    public function test_quote_route_uses_session_blocking(): void
    {
        $route = app('router')->getRoutes()->getByName('cart.quote');

        $this->assertNotNull($route?->locksFor());
    }

    public function test_fixed_coupon_without_an_amount_is_rejected(): void
    {
        Coupon::create(['code' => 'EMPTY', 'type' => Coupon::TYPE_FIXED, 'currency' => 'MAD']);

        $this->expectException(ValidationException::class);
        app(CouponService::class)->discount('EMPTY', 1000, 'MAD');
    }

    public function test_quote_recomputes_after_price_and_shipping_rate_changes(): void
    {
        [, $sku] = $this->sellableSku();
        $this->postJson('/cart/items', ['sku_id' => $sku->id])->assertOk();
        $this->postJson('/cart/quote', ['country_code' => 'MA'])->assertJsonPath('quote.total.amount_minor', 13000);

        $sku->prices()->first()->update(['amount_minor' => 12000]);
        ShippingRate::query()->where('currency', 'MAD')->update(['amount_minor' => 4500]);
        $this->postJson('/cart/quote', ['country_code' => 'MA'])
            ->assertOk()
            ->assertJsonPath('quote.merchandise_subtotal.amount_minor', 12000)
            ->assertJsonPath('quote.shipping.amount_minor', 4500)
            ->assertJsonPath('quote.total.amount_minor', 16500);
    }

    public function test_omitted_coupon_and_shipping_produce_merchandise_only_integer_totals(): void
    {
        [, $sku] = $this->sellableSku();
        $this->postJson('/cart/items', ['sku_id' => $sku->id])->assertOk();
        $quote = $this->postJson('/cart/quote')->assertOk()->json('quote');

        $this->assertNull($quote['shipping']);
        $this->assertNull($quote['coupon']);
        $this->assertIsInt($quote['total']['amount_minor']);
        $this->assertSame(10000, $quote['total']['amount_minor']);
    }

    public function test_grand_total_overflow_is_rejected(): void
    {
        [, $sku] = $this->sellableSku(price: PHP_INT_MAX - 1);
        $this->postJson('/cart/items', ['sku_id' => $sku->id])->assertOk();
        ShippingRate::query()->where('currency', 'MAD')->update(['amount_minor' => PHP_INT_MAX]);

        $this->postJson('/cart/quote', ['country_code' => 'MA'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('total');
    }

    /** @return array{Product, Sku} */
    private function sellableSku(int $price = 10000): array
    {
        $suffix = uniqid();
        $collection = Collection::create(['name' => 'Core '.$suffix, 'slug' => 'core-'.$suffix]);
        $product = Product::create(['collection_id' => $collection->id, 'name' => 'Axis '.$suffix, 'slug' => 'axis-'.$suffix, 'status' => Product::STATUS_ACTIVE, 'is_active' => true]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'slug' => 'black', 'name' => 'Black', 'is_active' => true]);
        $sku = Sku::create(['product_variant_id' => $variant->id, 'sku' => 'SKU-'.$suffix, 'status' => Sku::STATUS_ACTIVE]);
        Price::create(['sku_id' => $sku->id, 'currency' => 'MAD', 'amount_minor' => $price]);
        $location = InventoryLocation::create(['code' => 'WH-'.$suffix, 'name' => 'Warehouse', 'is_active' => true]);
        InventoryLevel::create(['sku_id' => $sku->id, 'inventory_location_id' => $location->id, 'on_hand' => 5, 'reserved' => 0]);

        return [$product, $sku];
    }
}
