<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * This intentionally uses only versioned table contracts and the query
     * builder. Historical migrations must not depend on mutable application
     * models, relationships, configuration, or services.
     */
    public function up(): void
    {
        DB::table('products')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->eachById(function (object $product): void {
                DB::transaction(function () use ($product): void {
                    $timestamp = now();

                    if ($product->collection_id !== null) {
                        DB::table('collection_product')->insertOrIgnore([
                            'collection_id' => $product->collection_id,
                            'product_id' => $product->id,
                            'display_order' => $product->display_order,
                            'is_featured' => false,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ]);
                    }

                    $variant = DB::table('product_variants')
                        ->where('product_id', $product->id)
                        ->where('slug', $product->slug.'-legacy-default')
                        ->first();

                    $variantId = $variant?->id ?? DB::table('product_variants')->insertGetId([
                        'product_id' => $product->id,
                        'slug' => $product->slug.'-legacy-default',
                        'name' => 'Default',
                        'colour_name' => $product->color,
                        'material' => $product->material,
                        'frame_shape' => $product->frame,
                        'lens_type' => $product->lens,
                        'is_active' => $product->is_active,
                        'display_order' => 0,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);

                    $legacySku = sprintf('LEGACY-%06d-DEFAULT', $product->id);
                    $sku = DB::table('skus')->where('sku', $legacySku)->first();
                    $skuId = $sku?->id ?? DB::table('skus')->insertGetId([
                        'product_variant_id' => $variantId,
                        'sku' => $legacySku,
                        'status' => $product->is_active ? 'active' : 'inactive',
                        'is_backorderable' => false,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);

                    if ($product->price !== null) {
                        $amountMinor = $this->minorUnits((string) $product->price, $product->id);
                        $priceExists = DB::table('prices')
                            ->where('sku_id', $skuId)
                            ->where('currency', 'XXX')
                            ->where('amount_minor', $amountMinor)
                            ->whereNull('valid_from')
                            ->whereNull('valid_to')
                            ->exists();

                        if (! $priceExists) {
                            DB::table('prices')->insert([
                                'sku_id' => $skuId,
                                'currency' => 'XXX',
                                'amount_minor' => $amountMinor,
                                'tax_inclusive' => false,
                                'created_at' => $timestamp,
                                'updated_at' => $timestamp,
                            ]);
                        }
                    }

                    DB::table('product_images')
                        ->where('product_id', $product->id)
                        ->orderBy('id')
                        ->each(function (object $image) use ($product, $timestamp): void {
                            $asset = DB::table('media_assets')
                                ->where('disk', 'public')
                                ->where('path', $image->image_path)
                                ->first();

                            $assetId = $asset?->id ?? DB::table('media_assets')->insertGetId([
                                'disk' => 'public',
                                'path' => $image->image_path,
                                'original_name' => basename($image->image_path),
                                'processing_status' => 'ready',
                                'created_at' => $timestamp,
                                'updated_at' => $timestamp,
                            ]);

                            $role = $image->type ?? 'gallery';
                            $associationExists = DB::table('product_media')
                                ->where('product_id', $product->id)
                                ->where('media_asset_id', $assetId)
                                ->where('kind', 'image')
                                ->where('role', $role)
                                ->where('display_order', $image->display_order)
                                ->exists();

                            if (! $associationExists) {
                                DB::table('product_media')->insert([
                                    'product_id' => $product->id,
                                    'media_asset_id' => $assetId,
                                    'kind' => 'image',
                                    'role' => $role,
                                    'sequence_index' => $image->display_order,
                                    'display_order' => $image->display_order,
                                    'is_primary' => $image->display_order === 1,
                                    'created_at' => $timestamp,
                                    'updated_at' => $timestamp,
                                ]);
                            }
                        });
                });
            });
    }

    public function down(): void
    {
        // Legacy source columns remain intact. Automatic deletion could remove
        // commerce records added after the historical migration completed.
    }

    private function minorUnits(string $amount, int $productId): int
    {
        $normalized = trim($amount);

        if (str_starts_with($normalized, '-')) {
            throw new \RuntimeException("Cannot backfill negative legacy price for product {$productId}.");
        }

        $normalized = ltrim($normalized, '+');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
};
