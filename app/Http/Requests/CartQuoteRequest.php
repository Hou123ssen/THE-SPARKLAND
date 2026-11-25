<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CartQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'coupon_code' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
        ];
    }
}
