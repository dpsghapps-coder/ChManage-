<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Session and the District Church Council are the governing bodies of a Presbyterian congregation and hold meetings
 * like any committee, so they join the Committees list (which stays editable on the Committees page).
 */
return new class extends Migration
{
    private const BODIES = ['Session', 'District Church Council'];

    public function up(): void
    {
        foreach (self::BODIES as $name) {
            if (! DB::table('committees')->where('name', $name)->exists()) {
                DB::table('committees')->insert(['name' => $name, 'sort_order' => (int) DB::table('committees')->max('sort_order') + 1]);
            }
        }
    }

    public function down(): void
    {
        // Left in place: meetings or service records may refer to them.
    }
};
