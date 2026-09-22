<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Roles are built by combining permissions. `roles` already exists (migrated from the legacy access levels),
 * so it is extended rather than recreated; existing users keep their role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('slug', 60)->nullable()->after('name');
            $table->string('description')->nullable()->after('slug');
            $table->boolean('is_system')->default(false)->after('description');
            $table->timestamps();
        });

        // Stable machine names for the roles that already exist. "admin" is the one code special-cases.
        foreach (DB::table('roles')->get() as $role) {
            DB::table('roles')->where('id', $role->id)->update([
                'slug' => $role->name === 'Administrator' ? 'admin' : Str::slug($role->name, '_'),
                'is_system' => $role->name === 'Administrator',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->string('slug', 60)->nullable(false)->unique()->change();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100)->unique();        // module.action, e.g. members.view
            $table->string('module', 50)->index();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_permission', function (Blueprint $table) {
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('permission_id');
            $table->primary(['role_id', 'permission_id']);
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('permissions');

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'description', 'is_system', 'created_at', 'updated_at']);
        });
    }
};
