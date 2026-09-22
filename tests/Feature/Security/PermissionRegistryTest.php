<?php

namespace Tests\Feature\Security;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_creates_every_registered_permission_and_prunes_stale_ones(): void
    {
        Permission::create(['name' => 'ghost.feature', 'module' => 'ghost']);

        $result = PermissionRegistry::sync();

        $this->assertSame(count(PermissionRegistry::names()), Permission::count());
        $this->assertSame(1, $result['removed']);
        $this->assertDatabaseMissing('permissions', ['name' => 'ghost.feature']);
    }

    public function test_expand_understands_wildcards_and_drops_unknown_names(): void
    {
        $all = PermissionRegistry::names();

        $this->assertSame($all, PermissionRegistry::expand(['*']));

        $members = PermissionRegistry::expand(['members.*']);
        $this->assertContains('members.view', $members);
        $this->assertContains('members.export', $members);
        $this->assertNotContains('income.view', $members);

        $this->assertSame(['members.view'], PermissionRegistry::expand(['members.view', 'typo.nothing']));
    }

    public function test_every_default_role_pattern_refers_to_real_permissions(): void
    {
        $names = PermissionRegistry::names();

        foreach (config('permissions.default_roles') as $slug => $patterns) {
            foreach ($patterns as $pattern) {
                $this->assertNotEmpty(
                    PermissionRegistry::expand([$pattern]),
                    "Default role [{$slug}] lists [{$pattern}], which matches no permission.",
                );
            }
        }

        $this->assertNotEmpty($names);
    }

    public function test_seeder_is_repeatable_and_never_overwrites_permissions_edited_in_the_ui(): void
    {
        $this->seedRoles();

        $clerk = Role::where('slug', 'clerk')->firstOrFail();
        $this->assertTrue($clerk->permissions()->where('name', 'members.view')->exists());

        // An administrator trims the clerk role down to one permission...
        $clerk->permissions()->sync(Permission::where('name', 'visitors.view')->pluck('id'));

        // ...and a later deploy re-runs the seeder.
        $this->seedRoles();

        $this->assertSame(['visitors.view'], $clerk->permissions()->pluck('name')->all());
        $this->assertSame(1, Role::where('slug', 'admin')->count());
    }
}
