<?php

namespace App\Services;

use App\Models\ShippingZone;
use Illuminate\Validation\ValidationException;

class ShippingQuoteService
{
    /** @return array{zone: string, country_code: string, amount_minor: int, currency: string} */
    public function quote(string $countryCode, string $currency): array
    {
        $countryCode = strtoupper(trim($countryCode));
        $currency = strtoupper(trim($currency));
        if (! preg_match('/^[A-Z]{2}$/', $countryCode) || ! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw ValidationException::withMessages(['shipping' => 'Shipping is unavailable for this destination and currency.']);
        }

        $zones = ShippingZone::query()->where('country_code', $countryCode)->where('is_active', true)->get();
        $zone = $zones->count() === 1 ? $zones->first() : null;
        $rate = $zone?->rates()->where('currency', $currency)->where('is_active', true)->first();

        if ($zone === null || $rate === null) {
            throw ValidationException::withMessages(['shipping' => 'Shipping is unavailable for this destination and currency.']);
        }

        return [
            'zone' => $zone->code,
            'country_code' => $countryCode,
            'amount_minor' => (int) $rate->amount_minor,
            'currency' => $rate->currency,
        ];
    }
}
