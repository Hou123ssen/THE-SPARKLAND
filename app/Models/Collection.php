<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'display_order', 'is_active'])]
class Collection extends Model
{
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
