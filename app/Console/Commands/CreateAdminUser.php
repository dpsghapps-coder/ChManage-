<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Bootstrap: on a fresh database (schema + seeded roles, but no imported users) there is no admin
 * account for `user:reset-password` to issue a password for. This creates the very first one.
 */
class CreateAdminUser extends Command
{
    protected $signature = 'user:create-admin {username : Login username for the new administrator} {email? : Optional email address}';

    protected $description = 'Create the first administrator account on a fresh database, with a one-time temporary password';

    public function handle(): int
    {
        $username = $this->argument('username');
        $email = $this->argument('email');

        $validator = validator(
            ['username' => $username, 'email' => $email],
            ['username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')], 'email' => ['nullable', 'email', 'max:255']]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $role = Role::where('slug', Role::ADMIN)->first();

        if (! $role) {
            $this->error('No "admin" role found — run the RolesAndPermissionsSeeder first.');

            return self::FAILURE;
        }

        $temporary = Str::password(12, symbols: false);

        $user = User::create([
            'username' => $username,
            'email' => $email,
            'role_id' => $role->id,
            'password' => $temporary,
            'is_active' => true,
            'must_reset_password' => true,
        ]);

        Audit::record('user.created', "Administrator {$user->username} created from the command line", $user, [], null);

        $this->info("Administrator \"{$username}\" created.");
        $this->line("Temporary password: {$temporary}");
        $this->line('It works once: the user must choose a new password when they sign in.');

        return self::SUCCESS;
    }
}
