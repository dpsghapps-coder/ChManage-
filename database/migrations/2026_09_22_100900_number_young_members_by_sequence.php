<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A young member's number is PCG/ECM/YEAR/DEP/SEQ, where DEP (CS or JY) follows the child's age, so it changes on its own when
 * they reach 12 while the year and sequence stay the same. Only the year and sequence are stored; the sequence is one running
 * count shared by both departments, so a number can never repeat when a child moves from CS to JY.
 *
 * Existing records are numbered from 000001 in their old order, keeping the year in their old number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('young_members', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_year')->nullable()->after('id');
            $table->unsignedInteger('number_seq')->nullable()->after('number_year');
        });

        $sequence = 1;

        DB::table('young_members')
            ->orderByRaw("cast(substring_index(member_number, '/', -1) as unsigned)")
            ->orderBy('id')
            ->get(['id', 'member_number'])
            ->each(function ($row) use (&$sequence) {
                $year = (int) (explode('/', $row->member_number)[2] ?? 0);

                DB::table('young_members')->where('id', $row->id)->update([
                    'number_year' => $year >= 1990 && $year <= (int) date('Y') ? $year : (int) date('Y'),
                    'number_seq' => $sequence++,
                ]);
            });

        Schema::table('young_members', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_year')->nullable(false)->change();
            $table->unsignedInteger('number_seq')->nullable(false)->change();
            $table->unique('number_seq');
            $table->dropColumn('member_number');
        });
    }

    public function down(): void
    {
        Schema::table('young_members', function (Blueprint $table) {
            $table->string('member_number', 40)->nullable()->after('id');
        });

        DB::statement("UPDATE young_members SET member_number = CONCAT('PCG/ECM/', number_year, '/', LPAD(number_seq, 6, '0'))");

        Schema::table('young_members', function (Blueprint $table) {
            $table->unique('member_number');
            $table->dropUnique(['number_seq']);
            $table->dropColumn(['number_year', 'number_seq']);
        });
    }
};
