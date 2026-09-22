<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Member details carried over from the CHMSv2 sections: more social accounts, where the member lives, the spouse as a
 * linked member, a generational group, a reason for not being a communicant, more marriage types, and the service
 * records (committees, executives, leadership) a member has held.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('instagram_id', 150)->nullable()->after('facebook_id');
            $table->string('twitter_id', 150)->nullable()->after('instagram_id');
            $table->string('tiktok_id', 150)->nullable()->after('twitter_id');
            $table->string('residence', 150)->nullable()->after('hometown')->comment('Neighbourhood where the member lives');
            $table->unsignedInteger('spouse_member_id')->nullable()->after('spouse_name')->comment('Set when the spouse is also a member');
            $table->string('generational_group', 40)->nullable()->after('is_communicant');
            $table->text('non_communicant_reason')->nullable()->after('is_communicant');

            $table->foreign('spouse_member_id')->references('id')->on('members')->nullOnDelete();
        });

        DB::statement("ALTER TABLE members MODIFY marriage_type ENUM('ordinance','customary','islamic','traditional') NULL");

        Schema::create('member_service_records', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('member_id');
            $table->enum('type', ['committee', 'executive', 'leadership']);
            $table->string('name', 150)->comment('The committee, executive or body served on');
            $table->string('position', 150)->nullable();
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable()->comment('Empty while the service continues');

            $table->index('member_id');
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_service_records');

        DB::statement("UPDATE members SET marriage_type = NULL WHERE marriage_type IN ('islamic','traditional')");
        DB::statement("ALTER TABLE members MODIFY marriage_type ENUM('ordinance','customary') NULL");

        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['spouse_member_id']);
            $table->dropColumn(['instagram_id', 'twitter_id', 'tiktok_id', 'residence', 'spouse_member_id', 'generational_group', 'non_communicant_reason']);
        });
    }
};
