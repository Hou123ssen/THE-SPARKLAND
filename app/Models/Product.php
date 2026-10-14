<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'collection_id',
    'name',
    'slug',
    'description',
    'price',
    'color',
    'material',
    'lens',
    'frame',
    'dimensions',
    'display_order',
    'is_active',
])]
class Product extends Model
{
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
