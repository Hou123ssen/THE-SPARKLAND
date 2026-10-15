<?php

return [
    // The legacy catalog has decimal prices without a currency. XXX is the ISO
    // 4217 no-currency code and forces a merchandising decision before sale.
    'legacy_price_currency' => env('COMMERCE_LEGACY_PRICE_CURRENCY', 'XXX'),
];
