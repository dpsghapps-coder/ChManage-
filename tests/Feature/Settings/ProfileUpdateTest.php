<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_email_is_optional_but_a_first_name_is_required()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['first_name' => 'Solo', 'last_name' => '', 'email' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull($user->refresh()->email);

        $this->actingAs($user)
            ->patch(route('profile.update'), ['first_name' => '', 'last_name' => 'X', 'email' => ''])
            ->assertSessionHasErrors('first_name');
    }

    public function test_users_cannot_change_their_own_username_role_or_active_flag_through_the_profile()
    {
        $user = User::factory()->create(['username' => 'fixed', 'role_id' => null]);

        $this->actingAs($user)->patch(route('profile.update'), [
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => '',
            'username' => 'hacked',
            'role_id' => 1,
            'is_active' => false,
            'staff_id' => 1,
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('fixed', $user->username);
        $this->assertNull($user->role_id);
        $this->assertNull($user->staff_id);
        $this->assertTrue($user->is_active);
    }

    public function test_accounts_can_no_longer_be_deleted_by_their_owner()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->delete('/settings/profile', ['password' => 'password'])->assertStatus(405);

        $this->assertNotNull($user->fresh());
    }
}
