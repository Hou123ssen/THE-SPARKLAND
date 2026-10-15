<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['disk', 'path', 'mime_type', 'width', 'height', 'has_alpha', 'bytes', 'checksum_sha256', 'original_name', 'processing_status', 'processed_at', 'processing_error'])]
class MediaAsset extends Model
{
    public function productMedia(): HasMany { return $this->hasMany(ProductMedia::class); }
    public function derivatives(): HasMany { return $this->hasMany(MediaDerivative::class); }

    protected function casts(): array
    {
        return ['width' => 'integer', 'height' => 'integer', 'has_alpha' => 'boolean', 'bytes' => 'integer', 'processed_at' => 'datetime'];
    }
}
