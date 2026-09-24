<?php

namespace App\Console\Commands;

use App\Models\Member;
use Illuminate\Console\Command;

class SyncGenerationalGroups extends Command
{
    protected $signature = 'members:sync-generational-groups';

    protected $description = "Set each adult member's generational group from their age and sex (run daily by the scheduler)";

    public function handle(): int
    {
        $this->info('Generational groups updated for '.Member::syncGenerationalGroups().' members.');

        return self::SUCCESS;
    }
}
