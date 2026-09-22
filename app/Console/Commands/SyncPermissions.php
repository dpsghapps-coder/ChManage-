<?php

namespace App\Console\Commands;

use App\Support\PermissionRegistry;
use Illuminate\Console\Command;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Load the permissions defined in config/permissions.php into the database';

    public function handle(): int
    {
        $result = PermissionRegistry::sync();

        $this->info(sprintf(
            'Permissions synced: %d created, %d updated, %d removed.',
            $result['created'], $result['updated'], $result['removed'],
        ));

        return self::SUCCESS;
    }
}
