<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sku_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('compare_at_minor')->nullable();
            $table->boolean('tax_inclusive')->default(false);
            $table->timestamp('valid_from')->nullable()->index();
            $table->timestamp('valid_to')->nullable()->index();
            $table->timestamps();

            $table->index(['sku_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};
