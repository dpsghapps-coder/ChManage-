<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionRegistry;
use Illuminate\Database\Seeder;

/**
 * Safe to run repeatedly: syncs the permission list, creates the seeded roles that are missing, and gives
 * default permissions only to roles that have none - so changes made in the UI are never overwritten.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        PermissionRegistry::sync();

        // A fresh install has no roles at all; the administrator role must always exist.
        Role::firstOrCreate(['slug' => Role::ADMIN], [
            'name' => 'Administrator',
            'description' => 'Full access to everything. Cannot be edited or deleted.',
            'is_system' => true,
        ]);

        foreach (config('permissions.new_roles') as $slug => [$name, $description]) {
            Role::firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => $description]);
        }

        $ids = Permission::pluck('id', 'name');

        foreach (config('permissions.default_roles') as $slug => $patterns) {
            $role = Role::where('slug', $slug)->first();

            if (! $role || $role->permissions()->exists()) {
                continue;
            }

            $role->permissions()->sync(
                collect(PermissionRegistry::expand($patterns))->map(fn ($name) => $ids[$name])->all(),
            );
        }
    }
}
