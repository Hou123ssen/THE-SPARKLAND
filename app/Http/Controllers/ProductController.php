<?php

namespace App\Http\Controllers;

use App\Services\ProductDetail;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function show(string $slug, ProductDetail $detail): Response
    {
        return Inertia::render('ProductShow', $detail->forSlug($slug));
    }
}
