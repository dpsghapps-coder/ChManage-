<?php

namespace App\Console\Commands;

use App\Models\Committee;
use App\Models\CommitteeMember;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Member;
use App\Models\MemberGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Gives a sample of events a participant list, the same three ways the Event page does it: a handful of people
 * added one by one, a whole service group, or a whole committee as it stood on the day. Every row is flagged
 * `is_sample`, so --purge removes exactly those and leaves the events themselves alone.
 */
class SeedSampleEventParticipants extends Command
{
    protected $signature = 'event-participants:sample
        {--purge : Delete the sample event participants instead (real ones are never touched)}';

    protected $description = 'Create (or remove) sample participant lists for events';

    public function handle(): int
    {
        if ($this->option('purge')) {
            $this->info('Removed '.EventParticipant::where('is_sample', true)->delete().' sample event participants.');

            return self::SUCCESS;
        }

        $events = Event::query()->where('status', 'scheduled')->inRandomOrder()->limit(40)->get();

        if ($events->isEmpty()) {
            $this->warn('No events were found. Run events:sample first.');

            return self::FAILURE;
        }

        $pool = Member::query()->where('status', 'active')->inRandomOrder()->limit(200)->get(['id', 'full_name']);

        if ($pool->count() < 10) {
            $this->warn('Fewer than 10 active members were found, so nothing was created.');

            return self::FAILURE;
        }

        $groups = MemberGroup::pluck('id', 'name');
        $committees = Committee::pluck('id', 'name');
        $count = 0;

        DB::transaction(function () use ($events, $pool, $groups, $committees, &$count) {
            foreach ($events as $event) {
                $roll = random_int(1, 100);

                $count += match (true) {
                    $roll <= 40 => $this->individuals($event, $pool),
                    $roll <= 70 && $groups->isNotEmpty() => $this->group($event, $groups),
                    $committees->isNotEmpty() => $this->committee($event, $committees),
                    default => $this->individuals($event, $pool),
                };
            }
        });

        $this->info("Added {$count} sample event participants.");
        $this->line('Remove them with: php artisan event-participants:sample --purge');

        return self::SUCCESS;
    }

    private function individuals(Event $event, $pool): int
    {
        $added = 0;

        foreach ($pool->shuffle()->take(random_int(5, 15)) as $member) {
            $event->participants()->create(['member_id' => $member->id, 'name' => $member->full_name, 'is_sample' => true]);
            $added++;
        }

        return $added;
    }

    private function group(Event $event, $groups): int
    {
        $name = $groups->keys()->random();
        $id = $groups[$name];

        $members = Member::query()->where('status', 'active')
            ->whereIn('id', fn ($q) => $q->select('member_id')->from('member_group_memberships')->where('member_group_id', $id))
            ->get(['id', 'full_name']);

        if ($members->isEmpty()) {
            return 0;
        }

        foreach ($members as $member) {
            $event->participants()->create(['member_id' => $member->id, 'name' => $member->full_name, 'source' => $name, 'is_sample' => true]);
        }

        return $members->count();
    }

    private function committee(Event $event, $committees): int
    {
        $name = $committees->keys()->random();
        $id = $committees[$name];
        $day = $event->starts_on->toDateString();

        $terms = CommitteeMember::query()->where('committee_id', $id)->where('started_on', '<=', $day)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $day))
            ->get(['member_id', 'name']);

        if ($terms->isEmpty()) {
            return 0;
        }

        $seen = [];
        $added = 0;

        foreach ($terms as $term) {
            if ($term->member_id && isset($seen[$term->member_id])) {
                continue;
            }

            $event->participants()->create(['member_id' => $term->member_id, 'name' => $term->name, 'source' => $name, 'is_sample' => true]);
            $term->member_id && $seen[$term->member_id] = true;
            $added++;
        }

        return $added;
    }
}
