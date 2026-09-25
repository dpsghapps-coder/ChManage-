<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Visitors, newcomers and catechumens: one record per person that moves Visitor → Newcomer → Catechumen → Member,
 * modelled on the "First Time Worshippers / New Comers" form. The legacy `visitors` table (one 2017 row) is retired.
 */
return new class extends Migration
{
    /** The editable lists on the form; the values are stored as text on the newcomer, like the member form's ChoiceOrOther fields. */
    private const OPTIONS = [
        'title' => ['Dr.', 'Mr.', 'Mrs.', 'Miss'],
        'current_status' => ['Worker', 'Student', 'Apprentice', 'Unemployed', 'Pensioner', 'Self Employed'],
        'purpose' => ['Visitation', 'Temporal Membership', 'Permanent Membership'],
        'service' => ['Morning', 'Afternoon'],
        'source' => ['Friend', 'Family', 'Church member', 'Social media', 'Flyer or poster', 'Passing by', 'Radio or TV', 'Church event'],
        'former_church' => [],
    ];

    public function up(): void
    {
        Schema::create('newcomer_options', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30);
            $table->string('name', 150);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unique(['kind', 'name']);
        });

        foreach (self::OPTIONS as $kind => $names) {
            foreach ($names as $i => $name) {
                DB::table('newcomer_options')->insert(['kind' => $kind, 'name' => $name, 'sort_order' => $i + 1]);
            }
        }

        // A counsellor is always a member of the church.
        Schema::create('newcomer_counsellors', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('member_id')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
        });

        // The class's lessons, in teaching order.
        Schema::create('newcomer_lessons', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200)->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('newcomers', function (Blueprint $table) {
            $table->id();
            $table->string('stage', 20)->default('visitor')->index();
            $table->string('status', 20)->default('active')->index();
            $table->string('inactive_reason', 200)->nullable();

            // The visit
            $table->date('first_visit_on');
            $table->string('first_service', 100)->nullable();
            $table->string('purpose', 100)->nullable();
            $table->string('heard_via', 150)->nullable();
            $table->string('heard_contact', 200)->nullable();

            // Basic information
            $table->string('title', 50)->nullable();
            $table->string('surname', 100);
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('sex', 10)->nullable();
            $table->date('date_of_birth')->nullable();

            // Contact
            $table->string('mobile', 30)->nullable();
            $table->string('other_numbers', 100)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('residential_address', 250)->nullable();
            $table->string('postal_address', 250)->nullable();
            $table->string('emergency_number', 30)->nullable();
            $table->string('email', 150)->nullable();

            // Other information
            $table->string('current_status', 100)->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->string('marriage_type', 20)->nullable();
            $table->string('religious_background', 20)->nullable();
            $table->string('religious_other', 150)->nullable();
            $table->string('former_church', 200)->nullable();
            $table->boolean('is_baptized')->default(false);
            $table->boolean('is_confirmed')->default(false);

            // Only asked when the person is under 18
            $table->string('guardian_name', 200)->nullable();
            $table->string('guardian_relationship', 100)->nullable();
            $table->string('guardian_phone', 30)->nullable();

            // Official use
            $table->foreignId('counsellor_id')->nullable()->constrained('newcomer_counsellors')->nullOnDelete();
            $table->text('remarks')->nullable();

            // Set when they are made a member
            $table->unsignedInteger('member_id')->nullable();
            $table->date('made_member_on')->nullable();

            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['surname', 'first_name']);
            $table->index('mobile');
        });

        // A visit each time they come, the first one included.
        Schema::create('newcomer_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newcomer_id')->constrained('newcomers')->cascadeOnDelete();
            $table->date('visited_on');
            $table->string('service', 100)->nullable();
            $table->string('note', 250)->nullable();
            $table->unsignedInteger('recorded_by')->nullable();
            $table->timestamps();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();
        });

        // Every move between stages, and every change of status.
        Schema::create('newcomer_stage_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newcomer_id')->constrained('newcomers')->cascadeOnDelete();
            $table->string('kind', 10)->default('stage');
            $table->string('from_value', 50)->nullable();
            $table->string('to_value', 50);
            $table->date('changed_on');
            $table->string('note', 250)->nullable();
            $table->unsignedInteger('changed_by')->nullable();
            $table->timestamps();
            $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::dropIfExists('visitors');
    }

    public function down(): void
    {
        Schema::dropIfExists('newcomer_stage_changes');
        Schema::dropIfExists('newcomer_visits');
        Schema::dropIfExists('newcomers');
        Schema::dropIfExists('newcomer_lessons');
        Schema::dropIfExists('newcomer_counsellors');
        Schema::dropIfExists('newcomer_options');
    }
};
