<?php

namespace App\Http\Controllers;

use App\Services\ShopCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    public function __invoke(Request $request, ShopCatalog $catalog): Response
    {
        return Inertia::render('Shop', $catalog->page($request->query()));
    }
}
