<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sku_id', 'inventory_location_id', 'on_hand', 'reserved', 'reorder_level'])]
class InventoryLevel extends Model
{
    public function sku(): BelongsTo { return $this->belongsTo(Sku::class); }
    public function location(): BelongsTo { return $this->belongsTo(InventoryLocation::class, 'inventory_location_id'); }
    protected function casts(): array { return ['on_hand' => 'integer', 'reserved' => 'integer', 'reorder_level' => 'integer']; }
}
