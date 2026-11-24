<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Price;
use App\Models\Sku;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public const SESSION_KEY = 'cart.active_id';
    public const MAX_QUANTITY = 4_294_967_295;

    public function __construct(
        private readonly SkuCommerceState $commerceState,
        private readonly ProductMediaManifest $mediaManifest,
    ) {}

    public function current(Session $session): Cart
    {
        $cartId = $session->get(self::SESSION_KEY);
        $cart = is_int($cartId) || ctype_digit((string) $cartId)
            ? Cart::query()->whereKey((int) $cartId)->where('status', Cart::STATUS_ACTIVE)->first()
            : null;

        if ($cart !== null) {
            return $cart;
        }

        $cart = Cart::query()->create([
            'user_id' => Auth::id(),
            'status' => Cart::STATUS_ACTIVE,
        ]);
        $session->put(self::SESSION_KEY, $cart->id);

        return $cart;
    }

    public function existing(Session $session): ?Cart
    {
        $cartId = $session->get(self::SESSION_KEY);

        return is_int($cartId) || ctype_digit((string) $cartId)
            ? Cart::query()->whereKey((int) $cartId)->where('status', Cart::STATUS_ACTIVE)->first()
            : null;
    }

    public function add(Session $session, int $skuId, int $quantity): Cart
    {
        $cart = $this->current($session);

        DB::transaction(function () use ($cart, $skuId, $quantity): void {
            Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $sku = $this->skuQuery()->whereKey($skuId)->lockForUpdate()->first();
            if ($sku === null) {
                throw ValidationException::withMessages(['sku_id' => 'The selected item is unavailable.']);
            }

            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('sku_id', $sku->id)
                ->lockForUpdate()
                ->first();
            $existingQuantity = $item?->quantity ?? 0;
            if ($quantity > self::MAX_QUANTITY || $existingQuantity > self::MAX_QUANTITY - $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'The requested quantity exceeds the technical cart limit.',
                ]);
            }
            $newQuantity = $existingQuantity + $quantity;
            $this->validateQuantity($sku, $newQuantity);
            $this->validateCartCurrency($cart, $sku);

            if ($item === null) {
                CartItem::query()->create([
                    'cart_id' => $cart->id,
                    'sku_id' => $sku->id,
                    'quantity' => $newQuantity,
                ]);
            } else {
                $item->update(['quantity' => $newQuantity]);
            }
        });

        return $cart->fresh();
    }

    public function update(Session $session, int $itemId, int $quantity): Cart
    {
        $cart = $this->existing($session);
        if ($cart === null) {
            throw (new ModelNotFoundException)->setModel(Cart::class);
        }

        DB::transaction(function () use ($cart, $itemId, $quantity): void {
            Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->whereKey($itemId)
                ->lockForUpdate()
                ->firstOrFail();
            $sku = $this->skuQuery()->whereKey($item->sku_id)->lockForUpdate()->first();
            if ($sku === null) {
                throw ValidationException::withMessages(['quantity' => 'This item is no longer available.']);
            }

            $this->validateQuantity($sku, $quantity);
            $item->update(['quantity' => $quantity]);
        });

        return $cart->fresh();
    }

    public function remove(Session $session, int $itemId): Cart
    {
        $cart = $this->existing($session);
        if ($cart === null) {
            throw (new ModelNotFoundException)->setModel(Cart::class);
        }

        DB::transaction(function () use ($cart, $itemId): void {
            Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->whereKey($itemId)
                ->lockForUpdate()
                ->firstOrFail();
            $item->delete();
        });

        return $cart->fresh();
    }

    /** @return array<string, mixed> */
    public function serialize(Cart $cart): array
    {
        $cart->load([
            'items' => fn ($items) => $items->orderBy('id')->with([
                'sku' => fn ($skus) => $skus->with($this->skuRelations()),
            ]),
        ]);

        $items = $cart->items->map(fn (CartItem $item): array => $this->serializeItem($item))->all();
        $subtotal = null;
        $subtotalAmount = 0;
        $subtotalCurrency = null;
        $subtotalSafe = true;
        foreach ($items as $item) {
            $lineTotal = $item['line_total'];
            if ($lineTotal === null || ! is_int($lineTotal['amount_minor'])) {
                $subtotalSafe = false;
                break;
            }
            if ($subtotalCurrency === null) {
                $subtotalCurrency = $lineTotal['currency'];
            } elseif ($subtotalCurrency !== $lineTotal['currency']) {
                $subtotalSafe = false;
                break;
            }
            if ($lineTotal['amount_minor'] > PHP_INT_MAX - $subtotalAmount) {
                $subtotalSafe = false;
                break;
            }
            $subtotalAmount += $lineTotal['amount_minor'];
        }
        if ($subtotalSafe && $subtotalCurrency !== null) {
            $subtotal = [
                'amount_minor' => $subtotalAmount,
                'currency' => $subtotalCurrency,
            ];
        }

        return [
            'item_count' => $cart->items->sum('quantity'),
            'merchandise_subtotal' => $subtotal,
            'items' => $items,
        ];
    }

    private function validateQuantity(Sku $sku, int $quantity): void
    {
        $state = $this->commerceState->forSku($sku);
        if (! $state['can_purchase'] || $state['price'] === null) {
            throw ValidationException::withMessages(['sku_id' => 'The selected item is unavailable for purchase.']);
        }

        $amountMinor = max(1, (int) $state['price']->amount_minor);
        $safeMaximum = min(self::MAX_QUANTITY, intdiv(PHP_INT_MAX, $amountMinor));
        if ($quantity < 1 || $quantity > $safeMaximum) {
            throw ValidationException::withMessages([
                'quantity' => 'The requested quantity exceeds the technical cart limit.',
            ]);
        }

        $maximum = $state['maximum_quantity'];
        if ($maximum !== null && $quantity > $maximum) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$maximum} of this item are currently available.",
            ]);
        }
    }

    private function validateCartCurrency(Cart $cart, Sku $sku): void
    {
        $newPrice = $this->commerceState->forSku($sku)['price'];
        $existingCurrencies = CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('sku_id', '!=', $sku->id)
            ->with(['sku' => fn ($skus) => $skus->with($this->skuRelations())])
            ->get()
            ->map(fn (CartItem $item): ?string => $this->commerceState->forSku($item->sku)['price']?->currency)
            ->filter()
            ->unique();

        if ($existingCurrencies->isNotEmpty() && ! $existingCurrencies->contains($newPrice?->currency)) {
            throw ValidationException::withMessages([
                'sku_id' => 'Items priced in different currencies cannot share one cart.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function serializeItem(CartItem $item): array
    {
        $sku = $item->sku;
        $variant = $sku->variant;
        $product = $variant?->product;
        $state = $this->commerceState->forSku($sku);
        /** @var Price|null $price */
        $price = $state['price'];
        $unitPrice = $price === null ? null : [
            'amount_minor' => $price->amount_minor,
            'currency' => $price->currency,
        ];
        $lineTotal = null;
        if ($price !== null && $item->quantity <= intdiv(PHP_INT_MAX, max(1, $price->amount_minor))) {
            $lineTotal = [
                'amount_minor' => $price->amount_minor * $item->quantity,
                'currency' => $price->currency,
            ];
        }
        $media = $product === null ? collect() : collect($this->mediaManifest->forProduct($product, $variant));
        $primaryMedia = $media->firstWhere('role', 'front') ?? $media->first();

        return [
            'id' => $item->id,
            'sku_id' => $sku->id,
            'quantity' => $item->quantity,
            'product' => $product === null ? null : [
                'id' => $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
            ],
            'selected_options' => $sku->optionValues
                ->sortBy(fn ($value): array => [$value->option?->display_order ?? PHP_INT_MAX, $value->id])
                ->map(fn ($value): array => [
                    'option' => [
                        'id' => $value->option?->id,
                        'code' => $value->option?->code,
                        'name' => $value->option?->name,
                    ],
                    'value' => [
                        'id' => $value->id,
                        'code' => $value->code,
                        'label' => $value->label,
                    ],
                ])->values()->all(),
            'media' => $primaryMedia === null ? null : [
                'alt' => $primaryMedia['alt'] ?: $product?->name,
                'responsive' => $primaryMedia['responsive'],
            ],
            'unit_price' => $unitPrice,
            'sale' => $state['sale'],
            'line_total' => $lineTotal,
            'availability' => $state['availability'],
            'can_purchase' => $state['can_purchase'] && $price !== null && $lineTotal !== null,
        ];
    }

    private function skuQuery()
    {
        return Sku::query()->with($this->skuRelations());
    }

    /** @return array<string, mixed> */
    private function skuRelations(): array
    {
        return [
            'prices' => fn ($prices) => $prices->current()->orderBy('id'),
            'inventoryLevels.location',
            'optionValues.option',
            'variant' => fn ($variants) => $variants->withTrashed()->with([
                'product' => fn ($products) => $products->withTrashed()->with([
                    'options',
                    'media' => fn ($media) => $media->with('mediaAsset.derivatives')->orderBy('display_order')->orderBy('id'),
                ]),
            ]),
        ];
    }
}
