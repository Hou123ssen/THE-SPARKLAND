<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['product_variant_id', 'sku', 'barcode', 'status', 'weight_g', 'is_backorderable'])]
class Sku extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
    public function prices(): HasMany { return $this->hasMany(Price::class); }
    public function inventoryLevels(): HasMany { return $this->hasMany(InventoryLevel::class); }
    public function inventoryMovements(): HasMany { return $this->hasMany(InventoryMovement::class); }
    public function optionValues(): BelongsToMany { return $this->belongsToMany(ProductOptionValue::class, 'sku_product_option_value')->withPivot('product_option_id')->withTimestamps(); }

    /**
     * Application writes must use this method, never raw pivot attach/sync.
     *
     * @param list<int> $valueIds
     */
    public function syncOptionValues(array $valueIds): void
    {
        DB::transaction(function () use ($valueIds): void {
            $sku = self::query()->lockForUpdate()->findOrFail($this->id);
            if (count($valueIds) !== count(array_unique($valueIds))) {
                throw new \InvalidArgumentException('SKU option values must not contain duplicate selections.');
            }

            $values = ProductOptionValue::query()->with('option')->lockForUpdate()->whereIn('id', $valueIds)->get();
            $productId = $sku->variant()->value('product_id');
            $required = ProductOption::query()->where('product_id', $productId)->where('is_active', true)->where('is_required', true)->pluck('id');

            if ($values->count() !== count($valueIds)
                || $values->contains(fn (ProductOptionValue $value): bool => $value->option === null || $value->option->product_id !== $productId || ! $value->option->is_active || ! $value->is_active)
                || $values->pluck('product_option_id')->unique()->count() !== $values->count()
                || $required->diff($values->pluck('product_option_id'))->isNotEmpty()) {
                throw new \InvalidArgumentException('SKU option values must be a complete, unique active combination from the SKU product.');
            }

            $sku->optionValues()->sync($values->mapWithKeys(fn (ProductOptionValue $value): array => [
                $value->id => ['product_option_id' => $value->product_option_id],
            ])->all());
        });

        $this->unsetRelation('optionValues');
    }

    protected function casts(): array
    {
        return ['weight_g' => 'integer', 'is_backorderable' => 'boolean'];
    }
}
