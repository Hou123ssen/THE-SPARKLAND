<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->string('colour_name')->nullable();
            $table->string('colour_hex', 7)->nullable();
            $table->string('material')->nullable();
            $table->string('frame_shape')->nullable();
            $table->string('fit')->nullable();
            $table->unsignedInteger('frame_width_mm')->nullable();
            $table->unsignedInteger('lens_width_mm')->nullable();
            $table->unsignedInteger('lens_height_mm')->nullable();
            $table->unsignedInteger('bridge_width_mm')->nullable();
            $table->unsignedInteger('temple_length_mm')->nullable();
            $table->string('lens_type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['product_id', 'slug']);
            $table->index(['product_id', 'is_active', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
