<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CheckoutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_bag_page_is_an_inertia_page_and_does_not_create_a_cart(): void
    {
        $this->get('/bag')->assertInertia(fn (Assert $page) => $page->component('Bag'));
        $this->assertDatabaseCount('carts', 0);
    }

    public function test_checkout_page_is_an_inertia_page_and_json_cart_contract_remains_separate(): void
    {
        $this->get('/checkout')->assertInertia(fn (Assert $page) => $page->component('Checkout'));
        $this->getJson('/cart')->assertOk()->assertJsonPath('cart.items', []);
    }
}
