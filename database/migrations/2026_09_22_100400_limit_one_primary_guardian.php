<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A child has at most one primary guardian, enforced by the database and not only by the form. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('young_member_guardians', function (Blueprint $table) {
            // 1 for the primary guardian, NULL for the rest: a unique index over it allows only one primary per child.
            $table->unsignedTinyInteger('primary_slot')->nullable()->storedAs('IF(is_primary, 1, NULL)')->after('is_primary');
            $table->unique(['young_member_id', 'primary_slot'], 'one_primary_guardian_per_child');
        });
    }

    public function down(): void
    {
        Schema::table('young_member_guardians', function (Blueprint $table) {
            $table->dropUnique('one_primary_guardian_per_child');
            $table->dropColumn('primary_slot');
        });
    }
};
