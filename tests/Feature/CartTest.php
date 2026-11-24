<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
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
use App\Services\CartService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cart_uses_authoritative_sku_price_and_totals(): void
    {
        [, , $sku] = $this->sellableSku(price: 12500, stock: 5);

        $response = $this->postJson('/cart/items', [
            'sku_id' => $sku->id,
            'quantity' => 2,
            'price' => 1,
            'line_total' => 2,
        ]);

        $response->assertOk()
            ->assertJsonPath('cart.item_count', 2)
            ->assertJsonPath('cart.items.0.unit_price.amount_minor', 12500)
            ->assertJsonPath('cart.items.0.line_total.amount_minor', 25000)
            ->assertJsonPath('cart.merchandise_subtotal.amount_minor', 25000)
            ->assertJsonPath('cart.merchandise_subtotal.currency', 'MAD');
    }

    public function test_quantity_defaults_to_one_and_same_sku_merges_into_one_line(): void
    {
        [, , $sku] = $this->sellableSku(stock: 5);

        $this->postJson('/cart/items', ['sku_id' => $sku->id])->assertOk();
        $this->postJson('/cart/items', ['sku_id' => $sku->id, 'quantity' => 2])
            ->assertOk()
            ->assertJsonCount(1, 'cart.items')
            ->assertJsonPath('cart.items.0.quantity', 3);

        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_database_enforces_one_line_per_cart_and_sku(): void
    {
        [, , $sku] = $this->sellableSku();
        $cart = Cart::create(['status' => Cart::STATUS_ACTIVE]);
        CartItem::create(['cart_id' => $cart->id, 'sku_id' => $sku->id, 'quantity' => 1]);

        $this->expectException(QueryException::class);
        CartItem::create(['cart_id' => $cart->id, 'sku_id' => $sku->id, 'quantity' => 1]);
    }

    public function test_merged_quantity_is_validated_against_available_inventory(): void
    {
        [, , $sku] = $this->sellableSku(stock: 3);
        $this->postJson('/cart/items', ['sku_id' => $sku->id, 'quantity' => 2])->assertOk();

        $this->postJson('/cart/items', ['sku_id' => $sku->id, 'quantity' => 2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertDatabaseHas('cart_items', ['sku_id' => $sku->id, 'quantity' => 2]);
    }

    public function test_update_revalidates_quantity_and_never_clamps(): void
    {
        [, , $sku] = $this->sellableSku(stock: 4);
        $itemId = $this->postJson('/cart/items', ['sku_id' => $sku->id, 'quantity' => 2])
            ->json('cart.items.0.id');

        $this->patchJson("/cart/items/{$itemId}", ['quantity' => 4])
            ->assertOk()
            ->assertJsonPath('cart.items.0.quantity', 4);
        $this->patchJson("/cart/items/{$itemId}", ['quantity' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
        $this->assertDatabaseHas('cart_items', ['id' => $itemId, 'quantity' => 4]);
    }

    public function test_remove_leaves_a_valid_empty_cart(): void
    {
        [, , $sku] = $this->sellableSku();
        $itemId = $this->postJson('/cart/items', ['sku_id' => $sku->id])->json('cart.items.0.id');

        $this->deleteJson("/cart/items/{$itemId}")
            ->assertOk()
            ->assertJsonPath('cart.item_count', 0)
            ->assertJsonCount(0, 'cart.items')
            ->assertJsonPath('cart.merchandise_subtotal', null);
    }

    public function test_cart_item_ids_cannot_cross_session_cart_boundaries(): void
    {
        [, , $sku] = $this->sellableSku();
        $first = Cart::create(['status' => Cart::STATUS_ACTIVE]);
        $second = Cart::create(['status' => Cart::STATUS_ACTIVE]);
        $foreignItem = CartItem::create(['cart_id' => $first->id, 'sku_id' => $sku->id, 'quantity' => 1]);

        $this->withSession([CartService::SESSION_KEY => $second->id])
            ->patchJson("/cart/items/{$foreignItem->id}", ['quantity' => 2])
            ->assertNotFound();
        $this->withSession([CartService::SESSION_KEY => $second->id])
            ->deleteJson("/cart/items/{$foreignItem->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('cart_items', ['id' => $foreignItem->id, 'quantity' => 1]);
    }

    public function test_browser_supplied_cart_id_does_not_select_cart_ownership(): void
    {
        [, , $sku] = $this->sellableSku();
        $foreign = Cart::create(['status' => Cart::STATUS_ACTIVE]);

        $this->postJson('/cart/items', [
            'cart_id' => $foreign->id,
            'sku_id' => $sku->id,
            'quantity' => 1,
        ])->assertOk();

        $this->assertDatabaseMissing('cart_items', ['cart_id' => $foreign->id]);
        $this->assertNotSame($foreign->id, session(CartService::SESSION_KEY));
    }

    public function test_same_session_retains_the_same_cart_across_requests(): void
    {
        [, , $sku] = $this->sellableSku();
        $this->postJson('/cart/items', ['sku_id' => $sku->id])->assertOk();
        $cartId = session(CartService::SESSION_KEY);

        $this->getJson('/cart')
            ->assertOk()
            ->assertJsonPath('cart.items.0.sku_id', $sku->id);

        $this->assertSame($cartId, session(CartService::SESSION_KEY));
        $this->assertDatabaseCount('carts', 1);
    }

    public function test_sale_price_and_discount_are_derived_from_current_price(): void
    {
        [, , $sku] = $this->sellableSku(price: 8000, stock: 3, compareAt: 10000);

        $this->postJson('/cart/items', ['sku_id' => $sku->id, 'quantity' => 2])
            ->assertOk()
            ->assertJsonPath('cart.items.0.unit_price.amount_minor', 8000)
            ->assertJsonPath('cart.items.0.sale.compare_at_minor', 10000)
            ->assertJsonPath('cart.items.0.sale.discount_percentage', 20)
            ->assertJsonPath('cart.items.0.line_total.amount_minor', 16000);
    }

    public function test_cart_read_recalculates_price_instead_of_locking_add_time_price(): void
    {
        [, , $sku] = $this->sellableSku(price: 8000, stock: 3, compareAt: 10000);
        $this->postJson('/cart/items', ['sku_id' => $sku->id])->assertOk();

        $sku->prices()->first()->update(['amount_minor' => 10000, 'compare_at_minor' => null]);

        $this->getJson('/cart')
            ->assertOk()
            ->assertJsonPath('cart.items.0.unit_price.amount_minor', 10000)
            ->assertJsonPath('cart.items.0.sale', null)
            ->assertJsonPath('cart.merchandise_subtotal.amount_minor', 10000);
    }

    public function test_ambiguous_current_price_marks_existing_line_unpurchasable(): void
    {
        [, , $sku] = $this->sellableSku();
        $this->postJson('/cart/items', ['sku_id' => $sku->id])->assertOk();
        Price::create(['sku_id' => $sku->id, 'currency' => 'MAD', 'amount_minor' => 9900]);

        $this->getJson('/cart')
            ->assertOk()
            ->assertJsonPath('cart.items.0.unit_price', null)
            ->assertJsonPath('cart.items.0.line_total', null)
            ->assertJsonPath('cart.items.0.can_purchase', false)
            ->assertJsonPath('cart.merchandise_subtotal', null);

        $this->assertDatabaseHas('cart_items', ['sku_id' => $sku->id]);
    }

    public function test_disabled_product_remains_visible_as_a_stale_unpurchasable_line(): void
    {
        [$product, , $sku] = $this->sellableSku();
        $this->postJson('/cart/items', ['sku_id' => $sku->id])->assertOk();
        $product->update(['is_active' => false]);

        $this->getJson('/cart')
            ->assertOk()
            ->assertJsonPath('cart.items.0.product.name', $product->name)
            ->assertJsonPath('cart.items.0.can_purchase', false);

        $this->assertDatabaseHas('cart_items', ['sku_id' => $sku->id]);
    }

    public function test_inactive_sku_cannot_be_added(): void
    {
        [, , $sku] = $this->sellableSku(skuAttributes: ['status' => Sku::STATUS_INACTIVE]);

        $this->postJson('/cart/items', ['sku_id' => $sku->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sku_id');
    }

    public function test_only_active_inventory_locations_supply_available_quantity(): void
    {
        [, , $sku] = $this->sellableSku(stock: 2);
        $inactive = InventoryLocation::create(['code' => 'OFF-'.uniqid(), 'name' => 'Closed', 'is_active' => false]);
        InventoryLevel::create(['sku_id' => $sku->id, 'inventory_location_id' => $inactive->id, 'on_hand' => 100, 'reserved' => 0]);

        $this->postJson('/cart/items', ['sku_id' => $sku->id, 'quantity' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
    }

    public function test_reserved_inventory_reduces_maximum_quantity(): void
    {
        [, , $sku] = $this->sellableSku(stock: 5, reserved: 3);

        $this->postJson('/cart/items', ['sku_id' => $sku->id, 'quantity' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
        $this->postJson('/cart/items', ['sku_id' => $sku->id, 'quantity' => 2])->assertOk();
    }

    public function test_backorderable_sku_can_be_added_without_stock(): void
    {
        [, , $sku] = $this->sellableSku(stock: 0, skuAttributes: ['is_backorderable' => true]);

        $this->postJson('/cart/items', ['sku_id' => $sku->id, 'quantity' => 25])
            ->assertOk()
            ->assertJsonPath('cart.items.0.availability', 'backorder')
            ->assertJsonPath('cart.items.0.can_purchase', true);
    }

    public function test_adding_to_cart_does_not_decrement_or_reserve_inventory(): void
    {
        [, , $sku] = $this->sellableSku(stock: 5, reserved: 1);
        $level = $sku->inventoryLevels()->first();

        $this->postJson('/cart/items', ['sku_id' => $sku->id, 'quantity' => 2])->assertOk();

        $this->assertDatabaseHas('inventory_levels', [
            'id' => $level->id,
            'on_hand' => 5,
            'reserved' => 1,
        ]);
    }

    public function test_selected_options_are_derived_from_generic_sku_relations(): void
    {
        [$product, , $sku] = $this->sellableSku();
        $option = ProductOption::create([
            'product_id' => $product->id,
            'name' => 'Finish',
            'code' => 'finish',
            'is_required' => true,
        ]);
        $value = ProductOptionValue::create([
            'product_option_id' => $option->id,
            'label' => 'Matte',
            'code' => 'matte',
        ]);
        $sku->syncOptionValues([$value->id]);

        $this->postJson('/cart/items', ['sku_id' => $sku->id])
            ->assertOk()
            ->assertJsonPath('cart.items.0.selected_options.0.option.name', 'Finish')
            ->assertJsonPath('cart.items.0.selected_options.0.value.label', 'Matte');
    }

    public function test_missing_required_option_value_prevents_add(): void
    {
        [$product, , $sku] = $this->sellableSku();
        ProductOption::create([
            'product_id' => $product->id,
            'name' => 'Finish',
            'code' => 'finish',
            'is_required' => true,
        ]);

        $this->postJson('/cart/items', ['sku_id' => $sku->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sku_id');
    }

    public function test_primary_media_uses_existing_public_manifest_contract(): void
    {
        [$product, $variant, $sku] = $this->sellableSku();
        $asset = MediaAsset::create([
            'disk' => 'public',
            'path' => '/images/cart-frame.png',
            'mime_type' => 'image/png',
            'width' => 960,
            'height' => 600,
        ]);
        ProductMedia::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'media_asset_id' => $asset->id,
            'kind' => 'image',
            'role' => 'front',
            'is_primary' => true,
        ]);

        $this->postJson('/cart/items', ['sku_id' => $sku->id])
            ->assertOk()
            ->assertJsonPath('cart.items.0.media.responsive.fallback.0.url', '/images/cart-frame.png');
    }

    public function test_mixed_currencies_are_rejected_before_a_second_line_is_saved(): void
    {
        [, , $madSku] = $this->sellableSku(currency: 'MAD');
        [, , $eurSku] = $this->sellableSku(currency: 'EUR');
        $this->postJson('/cart/items', ['sku_id' => $madSku->id])->assertOk();

        $this->postJson('/cart/items', ['sku_id' => $eurSku->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sku_id');

        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_quantity_validation_requires_positive_integers(): void
    {
        [, , $sku] = $this->sellableSku();

        $this->postJson('/cart/items', ['sku_id' => $sku->id, 'quantity' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->postJson('/cart/items', ['sku_id' => $sku->id, 'quantity' => 1.5])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
    }

    public function test_cart_mutations_are_registered_in_the_web_middleware_group(): void
    {
        foreach (['cart.items.store', 'cart.items.update', 'cart.items.destroy'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertContains('web', $route->middleware());
        }
    }

    /** @return array{Product, ProductVariant, Sku} */
    private function sellableSku(
        int $price = 10000,
        int $stock = 5,
        int $reserved = 0,
        string $currency = 'MAD',
        ?int $compareAt = null,
        array $productAttributes = [],
        array $skuAttributes = [],
    ): array {
        $suffix = uniqid();
        $collection = Collection::create(['name' => 'Core '.$suffix, 'slug' => 'core-'.$suffix]);
        $product = Product::create([
            'collection_id' => $collection->id,
            'name' => 'Axis '.$suffix,
            'slug' => 'axis-'.$suffix,
            'status' => Product::STATUS_ACTIVE,
            'is_active' => true,
            ...$productAttributes,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'slug' => 'black',
            'name' => 'Black',
            'is_active' => true,
        ]);
        $sku = Sku::create([
            'product_variant_id' => $variant->id,
            'sku' => 'SKU-'.$suffix,
            'status' => Sku::STATUS_ACTIVE,
            ...$skuAttributes,
        ]);
        Price::create([
            'sku_id' => $sku->id,
            'currency' => $currency,
            'amount_minor' => $price,
            'compare_at_minor' => $compareAt,
        ]);
        $location = InventoryLocation::create([
            'code' => 'WH-'.$suffix,
            'name' => 'Warehouse',
            'is_active' => true,
        ]);
        InventoryLevel::create([
            'sku_id' => $sku->id,
            'inventory_location_id' => $location->id,
            'on_hand' => $stock,
            'reserved' => $reserved,
        ]);

        return [$product, $variant, $sku];
    }
}
