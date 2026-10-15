<?php

namespace Database\Seeders;

use App\Services\LegacyCatalogBackfill;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Local development entrypoint. Deployments should load production catalog
     * data through their own import pipeline rather than this demo seed.
     */
    public function run(): void
    {
        $this->call(DemoCatalogSeeder::class);
        app(LegacyCatalogBackfill::class)->run();
    }
}
