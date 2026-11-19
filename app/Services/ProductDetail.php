<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\Sku;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Public, selection-safe contract for the Product Detail page. */
class ProductDetail extends StorefrontCatalog
{
    /** @return array<string, mixed> */
    public function forSlug(string $slug): array
    {
        $product = $this->visibleProducts()
            ->where('products.slug', $slug)
            ->with([
                'options' => fn ($options) => $options->where('is_active', true)->orderBy('display_order')->orderBy('id')->with([
                    'values' => fn ($values) => $values->where('is_active', true)->orderBy('display_order')->orderBy('id'),
                ]),
                'variants' => function ($variants): void {
                    $this->applyVariantConstraints($variants);
                    $variants->orderBy('display_order')->orderBy('id')->with([
                        'skus' => function ($skus): void {
                            $skus->where('status', Sku::STATUS_ACTIVE)->orderBy('id')->with([
                                'prices' => fn ($prices) => $this->currentPrices($prices),
                                'inventoryLevels' => fn ($levels) => $levels->whereHas('location', fn (Builder $locations) => $locations->where('is_active', true)),
                                'optionValues.option',
                            ]);
                        },
                    ]);
                },
                'media' => fn ($media) => $media->with('mediaAsset.derivatives')->orderBy('display_order')->orderBy('id'),
            ])
            ->firstOrFail();

        $options = $product->options->map(fn (ProductOption $option): array => $this->serializeOption($option))->all();
        $requiredOptionIds = $product->options->where('is_required', true)->pluck('id')->all();
        $variants = $product->variants
            ->map(fn (ProductVariant $variant): ?array => $this->serializeVariant($product, $variant, $requiredOptionIds))
            ->filter()
            ->values()
            ->all();

        if ($variants === []) {
            throw new NotFoundHttpException();
        }

        return [
            'product' => array_filter([
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'product_type' => $product->product_type,
                'is_prescription_eligible' => $product->is_prescription_eligible ?: null,
                'media' => $this->mediaManifest->forProduct($product),
            ], fn ($value): bool => $value !== null && $value !== ''),
            'options' => $options,
            'variants' => $variants,
            'initial_selection' => $this->initialSelection($options, $variants),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeOption(ProductOption $option): array
    {
        return [
            'id' => $option->id,
            'name' => $option->name,
            'code' => $option->code,
            'required' => $option->is_required,
            'values' => $option->values->map(fn (ProductOptionValue $value): array => [
                'id' => $value->id,
                'label' => $value->label,
                'code' => $value->code,
                'default' => $value->is_default,
            ])->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function serializeVariant(Product $product, ProductVariant $variant, array $requiredOptionIds): ?array
    {
        $skus = $variant->skus
            ->map(fn (Sku $sku): ?array => $this->serializeSku($product, $sku, $requiredOptionIds))
            ->filter()
            ->values()
            ->all();

        if ($skus === []) {
            return null;
        }

        $specifications = array_filter([
            'colour' => $variant->colour_name,
            'colour_hex' => $variant->colour_hex,
            'material' => $variant->material,
            'frame_shape' => $variant->frame_shape,
            'fit' => $variant->fit,
            'frame_width_mm' => $variant->frame_width_mm,
            'lens_width_mm' => $variant->lens_width_mm,
            'lens_height_mm' => $variant->lens_height_mm,
            'bridge_width_mm' => $variant->bridge_width_mm,
            'temple_length_mm' => $variant->temple_length_mm,
            'lens_type' => $variant->lens_type,
        ], fn ($value): bool => $value !== null && $value !== '');

        return [
            'id' => $variant->id,
            'slug' => $variant->slug,
            'name' => $variant->name,
            'specifications' => $specifications,
            'media' => $this->mediaManifest->forProduct($product, $variant),
            'skus' => $skus,
        ];
    }

    /** @return array<string, mixed>|null */
    private function serializeSku(Product $product, Sku $sku, array $requiredOptionIds): ?array
    {
        $values = $sku->optionValues;
        if (! $this->validOptionCombination($product, $values, $requiredOptionIds)) {
            return null;
        }

        $price = $this->unambiguousCurrentPrice($sku);
        $compareAt = $price?->compare_at_minor;
        $sale = $price !== null && $compareAt !== null && $compareAt > $price->amount_minor
            ? ['compare_at_minor' => $compareAt, 'discount_percentage' => (int) round((($compareAt - $price->amount_minor) / $compareAt) * 100)]
            : null;
        $available = $sku->inventoryLevels->sum(fn ($level): int => $level->on_hand - $level->reserved);
        $availability = $available > 0 ? 'in_stock' : ($sku->is_backorderable ? 'backorder' : 'out_of_stock');

        return [
            'id' => $sku->id,
            'option_values' => $values->map(fn (ProductOptionValue $value): array => [
                'id' => $value->id,
                'option_id' => $value->product_option_id,
                'code' => $value->code,
            ])->sortBy('option_id')->values()->all(),
            'price' => $price === null ? null : ['amount_minor' => $price->amount_minor, 'currency' => $price->currency],
            'sale' => $sale,
            'availability' => $availability,
            'can_purchase' => $availability !== 'out_of_stock',
        ];
    }

    private function validOptionCombination(Product $product, $values, array $requiredOptionIds): bool
    {
        return $values->every(fn (ProductOptionValue $value): bool => $value->option !== null
            && $value->option->product_id === $product->id
            && $value->option->is_active
            && $value->is_active)
            && $values->pluck('product_option_id')->unique()->count() === $values->count()
            && collect($requiredOptionIds)->diff($values->pluck('product_option_id'))->isEmpty();
    }

    /** @param list<array<string, mixed>> $options @param list<array<string, mixed>> $variants */
    private function initialSelection(array $options, array $variants): ?array
    {
        $skus = collect($variants)->flatMap(fn (array $variant) => collect($variant['skus'])->map(fn (array $sku) => [
            'variant_id' => $variant['id'], 'sku_id' => $sku['id'], 'option_values' => $sku['option_values'],
        ]));

        if ($options === []) {
            $firstVariant = collect($variants)->first();
            if ($firstVariant === null || count($firstVariant['skus']) !== 1) {
                return null;
            }

            return ['variant_id' => $firstVariant['id'], 'sku_id' => $firstVariant['skus'][0]['id']];
        }

        $defaults = [];
        foreach ($options as $option) {
            $activeDefaults = collect($option['values'])->where('default', true)->values();
            if ($option['required'] && $activeDefaults->count() !== 1) {
                return null;
            }
            if ($activeDefaults->count() === 1) {
                $defaults[] = $activeDefaults->first()['id'];
            }
        }

        $defaultByOption = collect($options)->mapWithKeys(function (array $option) use ($defaults): array {
            $value = collect($option['values'])->first(fn (array $value): bool => in_array($value['id'], $defaults, true));
            return $value === null ? [] : [$option['id'] => $value['id']];
        });
        $matches = $skus->filter(function (array $sku) use ($defaultByOption): bool {
            $selected = collect($sku['option_values'])->mapWithKeys(fn (array $value): array => [$value['option_id'] => $value['id']]);
            return $defaultByOption->every(fn (int $valueId, int $optionId): bool => $selected->get($optionId) === $valueId);
        })->values();

        if ($matches->count() !== 1) {
            return null;
        }

        return ['variant_id' => $matches[0]['variant_id'], 'sku_id' => $matches[0]['sku_id']];
    }
}
