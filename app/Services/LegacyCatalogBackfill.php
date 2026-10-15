<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\Sku;
use Illuminate\Support\Facades\DB;

class LegacyCatalogBackfill
{
    /**
     * Copy legacy catalog records into the additive commerce foundation.
     * The legacy columns and product_images records remain authoritative for
     * the current public site until a later storefront migration.
     */
    public function run(): void
    {
        Product::query()->with('images')->orderBy('id')->eachById(function (Product $product): void {
            DB::transaction(function () use ($product): void {
                if ($product->collection_id !== null) {
                    DB::table('collection_product')->insertOrIgnore([
                        'collection_id' => $product->collection_id,
                        'product_id' => $product->id,
                        'display_order' => $product->display_order,
                        'is_featured' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $variant = $product->variants()->firstOrCreate(
                    ['slug' => $product->slug.'-legacy-default'],
                    [
                        'name' => 'Default',
                        'colour_name' => $product->color,
                        'material' => $product->material,
                        'frame_shape' => $product->frame,
                        'lens_type' => $product->lens,
                        'is_active' => $product->is_active,
                        'display_order' => 0,
                    ],
                );

                $sku = $variant->skus()->firstOrCreate(
                    ['sku' => sprintf('LEGACY-%06d-DEFAULT', $product->id)],
                    [
                        'status' => $product->is_active ? Sku::STATUS_ACTIVE : Sku::STATUS_INACTIVE,
                        'is_backorderable' => false,
                    ],
                );

                if ($product->price !== null) {
                    Price::firstOrCreate(
                        [
                            'sku_id' => $sku->id,
                            'currency' => config('commerce.legacy_price_currency'),
                            'amount_minor' => $this->minorUnits((string) $product->price),
                            'valid_from' => null,
                            'valid_to' => null,
                        ],
                        ['tax_inclusive' => false],
                    );
                }

                foreach ($product->images as $image) {
                    $asset = MediaAsset::firstOrCreate(
                        ['disk' => 'public', 'path' => $image->image_path],
                        [
                            'original_name' => basename($image->image_path),
                            'processing_status' => 'ready',
                        ],
                    );

                    ProductMedia::firstOrCreate(
                        [
                            'product_id' => $product->id,
                            'media_asset_id' => $asset->id,
                            'kind' => 'image',
                            'role' => $image->type ?? 'gallery',
                            'display_order' => $image->display_order,
                        ],
                        [
                            'sequence_index' => $image->display_order,
                            'is_primary' => $image->display_order === 1,
                        ],
                    );
                }
            });
        });
    }

    private function minorUnits(string $amount): int
    {
        $normalized = trim($amount);
        if (str_starts_with($normalized, '-')) {
            throw new \InvalidArgumentException('Legacy catalog prices must not be negative.');
        }

        $normalized = ltrim($normalized, '+');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0');
        $minor = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $minor;
    }
}
