<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['product_variant_id', 'sku', 'barcode', 'status', 'weight_g', 'is_backorderable'])]
class Sku extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
    public function prices(): HasMany { return $this->hasMany(Price::class); }
    public function inventoryLevels(): HasMany { return $this->hasMany(InventoryLevel::class); }
    public function inventoryMovements(): HasMany { return $this->hasMany(InventoryMovement::class); }

    protected function casts(): array
    {
        return ['weight_g' => 'integer', 'is_backorderable' => 'boolean'];
    }
}
