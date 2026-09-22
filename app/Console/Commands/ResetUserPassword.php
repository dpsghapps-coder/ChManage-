<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Bootstrap / recovery: the migrated users have no password, so someone must be able to get the very first
 * administrator in from the command line. The password is printed once and must be changed at first sign-in.
 */
class ResetUserPassword extends Command
{
    protected $signature = 'user:reset-password {username : The username to issue a temporary password for}';

    protected $description = 'Issue a one-time temporary password for a user (they must change it at next sign-in)';

    public function handle(): int
    {
        $user = User::where('username', $this->argument('username'))->first();

        if (! $user) {
            $this->error("No user with username \"{$this->argument('username')}\".");

            return self::FAILURE;
        }

        $temporary = Str::password(12, symbols: false);

        $user->forceFill(['password' => $temporary, 'must_reset_password' => true, 'is_active' => true])->save();

        Audit::record('user.password_reset', "Temporary password issued for {$user->username} from the command line", $user, [], null);

        $this->info("Temporary password for {$user->username}: {$temporary}");
        $this->line('It works once: the user must choose a new password when they sign in.');

        return self::SUCCESS;
    }
}
