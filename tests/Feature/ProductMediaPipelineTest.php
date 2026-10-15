<?php

namespace Tests\Feature;

use App\Jobs\ProcessMediaAsset;
use App\Models\MediaAsset;
use App\Models\MediaDerivative;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Services\MediaDerivativeGenerator;
use App\Services\ProductMediaIngestor;
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

    public function test_processing_failure_marks_the_asset_failed_and_keeps_the_master_record(): void
    {
        $asset = MediaAsset::create([
            'disk' => 'media-original', 'path' => 'missing/source.png', 'mime_type' => 'image/png',
            'width' => 200, 'height' => 100, 'bytes' => 1, 'checksum_sha256' => str_repeat('a', 64),
            'processing_status' => 'pending',
        ]);

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
        $this->assertNotNull($manifest[0]['device_browser']);
        $this->assertArrayNotHasKey('path', $manifest[0]['original']);
        $this->assertArrayNotHasKey('disk', $manifest[0]['original']);

        if (function_exists('imageavif')) {
            $this->assertNotEmpty($manifest[0]['responsive']['avif']);
        } else {
            $this->assertSame([], $manifest[0]['responsive']['avif']);
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
