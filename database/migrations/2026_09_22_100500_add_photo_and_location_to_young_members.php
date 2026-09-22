<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A photo (file kept in storage) and a GPS fix taken at registration. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('young_members', function (Blueprint $table) {
            $table->string('photo_path', 120)->nullable()->after('telephone')->comment('File name inside the young-member photos folder');
            $table->decimal('latitude', 10, 7)->nullable()->after('photo_path');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedSmallInteger('location_accuracy')->nullable()->after('longitude')->comment('GPS accuracy in metres');
        });
    }

    public function down(): void
    {
        Schema::table('young_members', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'latitude', 'longitude', 'location_accuracy']);
        });
    }
};
