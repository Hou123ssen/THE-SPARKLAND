<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly CartService $carts,
        private readonly CartTotalsService $totals,
        private readonly InventoryReservationService $inventory,
    ) {}

    /** @param array<string, mixed> $input */
    public function place(Session $session, array $input): Order
    {
        $cart = $this->carts->existing($session);
        if ($cart === null) {
            throw ValidationException::withMessages(['cart' => 'An active cart is required to place an order.']);
        }

        $order = DB::transaction(function () use ($cart, $input): Order {
            $lockedCart = Cart::query()
                ->whereKey($cart->id)
                ->where('status', Cart::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();
            if ($lockedCart === null) {
                throw ValidationException::withMessages(['cart' => 'An active cart is required to place an order.']);
            }

            $lockedCart->load(['items' => fn ($items) => $items->orderBy('id')->lockForUpdate()]);
            if ($lockedCart->items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }

            $pricing = $this->carts->authoritativePricing($lockedCart);
            $quote = $this->totals->quoteFromPricing(
                $pricing,
                $input['coupon_code'] ?? null,
                $input['country_code'],
            );

            if (! $pricing['can_quote']) {
                throw ValidationException::withMessages(['cart' => 'This cart cannot currently be ordered.']);
            }

            foreach ($lockedCart->items->sortBy('sku_id') as $item) {
                $this->inventory->reserve($item->sku, $item->quantity);
            }

            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'cart_id' => $lockedCart->id,
                'user_id' => Auth::id(),
                'customer_name' => $input['customer_name'],
                'customer_phone' => $input['customer_phone'],
                'customer_email' => $input['customer_email'] ?? null,
                'address_line1' => $input['address_line1'],
                'address_line2' => $input['address_line2'] ?? null,
                'city' => $input['city'],
                'country_code' => $input['country_code'],
                'currency' => $quote['currency'],
                'merchandise_subtotal_minor' => $quote['merchandise_subtotal']['amount_minor'],
                'discount_minor' => $quote['discount']['amount_minor'],
                'shipping_minor' => $quote['shipping']['amount_minor'],
                'total_minor' => $quote['total']['amount_minor'],
                'coupon_code' => $quote['coupon']['code'] ?? null,
                'shipping_zone_code' => $quote['shipping_zone'],
                'payment_method' => Order::PAYMENT_METHOD_COD,
                'status' => Order::STATUS_PENDING,
                'payment_status' => Order::PAYMENT_PENDING,
                'placed_at' => now(),
            ]);

            foreach ($lockedCart->items as $item) {
                $line = $pricing['lines'][$item->id];
                $sku = $item->sku;
                $variant = $sku->variant;
                $product = $variant?->product;
                $options = $sku->optionValues
                    ->sortBy(fn ($value): array => [$value->option?->display_order ?? PHP_INT_MAX, $value->id])
                    ->map(fn ($value): array => [
                        'option' => ['code' => $value->option?->code, 'name' => $value->option?->name],
                        'value' => ['code' => $value->code, 'label' => $value->label],
                    ])->values()->all();

                OrderItem::create([
                    'order_id' => $order->id,
                    'sku_id' => $sku->id,
                    'sku_code' => $sku->sku,
                    'product_name' => $product?->name ?? 'Unknown product',
                    'variant_name' => $variant?->name,
                    'selected_options' => $options,
                    'quantity' => $item->quantity,
                    'unit_price_minor' => $line['unit_price']['amount_minor'],
                    'line_total_minor' => $line['line_total']['amount_minor'],
                ]);
            }

            $lockedCart->update(['status' => Cart::STATUS_CONVERTED]);

            return $order->fresh('items');
        });

        $session->forget(CartService::SESSION_KEY);

        return $order;
    }

    /** @return array<string, mixed> */
    public function serialize(Order $order): array
    {
        $order->loadMissing('items');

        return [
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'customer' => [
                'name' => $order->customer_name,
                'phone' => $order->customer_phone,
                'email' => $order->customer_email,
            ],
            'address' => [
                'line1' => $order->address_line1,
                'line2' => $order->address_line2,
                'city' => $order->city,
                'country_code' => $order->country_code,
            ],
            'currency' => $order->currency,
            'coupon_code' => $order->coupon_code,
            'shipping_zone_code' => $order->shipping_zone_code,
            'merchandise_subtotal' => ['amount_minor' => $order->merchandise_subtotal_minor, 'currency' => $order->currency],
            'discount' => ['amount_minor' => $order->discount_minor, 'currency' => $order->currency],
            'shipping' => ['amount_minor' => $order->shipping_minor, 'currency' => $order->currency],
            'total' => ['amount_minor' => $order->total_minor, 'currency' => $order->currency],
            'items' => $order->items->map(fn (OrderItem $item): array => [
                'sku_code' => $item->sku_code,
                'product_name' => $item->product_name,
                'variant_name' => $item->variant_name,
                'selected_options' => $item->selected_options,
                'quantity' => $item->quantity,
                'unit_price' => ['amount_minor' => $item->unit_price_minor, 'currency' => $order->currency],
                'line_total' => ['amount_minor' => $item->line_total_minor, 'currency' => $order->currency],
            ])->all(),
            'placed_at' => $order->placed_at?->toISOString(),
        ];
    }
}
