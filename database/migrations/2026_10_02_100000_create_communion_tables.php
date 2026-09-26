<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Communion: the services at which it is held (with who received it), and the notes of speaking to members before it.
 * A speaking note belongs to a communion service. Its text is stored encrypted, and only people with the Speaking
 * permission can read it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communion_services', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->date('held_on');
            $table->string('venue', 150)->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->text('note')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->timestamps();

            $table->index('held_on');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('communion_attendees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('communion_service_id');
            $table->unsignedInteger('member_id');
            $table->string('status', 20)->default('absent');
            $table->timestamps();

            $table->unique(['communion_service_id', 'member_id']);
            $table->foreign('communion_service_id')->references('id')->on('communion_services')->cascadeOnDelete();
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
        });

        Schema::create('speaking_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('member_id');
            $table->unsignedBigInteger('communion_service_id')->nullable();
            $table->date('spoken_on');
            $table->unsignedInteger('spoken_by_member_id')->nullable();
            $table->string('spoken_by_name', 150)->nullable();
            $table->string('outcome', 20)->default('cleared');
            $table->longText('notes')->comment('Encrypted');
            $table->unsignedInteger('created_by')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->timestamps();

            $table->index(['member_id', 'spoken_on']);
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
            $table->foreign('communion_service_id')->references('id')->on('communion_services')->nullOnDelete();
            $table->foreign('spoken_by_member_id')->references('id')->on('members')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speaking_notes');
        Schema::dropIfExists('communion_attendees');
        Schema::dropIfExists('communion_services');
    }
};
