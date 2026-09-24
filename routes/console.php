<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Members move into the next generational group as they age (YPG → YAF → Men's/Women's Fellowship).
Schedule::command('members:sync-generational-groups')->dailyAt('01:00');
