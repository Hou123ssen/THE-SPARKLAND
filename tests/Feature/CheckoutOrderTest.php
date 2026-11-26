<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Collection;
use App\Models\Coupon;
use App\Models\InventoryLevel;
use App\Models\InventoryLocation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use App\Models\ShippingRate;
use App\Models\Sku;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_place_cod_order_and_convert_cart(): void
    {
        [$product, $variant, $sku] = $this->sellableSku(stock: 5);
        $this->addToCart($sku, 2);

        $response = $this->postJson('/checkout/orders', $this->checkoutInput());

        $response->assertCreated()
            ->assertJsonPath('order.status', Order::STATUS_PENDING)
            ->assertJsonPath('order.payment_status', Order::PAYMENT_PENDING)
            ->assertJsonPath('order.payment_method', Order::PAYMENT_METHOD_COD)
            ->assertJsonPath('order.currency', 'MAD')
            ->assertJsonPath('order.merchandise_subtotal.amount_minor', 20000)
            ->assertJsonPath('order.shipping.amount_minor', 3000)
            ->assertJsonPath('order.total.amount_minor', 23000)
            ->assertJsonPath('order.items.0.quantity', 2);

        $order = Order::query()->firstOrFail();
        $this->assertDatabaseHas('carts', ['id' => $order->cart_id, 'status' => Cart::STATUS_CONVERTED]);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'sku_id' => $sku->id, 'sku_code' => $sku->sku]);
        $this->assertFalse($this->app['session']->has(CartService::SESSION_KEY));
        $this->assertSame($product->name, $order->items->first()->product_name);
        $this->assertSame($variant->name, $order->items->first()->variant_name);
    }

    public function test_browser_cannot_control_authoritative_order_fields(): void
    {
        [, , $sku] = $this->sellableSku();
        $this->addToCart($sku);

        $this->postJson('/checkout/orders', array_merge($this->checkoutInput(), [
            'total_minor' => 1,
            'currency' => 'EUR',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_PAID,
            'order_number' => 'FAKE',
            'user_id' => 99,
        ]))->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_price_and_shipping_are_recomputed_at_order_time(): void
    {
        [, , $sku] = $this->sellableSku(price: 10000);
        $this->addToCart($sku);
        $sku->prices()->firstOrFail()->update(['amount_minor' => 12000]);
        ShippingRate::query()->where('currency', 'MAD')->update(['amount_minor' => 4500]);

        $this->postJson('/checkout/orders', $this->checkoutInput())
            ->assertCreated()
            ->assertJsonPath('order.merchandise_subtotal.amount_minor', 12000)
            ->assertJsonPath('order.shipping.amount_minor', 4500)
            ->assertJsonPath('order.total.amount_minor', 16500);
    }

    public function test_coupon_expiry_before_order_is_rejected_without_mutation(): void
    {
        [, , $sku] = $this->sellableSku();
        $this->addToCart($sku);
        Coupon::create(['code' => 'SAVE', 'type' => Coupon::TYPE_FIXED, 'currency' => 'MAD', 'fixed_amount_minor' => 500, 'ends_at' => now()->subMinute()]);

        $this->postJson('/checkout/orders', array_merge($this->checkoutInput(), ['coupon_code' => 'SAVE']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('coupon_code');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('carts', ['status' => Cart::STATUS_ACTIVE]);
    }

    public function test_stock_drift_rejects_quote_and_order_without_reservation(): void
    {
        [, , $sku] = $this->sellableSku(stock: 5);
        $this->addToCart($sku, 4);
        $sku->inventoryLevels()->update(['on_hand' => 1]);

        $this->getJson('/cart')->assertJsonPath('cart.items.0.can_purchase', false)->assertJsonPath('cart.merchandise_subtotal', null);
        $this->postJson('/cart/quote', ['country_code' => 'MA'])->assertUnprocessable();
        $this->postJson('/checkout/orders', $this->checkoutInput())->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('inventory_levels', ['sku_id' => $sku->id, 'reserved' => 0]);
    }

    public function test_inactive_product_is_rejected_at_order_time(): void
    {
        [$product, , $sku] = $this->sellableSku();
        $this->addToCart($sku);
        $product->update(['is_active' => false]);

        $this->postJson('/checkout/orders', $this->checkoutInput())->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_inactive_variant_is_rejected_at_order_time(): void
    {
        [, $variant, $sku] = $this->sellableSku();
        $this->addToCart($sku);
        $variant->update(['is_active' => false]);
        $this->postJson('/checkout/orders', $this->checkoutInput())->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_inactive_sku_is_rejected_at_order_time(): void
    {
        [, , $sku] = $this->sellableSku();
        $this->addToCart($sku);
        $sku->update(['status' => Sku::STATUS_INACTIVE]);

        $this->postJson('/checkout/orders', $this->checkoutInput())->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_invalid_required_option_combination_is_rejected_at_order_time(): void
    {
        [$product, , $sku] = $this->sellableSku();
        $this->addToCart($sku);
        ProductOption::create(['product_id' => $product->id, 'name' => 'Finish', 'code' => 'finish', 'is_required' => true]);

        $this->postJson('/checkout/orders', $this->checkoutInput())->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_successful_order_reserves_deterministically_without_decrementing_on_hand(): void
    {
        [, , $sku] = $this->sellableSku(stock: 0);
        $first = InventoryLocation::create(['code' => 'A-'.uniqid(), 'name' => 'A', 'is_active' => true]);
        $second = InventoryLocation::create(['code' => 'B-'.uniqid(), 'name' => 'B', 'is_active' => true]);
        $levelOne = InventoryLevel::create(['sku_id' => $sku->id, 'inventory_location_id' => $first->id, 'on_hand' => 2, 'reserved' => 0]);
        $levelTwo = InventoryLevel::create(['sku_id' => $sku->id, 'inventory_location_id' => $second->id, 'on_hand' => 3, 'reserved' => 0]);
        $this->addToCart($sku, 4);

        $this->postJson('/checkout/orders', $this->checkoutInput())->assertCreated();

        $this->assertDatabaseHas('inventory_levels', ['id' => $levelOne->id, 'on_hand' => 2, 'reserved' => 2]);
        $this->assertDatabaseHas('inventory_levels', ['id' => $levelTwo->id, 'on_hand' => 3, 'reserved' => 2]);
    }

    public function test_inactive_locations_do_not_supply_stock(): void
    {
        [, , $sku] = $this->sellableSku(stock: 5);
        $this->addToCart($sku);
        InventoryLocation::query()->update(['is_active' => false]);

        $this->postJson('/checkout/orders', $this->checkoutInput())->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_insufficient_multi_item_stock_rolls_back_earlier_reservation(): void
    {
        [, , $firstSku] = $this->sellableSku(stock: 2);
        [, , $secondSku] = $this->sellableSku(stock: 2);
        $this->addToCart($firstSku);
        $this->addToCart($secondSku, 2);
        InventoryLevel::query()->where('sku_id', $secondSku->id)->update(['on_hand' => 1]);

        $this->postJson('/checkout/orders', $this->checkoutInput())->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('inventory_levels', ['sku_id' => $firstSku->id, 'reserved' => 0]);
        $this->assertDatabaseHas('inventory_levels', ['sku_id' => $secondSku->id, 'reserved' => 0]);
        $this->assertDatabaseHas('carts', ['status' => Cart::STATUS_ACTIVE]);
    }

    public function test_backorder_reserves_only_physical_stock(): void
    {
        [, , $sku] = $this->sellableSku(stock: 1, skuAttributes: ['is_backorderable' => true]);
        $this->addToCart($sku, 4);

        $this->postJson('/checkout/orders', $this->checkoutInput())->assertCreated();
        $this->assertDatabaseHas('inventory_levels', ['sku_id' => $sku->id, 'on_hand' => 1, 'reserved' => 1]);
    }

    public function test_one_cart_cannot_create_two_orders(): void
    {
        [, , $sku] = $this->sellableSku();
        $this->addToCart($sku);
        $this->postJson('/checkout/orders', $this->checkoutInput())->assertCreated();

        $this->postJson('/checkout/orders', $this->checkoutInput())->assertUnprocessable();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_order_item_snapshots_survive_catalog_changes(): void
    {
        [$product, $variant, $sku] = $this->sellableSku();
        $this->addToCart($sku);
        $this->postJson('/checkout/orders', $this->checkoutInput())->assertCreated();
        $item = OrderItem::query()->firstOrFail();
        $originalProductName = $item->product_name;
        $originalVariantName = $item->variant_name;
        $product->update(['name' => 'Changed Product']);
        $variant->update(['name' => 'Changed Variant']);
        $sku->prices()->firstOrFail()->update(['amount_minor' => 999]);

        $this->assertSame($originalProductName, $item->product_name);
        $this->assertSame($originalVariantName, $item->variant_name);
        $this->assertSame(10000, $item->fresh()->unit_price_minor);
    }

    public function test_no_active_or_empty_cart_is_rejected(): void
    {
        $this->postJson('/checkout/orders', $this->checkoutInput())->assertUnprocessable();
        Cart::create(['status' => Cart::STATUS_ACTIVE]);
        $this->withSession([CartService::SESSION_KEY => Cart::query()->latest('id')->value('id')])
            ->postJson('/checkout/orders', $this->checkoutInput())->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_invalid_payment_shipping_and_checkout_fields_are_rejected(): void
    {
        [, , $sku] = $this->sellableSku();
        $this->addToCart($sku);
        $this->postJson('/checkout/orders', array_merge($this->checkoutInput(), ['payment_method' => 'card']))->assertUnprocessable();
        $this->postJson('/checkout/orders', array_merge($this->checkoutInput(), ['country_code' => 'FR']))->assertUnprocessable();
        $this->postJson('/checkout/orders', ['customer_name' => ''])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_route_uses_session_blocking(): void
    {
        $route = app('router')->getRoutes()->getByName('checkout.orders.store');
        $this->assertNotNull($route?->locksFor());
    }

    /** @return array<string, string> */
    private function checkoutInput(): array
    {
        return [
            'customer_name' => 'Samira Example',
            'customer_phone' => '+212600000000',
            'customer_email' => 'samira@example.test',
            'address_line1' => '1 Rue Example',
            'city' => 'Casablanca',
            'country_code' => 'ma',
            'payment_method' => 'cash_on_delivery',
        ];
    }

    private function addToCart(Sku $sku, int $quantity = 1): void
    {
        $this->postJson('/cart/items', ['sku_id' => $sku->id, 'quantity' => $quantity])->assertOk();
    }

    /** @return array{Product, ProductVariant, Sku} */
    private function sellableSku(int $price = 10000, int $stock = 5, array $skuAttributes = []): array
    {
        $suffix = uniqid();
        $collection = Collection::create(['name' => 'Core '.$suffix, 'slug' => 'core-'.$suffix]);
        $product = Product::create(['collection_id' => $collection->id, 'name' => 'Axis '.$suffix, 'slug' => 'axis-'.$suffix, 'status' => Product::STATUS_ACTIVE, 'is_active' => true]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'slug' => 'black-'.$suffix, 'name' => 'Black '.$suffix, 'is_active' => true]);
        $sku = Sku::create(array_merge(['product_variant_id' => $variant->id, 'sku' => 'SKU-'.$suffix, 'status' => Sku::STATUS_ACTIVE], $skuAttributes));
        Price::create(['sku_id' => $sku->id, 'currency' => 'MAD', 'amount_minor' => $price]);
        $location = InventoryLocation::create(['code' => 'WH-'.$suffix, 'name' => 'Warehouse', 'is_active' => true]);
        InventoryLevel::create(['sku_id' => $sku->id, 'inventory_location_id' => $location->id, 'on_hand' => $stock, 'reserved' => 0]);

        return [$product, $variant, $sku];
    }
}
