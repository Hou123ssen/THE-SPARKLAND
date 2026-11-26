<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/shop', ShopController::class)->name('shop');
Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('/collections', [CollectionController::class, 'index'])->name('collections.index');
Route::get('/collections/{slug}', [CollectionController::class, 'show'])->name('collections.show');
Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::post('/cart/quote', [CartController::class, 'quote'])->block()->name('cart.quote');
// Serialize same-session mutations so first-cart creation and established-cart row locks share one boundary.
Route::post('/cart/items', [CartController::class, 'store'])->block()->name('cart.items.store');
Route::patch('/cart/items/{item}', [CartController::class, 'update'])->whereNumber('item')->block()->name('cart.items.update');
Route::delete('/cart/items/{item}', [CartController::class, 'destroy'])->whereNumber('item')->block()->name('cart.items.destroy');
Route::post('/checkout/orders', [CheckoutController::class, 'store'])->block()->name('checkout.orders.store');
