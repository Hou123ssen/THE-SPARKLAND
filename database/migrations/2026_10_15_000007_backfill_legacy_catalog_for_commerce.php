<?php

use App\Services\LegacyCatalogBackfill;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(LegacyCatalogBackfill::class)->run();
    }

    public function down(): void
    {
        // The legacy source columns remain intact. Automatically deleting
        // additive records could remove data created after this migration.
    }
};
