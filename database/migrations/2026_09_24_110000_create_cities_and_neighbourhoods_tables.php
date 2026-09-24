<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cities and their neighbourhoods, edited on the Neighbourhoods page. The church's City (Church Settings) picks which
 * city's neighbourhoods the member form suggests for Residence. resources/data/ghana-neighbourhoods.json (Accra, from
 * the dps-erp CRM) seeds the tables once, here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 150)->unique();
            $table->string('region', 60)->nullable()->comment('Its region; Residence also suggests that region\'s towns');
            $table->timestamps();
        });

        Schema::create('neighbourhoods', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('city_id');
            $table->string('name', 150);
            $table->timestamps();

            $table->unique(['city_id', 'name']);
            $table->foreign('city_id')->references('id')->on('cities')->cascadeOnDelete();
        });

        $path = config('church.neighbourhoods');

        if (! is_string($path) || ! is_file($path)) {
            return;
        }

        $now = now();
        $regions = ['Accra' => 'Greater Accra', 'Kumasi' => 'Ashanti', 'Tamale' => 'Northern', 'Takoradi' => 'Western', 'Cape Coast' => 'Central'];

        foreach ((array) json_decode((string) file_get_contents($path), true) as $city => $names) {
            $id = DB::table('cities')->insertGetId(['name' => $city, 'region' => $regions[$city] ?? null, 'created_at' => $now, 'updated_at' => $now]);

            DB::table('neighbourhoods')->insert(collect($names)
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->unique(fn ($name) => mb_strtolower($name))
                ->map(fn ($name) => ['city_id' => $id, 'name' => $name, 'created_at' => $now, 'updated_at' => $now])
                ->values()->all());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('neighbourhoods');
        Schema::dropIfExists('cities');
    }
};
