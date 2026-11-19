<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['product_option_id', 'label', 'code', 'display_order', 'is_default', 'is_active'])]
class ProductOptionValue extends Model
{
    public function option(): BelongsTo { return $this->belongsTo(ProductOption::class, 'product_option_id'); }
    public function skus(): BelongsToMany { return $this->belongsToMany(Sku::class, 'sku_product_option_value')->withPivot('product_option_id')->withTimestamps(); }

    protected function casts(): array
    {
        return ['display_order' => 'integer', 'is_default' => 'boolean', 'is_active' => 'boolean'];
    }
}
