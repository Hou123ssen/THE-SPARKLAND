<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('product_type')->default('frame');
            $table->string('status')->default('active')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->boolean('is_prescription_eligible')->default(false);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropIndex(['published_at']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'product_type', 'status', 'published_at', 'is_prescription_eligible',
                'seo_title', 'seo_description',
            ]);
        });
    }
};
