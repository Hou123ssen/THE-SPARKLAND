<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\MediaDerivative;
use App\Services\ProductMediaKind;
use App\Services\ProductMediaRole;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MediaDerivativeGenerator
{
    public function __construct(private readonly ImageProcessor $processor)
    {
    }

    public function generate(MediaAsset $asset): void
    {
        $policy = $this->policyFor($asset);
        if (! $policy['responsive'] && ! $policy['device_browser']) {
            return;
        }

        $sourcePath = $this->stageOriginal($asset);

        try {
            $source = $this->processor->prepare($sourcePath);
            try {
                $asset->update(['has_alpha' => $source->hasAlpha]);
                $formats = $this->formatsFor($asset, $source);

                if ($policy['responsive']) {
                    foreach (config('media.responsive_widths') as $width) {
                        foreach ($formats as $format) {
                            $this->generateDerivative($asset, $source, "responsive_{$width}", $format, [
                                'name' => "responsive_{$width}",
                                'width' => $width,
                                'padding_ratio' => config('media.presets.responsive_padding_ratio'),
                            ]);
                        }
                    }
                }

                if ($policy['device_browser']) {
                    foreach ($formats as $format) {
                        $this->generateDerivative($asset, $source, 'device_browser', $format, [
                            'name' => 'device_browser',
                            ...config('media.presets.device_browser'),
                        ]);
                    }
                }
            } finally {
                $source->release();
            }
        } finally {
            @unlink($sourcePath);
        }
    }

    private function generateDerivative(MediaAsset $asset, PreparedImage $source, string $preset, string $format, array $definition): void
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'sparkland-media-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create an image-processing temporary file.');
        }

        try {
            $metadata = $this->processor->render($source, $temporaryPath, $format, $definition);
            $path = $this->derivativePath($asset, $preset, $format, $metadata['checksum_sha256']);
            $stream = fopen($temporaryPath, 'rb');
            $disk = Storage::disk(config('media.derivative_disk'));
            try {
                if (! is_resource($stream) || $disk->writeStream($path, $stream, ['visibility' => 'public']) !== true || ! $disk->exists($path)) {
                    throw new RuntimeException('Unable to write the public media derivative.');
                }
            } catch (\Throwable $exception) {
                $disk->delete($path);
                throw $exception;
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            MediaDerivative::query()->updateOrCreate(
                ['media_asset_id' => $asset->id, 'preset' => $preset, 'format' => $format],
                ['disk' => config('media.derivative_disk'), 'path' => $path, ...$metadata],
            );
        } finally {
            @unlink($temporaryPath);
        }
    }

    /** @return array{responsive: bool, device_browser: bool} */
    private function policyFor(MediaAsset $asset): array
    {
        $media = $asset->productMedia()->get(['kind', 'role']);
        $imageMedia = $media->filter(fn ($item): bool => $item->kind === ProductMediaKind::IMAGE);

        return [
            'responsive' => $imageMedia->isNotEmpty(),
            'device_browser' => $imageMedia->contains(fn ($item): bool => $item->role === ProductMediaRole::FRONT),
        ];
    }

    /** @return list<string> */
    private function formatsFor(MediaAsset $asset, PreparedImage $source): array
    {
        $capabilities = $this->processor->capabilities();
        $fallback = $source->hasAlpha ? 'png' : 'jpeg';

        return array_values(array_unique(array_filter([
            $this->formatForMime($asset->mime_type),
            $fallback,
            $capabilities['webp'] ? 'webp' : null,
            $capabilities['avif'] ? 'avif' : null,
        ])));
    }

    private function stageOriginal(MediaAsset $asset): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'sparkland-original-');
        $stream = Storage::disk($asset->disk)->readStream($asset->path);
        if ($temporaryPath === false || ! is_resource($stream)) {
            throw new RuntimeException('The source master could not be read from managed storage.');
        }

        $target = fopen($temporaryPath, 'wb');
        if (! is_resource($target)) {
            fclose($stream);
            @unlink($temporaryPath);
            throw new RuntimeException('Unable to stage the source master for processing.');
        }
        try {
            if (stream_copy_to_stream($stream, $target) === false) {
                throw new RuntimeException('Unable to stage the source master for processing.');
            }
        } finally {
            fclose($stream);
            fclose($target);
        }

        return $temporaryPath;
    }

    private function derivativePath(MediaAsset $asset, string $preset, string $format, string $checksum): string
    {
        return sprintf('products/derivatives/%s/%s/%s-%s.%s', substr($checksum, 0, 2), $checksum, $asset->id, $preset, $this->extension($format));
    }

    private function formatForMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpeg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/avif' => 'avif',
            default => throw new RuntimeException("Unsupported master media type [{$mime}]."),
        };
    }

    private function extension(string $format): string
    {
        return $format === 'jpeg' ? 'jpg' : $format;
    }
}
