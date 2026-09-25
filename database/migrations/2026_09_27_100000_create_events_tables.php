<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Church events and the calendar. An event has a host (the church, a service group or a committee), is internal or
 * external and public or private, and is held at a venue by date and time. The venue is stored as text (like the
 * newcomer form lists), so renaming or removing a venue never changes an event already saved.
 */
return new class extends Migration
{
    private const VENUES = ['Main Chapel', 'JY Chapel', "Children's Chapel", 'Main Compound', 'Volley Ball Court', 'Manse'];

    public function up(): void
    {
        Schema::create('event_venues', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->unsignedInteger('sort_order')->default(0);
        });

        foreach (self::VENUES as $i => $name) {
            DB::table('event_venues')->insert(['name' => $name, 'sort_order' => $i + 1]);
        }

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('purpose', 250)->nullable();

            // Who hosts it: the church as a whole, one service group, or one committee.
            $table->string('host_type', 20)->default('church');
            $table->unsignedInteger('member_group_id')->nullable();
            $table->unsignedInteger('committee_id')->nullable();

            $table->string('scope', 20)->default('internal');
            $table->string('visibility', 20)->default('public');
            $table->string('status', 20)->default('scheduled');

            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_all_day')->default(false);
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('venue', 150)->nullable();

            // A member of the church, or the name of someone outside it.
            $table->unsignedInteger('organizer_member_id')->nullable();
            $table->string('organizer_name', 150)->nullable();

            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->timestamps();

            $table->index(['starts_on', 'ends_on']);
            $table->foreign('member_group_id')->references('id')->on('member_groups')->nullOnDelete();
            $table->foreign('committee_id')->references('id')->on('committees')->nullOnDelete();
            $table->foreign('organizer_member_id')->references('id')->on('members')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
        Schema::dropIfExists('event_venues');
    }
};
