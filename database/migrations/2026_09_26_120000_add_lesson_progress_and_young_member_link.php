<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each catechumen's progress through the class, one row per lesson, and a link to the young member record when a
 * person under 18 is made a member (an adult is linked through `member_id`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newcomer_lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newcomer_id')->constrained('newcomers')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('newcomer_lessons')->cascadeOnDelete();
            $table->string('status', 20)->default('not_started');
            $table->date('completed_on')->nullable();
            $table->string('note', 250)->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['newcomer_id', 'lesson_id']);
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('newcomers', function (Blueprint $table) {
            $table->unsignedInteger('young_member_id')->nullable()->after('member_id');
            $table->foreign('young_member_id')->references('id')->on('young_members')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('newcomers', function (Blueprint $table) {
            $table->dropForeign(['young_member_id']);
            $table->dropColumn('young_member_id');
        });

        Schema::dropIfExists('newcomer_lesson_progress');
    }
};
