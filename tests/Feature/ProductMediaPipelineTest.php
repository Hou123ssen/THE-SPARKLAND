<?php

namespace Tests\Feature;

use App\Jobs\ProcessMediaAsset;
use App\Models\MediaAsset;
use App\Models\MediaDerivative;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Services\MediaDerivativeGenerator;
use App\Services\ImageProcessor;
use App\Services\NativeGdImageProcessor;
use App\Services\PreparedImage;
use App\Services\ProductMediaIngestor;
use App\Services\ProductMediaKind;
use App\Services\ProductMediaManifest;
use App\Services\ProductMediaSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class ProductMediaPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('The native GD extension is required for image pipeline tests.');
        }

        config([
            'media.original_disk' => 'media-original',
            'media.derivative_disk' => 'media-derivatives',
            'media.max_upload_mb' => 12,
            'media.max_pixels' => 40_000_000,
        ]);
        Storage::fake('media-original');
        Storage::fake('media-derivatives');
    }

    public function test_ingests_a_valid_image_to_managed_master_storage_with_checksum_and_media_association(): void
    {
        Queue::fake();
        $product = $this->product();
        $upload = $this->transparentUpload();
        $expectedHash = hash_file('sha256', $upload->getRealPath());

        $media = app(ProductMediaIngestor::class)->ingest($upload, $product, null, 'front', 1, true);

        $this->assertTrue($media->mediaAsset->processing_status === 'pending');
        $this->assertSame($expectedHash, $media->mediaAsset->checksum_sha256);
        $this->assertTrue($media->is_primary);
        Storage::disk('media-original')->assertExists($media->mediaAsset->path);
        Queue::assertPushed(ProcessMediaAsset::class, fn (ProcessMediaAsset $job): bool => $job->mediaAssetId === $media->media_asset_id);
    }

    public function test_rejects_an_invalid_file_even_when_its_filename_claims_to_be_an_image(): void
    {
        Queue::fake();
        $path = tempnam(sys_get_temp_dir(), 'invalid-image-');
        file_put_contents($path, '<svg><script>alert(1)</script></svg>');
        $upload = new UploadedFile($path, 'glasses.png', 'image/png', null, true);

        $this->expectException(RuntimeException::class);
        app(ProductMediaIngestor::class)->ingest($upload, $this->product(), null, 'front');
    }

    public function test_enforces_configured_pixel_limits_before_storing_a_master(): void
    {
        config(['media.max_pixels' => 100]);
        Queue::fake();

        $this->expectException(InvalidArgumentException::class);
        app(ProductMediaIngestor::class)->ingest($this->transparentUpload(), $this->product(), null, 'front');
    }

    public function test_rejects_oversized_headers_before_attempting_a_full_decode(): void
    {
        $processor = new class implements ImageProcessor {
            public bool $decoded = false;
            public function capabilities(): array { return ['jpeg' => true, 'png' => true, 'webp' => true, 'avif' => false, 'exif' => false]; }
            public function probe(string $sourcePath): array { return ['mime_type' => 'image/png', 'width' => 10_000, 'height' => 10_000]; }
            public function validateDecodable(string $sourcePath, array $probe): void { $this->decoded = true; }
            public function prepare(string $sourcePath): PreparedImage { throw new RuntimeException('Not reached.'); }
            public function render(PreparedImage $source, string $targetPath, string $format, array $preset): array { throw new RuntimeException('Not reached.'); }
        };
        $this->app->instance(ImageProcessor::class, $processor);
        config(['media.max_pixels' => 1_000]);

        try {
            app(ProductMediaIngestor::class)->ingest($this->transparentUpload(), $this->product(), null, 'front');
            $this->fail('A large header must be rejected before decoding.');
        } catch (InvalidArgumentException) {
            $this->assertFalse($processor->decoded);
        }
    }

    public function test_enforces_configured_upload_size_limits_before_storing_a_master(): void
    {
        config(['media.max_upload_mb' => 0]);
        Queue::fake();

        $this->expectException(InvalidArgumentException::class);
        app(ProductMediaIngestor::class)->ingest($this->transparentUpload(), $this->product(), null, 'front');
    }

    public function test_variant_specific_media_replaces_matching_product_role_and_falls_back_for_other_roles(): void
    {
        $product = $this->product();
        $variant = ProductVariant::create(['product_id' => $product->id, 'slug' => 'axis-black', 'name' => 'Black']);
        $productFront = $this->media($product, null, 'front');
        $productAngle = $this->media($product, null, 'angle');
        $variantFront = $this->media($product, $variant, 'front');

        $selected = app(ProductMediaSelector::class)->forProduct($product, $variant);

        $this->assertFalse($selected->contains($productFront));
        $this->assertTrue($selected->contains($variantFront));
        $this->assertTrue($selected->contains($productAngle));
    }

    public function test_processing_creates_idempotent_responsive_and_device_browser_derivatives_without_removing_the_master(): void
    {
        Queue::fake();
        $media = app(ProductMediaIngestor::class)->ingest($this->transparentUpload(), $this->product(), null, 'front');
        $asset = $media->mediaAsset;
        $masterHash = hash('sha256', Storage::disk('media-original')->get($asset->path));
        $job = new ProcessMediaAsset($asset->id);

        $job->handle(app(MediaDerivativeGenerator::class));
        $firstCount = MediaDerivative::query()->where('media_asset_id', $asset->id)->count();
        $job->handle(app(MediaDerivativeGenerator::class));

        $asset->refresh();
        $this->assertSame('ready', $asset->processing_status);
        $this->assertNotNull($asset->processed_at);
        $this->assertSame($firstCount, MediaDerivative::query()->where('media_asset_id', $asset->id)->count());
        $this->assertDatabaseHas('media_derivatives', ['media_asset_id' => $asset->id, 'preset' => 'responsive_320', 'format' => 'webp']);
        $this->assertDatabaseHas('media_derivatives', ['media_asset_id' => $asset->id, 'preset' => 'device_browser', 'format' => 'webp']);
        $this->assertSame($masterHash, hash('sha256', Storage::disk('media-original')->get($asset->path)));
    }

    public function test_processing_prepares_the_source_once_for_the_complete_derivative_set(): void
    {
        Queue::fake();
        $media = app(ProductMediaIngestor::class)->ingest($this->transparentUpload(), $this->product(), null, 'front');
        $native = app(NativeGdImageProcessor::class);
        $processor = new class($native) implements ImageProcessor {
            public int $prepareCalls = 0;
            public int $renderCalls = 0;
            public function __construct(private NativeGdImageProcessor $native) {}
            public function capabilities(): array { return $this->native->capabilities(); }
            public function probe(string $sourcePath): array { return $this->native->probe($sourcePath); }
            public function validateDecodable(string $sourcePath, array $probe): void { $this->native->validateDecodable($sourcePath, $probe); }
            public function prepare(string $sourcePath): PreparedImage { $this->prepareCalls++; return $this->native->prepare($sourcePath); }
            public function render(PreparedImage $source, string $targetPath, string $format, array $preset): array { $this->renderCalls++; return $this->native->render($source, $targetPath, $format, $preset); }
        };

        (new ProcessMediaAsset($media->media_asset_id))->handle(new MediaDerivativeGenerator($processor));

        $this->assertSame(1, $processor->prepareCalls);
        $this->assertGreaterThan(1, $processor->renderCalls);
    }

    public function test_processing_failure_marks_the_asset_failed_and_keeps_the_master_record(): void
    {
        $media = $this->media($this->product(), null, 'front');
        $asset = $media->mediaAsset;

        try {
            (new ProcessMediaAsset($asset->id))->handle(app(MediaDerivativeGenerator::class));
            $this->fail('Processing a missing master should throw.');
        } catch (RuntimeException) {
            $asset->refresh();
            $this->assertSame('failed', $asset->processing_status);
            $this->assertNotNull($asset->processing_error);
            $this->assertDatabaseHas('media_assets', ['id' => $asset->id]);
        }
    }

    public function test_device_browser_preset_centers_trimmed_transparent_content_on_a_predictable_canvas(): void
    {
        Queue::fake();
        $media = app(ProductMediaIngestor::class)->ingest($this->transparentUpload(), $this->product(), null, 'front');
        (new ProcessMediaAsset($media->media_asset_id))->handle(app(MediaDerivativeGenerator::class));
        $derivative = MediaDerivative::query()->where('media_asset_id', $media->media_asset_id)->where('preset', 'device_browser')->where('format', 'png')->sole();
        $contents = Storage::disk('media-derivatives')->get($derivative->path);
        $path = tempnam(sys_get_temp_dir(), 'device-derivative-');
        file_put_contents($path, $contents);
        $image = imagecreatefrompng($path);
        [$left, $top, $width, $height] = $this->opaqueBounds($image);

        $this->assertSame(960, imagesx($image));
        $this->assertSame(540, imagesy($image));
        $this->assertSame(127, (imagecolorat($image, 0, 0) >> 24) & 0x7f);
        $this->assertSame(729, $width);
        $this->assertSame(292, $height);
        $this->assertSame((int) floor((960 - $width) / 2), $left);
        $this->assertSame((int) floor((540 - $height) / 2), $top);
        imagedestroy($image);
    }

    public function test_webp_and_avif_derivatives_follow_runtime_capabilities_and_manifest_hides_storage_paths(): void
    {
        Queue::fake();
        $product = $this->product();
        $media = app(ProductMediaIngestor::class)->ingest($this->transparentUpload(), $product, null, 'front');
        (new ProcessMediaAsset($media->media_asset_id))->handle(app(MediaDerivativeGenerator::class));

        $manifest = app(ProductMediaManifest::class)->forProduct($product);
        $this->assertNotEmpty($manifest[0]['responsive']['webp']);
        $this->assertNotEmpty($manifest[0]['responsive']['fallback']);
        $this->assertSame('image/png', $manifest[0]['responsive']['fallback'][0]['mime_type']);
        $this->assertNotNull($manifest[0]['device_browser']);
        $this->assertArrayNotHasKey('path', $manifest[0]['original']);
        $this->assertArrayNotHasKey('disk', $manifest[0]['original']);

        if (function_exists('imageavif')) {
            $this->assertNotEmpty($manifest[0]['responsive']['avif']);
        } else {
            $this->assertSame([], $manifest[0]['responsive']['avif']);
        }
    }

    public function test_derivative_policy_only_generates_device_browser_for_front_images_and_skips_spin_assets(): void
    {
        Queue::fake();
        $product = $this->product();
        $front = app(ProductMediaIngestor::class)->ingest($this->transparentUpload(), $product, null, 'front');
        $angle = app(ProductMediaIngestor::class)->ingest($this->transparentUpload(), $product, null, 'angle');
        $side = app(ProductMediaIngestor::class)->ingest($this->transparentUpload(), $product, null, 'side');
        $lifestyle = app(ProductMediaIngestor::class)->ingest($this->transparentUpload(), $product, null, 'lifestyle');
        $spin = app(ProductMediaIngestor::class)->ingest($this->transparentUpload(), $product, null, 'spin', kind: ProductMediaKind::SPIN);

        foreach ([$front, $angle, $side, $lifestyle, $spin] as $media) {
            (new ProcessMediaAsset($media->media_asset_id))->handle(app(MediaDerivativeGenerator::class));
        }

        $this->assertDatabaseHas('media_derivatives', ['media_asset_id' => $front->media_asset_id, 'preset' => 'device_browser']);
        $this->assertDatabaseMissing('media_derivatives', ['media_asset_id' => $angle->media_asset_id, 'preset' => 'device_browser']);
        $this->assertDatabaseMissing('media_derivatives', ['media_asset_id' => $side->media_asset_id, 'preset' => 'device_browser']);
        $this->assertDatabaseMissing('media_derivatives', ['media_asset_id' => $lifestyle->media_asset_id, 'preset' => 'device_browser']);
        $this->assertDatabaseMissing('media_derivatives', ['media_asset_id' => $spin->media_asset_id, 'preset' => 'device_browser']);
        $this->assertDatabaseMissing('media_derivatives', ['media_asset_id' => $spin->media_asset_id]);
    }

    public function test_derivative_write_failure_marks_the_asset_failed_without_creating_a_broken_record(): void
    {
        Queue::fake();
        $media = app(ProductMediaIngestor::class)->ingest($this->transparentUpload(), $this->product(), null, 'front');
        $invalidRoot = tempnam(sys_get_temp_dir(), 'media-derivative-root-');
        config([
            'media.derivative_disk' => 'failing-media-derivatives',
            'filesystems.disks.failing-media-derivatives' => ['driver' => 'local', 'root' => $invalidRoot, 'throw' => false],
        ]);
        Storage::forgetDisk('failing-media-derivatives');

        try {
            (new ProcessMediaAsset($media->media_asset_id))->handle(app(MediaDerivativeGenerator::class));
            $this->fail('A non-directory derivative root must fail processing.');
        } catch (\Throwable) {
            $asset = $media->mediaAsset->fresh();
            $this->assertSame('failed', $asset->processing_status);
            $this->assertDatabaseMissing('media_derivatives', ['media_asset_id' => $asset->id]);
        } finally {
            @unlink($invalidRoot);
        }
    }

    public function test_rejects_unsupported_product_media_kinds(): void
    {
        Queue::fake();
        $this->expectException(InvalidArgumentException::class);
        app(ProductMediaIngestor::class)->ingest($this->transparentUpload(), $this->product(), null, 'front', kind: 'video');
    }

    public function test_complete_exif_orientation_rotates_and_transposes_jpegs(): void
    {
        if (! function_exists('exif_read_data')) {
            $this->markTestSkipped('The EXIF extension is required for this orientation test.');
        }

        foreach ([5, 6] as $orientation) {
            $prepared = app(NativeGdImageProcessor::class)->prepare($this->orientedJpeg($orientation));
            $this->assertSame(10, $prepared->width);
            $this->assertSame(30, $prepared->height);
            $prepared->release();
        }
    }

    private function product(): Product
    {
        $collection = \App\Models\Collection::create(['name' => 'Core', 'slug' => 'core']);
        return Product::create(['collection_id' => $collection->id, 'name' => 'Axis', 'slug' => 'axis']);
    }

    private function media(Product $product, ?ProductVariant $variant, string $role): ProductMedia
    {
        $asset = MediaAsset::create([
            'disk' => 'media-original', 'path' => "products/{$product->id}/{$role}-".uniqid().'.png',
            'mime_type' => 'image/png', 'width' => 200, 'height' => 100, 'bytes' => 1,
            'checksum_sha256' => hash('sha256', uniqid('', true)), 'processing_status' => 'ready',
        ]);

        return ProductMedia::create([
            'product_id' => $product->id, 'product_variant_id' => $variant?->id, 'media_asset_id' => $asset->id,
            'kind' => 'image', 'role' => $role,
        ]);
    }

    private function transparentUpload(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'transparent-product-');
        $image = imagecreatetruecolor(200, 100);
        imagealphablending($image, false);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefilledrectangle($image, 0, 0, 199, 99, $transparent);
        $black = imagecolorallocatealpha($image, 0, 0, 0, 0);
        imagefilledrectangle($image, 50, 30, 149, 69, $black);
        imagesavealpha($image, true);
        imagepng($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, 'sparkland-packshot.png', 'image/png', null, true);
    }

    private function orientedJpeg(int $orientation): string
    {
        $path = tempnam(sys_get_temp_dir(), 'oriented-product-');
        $image = imagecreatetruecolor(30, 10);
        imagefilledrectangle($image, 0, 0, 29, 9, imagecolorallocate($image, 20, 20, 20));
        imagejpeg($image, $path, 90);
        imagedestroy($image);

        $tiff = "II*\x00\x08\x00\x00\x00\x01\x00\x12\x01\x03\x00\x01\x00\x00\x00".pack('v', $orientation)."\x00\x00\x00\x00\x00\x00";
        $exif = "Exif\x00\x00".$tiff;
        $jpeg = file_get_contents($path);
        file_put_contents($path, substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2));

        return $path;
    }

    /** @return array{int, int, int, int} */
    private function opaqueBounds(\GdImage $image): array
    {
        $left = imagesx($image);
        $top = imagesy($image);
        $right = -1;
        $bottom = -1;
        for ($y = 0; $y < imagesy($image); $y++) {
            for ($x = 0; $x < imagesx($image); $x++) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7f) < 127) {
                    $left = min($left, $x); $top = min($top, $y); $right = max($right, $x); $bottom = max($bottom, $y);
                }
            }
        }
        return [$left, $top, $right - $left + 1, $bottom - $top + 1];
    }
}
