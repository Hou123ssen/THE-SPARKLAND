<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['disk', 'path', 'mime_type', 'width', 'height', 'bytes', 'checksum_sha256', 'original_name', 'processing_status'])]
class MediaAsset extends Model
{
    public function productMedia(): HasMany { return $this->hasMany(ProductMedia::class); }
    protected function casts(): array { return ['width' => 'integer', 'height' => 'integer', 'bytes' => 'integer']; }
}
