<?php

namespace App\Services;

use App\Models\Coupon;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /** @return array{coupon: Coupon, amount_minor: int} */
    public function discount(string $code, int $subtotal, string $currency): array
    {
        $normalized = strtoupper(trim($code));
        $coupon = Coupon::query()->where('code', $normalized)->where('is_active', true)->first();
        if ($coupon === null || ($coupon->starts_at !== null && $coupon->starts_at->isFuture()) || ($coupon->ends_at !== null && $coupon->ends_at->isPast())) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon is not currently valid.']);
        }
        if ($coupon->currency !== strtoupper(trim($currency)) || $subtotal < 0 || ($coupon->minimum_subtotal_minor !== null && $subtotal < $coupon->minimum_subtotal_minor)) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon does not apply to the current cart.']);
        }

        if ($coupon->type === Coupon::TYPE_PERCENTAGE && ($coupon->percentage_basis_points === null || $coupon->percentage_basis_points < 0 || $coupon->percentage_basis_points > Coupon::MAX_BASIS_POINTS)) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon has an invalid configuration.']);
        }
        if ($coupon->type === Coupon::TYPE_FIXED && ($coupon->fixed_amount_minor === null || $coupon->fixed_amount_minor <= 0)) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon has an invalid configuration.']);
        }
        $amount = match ($coupon->type) {
            Coupon::TYPE_FIXED => min($coupon->fixed_amount_minor ?? 0, $subtotal),
            Coupon::TYPE_PERCENTAGE => $this->percentageDiscount($subtotal, $coupon->percentage_basis_points),
            default => throw ValidationException::withMessages(['coupon_code' => 'This coupon has an invalid configuration.']),
        };

        return ['coupon' => $coupon, 'amount_minor' => $amount];
    }

    private function percentageDiscount(int $subtotal, int $basisPoints): int
    {
        $whole = intdiv($subtotal, 10_000) * $basisPoints;
        $remainder = intdiv(($subtotal % 10_000) * $basisPoints, 10_000);

        if ($whole > PHP_INT_MAX - $remainder) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon cannot be applied safely.']);
        }

        return $whole + $remainder;
    }
}
