<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A photo and a GPS location for each person (as on young members), and a flag that marks generated sample rows. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newcomers', function (Blueprint $table) {
            $table->string('photo_path', 120)->nullable()->after('email')->comment('File name inside the newcomer photos folder');
            $table->decimal('latitude', 10, 7)->nullable()->after('photo_path');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedSmallInteger('location_accuracy')->nullable()->after('longitude')->comment('GPS accuracy in metres');
            $table->boolean('is_sample')->default(false)->after('created_by');
        });

        foreach (['newcomer_counsellors', 'newcomer_lessons'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->boolean('is_sample')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach (['newcomer_counsellors', 'newcomer_lessons'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('is_sample'));
        }

        Schema::table('newcomers', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'latitude', 'longitude', 'location_accuracy', 'is_sample']);
        });
    }
};
