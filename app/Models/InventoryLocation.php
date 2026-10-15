<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'type', 'is_active'])]
class InventoryLocation extends Model
{
    public function levels(): HasMany { return $this->hasMany(InventoryLevel::class); }
    public function movements(): HasMany { return $this->hasMany(InventoryMovement::class); }

    protected function casts(): array { return ['is_active' => 'boolean']; }
}
