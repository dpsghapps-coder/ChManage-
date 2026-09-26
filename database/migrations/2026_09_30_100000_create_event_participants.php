<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who an event is for. Adding a group or a committee copies its members onto the event at that moment, so the
 * list stays as it was on the day even if the group or committee changes later. This is the list attendance will be
 * recorded against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->unsignedInteger('member_id')->nullable();
            $table->string('name', 150)->comment('A copy of the member name, or the name of someone outside the church');
            $table->string('source', 150)->nullable()->comment('How they came onto the list: a group or committee name, or empty when added one by one');
            $table->timestamps();

            $table->unique(['event_id', 'member_id']);
            $table->foreign('member_id')->references('id')->on('members')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_participants');
    }
};
