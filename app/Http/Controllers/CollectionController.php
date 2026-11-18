<?php

namespace App\Http\Controllers;

use App\Services\CollectionCatalog;
use App\Services\CollectionDiscovery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    public function index(CollectionDiscovery $collections): Response
    {
        return Inertia::render('Collections', [
            'collections' => $collections->all(),
        ]);
    }

    public function show(Request $request, string $slug, CollectionCatalog $catalog): Response
    {
        return Inertia::render('CollectionShow', $catalog->page($slug, $request->query()));
    }
}
