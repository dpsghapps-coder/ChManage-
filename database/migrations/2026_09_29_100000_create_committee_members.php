<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who serves on each committee, and for which term. A committee can set a term length and a maximum number of
 * terms; the list warns (it never blocks) when a term is about to end or a member is past the maximum. This list is
 * separate from the committee service records on a member's bio, which stay as each person's own history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('committees', function (Blueprint $table) {
            $table->unsignedTinyInteger('term_years')->nullable()->comment('How long a term lasts, in years');
            $table->unsignedTinyInteger('max_terms')->nullable()->comment('The most terms one person should serve');
        });

        Schema::create('committee_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('committee_id');
            $table->unsignedInteger('member_id')->nullable();
            $table->string('name', 150)->comment('A copy of the member name, or the name of someone outside the church');
            $table->string('position', 100)->nullable();
            $table->date('started_on');
            $table->date('ends_on')->nullable()->comment('The end of the term; empty when it has no fixed end');
            $table->string('end_note', 250)->nullable()->comment('Why a term ended early');
            $table->unsignedBigInteger('renewed_from_id')->nullable()->comment('The term this one follows');
            $table->boolean('is_sample')->default(false);
            $table->timestamps();

            $table->index(['committee_id', 'ends_on']);
            $table->index('member_id');
            $table->foreign('committee_id')->references('id')->on('committees')->cascadeOnDelete();
            $table->foreign('member_id')->references('id')->on('members')->nullOnDelete();
            $table->foreign('renewed_from_id')->references('id')->on('committee_members')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_members');

        Schema::table('committees', function (Blueprint $table) {
            $table->dropColumn(['term_years', 'max_terms']);
        });
    }
};
