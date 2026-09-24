<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** How the next of kin is related to the member (the emergency contact already had this). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_next_of_kin', function (Blueprint $table) {
            $table->string('relationship', 100)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('member_next_of_kin', function (Blueprint $table) {
            $table->dropColumn('relationship');
        });
    }
};
