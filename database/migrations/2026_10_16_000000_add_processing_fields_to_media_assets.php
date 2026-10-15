<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->timestamp('processed_at')->nullable()->index();
            $table->text('processing_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropIndex(['processed_at']);
            $table->dropColumn(['processed_at', 'processing_error']);
        });
    }
};
