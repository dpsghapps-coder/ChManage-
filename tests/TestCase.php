<?php

namespace Tests;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /** Load the permission list and the seeded roles (call from tests that need them). */
    protected function seedRoles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** A role that has exactly the given permission names. */
    protected function roleWith(array $permissions, string $name = 'Test Role'): Role
    {
        $this->seedRoles();

        $role = Role::create(['name' => $name.' '.uniqid(), 'slug' => 'test_'.uniqid()]);
        $role->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));

        return $role;
    }

    /** A signed-in-able user whose role has exactly the given permissions. */
    protected function userWith(array $permissions, array $attributes = []): User
    {
        return User::factory()->create(['role_id' => $this->roleWith($permissions)->id, ...$attributes]);
    }

    protected function adminUser(array $attributes = []): User
    {
        $this->seedRoles();

        return User::factory()->create(['role_id' => Role::where('slug', Role::ADMIN)->value('id'), ...$attributes]);
    }
}
