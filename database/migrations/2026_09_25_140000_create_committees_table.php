<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The church's committees, offered for committee service records and edited on the Committees page. */
return new class extends Migration
{
    private const COMMITTEES = [
        'Administration and Human Resource Management',
        'Physical Development / Building and Property',
        'Scholarship Committee',
        'Development And Social Services',
        'Committee on Ecumenical and Social Relations',
        'Committee on Mission and Evangelism',
        'Committee on Welfare',
        'Harvest Committee',
        'Committee on Finance',
        'Church Life and Nurture (CLAN)',
        'Committee on Counselling and Family Life',
        'Committee on Education',
        'School Management Committee (SMC)',
    ];

    public function up(): void
    {
        Schema::create('committees', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 150)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
        });

        DB::table('committees')->insert(array_map(
            fn ($name, $i) => ['name' => $name, 'sort_order' => $i + 1],
            self::COMMITTEES, array_keys(self::COMMITTEES),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('committees');
    }
};
