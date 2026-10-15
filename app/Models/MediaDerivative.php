<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['media_asset_id', 'preset', 'format', 'disk', 'path', 'mime_type', 'width', 'height', 'bytes', 'checksum_sha256'])]
class MediaDerivative extends Model
{
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    protected function casts(): array
    {
        return ['width' => 'integer', 'height' => 'integer', 'bytes' => 'integer'];
    }
}
