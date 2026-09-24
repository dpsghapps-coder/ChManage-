<?php

use App\Support\Neighbourhoods;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds the 58 cities (all 16 regions) and their neighbourhoods from resources/data/ghana-cities-neighbourhoods.json.
 * Only what is missing is added, so lists already edited on the Neighbourhoods page are kept. The same file can be
 * re-applied with `php artisan neighbourhoods:import resources/data/ghana-cities-neighbourhoods.json`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $path = resource_path('data/ghana-cities-neighbourhoods.json');

        if (is_file($path)) {
            Neighbourhoods::import((array) json_decode((string) file_get_contents($path), true));
        }
    }

    public function down(): void
    {
        // Left in place: by now these lists may have been edited on the Neighbourhoods page.
    }
};
