<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\MediaDerivative;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MediaDerivativeGenerator
{
    public function __construct(private readonly ImageProcessor $processor)
    {
    }

    public function generate(MediaAsset $asset): void
    {
        $sourcePath = $this->stageOriginal($asset);

        try {
            $sourceFormat = $this->formatForMime($asset->mime_type);
            $formats = array_values(array_unique(array_filter([
                $sourceFormat,
                $this->processor->capabilities()['webp'] ? 'webp' : null,
                $this->processor->capabilities()['avif'] ? 'avif' : null,
            ])));

            foreach (config('media.responsive_widths') as $width) {
                foreach ($formats as $format) {
                    $this->generateDerivative($asset, $sourcePath, "responsive_{$width}", $format, [
                        'name' => "responsive_{$width}",
                        'width' => $width,
                        'padding_ratio' => config('media.presets.responsive_padding_ratio'),
                    ]);
                }
            }

            foreach ($formats as $format) {
                $this->generateDerivative($asset, $sourcePath, 'device_browser', $format, [
                    'name' => 'device_browser',
                    ...config('media.presets.device_browser'),
                ]);
            }
        } finally {
            @unlink($sourcePath);
        }
    }

    private function generateDerivative(MediaAsset $asset, string $sourcePath, string $preset, string $format, array $definition): void
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'sparkland-media-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create an image-processing temporary file.');
        }

        try {
            $metadata = $this->processor->render($sourcePath, $temporaryPath, $format, $definition);
            $path = $this->derivativePath($asset, $preset, $format, $metadata['checksum_sha256']);
            $stream = fopen($temporaryPath, 'rb');
            Storage::disk(config('media.derivative_disk'))->writeStream($path, $stream, ['visibility' => 'public']);
            if (is_resource($stream)) {
                fclose($stream);
            }

            MediaDerivative::query()->updateOrCreate(
                ['media_asset_id' => $asset->id, 'preset' => $preset, 'format' => $format],
                ['disk' => config('media.derivative_disk'), 'path' => $path, ...$metadata],
            );
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function stageOriginal(MediaAsset $asset): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'sparkland-original-');
        $stream = Storage::disk($asset->disk)->readStream($asset->path);
        if ($temporaryPath === false || ! is_resource($stream)) {
            throw new RuntimeException('The source master could not be read from managed storage.');
        }

        $target = fopen($temporaryPath, 'wb');
        stream_copy_to_stream($stream, $target);
        fclose($stream);
        fclose($target);

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
