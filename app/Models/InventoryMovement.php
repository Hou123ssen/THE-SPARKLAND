<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sku_id', 'inventory_location_id', 'type', 'quantity_delta', 'reference_type', 'reference_id', 'reason', 'created_by'])]
class InventoryMovement extends Model
{
    public $timestamps = false;

    public function sku(): BelongsTo { return $this->belongsTo(Sku::class); }
    public function location(): BelongsTo { return $this->belongsTo(InventoryLocation::class, 'inventory_location_id'); }
    protected function casts(): array { return ['quantity_delta' => 'integer', 'reference_id' => 'integer', 'created_by' => 'integer']; }
}
