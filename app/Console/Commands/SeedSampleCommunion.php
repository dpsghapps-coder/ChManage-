<?php

namespace App\Console\Commands;

use App\Models\CommunionService;
use App\Models\Member;
use App\Models\SpeakingNote;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Fills Communion with believable past services (with an attendance sheet) and one coming up, plus a handful of
 * Speaking notes so the encrypted note view and the Speaking permission can be tried out. Every communion service
 * and speaking note is flagged `is_sample`; their attendees go with the service (cascade delete), so --purge on
 * the services is enough to remove them all.
 */
class SeedSampleCommunion extends Command
{
    protected $signature = 'communion:sample
        {--purge : Delete the sample services and speaking notes instead (real ones are never touched)}';

    protected $description = 'Create (or remove) sample communion services, attendance and speaking notes';

    private const NOTES = [
        'Spoke with them before communion; nothing outstanding, cleared to receive.',
        'Long absence discussed; they have resumed attending and are settled. Cleared.',
        'A family matter was raised. Advised to see the pastor for counselling; deferred for now.',
        'New to the congregation; still being followed up before being cleared.',
        'Confirmed they are in good standing with no outstanding discipline matter.',
    ];

    public function handle(): int
    {
        if ($this->option('purge')) {
            $services = CommunionService::where('is_sample', true)->delete();
            $notes = SpeakingNote::where('is_sample', true)->delete();
            $this->info("Removed {$services} sample communion services (with their attendance) and {$notes} sample speaking notes.");

            return self::SUCCESS;
        }

        $pool = Member::query()->where('status', 'active')->inRandomOrder()->limit(300)->get(['id', 'full_name']);

        if ($pool->count() < 20) {
            $this->warn('Fewer than 20 active members were found, so nothing was created.');

            return self::FAILURE;
        }

        $admin = User::query()->orderBy('id')->value('id');
        $elders = $pool->random(min(6, $pool->count()));
        $count = 0;

        DB::transaction(function () use ($pool, $elders, $admin, &$count) {
            // Four held services, six weeks apart, then one scheduled ahead.
            foreach ([-24, -18, -12, -6] as $weeks) {
                $service = CommunionService::create([
                    'title' => 'Holy Communion',
                    'held_on' => today()->addWeeks($weeks)->toDateString(),
                    'venue' => 'Main Chapel',
                    'status' => 'held',
                    'created_by' => $admin,
                    'is_sample' => true,
                ]);
                $count++;

                $attendees = $pool->shuffle()->take(random_int(60, min(150, $pool->count())));
                foreach ($attendees as $member) {
                    $service->attendees()->create(['member_id' => $member->id, 'status' => match (true) {
                        ($roll = random_int(1, 100)) <= 82 => 'present',
                        $roll <= 93 => 'absent',
                        default => 'excused',
                    }]);
                }

                // A couple of speaking notes ahead of each held service.
                foreach ($pool->shuffle()->take(random_int(1, 3)) as $member) {
                    $outcome = match (($roll = random_int(1, 100)) <= 70) {
                        true => 'cleared',
                        default => $roll <= 90 ? 'follow_up' : 'deferred',
                    };

                    SpeakingNote::create([
                        'member_id' => $member->id,
                        'communion_service_id' => $service->id,
                        'spoken_on' => $service->held_on->copy()->subDays(random_int(1, 5)),
                        'spoken_by_member_id' => $elders->isNotEmpty() && random_int(1, 100) <= 70 ? $elders->random()->id : null,
                        'spoken_by_name' => $elders->isEmpty() ? 'The Presiding Elder' : null,
                        'outcome' => $outcome,
                        'notes' => Arr::random(self::NOTES),
                        'created_by' => $admin,
                        'is_sample' => true,
                    ]);
                    $count++;
                }
            }

            CommunionService::create([
                'title' => 'Holy Communion',
                'held_on' => today()->addWeeks(2)->toDateString(),
                'venue' => 'Main Chapel',
                'status' => 'scheduled',
                'created_by' => $admin,
                'is_sample' => true,
            ]);
            $count++;
        });

        $this->info("Created {$count} sample communion services and speaking notes.");
        $this->line('Remove them with: php artisan communion:sample --purge');

        return self::SUCCESS;
    }
}
