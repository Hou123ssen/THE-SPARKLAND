<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class CommercePageController extends Controller
{
    public function bag(): Response
    {
        return Inertia::render('Bag');
    }

    public function checkout(): Response
    {
        return Inertia::render('Checkout');
    }
}
