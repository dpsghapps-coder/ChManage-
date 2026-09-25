<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Occupations: the legacy `professions` list (already linked from members.profession_id) gains categories and the
 * occupations in resources/data/occupations.json, edited afterwards on the Occupations page. Names already on the list
 * are not added twice; the older entries stay uncategorised until someone files them. Members also get the congregation
 * they came from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professions', function (Blueprint $table) {
            $table->string('category', 100)->nullable()->after('name');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('category');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->string('previous_congregation', 150)->nullable()->after('joined_on')->comment('The congregation the member came from');
        });

        $path = resource_path('data/occupations.json');

        if (! is_file($path)) {
            return;
        }

        $existing = DB::table('professions')->pluck('name')->map(fn ($n) => mb_strtolower(trim($n)))->all();

        foreach ((array) json_decode((string) file_get_contents($path), true) as $group) {
            foreach ($group['occupations'] ?? [] as $i => $name) {
                $name = trim((string) $name);

                if ($name === '' || in_array(mb_strtolower($name), $existing, true)) {
                    continue;
                }

                DB::table('professions')->insert(['name' => $name, 'category' => trim($group['category']), 'sort_order' => $i + 1]);
                $existing[] = mb_strtolower($name);
            }
        }
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('previous_congregation');
        });

        // Occupations added from the file are left; members may have them by now.
        Schema::table('professions', function (Blueprint $table) {
            $table->dropColumn(['category', 'sort_order']);
        });
    }
};
