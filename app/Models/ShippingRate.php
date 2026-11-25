<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['shipping_zone_id', 'currency', 'amount_minor', 'is_active'])]
class ShippingRate extends Model
{
    public function setCurrencyAttribute(string $value): void
    {
        $this->attributes['currency'] = strtoupper(trim($value));
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'is_active' => 'boolean'];
    }
}
