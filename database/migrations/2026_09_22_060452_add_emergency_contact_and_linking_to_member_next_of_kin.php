<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('member_next_of_kin', function (Blueprint $table) {
            // The next of kin's own member record, if they are also a member.
            $table->unsignedInteger('related_member_id')->nullable()->after('postal_address');

            $table->string('emergency_contact_name', 150)->nullable()->after('related_member_id');
            $table->string('emergency_contact_phone', 30)->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_relationship', 100)->nullable()->after('emergency_contact_phone');
            $table->unsignedInteger('emergency_contact_member_id')->nullable()->after('emergency_contact_relationship');
        });

        Schema::table('member_next_of_kin', function (Blueprint $table) {
            $table->foreign('related_member_id')->references('id')->on('members')->nullOnDelete();
            $table->foreign('emergency_contact_member_id')->references('id')->on('members')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_next_of_kin', function (Blueprint $table) {
            $table->dropForeign(['related_member_id']);
            $table->dropForeign(['emergency_contact_member_id']);
            $table->dropColumn(['related_member_id', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship', 'emergency_contact_member_id']);
        });
    }
};
