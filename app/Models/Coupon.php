<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'type', 'currency', 'fixed_amount_minor', 'percentage_basis_points', 'minimum_subtotal_minor', 'is_active', 'starts_at', 'ends_at'])]
class Coupon extends Model
{
    public const TYPE_FIXED = 'fixed';

    public const TYPE_PERCENTAGE = 'percentage';

    public const MAX_BASIS_POINTS = 10_000;

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }

    public function setCurrencyAttribute(string $value): void
    {
        $this->attributes['currency'] = strtoupper(trim($value));
    }

    protected function casts(): array
    {
        return [
            'fixed_amount_minor' => 'integer',
            'percentage_basis_points' => 'integer',
            'minimum_subtotal_minor' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
