<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sex on the Junior Youth / Children Service register, so a guardian's record can list the child as Son or Daughter.
 * Filled in from the member's older adult-register record where they have one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('young_members', function (Blueprint $table) {
            $table->enum('sex', ['male', 'female'])->nullable()->after('other_names');
        });

        DB::table('young_members')
            ->join('members', 'members.id', '=', 'young_members.member_id')
            ->whereIn('members.sex', ['male', 'female'])
            ->update(['young_members.sex' => DB::raw('members.sex'), 'young_members.updated_at' => DB::raw('young_members.updated_at')]);
    }

    public function down(): void
    {
        Schema::table('young_members', function (Blueprint $table) {
            $table->dropColumn('sex');
        });
    }
};
