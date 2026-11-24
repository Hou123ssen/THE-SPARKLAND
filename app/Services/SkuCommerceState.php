<?php

namespace App\Services;

use App\Models\Price;
use App\Models\Product;
use App\Models\ProductOptionValue;
use App\Models\Sku;

/** Resolves current public commerce truth for one sellable SKU. */
class SkuCommerceState
{
    /** @return array<string, mixed> */
    public function forSku(Sku $sku): array
    {
        $sku->loadMissing([
            'prices',
            'inventoryLevels.location',
            'optionValues.option',
            'variant' => fn ($variants) => $variants->withTrashed()->with([
                'product' => fn ($products) => $products->withTrashed()->with('options'),
            ]),
        ]);

        $variant = $sku->variant;
        $product = $variant?->product;
        $price = $this->unambiguousCurrentPrice($sku);
        $sale = $this->sale($price);
        $activeLevels = $sku->inventoryLevels
            ->filter(fn ($level): bool => $level->location?->is_active === true);
        $availableQuantity = max(0, $activeLevels->sum(
            fn ($level): int => $level->on_hand - $level->reserved
        ));
        $availability = $availableQuantity > 0
            ? 'in_stock'
            : ($sku->is_backorderable ? 'backorder' : 'out_of_stock');
        $optionCombinationValid = $this->validOptionCombination($product, $sku->optionValues);
        $structurePurchasable = $this->structurePurchasable($sku, $product, $variant)
            && $optionCombinationValid;

        return [
            'price' => $price,
            'sale' => $sale,
            'availability' => $availability,
            'available_quantity' => $availableQuantity,
            'maximum_quantity' => $sku->is_backorderable ? null : $availableQuantity,
            'option_combination_valid' => $optionCombinationValid,
            'can_purchase' => $structurePurchasable
                && $availability !== 'out_of_stock',
        ];
    }

    private function unambiguousCurrentPrice(Sku $sku): ?Price
    {
        $prices = $sku->prices->filter(fn (Price $price): bool => $price->isCurrent())->values();

        return $prices->count() === 1 ? $prices->first() : null;
    }

    /** @return array{compare_at_minor: int, discount_percentage: int}|null */
    private function sale(?Price $price): ?array
    {
        if ($price === null || $price->compare_at_minor === null || $price->compare_at_minor <= $price->amount_minor) {
            return null;
        }

        return [
            'compare_at_minor' => $price->compare_at_minor,
            'discount_percentage' => (int) round(
                (($price->compare_at_minor - $price->amount_minor) / $price->compare_at_minor) * 100
            ),
        ];
    }

    private function structurePurchasable(Sku $sku, ?Product $product, $variant): bool
    {
        return $product !== null
            && $variant !== null
            && ! $product->trashed()
            && ! $variant->trashed()
            && $product->is_active
            && $product->status === Product::STATUS_ACTIVE
            && ($product->published_at === null || $product->published_at->lessThanOrEqualTo(now()))
            && $variant->is_active
            && $sku->status === Sku::STATUS_ACTIVE;
    }

    private function validOptionCombination(?Product $product, $values): bool
    {
        if ($product === null) {
            return false;
        }

        $requiredOptionIds = $product->options
            ->where('is_active', true)
            ->where('is_required', true)
            ->pluck('id');

        return $values->every(fn (ProductOptionValue $value): bool => $value->option !== null
            && $value->option->product_id === $product->id
            && $value->option->is_active
            && $value->is_active)
            && $values->pluck('product_option_id')->unique()->count() === $values->count()
            && $requiredOptionIds->diff($values->pluck('product_option_id'))->isEmpty();
    }
}
