<?php

namespace App\Http\Controllers;

use App\Http\Requests\CartQuoteRequest;
use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Services\CartService;
use App\Services\CartTotalsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function show(Request $request, CartService $carts): JsonResponse
    {
        $cart = $carts->existing($request->session());

        return response()->json(['cart' => $cart === null ? [
            'item_count' => 0,
            'merchandise_subtotal' => null,
            'items' => [],
        ] : $carts->serialize($cart)]);
    }

    public function quote(CartQuoteRequest $request, CartService $carts, CartTotalsService $totals): JsonResponse
    {
        $cart = $carts->existing($request->session());
        if ($cart === null) {
            return response()->json(['message' => 'An active cart is required for a quote.'], 422);
        }

        return response()->json(['quote' => $totals->quote(
            $cart,
            $request->validated('coupon_code'),
            $request->validated('country_code'),
        )]);
    }

    public function store(StoreCartItemRequest $request, CartService $carts): JsonResponse
    {
        $validated = $request->validated();
        $cart = $carts->add(
            $request->session(),
            (int) $validated['sku_id'],
            (int) ($validated['quantity'] ?? 1),
        );

        return response()->json(['cart' => $carts->serialize($cart)]);
    }

    public function update(UpdateCartItemRequest $request, int $item, CartService $carts): JsonResponse
    {
        $cart = $carts->update(
            $request->session(),
            $item,
            (int) $request->validated('quantity'),
        );

        return response()->json(['cart' => $carts->serialize($cart)]);
    }

    public function destroy(Request $request, int $item, CartService $carts): JsonResponse
    {
        $cart = $carts->remove($request->session(), $item);

        return response()->json(['cart' => $carts->serialize($cart)]);
    }
}
