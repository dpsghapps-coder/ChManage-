<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A GPS fix for the main register, the same as young_members has. Photos already come through members.photo_id. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('photo_id');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedSmallInteger('location_accuracy')->nullable()->after('longitude')->comment('GPS accuracy in metres');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'location_accuracy']);
        });
    }
};
