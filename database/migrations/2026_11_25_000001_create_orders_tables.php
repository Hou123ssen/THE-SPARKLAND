<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('cart_id')->unique()->constrained('carts')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city');
            $table->char('country_code', 2);
            $table->char('currency', 3);
            $table->unsignedBigInteger('merchandise_subtotal_minor');
            $table->unsignedBigInteger('discount_minor');
            $table->unsignedBigInteger('shipping_minor');
            $table->unsignedBigInteger('total_minor');
            $table->string('coupon_code')->nullable();
            $table->string('shipping_zone_code');
            $table->string('payment_method');
            $table->string('status')->index();
            $table->string('payment_status')->index();
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('sku_id')->nullable()->constrained('skus')->nullOnDelete();
            $table->string('sku_code');
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->json('selected_options');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->timestamps();

            $table->index(['order_id', 'sku_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
