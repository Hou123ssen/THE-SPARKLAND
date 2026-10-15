<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'product_id', 'slug', 'name', 'colour_name', 'colour_hex', 'material',
    'frame_shape', 'fit', 'frame_width_mm', 'lens_width_mm', 'lens_height_mm',
    'bridge_width_mm', 'temple_length_mm', 'lens_type', 'is_active', 'display_order',
])]
class ProductVariant extends Model
{
    use SoftDeletes;

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function skus(): HasMany { return $this->hasMany(Sku::class); }
    public function media(): HasMany { return $this->hasMany(ProductMedia::class, 'product_variant_id'); }

    protected function casts(): array
    {
        return [
            'frame_width_mm' => 'integer', 'lens_width_mm' => 'integer',
            'lens_height_mm' => 'integer', 'bridge_width_mm' => 'integer',
            'temple_length_mm' => 'integer', 'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }
}
