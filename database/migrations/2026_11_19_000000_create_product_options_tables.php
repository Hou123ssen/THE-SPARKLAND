<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'code']);
            $table->index(['product_id', 'is_active', 'display_order']);
        });

        Schema::create('product_option_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_option_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('code');
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_option_id', 'code']);
            $table->unique(['id', 'product_option_id']);
            $table->index(['product_option_id', 'is_active', 'display_order']);
        });

        Schema::create('sku_product_option_value', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sku_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_option_value_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['sku_id', 'product_option_id']);
            $table->unique(['sku_id', 'product_option_value_id']);
            $table->foreign(['product_option_value_id', 'product_option_id'])
                ->references(['id', 'product_option_id'])
                ->on('product_option_values')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sku_product_option_value');
        Schema::dropIfExists('product_option_values');
        Schema::dropIfExists('product_options');
    }
};
