<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\MemberServiceRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fills a handful of active members with believable committee/executive/leadership history, so the
 * Service tab, edit-wizard step and PDF report can be tried out. Every generated row is flagged
 * `is_sample`, so --purge removes exactly those.
 */
class SeedSampleServiceRecords extends Command
{
    protected $signature = 'service-records:sample
        {--count=15 : Members to give a service history to}
        {--purge : Delete the sample records instead (real ones are never touched)}';

    protected $description = 'Create (or remove) sample member service records (committee/executive/leadership)';

    private const COMMITTEES = ['Finance Committee', 'Welfare Committee', 'Evangelism Committee', 'Building Committee', 'Christian Education Committee', 'Music Committee', 'Youth Committee', 'Welcome Committee'];

    private const EXECUTIVES = ['Session', 'Presbytery Council', 'Congregational Executive', 'Women\'s Fellowship Executive', 'Men\'s Fellowship Executive'];

    private const LEADERSHIPS = ['Sunday School', 'Choir', 'Ushering Team', 'Prayer Ministry', 'Media Team'];

    private const POSITIONS = ['Chairperson', 'Vice Chairperson', 'Secretary', 'Assistant Secretary', 'Treasurer', 'Organiser', 'Member'];

    public function handle(): int
    {
        if ($this->option('purge')) {
            $removed = MemberServiceRecord::where('is_sample', true)->delete();

            $this->info("Removed {$removed} sample service records.");

            return self::SUCCESS;
        }

        $count = max(1, (int) $this->option('count'));

        $members = Member::query()
            ->where('status', 'active')
            ->where('date_of_birth', '<=', today()->subYears(20))
            ->inRandomOrder()->limit($count)
            ->get();

        if ($members->isEmpty()) {
            $this->error('No eligible active adult members found to attach service records to.');

            return self::FAILURE;
        }

        $created = 0;

        DB::transaction(function () use ($members, &$created) {
            foreach ($members as $member) {
                foreach (range(1, random_int(1, 2)) as $ignored) {
                    $this->record($member);
                    $created++;
                }
            }
        });

        $this->info("Created {$created} sample service records for {$members->count()} members.");
        $this->line('Remove them with: php artisan service-records:sample --purge');

        return self::SUCCESS;
    }

    private function record(Member $member): void
    {
        $type = $this->pick(array_keys(MemberServiceRecord::TYPES));
        $name = match ($type) {
            'committee' => $this->pick(self::COMMITTEES),
            'executive' => $this->pick(self::EXECUTIVES),
            'leadership' => $this->pick(self::LEADERSHIPS),
        };

        $started = today()->subYears(random_int(1, 8))->subDays(random_int(0, 364));
        $ongoing = random_int(1, 100) <= 60;

        $member->serviceRecords()->create([
            'type' => $type,
            'name' => $name,
            'position' => $this->pick(self::POSITIONS),
            'started_on' => $started->toDateString(),
            'ended_on' => $ongoing ? null : $started->copy()->addYears(random_int(1, 3))->toDateString(),
            'is_sample' => true,
        ]);
    }

    private function pick(array $items): string
    {
        return $items[array_rand($items)];
    }
}
