<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A child can have several guardians (mother, father, aunt ...). A guardian who is a church member is linked by
 * `member_id`, and their name and phone are read from the member record; `name` / `phone` are then only a copy taken
 * at registration, kept as a fallback if the member is later removed. This replaces the father/mother columns on
 * `young_members`: those are turned into guardian rows first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('young_member_guardians', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('young_member_id');
            $table->string('relationship', 30);
            $table->string('relationship_other', 100)->nullable()->comment('Describes the relationship when it is "other"');
            $table->unsignedInteger('member_id')->nullable()->comment('Set when the guardian is a church member');
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->boolean('is_primary')->default(false)->comment('The guardian to contact first');
            $table->timestamps();

            $table->index('young_member_id');
            $table->foreign('young_member_id')->references('id')->on('young_members')->cascadeOnDelete();
            $table->foreign('member_id')->references('id')->on('members')->nullOnDelete();
        });

        // Turn the existing father / mother details into guardians. The mother is the primary contact when present.
        foreach (['mother', 'father'] as $relationship) {
            DB::statement(<<<SQL
                INSERT INTO young_member_guardians
                    (young_member_id, relationship, member_id, name, is_primary, created_at, updated_at)
                SELECT y.id, '{$relationship}', y.{$relationship}_member_id, y.{$relationship}_name,
                       {$this->primaryRule($relationship)}, NOW(), NOW()
                FROM young_members y
                WHERE y.{$relationship}_name IS NOT NULL AND y.{$relationship}_name <> ''
            SQL);
        }

        Schema::table('young_members', function (Blueprint $table) {
            $table->dropForeign(['father_member_id']);
            $table->dropForeign(['mother_member_id']);
            $table->dropColumn(['father_member_id', 'father_name', 'mother_member_id', 'mother_name']);
        });
    }

    public function down(): void
    {
        Schema::table('young_members', function (Blueprint $table) {
            $table->unsignedInteger('father_member_id')->nullable()->after('joined_on');
            $table->string('father_name', 150)->nullable()->after('father_member_id');
            $table->unsignedInteger('mother_member_id')->nullable()->after('father_name');
            $table->string('mother_name', 150)->nullable()->after('mother_member_id');
            $table->foreign('father_member_id')->references('id')->on('members')->nullOnDelete();
            $table->foreign('mother_member_id')->references('id')->on('members')->nullOnDelete();
        });

        foreach (['mother', 'father'] as $relationship) {
            DB::statement("UPDATE young_members y JOIN young_member_guardians g ON g.young_member_id = y.id AND g.relationship = '{$relationship}'
                SET y.{$relationship}_name = g.name, y.{$relationship}_member_id = g.member_id");
        }

        Schema::dropIfExists('young_member_guardians');
    }

    /** SQL for is_primary: the mother, or the father when there is no mother. */
    private function primaryRule(string $relationship): string
    {
        return $relationship === 'mother'
            ? '1'
            : "IF(y.mother_name IS NULL OR y.mother_name = '', 1, 0)";
    }
};
