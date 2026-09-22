<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Junior Youth now ends at 17 (it was 18), so anyone of 18 or over belongs on the main register. The under-19s copied
 * into `young_members` earlier included 18-year-olds: drop those copies. Their `members` rows were never touched, so
 * they simply show on the Adults tab. Children registered through the form (no `member_id`) are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('young_members')
            ->whereNotNull('member_id')
            ->where('date_of_birth', '<=', now()->subYears(18)->toDateString())
            ->delete();
    }

    public function down(): void
    {
        // The copies can be rebuilt from `members`; there is nothing to restore.
    }
};
