<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/staff')->assertRedirect('/login');
        $this->get('/admin/users')->assertRedirect('/login');
    }

    public function test_a_user_without_a_role_is_forbidden(): void
    {
        $user = User::factory()->create(['role_id' => null]);

        $this->actingAs($user)->get('/staff')->assertForbidden();
    }

    public function test_a_user_without_the_permission_is_forbidden(): void
    {
        $user = $this->userWith(['members.view']);

        $this->actingAs($user)->get('/staff')->assertForbidden();
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->get('/admin/roles')->assertForbidden();
        $this->actingAs($user)->get('/admin/audit')->assertForbidden();
    }

    public function test_a_user_with_the_permission_gets_in(): void
    {
        $user = $this->userWith(['staff.view', 'users.view', 'roles.view', 'permissions.view', 'audit.view']);

        $this->actingAs($user)->get('/staff')->assertOk();
        $this->actingAs($user)->get('/admin/users')->assertOk();
        $this->actingAs($user)->get('/admin/roles')->assertOk();
        $this->actingAs($user)->get('/admin/permissions')->assertOk();
        $this->actingAs($user)->get('/admin/audit')->assertOk();
    }

    public function test_view_permission_does_not_imply_write_access(): void
    {
        $user = $this->userWith(['staff.view', 'users.view', 'roles.view']);

        $this->actingAs($user)->get('/staff/create')->assertForbidden();
        $this->actingAs($user)->post('/staff', ['full_name' => 'X', 'status' => 'active'])->assertForbidden();
        $this->actingAs($user)->get('/admin/users/create')->assertForbidden();
        $this->actingAs($user)->get('/admin/roles/create')->assertForbidden();
    }

    public function test_administrators_pass_every_permission_check(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->get('/staff/create')->assertOk();
        $this->actingAs($admin)->get('/admin/roles/create')->assertOk();
        $this->assertTrue($admin->can('anything.at_all'));
    }

    public function test_permissions_work_as_gate_abilities(): void
    {
        $user = $this->userWith(['members.view']);

        $this->assertTrue($user->can('members.view'));
        $this->assertFalse($user->can('members.delete'));
        $this->assertTrue($user->hasAnyPermission(['members.delete', 'members.view']));
        $this->assertFalse($user->hasAnyPermission(['members.delete']));
    }

    public function test_users_sign_in_with_a_username_and_the_sign_in_is_audited(): void
    {
        $user = User::factory()->create(['username' => 'kwesi', 'email' => null]);

        $this->post('/login', ['username' => 'kwesi', 'password' => 'password'])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_a_wrong_password_is_rejected_and_audited(): void
    {
        User::factory()->create(['username' => 'kwesi']);

        $this->post('/login', ['username' => 'kwesi', 'password' => 'nope'])->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertTrue(AuditLog::where('event', 'auth.failed')->exists());
    }

    public function test_deactivated_users_cannot_sign_in(): void
    {
        User::factory()->inactive()->create(['username' => 'gone']);

        $this->post('/login', ['username' => 'gone', 'password' => 'password'])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_users_without_a_password_cannot_sign_in(): void
    {
        // Migrated accounts wait for an administrator to issue a temporary password.
        User::factory()->create(['username' => 'legacy', 'password' => null]);

        $this->post('/login', ['username' => 'legacy', 'password' => ''])->assertSessionHasErrors();
        $this->post('/login', ['username' => 'legacy', 'password' => 'password'])->assertSessionHasErrors();

        $this->assertGuest();
    }

    public function test_a_user_deactivated_while_signed_in_is_signed_out_on_the_next_request(): void
    {
        $user = $this->userWith(['staff.view']);

        $this->actingAs($user)->get('/staff')->assertOk();

        $user->update(['is_active' => false]);

        $this->get('/staff')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_a_temporary_password_must_be_replaced_before_anything_else(): void
    {
        $user = $this->userWith(['staff.view'], ['must_reset_password' => true]);

        $this->actingAs($user)->get('/staff')->assertRedirect('/account/password');
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/account/password');

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'password',
            'password' => 'A-brand-new-passw0rd',
            'password_confirmation' => 'A-brand-new-passw0rd',
        ])->assertRedirect('/dashboard');

        $fresh = $user->fresh();
        $this->assertFalse($fresh->must_reset_password);
        $this->assertNotNull($fresh->password_changed_at);
        $this->actingAs($fresh)->get('/staff')->assertOk();
    }

    public function test_the_new_password_must_differ_from_the_temporary_one(): void
    {
        $user = $this->userWith([], ['must_reset_password' => true]);

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->must_reset_password);
    }
}
