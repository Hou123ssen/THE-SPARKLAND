<?php

namespace App\Services;

use App\Models\MediaDerivative;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Storage;

class ProductMediaManifest
{
    public function __construct(private readonly ProductMediaSelector $selector)
    {
    }

    /** @return list<array<string, mixed>> */
    public function forProduct(Product $product, ?ProductVariant $variant = null): array
    {
        return $this->selector->forProduct($product, $variant)
            ->map(fn (ProductMedia $media): array => $this->forMedia($media))
            ->all();
    }

    /** @return array<string, mixed> */
    public function forMedia(ProductMedia $media): array
    {
        $derivatives = $media->mediaAsset->derivatives;

        return [
            'id' => $media->id,
            'alt' => $media->alt_text,
            'role' => $media->role,
            'kind' => $media->kind,
            'original' => [
                'mime_type' => $media->mediaAsset->mime_type,
                'width' => $media->mediaAsset->width,
                'height' => $media->mediaAsset->height,
            ],
            'responsive' => [
                'fallback' => $this->fallbackDerivatives($derivatives),
                'webp' => $this->derivativesFor($derivatives, 'webp', 'responsive_'),
                'avif' => $this->derivativesFor($derivatives, 'avif', 'responsive_'),
            ],
            'device_browser' => $this->deviceDerivative($derivatives),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function derivativesFor($derivatives, string $format, string $prefix): array
    {
        return $derivatives
            ->filter(fn (MediaDerivative $derivative): bool => $derivative->format === $format && str_starts_with($derivative->preset, $prefix))
            ->sortBy('width')
            ->map(fn (MediaDerivative $derivative): array => $this->derivativeData($derivative))
            ->values()
            ->all();
    }

    private function deviceDerivative($derivatives): ?array
    {
        $derivative = $derivatives->first(fn (MediaDerivative $item): bool => $item->preset === 'device_browser' && $item->format === 'webp')
            ?? $derivatives->firstWhere('preset', 'device_browser')
            ?? $derivatives->first(fn (MediaDerivative $item): bool => $item->preset === 'device_browser');

        return $derivative === null ? null : $this->derivativeData($derivative);
    }

    /** @return list<array<string, mixed>> */
    private function fallbackDerivatives($derivatives): array
    {
        return $derivatives
            ->filter(fn (MediaDerivative $derivative): bool => str_starts_with($derivative->preset, 'responsive_') && in_array($derivative->format, ['jpeg', 'png'], true))
            ->sortBy('width')
            ->groupBy('width')
            ->map(fn ($items): array => $this->derivativeData($items->first()))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function derivativeData(MediaDerivative $derivative): array
    {
        return [
            'url' => Storage::disk($derivative->disk)->url($derivative->path),
            'mime_type' => $derivative->mime_type,
            'width' => $derivative->width,
            'height' => $derivative->height,
        ];
    }
}
