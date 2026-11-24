<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function show(Request $request, CartService $carts): JsonResponse
    {
        $cart = $carts->current($request->session());

        return response()->json(['cart' => $carts->serialize($cart)]);
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
