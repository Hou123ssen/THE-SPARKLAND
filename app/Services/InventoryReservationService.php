<?php

namespace App\Services;

use App\Models\Sku;
use Illuminate\Validation\ValidationException;

class InventoryReservationService
{
    /** Reserve physical stock for one locked SKU; backorders reserve only available units. */
    public function reserve(Sku $sku, int $quantity): void
    {
        $lockedSku = Sku::query()->whereKey($sku->id)->lockForUpdate()->firstOrFail();
        $levels = $lockedSku->inventoryLevels()
            ->whereHas('location', fn ($query) => $query->where('is_active', true))
            ->with('location')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $available = $levels->sum(fn ($level): int => max(0, $level->on_hand - $level->reserved));
        if (! $lockedSku->is_backorderable && $available < $quantity) {
            throw ValidationException::withMessages(['cart' => 'Inventory changed before this order could be placed.']);
        }

        $remaining = min($quantity, $available);
        foreach ($levels as $level) {
            if ($remaining <= 0) {
                break;
            }
            $capacity = max(0, $level->on_hand - $level->reserved);
            $reserve = min($capacity, $remaining);
            if ($reserve <= 0) {
                continue;
            }
            if ($level->reserved > PHP_INT_MAX - $reserve || $level->reserved + $reserve > $level->on_hand) {
                throw ValidationException::withMessages(['cart' => 'Inventory changed before this order could be placed.']);
            }
            $level->increment('reserved', $reserve);
            $remaining -= $reserve;
        }
    }
}
