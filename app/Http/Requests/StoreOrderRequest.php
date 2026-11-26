<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('country_code')) {
            $this->merge(['country_code' => strtoupper(trim((string) $this->input('country_code')))]);
        }
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'customer_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'payment_method' => ['required', 'string', 'in:cash_on_delivery'],
            'coupon_code' => ['sometimes', 'nullable', 'string', 'max:100'],
            'currency' => ['prohibited'],
            'merchandise_subtotal' => ['prohibited'],
            'merchandise_subtotal_minor' => ['prohibited'],
            'discount' => ['prohibited'],
            'discount_minor' => ['prohibited'],
            'shipping' => ['prohibited'],
            'shipping_minor' => ['prohibited'],
            'total' => ['prohibited'],
            'total_minor' => ['prohibited'],
            'order_number' => ['prohibited'],
            'order_status' => ['prohibited'],
            'status' => ['prohibited'],
            'payment_status' => ['prohibited'],
            'user_id' => ['prohibited'],
            'cart_id' => ['prohibited'],
            'sku_id' => ['prohibited'],
            'quantity' => ['prohibited'],
            'shipping_zone_code' => ['prohibited'],
            'placed_at' => ['prohibited'],
            'items' => ['prohibited'],
            'order_items' => ['prohibited'],
        ];
    }
}
