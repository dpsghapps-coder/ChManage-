<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A member's father and mother can be linked to their own member records (as the spouse already is), a marriage
 * records its date and the church it took place in, and a baptism or confirmation records the presbytery and district
 * it took place in. The sacrament's existing `place` column holds the congregation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->unsignedInteger('father_member_id')->nullable()->after('father_name')->comment('Set when the father is also a member');
            $table->unsignedInteger('mother_member_id')->nullable()->after('mother_name')->comment('Set when the mother is also a member');
            $table->date('marriage_date')->nullable()->after('marriage_type');
            $table->string('marriage_church', 150)->nullable()->after('marriage_date')->comment('Church where the marriage took place');

            $table->foreign('father_member_id')->references('id')->on('members')->nullOnDelete();
            $table->foreign('mother_member_id')->references('id')->on('members')->nullOnDelete();
        });

        Schema::table('member_sacraments', function (Blueprint $table) {
            $table->string('presbytery', 150)->nullable()->after('sacrament_date');
            $table->string('district', 150)->nullable()->after('presbytery');
        });
    }

    public function down(): void
    {
        Schema::table('member_sacraments', function (Blueprint $table) {
            $table->dropColumn(['presbytery', 'district']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['father_member_id']);
            $table->dropForeign(['mother_member_id']);
            $table->dropColumn(['father_member_id', 'mother_member_id', 'marriage_date', 'marriage_church']);
        });
    }
};
