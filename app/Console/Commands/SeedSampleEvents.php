<?php

namespace App\Console\Commands;

use App\Models\Committee;
use App\Models\Event;
use App\Models\Member;
use App\Models\MemberGroup;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fills the calendar with believable events (weekly services and meetings, group and committee activities, a few
 * one-off programmes) from six weeks back to twelve weeks ahead. Every row is flagged `is_sample`, so --purge
 * removes exactly those.
 */
class SeedSampleEvents extends Command
{
    protected $signature = 'events:sample
        {--purge : Delete the sample events instead (real events are never touched)}';

    protected $description = 'Create (or remove) sample events for the calendar';

    public function handle(): int
    {
        if ($this->option('purge')) {
            $this->info('Removed '.Event::where('is_sample', true)->delete().' sample events.');

            return self::SUCCESS;
        }

        $from = today()->subWeeks(6);
        $to = today()->addWeeks(12);
        $organizers = Member::query()->where('status', 'active')->whereNotNull('mobile')->where('mobile', '<>', '')
            ->where('date_of_birth', '<=', today()->subYears(25))->inRandomOrder()->limit(12)->pluck('id');
        $group = fn (string $name) => MemberGroup::where('name', $name)->value('id');
        $committee = fn (string $name) => Committee::where('name', $name)->value('id');
        $count = 0;

        $make = function (array $attributes) use (&$count, $organizers) {
            Event::create([
                'host_type' => 'church', 'scope' => 'internal', 'visibility' => 'public', 'status' => 'scheduled', 'is_sample' => true,
                'organizer_member_id' => $organizers->isNotEmpty() && random_int(1, 100) <= 60 ? $organizers->random() : null,
                ...$attributes,
                'ends_on' => $attributes['ends_on'] ?? $attributes['starts_on'],
            ]);
            $count++;
        };

        DB::transaction(function () use ($from, $to, $group, $committee, $make) {
            // Every week: Sunday worship and the midweek prayer meeting.
            foreach ($this->weekly($from, $to, CarbonInterface::SUNDAY) as $sunday) {
                $make(['title' => 'Sunday Worship Service', 'purpose' => 'Weekly worship of the congregation', 'starts_on' => $sunday, 'starts_at' => '08:00', 'ends_at' => '10:30', 'venue' => 'Main Chapel',
                    'description' => 'The main service of the week, with the sermon, offering and announcements.']);
                $make(['title' => 'Children Service', 'host_type' => 'church', 'purpose' => 'Sunday school for the children', 'starts_on' => $sunday, 'starts_at' => '08:00', 'ends_at' => '10:00', 'venue' => "Children's Chapel"]);
            }

            foreach ($this->weekly($from, $to, CarbonInterface::WEDNESDAY) as $wednesday) {
                $make(['title' => 'Midweek Prayer Meeting', 'purpose' => 'Prayer and Bible study', 'starts_on' => $wednesday, 'starts_at' => '18:30', 'ends_at' => '20:00', 'venue' => 'Main Chapel']);
            }

            // Groups: fortnightly rehearsals and a monthly parade.
            foreach ($this->weekly($from, $to, CarbonInterface::SATURDAY) as $i => $saturday) {
                if ($i % 2 === 0 && $id = $group('Church Choir')) {
                    $make(['title' => 'Church Choir Rehearsal', 'host_type' => 'group', 'member_group_id' => $id, 'purpose' => 'Preparing the Sunday anthems', 'starts_on' => $saturday, 'starts_at' => '16:00', 'ends_at' => '18:00', 'venue' => 'Main Chapel']);
                }

                if ($i % 2 === 1 && $id = $group('JY Choir')) {
                    $make(['title' => 'JY Choir Practice', 'host_type' => 'group', 'member_group_id' => $id, 'starts_on' => $saturday, 'starts_at' => '15:00', 'ends_at' => '17:00', 'venue' => 'JY Chapel']);
                }

                if ($i % 4 === 0 && $id = $group('Brigade')) {
                    $make(['title' => 'Brigade Parade', 'host_type' => 'group', 'member_group_id' => $id, 'purpose' => 'Monthly parade and drill', 'starts_on' => $saturday, 'starts_at' => '07:00', 'ends_at' => '09:00', 'venue' => 'Main Compound']);
                }
            }

            // Committees: monthly meetings.
            foreach ([['Committee on Finance', 'Finance Committee Meeting', 2], ['Harvest Committee', 'Harvest Planning Meeting', 3], ['Committee on Welfare', 'Welfare Committee Meeting', 4]] as [$name, $title, $week]) {
                if (! $id = $committee($name)) {
                    continue;
                }

                for ($date = $from->copy()->startOfMonth(); $date <= $to; $date = $date->addMonth()) {
                    $day = $date->copy()->startOfMonth()->addWeeks($week - 1)->next(CarbonInterface::THURSDAY);

                    if ($day->betweenIncluded($from, $to)) {
                        $make(['title' => $title, 'host_type' => 'committee', 'committee_id' => $id, 'visibility' => 'private', 'starts_on' => $day->toDateString(), 'starts_at' => '18:00', 'ends_at' => '19:30', 'venue' => 'Main Chapel']);
                    }
                }
            }

            // One-off programmes.
            $make(['title' => 'Harvest Thanksgiving', 'purpose' => 'Annual harvest and thanksgiving', 'starts_on' => today()->addWeeks(7)->next(CarbonInterface::SUNDAY)->toDateString(), 'is_all_day' => true, 'venue' => 'Main Compound',
                'description' => 'Thanksgiving service followed by the harvest auction on the compound.', 'notes' => 'Harvest committee to confirm the guest speaker.']);
            $make(['title' => 'Youth Retreat', 'host_type' => 'group', 'member_group_id' => $group('Youth Choir'), 'purpose' => 'Three days of teaching, prayer and fellowship for the youth', 'starts_on' => today()->addWeeks(3)->next(CarbonInterface::FRIDAY)->toDateString(),
                'ends_on' => today()->addWeeks(3)->next(CarbonInterface::FRIDAY)->addDays(2)->toDateString(), 'is_all_day' => true, 'venue' => 'Aburi Presbyterian Retreat Centre', 'visibility' => 'private']);
            $make(['title' => 'Presbytery Meeting', 'scope' => 'external', 'visibility' => 'private', 'purpose' => 'Half-yearly meeting of the presbytery', 'starts_on' => today()->addWeeks(2)->next(CarbonInterface::TUESDAY)->toDateString(), 'starts_at' => '09:00', 'ends_at' => '16:00', 'venue' => 'Presbytery Office, Accra', 'organizer_member_id' => null, 'organizer_name' => 'The Presbytery Clerk']);
            $make(['title' => 'District Church Choirs Festival', 'scope' => 'external', 'host_type' => 'group', 'member_group_id' => $group('Church Choir'), 'starts_on' => today()->addWeeks(5)->next(CarbonInterface::SATURDAY)->toDateString(), 'starts_at' => '14:00', 'ends_at' => '19:00', 'venue' => 'District Church Hall']);
            $make(['title' => 'Volleyball Tournament', 'host_type' => 'group', 'member_group_id' => $group('Brigade'), 'purpose' => 'Friendly tournament between the church societies', 'starts_on' => today()->addWeeks(4)->next(CarbonInterface::SATURDAY)->toDateString(), 'starts_at' => '09:00', 'ends_at' => '15:00', 'venue' => 'Volley Ball Court']);
            $make(['title' => "Children's Day", 'purpose' => 'A day of games, stories and prayer for the children', 'starts_on' => today()->subWeeks(3)->next(CarbonInterface::SATURDAY)->toDateString(), 'starts_at' => '10:00', 'ends_at' => '14:00', 'venue' => "Children's Chapel"]);
            $make(['title' => 'Pastor’s Open House', 'visibility' => 'private', 'purpose' => 'A visit to the manse for the new members', 'starts_on' => today()->addWeeks(1)->next(CarbonInterface::MONDAY)->toDateString(), 'starts_at' => '17:00', 'ends_at' => '19:00', 'venue' => 'Manse']);
            $make(['title' => 'Church Clean-up Day', 'status' => 'cancelled', 'purpose' => 'Cleaning the chapel and the compound', 'starts_on' => today()->addWeeks(1)->next(CarbonInterface::SATURDAY)->toDateString(), 'starts_at' => '06:30', 'ends_at' => '09:30', 'venue' => 'Main Compound',
                'notes' => 'Cancelled because of the rain forecast; to be rescheduled.']);
            $make(['title' => 'Easter Convention', 'purpose' => 'Annual convention with a guest preacher', 'starts_on' => today()->addWeeks(9)->next(CarbonInterface::THURSDAY)->toDateString(), 'ends_on' => today()->addWeeks(9)->next(CarbonInterface::THURSDAY)->addDays(3)->toDateString(), 'starts_at' => '18:00', 'venue' => 'Main Chapel']);
        });

        $this->info("Created {$count} sample events.");
        $this->line('Remove them with: php artisan events:sample --purge');

        return self::SUCCESS;
    }

    /** @return list<string> every date from `$from` to `$to` that falls on the given day of the week */
    private function weekly(CarbonInterface $from, CarbonInterface $to, int $dayOfWeek): array
    {
        $dates = [];

        for ($date = $from->copy()->next($dayOfWeek); $date <= $to; $date = $date->addWeek()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }
}
