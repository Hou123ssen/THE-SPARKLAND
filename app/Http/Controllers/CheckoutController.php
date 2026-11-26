<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function store(StoreOrderRequest $request, OrderService $orders): JsonResponse
    {
        $order = $orders->place($request->session(), $request->validated());

        return response()->json(['order' => $orders->serialize($order)], 201);
    }
}
