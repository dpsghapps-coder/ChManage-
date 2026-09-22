<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_gives_a_password_less_user_a_temporary_password_they_can_sign_in_with(): void
    {
        $user = User::factory()->create(['username' => 'jerry', 'password' => null, 'is_active' => false]);

        $this->artisan('user:reset-password', ['username' => 'jerry'])
            ->expectsOutputToContain('Temporary password for jerry:')
            ->assertSuccessful();

        $user->refresh();
        $this->assertNotNull($user->password);
        $this->assertTrue($user->must_reset_password);
        $this->assertTrue($user->is_active);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.password_reset', 'subject_id' => $user->id]);
    }

    public function test_it_fails_cleanly_for_an_unknown_username(): void
    {
        $this->artisan('user:reset-password', ['username' => 'nobody'])->assertFailed();
    }
}
