<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function ids(array $names): array
    {
        return Permission::whereIn('name', $names)->pluck('id')->all();
    }

    public function test_an_administrator_builds_a_role_by_combining_permissions(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post('/admin/roles', [
            'name' => 'Treasurer Assistant',
            'description' => 'Records giving',
            'permissions' => $this->ids(['contributions.view', 'contributions.create', 'members.view']),
        ])->assertRedirect('/admin/roles');

        $role = Role::where('name', 'Treasurer Assistant')->firstOrFail();
        $this->assertSame('treasurer_assistant', $role->slug);
        $this->assertEqualsCanonicalizing(
            ['contributions.view', 'contributions.create', 'members.view'],
            $role->permissions()->pluck('name')->all(),
        );
        $this->assertDatabaseHas('audit_logs', ['event' => 'role.created', 'subject_id' => $role->id]);
    }

    public function test_role_names_must_be_unique(): void
    {
        $admin = $this->adminUser();
        $this->roleWith([], 'Duplicate');
        $existing = Role::first()->name;

        $this->actingAs($admin)->post('/admin/roles', ['name' => $existing, 'permissions' => []])
            ->assertSessionHasErrors('name');
    }

    public function test_a_non_administrator_can_only_grant_permissions_they_hold(): void
    {
        $manager = $this->userWith(['roles.manage', 'members.view']);

        $this->actingAs($manager)->post('/admin/roles', [
            'name' => 'Sneaky',
            'permissions' => $this->ids(['members.view', 'income.delete', 'users.edit']),
        ])->assertRedirect();

        $role = Role::where('name', 'Sneaky')->firstOrFail();
        $this->assertSame(['members.view'], $role->permissions()->pluck('name')->all());
    }

    public function test_a_non_administrator_cannot_strip_permissions_they_do_not_hold(): void
    {
        $manager = $this->userWith(['roles.manage', 'members.view']);
        $target = $this->roleWith(['income.delete', 'members.view'], 'Finance');

        // Submitting an empty list would remove both, but income.delete is not theirs to remove.
        $this->actingAs($manager)->put("/admin/roles/{$target->id}", ['name' => $target->name, 'permissions' => []])
            ->assertRedirect();

        $this->assertSame(['income.delete'], $target->permissions()->pluck('name')->all());
    }

    public function test_permission_changes_are_audited_with_what_was_added_and_removed(): void
    {
        $admin = $this->adminUser();
        $role = $this->roleWith(['members.view', 'members.edit']);

        $this->actingAs($admin)->put("/admin/roles/{$role->id}", [
            'name' => $role->name,
            'permissions' => $this->ids(['members.view', 'income.view']),
        ])->assertRedirect();

        $log = AuditLog::where('event', 'role.permissions_changed')->firstOrFail();
        $this->assertSame(['income.view'], $log->properties['added']);
        $this->assertSame(['members.edit'], $log->properties['removed']);
    }

    public function test_the_administrator_role_cannot_be_renamed_stripped_or_deleted(): void
    {
        $admin = $this->adminUser();
        $role = $admin->role;

        $this->actingAs($admin)->put("/admin/roles/{$role->id}", [
            'name' => 'Something Else', 'description' => 'edited', 'permissions' => [],
        ])->assertRedirect();

        $role->refresh();
        $this->assertSame('Administrator', $role->name);
        $this->assertSame('edited', $role->description);

        $this->actingAs($admin)->delete("/admin/roles/{$role->id}")->assertSessionHasErrors('role');
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_a_role_that_is_in_use_cannot_be_deleted(): void
    {
        $admin = $this->adminUser();
        $role = $this->roleWith(['members.view']);
        User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($admin)->delete("/admin/roles/{$role->id}")->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_an_unused_role_can_be_deleted(): void
    {
        $admin = $this->adminUser();
        $role = $this->roleWith(['members.view']);

        $this->actingAs($admin)->delete("/admin/roles/{$role->id}")->assertRedirect('/admin/roles');

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'role.deleted']);
    }

    public function test_changing_a_role_takes_effect_for_its_users_immediately(): void
    {
        $admin = $this->adminUser();
        $role = $this->roleWith(['staff.view']);
        $worker = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($worker)->get('/staff')->assertOk();

        $this->actingAs($admin)->put("/admin/roles/{$role->id}", ['name' => $role->name, 'permissions' => []])->assertRedirect();

        $this->actingAs($worker->fresh())->get('/staff')->assertForbidden();
    }
}
