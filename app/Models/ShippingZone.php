<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'country_code', 'is_active', 'display_order'])]
class ShippingZone extends Model
{
    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }

    public function setCountryCodeAttribute(string $value): void
    {
        $this->attributes['country_code'] = strtoupper(trim($value));
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'display_order' => 'integer'];
    }
}
