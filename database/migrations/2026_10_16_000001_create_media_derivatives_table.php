<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_derivatives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->string('preset');
            $table->string('format', 8);
            $table->string('disk');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedBigInteger('bytes');
            $table->string('checksum_sha256', 64);
            $table->timestamps();

            $table->unique(['media_asset_id', 'preset', 'format']);
            $table->index(['media_asset_id', 'preset']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_derivatives');
    }
};
