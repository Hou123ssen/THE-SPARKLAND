<?php

namespace App\Http\Controllers;

use App\Services\PublicCatalog;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(PublicCatalog $catalog): Response
    {
        return Inertia::render('Home', [
            'catalog' => $catalog->all(),
        ]);
    }
}
