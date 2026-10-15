<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'product_variant_id', 'media_asset_id', 'kind', 'role', 'alt_text', 'sequence_index', 'angle_degrees', 'display_order', 'is_primary'])]
class ProductMedia extends Model
{
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
    public function mediaAsset(): BelongsTo { return $this->belongsTo(MediaAsset::class); }

    protected function casts(): array
    {
        return ['sequence_index' => 'integer', 'angle_degrees' => 'integer', 'display_order' => 'integer', 'is_primary' => 'boolean'];
    }
}
