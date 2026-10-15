<?php

namespace App\Services;

use RuntimeException;

class NativeGdImageProcessor implements ImageProcessor
{
    public function capabilities(): array
    {
        return [
            'jpeg' => function_exists('imagecreatefromjpeg') && function_exists('imagejpeg'),
            'png' => function_exists('imagecreatefrompng') && function_exists('imagepng'),
            'webp' => function_exists('imagecreatefromwebp') && function_exists('imagewebp'),
            'avif' => function_exists('imagecreatefromavif') && function_exists('imageavif'),
            'exif' => function_exists('exif_read_data'),
        ];
    }

    public function inspect(string $sourcePath): array
    {
        $info = @getimagesize($sourcePath);
        if ($info === false) {
            throw new RuntimeException('The uploaded file is not a decodable raster image.');
        }

        $mime = $info['mime'] ?? null;
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/avif'], true)) {
            throw new RuntimeException('Only JPEG, PNG, WebP, and supported AVIF product images are accepted.');
        }

        if ($mime === 'image/avif' && ! $this->capabilities()['avif']) {
            throw new RuntimeException('AVIF uploads are not supported by this runtime.');
        }

        // Force a server-side decode before accepting a file as a product image.
        $decoded = $this->load($sourcePath, $mime);
        imagedestroy($decoded);

        return [
            'mime_type' => $mime,
            'width' => $info[0],
            'height' => $info[1],
            'has_alpha' => $this->hasAlpha($sourcePath, $mime),
        ];
    }

    public function render(string $sourcePath, string $targetPath, string $format, array $preset): array
    {
        $sourceInfo = $this->inspect($sourcePath);
        $source = $this->load($sourcePath, $sourceInfo['mime_type']);
        $source = $this->orient($source, $sourcePath, $sourceInfo['mime_type']);

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        [$left, $top, $contentWidth, $contentHeight] = $sourceInfo['has_alpha']
            ? $this->contentBounds($source, $sourceWidth, $sourceHeight)
            : [0, 0, $sourceWidth, $sourceHeight];

        [$canvasWidth, $canvasHeight, $drawWidth, $drawHeight] = $this->dimensions(
            $contentWidth,
            $contentHeight,
            $sourceWidth,
            $sourceHeight,
            $preset,
            $sourceInfo['has_alpha'],
        );

        $canvas = $this->transparentCanvas($canvasWidth, $canvasHeight);
        $destinationX = (int) floor(($canvasWidth - $drawWidth) / 2);
        $destinationY = (int) floor(($canvasHeight - $drawHeight) / 2);
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $source, $destinationX, $destinationY, $left, $top, $drawWidth, $drawHeight, $contentWidth, $contentHeight);
        imagesavealpha($canvas, true);

        $mime = $this->encode($canvas, $targetPath, $format);
        imagedestroy($source);
        imagedestroy($canvas);

        return [
            'mime_type' => $mime,
            'width' => $canvasWidth,
            'height' => $canvasHeight,
            'bytes' => filesize($targetPath),
            'checksum_sha256' => hash_file('sha256', $targetPath),
        ];
    }

    private function load(string $path, string $mime): \GdImage
    {
        $format = match ($mime) {
            'image/jpeg' => 'jpeg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/avif' => 'avif',
            default => null,
        };
        if ($format === null || ! $this->capabilities()[$format]) {
            throw new RuntimeException("The runtime cannot decode {$mime} product images.");
        }

        $image = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            'image/avif' => imagecreatefromavif($path),
            default => false,
        };

        if (! $image instanceof \GdImage) {
            throw new RuntimeException('The raster image could not be decoded.');
        }

        return $image;
    }

    private function orient(\GdImage $image, string $path, string $mime): \GdImage
    {
        if ($mime !== 'image/jpeg' || ! $this->capabilities()['exif']) {
            return $image;
        }

        $orientation = @exif_read_data($path)['Orientation'] ?? 1;
        $oriented = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        if ($oriented !== $image) {
            imagedestroy($image);
        }

        return $oriented;
    }

    /** @return array{int, int, int, int} */
    private function contentBounds(\GdImage $image, int $width, int $height): array
    {
        $left = $width;
        $top = $height;
        $right = -1;
        $bottom = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $alpha = (imagecolorat($image, $x, $y) >> 24) & 0x7f;
                if ($alpha < 127) {
                    $left = min($left, $x);
                    $top = min($top, $y);
                    $right = max($right, $x);
                    $bottom = max($bottom, $y);
                }
            }
        }

        return $right < 0 ? [0, 0, $width, $height] : [$left, $top, $right - $left + 1, $bottom - $top + 1];
    }

    /** @return array{int, int, int, int} */
    private function dimensions(int $contentWidth, int $contentHeight, int $sourceWidth, int $sourceHeight, array $preset, bool $hasAlpha): array
    {
        // Only transparent packshots are normalized with breathing room. Opaque
        // photography keeps its supplied crop unless a future explicit crop exists.
        $padding = $hasAlpha ? $preset['padding_ratio'] : 0;
        if (isset($preset['height'])) {
            $canvasWidth = $preset['width'];
            $canvasHeight = $preset['height'];
            $availableWidth = (int) floor($canvasWidth * (1 - (2 * $padding)));
            $availableHeight = (int) floor($canvasHeight * (1 - (2 * $padding)));
            // The named device canvas is intentionally normalized for the
            // foldable browser, unlike ordinary responsive derivatives.
            $scale = min($availableWidth / $contentWidth, $availableHeight / $contentHeight);
            return [$canvasWidth, $canvasHeight, max(1, (int) round($contentWidth * $scale)), max(1, (int) round($contentHeight * $scale))];
        }

        $targetWidth = min($preset['width'], $sourceWidth);
        $drawWidth = min($contentWidth, (int) floor($targetWidth * (1 - (2 * $padding))));
        $drawHeight = max(1, (int) round($contentHeight * ($drawWidth / $contentWidth)));
        $canvasWidth = max(1, (int) ceil($drawWidth / (1 - (2 * $padding))));
        $canvasHeight = max(1, (int) ceil($drawHeight / (1 - (2 * $padding))));

        return [$canvasWidth, $canvasHeight, $drawWidth, $drawHeight];
    }

    private function transparentCanvas(int $width, int $height): \GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
        imagesavealpha($canvas, true);

        return $canvas;
    }

    private function encode(\GdImage $image, string $targetPath, string $format): string
    {
        $capabilities = $this->capabilities();
        $written = match ($format) {
            'jpeg' => $capabilities['jpeg'] ? imagejpeg($image, $targetPath, 90) : false,
            'png' => $capabilities['png'] ? imagepng($image, $targetPath, 6) : false,
            'webp' => $capabilities['webp'] ? imagewebp($image, $targetPath, 88) : false,
            'avif' => $capabilities['avif'] ? imageavif($image, $targetPath, 55) : false,
            default => false,
        };

        if (! $written) {
            throw new RuntimeException("The runtime cannot encode {$format} derivatives.");
        }

        return match ($format) {
            'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'avif' => 'image/avif',
        };
    }

    private function hasAlpha(string $sourcePath, string $mime): bool
    {
        if (! in_array($mime, ['image/png', 'image/webp', 'image/avif'], true)) {
            return false;
        }

        $image = $this->load($sourcePath, $mime);
        $width = imagesx($image);
        $height = imagesy($image);
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7f) > 0) {
                    imagedestroy($image);
                    return true;
                }
            }
        }
        imagedestroy($image);

        return false;
    }
}
