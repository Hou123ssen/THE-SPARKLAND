<?php

namespace App\Services;

final class PreparedImage
{
    /** @param array{int, int, int, int} $contentBounds */
    public function __construct(
        public readonly \GdImage $image,
        public readonly string $mimeType,
        public readonly int $width,
        public readonly int $height,
        public readonly bool $hasAlpha,
        public readonly array $contentBounds,
    ) {
    }

    public function release(): void
    {
        imagedestroy($this->image);
    }
}
