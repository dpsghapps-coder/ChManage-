<?php

use App\Support\Presbyteries;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The PCG presbyteries and their districts move from resources/data/pcg-presbyteries.md into the database, so they can
 * be edited in the app (a file inside the release would be replaced on the next deploy). The markdown file seeds the
 * tables once, here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presbyteries', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 150)->unique()->comment('As stored on records, e.g. "Ga"');
            $table->string('title', 150)->comment('As printed, e.g. "Ga Presbytery"');
            $table->string('short_name', 30)->nullable();
            $table->string('headquarters', 150)->nullable();
            $table->string('coverage', 500)->nullable();
            $table->json('facts')->nullable()->comment('Other notes: [{label, value}]');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('presbytery_districts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('presbytery_id');
            $table->string('name', 150);
            $table->string('note', 150)->nullable()->comment('e.g. "Head Station"');
            $table->timestamps();

            $table->unique(['presbytery_id', 'name']);
            $table->foreign('presbytery_id')->references('id')->on('presbyteries')->cascadeOnDelete();
        });

        $path = config('church.presbyteries');

        if (! is_string($path) || ! is_file($path)) {
            return;
        }

        $now = now();

        foreach (Presbyteries::parse((string) file_get_contents($path))['presbyteries'] as $order => $p) {
            $id = DB::table('presbyteries')->insertGetId([
                'name' => $p['name'],
                'title' => $p['title'],
                'short_name' => $p['short_name'],
                'headquarters' => $p['headquarters'],
                'coverage' => $p['coverage'],
                'facts' => json_encode($p['facts']),
                'sort_order' => $order + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // A district listed twice under one presbytery is kept once.
            $districts = collect($p['districts'])->unique(fn ($d) => mb_strtolower($d['name']));
            DB::table('presbytery_districts')->insert($districts->map(fn ($d) => [
                'presbytery_id' => $id,
                'name' => $d['name'],
                'note' => $d['note'],
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('presbytery_districts');
        Schema::dropIfExists('presbyteries');
    }
};
