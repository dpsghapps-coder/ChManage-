<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pcg_ecm already has a `users` table (created by db/01_schema.sql), so instead of the starter kit's
 * "create users" migration we adapt it for Laravel authentication.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('password_hash', 'password');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->rememberToken()->after('password');
            $table->timestamp('password_changed_at')->nullable()->after('must_reset_password');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_verified_at', 'remember_token', 'password_changed_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('password', 'password_hash');
        });
    }
};
