<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Junior Youth and Children Service are registered with a simplified form and kept in their own table.
 * The under-19s already on the main register are copied in (the `members` rows stay untouched, and
 * `member_id` points back at them) so they keep showing on those tabs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('young_members', function (Blueprint $table) {
            $table->increments('id');
            $table->string('member_number', 40)->unique();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable()->comment('Surname');
            $table->string('other_names', 150)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('joined_on')->nullable();
            $table->unsignedInteger('father_member_id')->nullable()->comment('Set when the father is a member');
            $table->string('father_name', 150)->nullable();
            $table->unsignedInteger('mother_member_id')->nullable()->comment('Set when the mother is a member');
            $table->string('mother_name', 150)->nullable()->comment('Typed in when the mother is not a member');
            $table->string('mobile', 30)->nullable()->comment('Contact no');
            $table->string('telephone', 30)->nullable()->comment('Contact no 2');
            $table->enum('status', ['active', 'invalid', 'transferred', 'deceased', 'deleted'])->default('active');
            $table->unsignedInteger('member_id')->nullable()->comment('The members row this was copied from, if any');
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
            $table->index('date_of_birth');
            $table->foreign('father_member_id')->references('id')->on('members')->nullOnDelete();
            $table->foreign('mother_member_id')->references('id')->on('members')->nullOnDelete();
            $table->foreign('member_id')->references('id')->on('members')->nullOnDelete();
        });

        DB::statement(<<<'SQL'
            INSERT INTO young_members
                (member_number, first_name, last_name, date_of_birth, joined_on, father_name, mother_name,
                 mobile, telephone, status, member_id, created_at, updated_at)
            SELECT member_number, first_name, COALESCE(last_name, full_name), date_of_birth, joined_on, father_name,
                   mother_name, mobile, telephone, status, id, NOW(), NOW()
            FROM members
            WHERE date_of_birth > DATE_SUB(CURDATE(), INTERVAL 19 YEAR)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('young_members');
    }
};
