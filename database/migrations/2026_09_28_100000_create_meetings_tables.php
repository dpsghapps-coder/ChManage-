<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Committee meetings and what comes of them: Meeting → Decision or Resolution → Action → Person responsible →
 * Deadline → Status. A meeting belongs to a committee (Session and the like are entries in the same list),
 * has its attendees, agenda and minutes, and its decisions carry the actions that follow. "Overdue" is not stored:
 * an action that is still pending or in progress after its deadline is overdue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('committee_id');
            $table->string('title', 200)->nullable()->comment('Optional; the committee name is used when empty');
            $table->date('meeting_date');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('venue', 150)->nullable();
            $table->string('status', 20)->default('scheduled');

            // A member of the church, or the name of someone outside it.
            $table->unsignedInteger('chairperson_member_id')->nullable();
            $table->string('chairperson_name', 150)->nullable();
            $table->unsignedInteger('secretary_member_id')->nullable();
            $table->string('secretary_name', 150)->nullable();

            $table->text('agenda')->nullable();
            $table->longText('minutes')->nullable();
            $table->string('minutes_status', 20)->default('draft');
            $table->date('minutes_confirmed_on')->nullable();
            $table->unsignedBigInteger('minutes_confirmed_at_meeting_id')->nullable()->comment('The meeting that adopted these minutes');

            $table->unsignedInteger('created_by')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->timestamps();

            $table->index('meeting_date');
            $table->foreign('committee_id')->references('id')->on('committees')->cascadeOnDelete();
            $table->foreign('chairperson_member_id')->references('id')->on('members')->nullOnDelete();
            $table->foreign('secretary_member_id')->references('id')->on('members')->nullOnDelete();
            $table->foreign('minutes_confirmed_at_meeting_id')->references('id')->on('meetings')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('meeting_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->unsignedInteger('member_id')->nullable();
            $table->string('name', 150)->comment('A copy of the member name, or the name of someone outside the church');
            $table->string('attendance', 20)->default('present');
            $table->timestamps();

            $table->unique(['meeting_id', 'member_id']);
            $table->foreign('member_id')->references('id')->on('members')->nullOnDelete();
        });

        Schema::create('meeting_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->string('kind', 20)->default('decision');
            $table->text('text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('meeting_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('decision_id')->constrained('meeting_decisions')->cascadeOnDelete();
            $table->text('description');
            $table->unsignedInteger('responsible_member_id')->nullable();
            $table->string('responsible_name', 150)->nullable();
            $table->date('deadline')->nullable();
            $table->string('status', 20)->default('pending');
            $table->date('completed_on')->nullable();
            $table->string('note', 500)->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'deadline']);
            $table->foreign('responsible_member_id')->references('id')->on('members')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_actions');
        Schema::dropIfExists('meeting_decisions');
        Schema::dropIfExists('meeting_attendees');
        Schema::dropIfExists('meetings');
    }
};
