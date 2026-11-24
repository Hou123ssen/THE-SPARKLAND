<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sku_id', 'currency', 'amount_minor', 'compare_at_minor', 'tax_inclusive', 'valid_from', 'valid_to'])]
class Price extends Model
{
    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    public function scopeCurrent(Builder $query): void
    {
        $query
            ->where(fn (Builder $current) => $current->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn (Builder $current) => $current->whereNull('valid_to')->orWhere('valid_to', '>=', now()));
    }

    public function isCurrent(): bool
    {
        $now = now();

        return ($this->valid_from === null || $this->valid_from->lessThanOrEqualTo($now))
            && ($this->valid_to === null || $this->valid_to->greaterThanOrEqualTo($now));
    }

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer', 'compare_at_minor' => 'integer',
            'tax_inclusive' => 'boolean', 'valid_from' => 'datetime', 'valid_to' => 'datetime',
        ];
    }
}
