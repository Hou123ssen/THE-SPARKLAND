<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'order_number', 'cart_id', 'user_id', 'customer_name', 'customer_phone', 'customer_email',
    'address_line1', 'address_line2', 'city', 'country_code', 'currency',
    'merchandise_subtotal_minor', 'discount_minor', 'shipping_minor', 'total_minor',
    'coupon_code', 'shipping_zone_code', 'payment_method', 'status', 'payment_status', 'placed_at',
])]
class Order extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_PENDING = 'pending';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_FAILED = 'failed';

    public const PAYMENT_REFUNDED = 'refunded';

    public const PAYMENT_METHOD_COD = 'cash_on_delivery';

    public static function generateOrderNumber(): string
    {
        return 'SP-'.strtoupper(Str::ulid()->toBase32());
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    protected function casts(): array
    {
        return [
            'merchandise_subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'shipping_minor' => 'integer',
            'total_minor' => 'integer',
            'placed_at' => 'datetime',
        ];
    }
}
