<?php

namespace App\Services;

use App\Models\Cart;
use Illuminate\Validation\ValidationException;

class CartTotalsService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CouponService $coupons,
        private readonly ShippingQuoteService $shipping,
    ) {}

    /** @return array<string, mixed> */
    public function quote(Cart $cart, ?string $couponCode = null, ?string $countryCode = null): array
    {
        $pricing = $this->cartService->authoritativePricing($cart);
        $subtotal = $pricing['merchandise_subtotal'];
        if (! $pricing['can_quote'] || $subtotal === null) {
            throw ValidationException::withMessages(['cart' => 'This cart cannot currently be quoted.']);
        }

        $discount = ['amount_minor' => 0, 'currency' => $subtotal['currency']];
        $coupon = null;
        if ($couponCode !== null && trim($couponCode) !== '') {
            $applied = $this->coupons->discount($couponCode, $subtotal['amount_minor'], $subtotal['currency']);
            $coupon = ['code' => $applied['coupon']->code, 'amount_minor' => $applied['amount_minor']];
            $discount['amount_minor'] = $applied['amount_minor'];
        }

        $shipping = null;
        if ($countryCode !== null && trim($countryCode) !== '') {
            $shipping = $this->shipping->quote($countryCode, $subtotal['currency']);
        }

        $afterDiscount = $subtotal['amount_minor'] - $discount['amount_minor'];
        $shippingAmount = $shipping['amount_minor'] ?? 0;
        if ($shippingAmount > PHP_INT_MAX - $afterDiscount) {
            throw ValidationException::withMessages(['total' => 'This cart total cannot be represented safely.']);
        }

        return [
            'currency' => $subtotal['currency'],
            'merchandise_subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shipping,
            'total' => ['amount_minor' => $afterDiscount + $shippingAmount, 'currency' => $subtotal['currency']],
            'coupon' => $coupon,
            'shipping_zone' => $shipping['zone'] ?? null,
        ];
    }
}
