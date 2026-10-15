<?php

namespace App\Services;

use App\Jobs\ProcessMediaAsset;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ProductMediaIngestor
{
    public function __construct(private readonly ImageProcessor $processor)
    {
    }

    public function ingest(UploadedFile $upload, Product $product, ?ProductVariant $variant, string $role, int $displayOrder = 0, bool $isPrimary = false, string $kind = 'image'): ProductMedia
    {
        ProductMediaRole::assertSupported($role);
        $this->assertVariantBelongsToProduct($variant, $product);
        $metadata = $this->validate($upload);
        $disk = config('media.original_disk');
        $path = sprintf('products/%d/masters/%s.%s', $product->id, (string) Str::uuid(), $this->extension($metadata['mime_type']));

        $source = fopen($upload->getRealPath(), 'rb');
        try {
            if (! is_resource($source) || ! Storage::disk($disk)->put($path, $source)) {
                throw new RuntimeException('Unable to store the validated product-image master.');
            }
        } finally {
            if (is_resource($source)) {
                fclose($source);
            }
        }

        try {
            return DB::transaction(function () use ($product, $variant, $role, $displayOrder, $isPrimary, $kind, $metadata, $upload, $disk, $path): ProductMedia {
                $asset = MediaAsset::create([
                    'disk' => $disk,
                    'path' => $path,
                    'mime_type' => $metadata['mime_type'],
                    'width' => $metadata['width'],
                    'height' => $metadata['height'],
                    'bytes' => $upload->getSize(),
                    'checksum_sha256' => hash_file('sha256', $upload->getRealPath()),
                    'original_name' => $upload->getClientOriginalName(),
                    'processing_status' => 'pending',
                ]);

                $scope = ProductMedia::query()->where('product_id', $product->id);
                $variant === null ? $scope->whereNull('product_variant_id') : $scope->where('product_variant_id', $variant->id);
                if ($isPrimary) {
                    $scope->update(['is_primary' => false]);
                }

                $media = ProductMedia::create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'media_asset_id' => $asset->id,
                    'kind' => $kind,
                    'role' => $role,
                    'display_order' => $displayOrder,
                    'is_primary' => $isPrimary,
                ]);

                ProcessMediaAsset::dispatch($asset->id)->onQueue(config('media.queue'))->afterCommit();

                return $media->load('mediaAsset');
            });
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }

    /** @return array{mime_type: string, width: int, height: int, has_alpha: bool} */
    private function validate(UploadedFile $upload): array
    {
        if (! $upload->isValid() || $upload->getRealPath() === false) {
            throw new InvalidArgumentException('The product image upload is invalid.');
        }

        $maxBytes = config('media.max_upload_mb') * 1024 * 1024;
        if ($upload->getSize() > $maxBytes) {
            throw new InvalidArgumentException('The product image exceeds the configured upload size limit.');
        }

        $metadata = $this->processor->inspect($upload->getRealPath());
        if (($metadata['width'] * $metadata['height']) > config('media.max_pixels')) {
            throw new InvalidArgumentException('The product image exceeds the configured pixel limit.');
        }

        return $metadata;
    }

    private function assertVariantBelongsToProduct(?ProductVariant $variant, Product $product): void
    {
        if ($variant !== null && $variant->product_id !== $product->id) {
            throw new InvalidArgumentException('The media variant must belong to the supplied product.');
        }
    }

    private function extension(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/avif' => 'avif',
            default => throw new RuntimeException("Unsupported upload media type [{$mime}]."),
        };
    }
}
