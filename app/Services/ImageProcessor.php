<?php

namespace App\Services;

interface ImageProcessor
{
    /** @return array{jpeg: bool, png: bool, webp: bool, avif: bool, exif: bool} */
    public function capabilities(): array;

    /** @return array{mime_type: string, width: int, height: int, has_alpha: bool} */
    public function inspect(string $sourcePath): array;

    /**
     * @param array{name: string, width?: int, height?: int, padding_ratio: float} $preset
     * @return array{mime_type: string, width: int, height: int, bytes: int, checksum_sha256: string}
     */
    public function render(string $sourcePath, string $targetPath, string $format, array $preset): array;
}
