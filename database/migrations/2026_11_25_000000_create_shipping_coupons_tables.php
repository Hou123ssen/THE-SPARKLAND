<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->char('country_code', 2)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('shipping_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained('shipping_zones')->cascadeOnDelete();
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['shipping_zone_id', 'currency']);
            $table->index(['shipping_zone_id', 'is_active', 'currency']);
        });

        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('type');
            $table->char('currency', 3);
            $table->unsignedBigInteger('fixed_amount_minor')->nullable();
            $table->unsignedInteger('percentage_basis_points')->nullable();
            $table->unsignedBigInteger('minimum_subtotal_minor')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamps();

            $table->index(['is_active', 'currency']);
        });

        DB::table('shipping_zones')->insertOrIgnore([
            'code' => 'MAROC',
            'name' => 'Morocco',
            'country_code' => 'MA',
            'is_active' => true,
            'display_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $zoneId = DB::table('shipping_zones')->where('code', 'MAROC')->value('id');
        DB::table('shipping_rates')->insertOrIgnore([
            'shipping_zone_id' => $zoneId,
            'currency' => 'MAD',
            'amount_minor' => 3000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('shipping_rates');
        Schema::dropIfExists('shipping_zones');
    }
};
