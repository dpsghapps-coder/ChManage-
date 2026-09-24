<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The positions a member can hold in an executive, the session or a committee (offered on the member form's Service
 * step and edited on the Service Positions page), and the church's full list of service groups. Groups already on
 * file are matched by name or short name ("Bible Study & Prayer Group" is BSPG), so only missing ones are added.
 */
return new class extends Migration
{
    private const POSITIONS = [
        'executive' => [
            'President', 'Vice President', 'Secretary', 'Assistant Secretary', 'Finance Secretary', 'Treasurer',
            'Organizing Secretary', 'Organizing Secretary (M)', 'Organizing Secretary (F)', "Women's Commissioner",
            'Evangelism Secretary / Coordinator', 'Chaplain', 'Choir Mother', 'Prayer Secretary',
        ],
        'leadership' => ['Presbyter', 'Senior Presbyter', 'Session Clerk', 'Protocol Officer', 'Church Treasurer'],
        'committee' => ['Committee Chairperson', 'Committee Secretary', 'Committee Member'],
    ];

    private const GROUPS = [
        ['BSPG', 'BSPG'], ['Singing Band', null], ['Church Choir', null], ['Youth Choir', null], ['JY Choir', null],
        ['Singing Group', null], ['Brigade', null], ['Prayer Team/Tower', null], ['Ushering Team', null],
        ['Bible Study Leaders', null], ['Other', null],
    ];

    public function up(): void
    {
        Schema::create('service_positions', function (Blueprint $table) {
            $table->increments('id');
            $table->enum('type', ['committee', 'executive', 'leadership'])->comment('As member_service_records.type');
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unique(['type', 'name']);
        });

        foreach (self::POSITIONS as $type => $names) {
            DB::table('service_positions')->insert(array_map(
                fn ($name, $i) => ['type' => $type, 'name' => $name, 'sort_order' => $i + 1],
                $names, array_keys($names),
            ));
        }

        // "B.S.P.G" and "BSPG" are the same group.
        $key = fn (?string $s) => preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $s));
        $existing = DB::table('member_groups')->get(['name', 'short_name'])
            ->flatMap(fn ($g) => [$key($g->name), $key($g->short_name)])->filter()->all();

        foreach (self::GROUPS as [$name, $short]) {
            if (! in_array($key($name), $existing, true)) {
                DB::table('member_groups')->insert(['name' => $name, 'short_name' => $short]);
                $existing[] = $key($name);
            }
        }
    }

    public function down(): void
    {
        // The added groups are left: members may have joined them by now.
        Schema::dropIfExists('service_positions');
    }
};
