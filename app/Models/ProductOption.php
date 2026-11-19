<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['product_id', 'name', 'code', 'display_order', 'is_required', 'is_active'])]
class ProductOption extends Model
{
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function values(): HasMany { return $this->hasMany(ProductOptionValue::class); }

    protected function casts(): array
    {
        return ['display_order' => 'integer', 'is_required' => 'boolean', 'is_active' => 'boolean'];
    }
}
