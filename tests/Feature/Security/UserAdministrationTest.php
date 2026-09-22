<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return [
            'username' => 'new.user',
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => null,
            'role_id' => $this->roleWith(['members.view'], 'Viewer')->id,
            'staff_id' => null,
            'is_active' => true,
            ...$overrides,
        ];
    }

    public function test_creating_a_user_issues_a_temporary_password_that_must_be_changed(): void
    {
        $admin = $this->userWith(['users.create']);

        $this->actingAs($admin)->post('/admin/users', $this->payload())->assertRedirect();

        $user = User::where('username', 'new.user')->firstOrFail();
        $this->assertTrue($user->must_reset_password);
        $this->assertNotNull($user->password);
        $this->assertTrue(Hash::isHashed($user->password));
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.created', 'subject_id' => $user->id]);
    }

    public function test_usernames_must_be_unique_and_well_formed(): void
    {
        $admin = $this->userWith(['users.create']);
        User::factory()->create(['username' => 'taken']);

        $this->actingAs($admin)->post('/admin/users', $this->payload(['username' => 'taken']))->assertSessionHasErrors('username');
        $this->actingAs($admin)->post('/admin/users', $this->payload(['username' => 'has spaces']))->assertSessionHasErrors('username');
    }

    public function test_only_an_administrator_can_assign_the_administrator_role(): void
    {
        $adminRole = $this->adminUser()->role;
        $clerkAdmin = $this->userWith(['users.create', 'users.edit']);

        $this->actingAs($clerkAdmin)
            ->post('/admin/users', $this->payload(['role_id' => $adminRole->id]))
            ->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['username' => 'new.user']);

        $this->actingAs($this->adminUser())
            ->post('/admin/users', $this->payload(['role_id' => $adminRole->id]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['username' => 'new.user', 'role_id' => $adminRole->id]);
    }

    public function test_a_non_administrator_cannot_edit_an_administrator(): void
    {
        $manager = $this->userWith(['users.edit']);
        $admin = $this->adminUser();

        $this->actingAs($manager)->put("/admin/users/{$admin->id}", $this->payload([
            'username' => $admin->username, 'role_id' => $manager->role_id,
        ]))->assertSessionHasErrors('role_id');

        $this->assertSame($admin->role_id, $admin->fresh()->role_id);
    }

    public function test_nobody_can_change_their_own_role_or_deactivate_themselves(): void
    {
        $admin = $this->adminUser();
        $other = $this->roleWith(['members.view']);

        $this->actingAs($admin)->put("/admin/users/{$admin->id}", $this->payload([
            'username' => $admin->username, 'role_id' => $other->id,
        ]))->assertSessionHasErrors('role_id');

        $this->actingAs($admin)->put("/admin/users/{$admin->id}", $this->payload([
            'username' => $admin->username, 'role_id' => $admin->role_id, 'is_active' => false,
        ]))->assertSessionHasErrors('is_active');

        $fresh = $admin->fresh();
        $this->assertSame($admin->role_id, $fresh->role_id);
        $this->assertTrue($fresh->is_active);
    }

    public function test_changing_a_role_and_deactivating_are_audited(): void
    {
        $admin = $this->adminUser();
        $target = User::factory()->create(['role_id' => $this->roleWith(['members.view'])->id]);
        $newRole = $this->roleWith(['income.view'], 'Income');

        $this->actingAs($admin)->put("/admin/users/{$target->id}", $this->payload([
            'username' => $target->username, 'role_id' => $newRole->id, 'is_active' => false,
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', ['event' => 'user.role_changed', 'subject_id' => $target->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.deactivated', 'subject_id' => $target->id]);
        $this->assertFalse($target->fresh()->is_active);
    }

    public function test_linking_a_staff_member_needs_its_own_permission_and_is_one_to_one(): void
    {
        $staff = Staff::create(['full_name' => 'Ama Mensah', 'status' => 'active']);
        $withoutLink = $this->userWith(['users.create']);
        $withLink = $this->userWith(['users.create', 'staff.link_user']);

        $this->actingAs($withoutLink)->post('/admin/users', $this->payload(['staff_id' => $staff->id]))
            ->assertSessionHasErrors('staff_id');
        $this->assertDatabaseMissing('users', ['username' => 'new.user']);

        $this->actingAs($withLink)->post('/admin/users', $this->payload(['staff_id' => $staff->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame($staff->id, User::where('username', 'new.user')->value('staff_id'));

        // The same staff member cannot get a second account.
        $this->actingAs($withLink)->post('/admin/users', $this->payload(['username' => 'second', 'staff_id' => $staff->id]))
            ->assertSessionHasErrors('staff_id');
    }

    public function test_resetting_a_password_issues_a_new_temporary_one(): void
    {
        $admin = $this->userWith(['users.reset_password']);
        $target = User::factory()->create(['role_id' => $this->roleWith([])->id]);
        $oldHash = $target->password;

        $this->actingAs($admin)->post("/admin/users/{$target->id}/reset-password")->assertRedirect();

        $target->refresh();
        $this->assertNotSame($oldHash, $target->password);
        $this->assertTrue($target->must_reset_password);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.password_reset', 'subject_id' => $target->id]);
    }

    public function test_only_an_administrator_can_reset_an_administrators_password(): void
    {
        $manager = $this->userWith(['users.reset_password']);
        $admin = $this->adminUser();

        $this->actingAs($manager)->post("/admin/users/{$admin->id}/reset-password")->assertForbidden();

        $this->assertFalse($admin->fresh()->must_reset_password);
    }

    public function test_password_hashes_are_never_sent_to_the_browser(): void
    {
        $viewer = $this->userWith(['users.view']);

        $response = $this->actingAs($viewer)->get('/admin/users');

        $response->assertOk();
        $this->assertStringNotContainsString($viewer->password, $response->getContent());
        $this->assertNotNull(Role::where('slug', 'clerk')->first());
    }
}
