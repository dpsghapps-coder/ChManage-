<?php

namespace App\Console\Commands;

use App\Models\Committee;
use App\Models\Meeting;
use App\Models\MeetingAction;
use App\Models\MeetingDecision;
use App\Models\Member;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Fills the Meetings pages with believable committee meetings: agendas, attendees, minutes (some confirmed at the
 * next meeting), decisions and resolutions, and actions in every status, some of them overdue. Every meeting is
 * flagged `is_sample`, so --purge removes exactly those, and their attendees, decisions and actions with them.
 */
class SeedSampleMeetings extends Command
{
    protected $signature = 'meetings:sample
        {--purge : Delete the sample meetings instead (real meetings are never touched)}';

    protected $description = 'Create (or remove) sample committee meetings, decisions and actions';

    private const COMMITTEES = [
        'Committee on Finance', 'Committee on Welfare', 'Harvest Committee', 'Physical Development / Building and Property',
        'Church Life and Nurture (CLAN)', 'Committee on Education',
    ];

    private const DECISIONS = [
        ['decision', 'The committee agreed to go ahead with the repairs to the chapel roof.', ['Get three quotations from roofing contractors', 'Report the cheapest quotation to the next meeting']],
        ['resolution', 'RESOLVED that the quarterly budget be approved as presented.', ['Send the approved budget to the Session']],
        ['decision', 'It was agreed to hold a fundraising dinner in the coming quarter.', ['Fix a date and book the venue', 'Draw up the guest list and invitations']],
        ['decision', 'The welfare visits to the sick and the elderly will be shared among the members.', ['Prepare the visiting roster']],
        ['resolution', 'RESOLVED that the church bank account signatories be reviewed by the end of the year.', ['Collect the specimen signatures']],
        ['decision', 'A training day for the group leaders will be organised.', ['Invite a facilitator', 'Circulate the programme to the leaders']],
        ['decision', 'The committee noted the report and asked for it to be tabled again next time.', []],
        ['decision', 'The list of needy members for the harvest support was approved.', ['Buy and pack the harvest hampers', 'Deliver the hampers before the harvest Sunday']],
    ];

    private const AGENDA = "1. Opening prayer\n2. Apologies and attendance\n3. Reading and confirming the minutes of the last meeting\n4. Matters arising\n5. Reports\n6. Any other business\n7. Closing prayer";

    public function handle(): int
    {
        if ($this->option('purge')) {
            $this->info('Removed '.Meeting::where('is_sample', true)->delete().' sample meetings, with their attendees, decisions and actions.');

            return self::SUCCESS;
        }

        $pool = Member::query()->where('status', 'active')->whereNotNull('mobile')->where('mobile', '<>', '')
            ->where('date_of_birth', '<=', today()->subYears(25))->inRandomOrder()->limit(60)->get(['id', 'full_name']);

        if ($pool->count() < 8) {
            $this->warn('Fewer than 8 active adult members with a phone were found, so no meetings were created.');

            return self::FAILURE;
        }

        $count = 0;

        DB::transaction(function () use ($pool, &$count) {
            foreach (Committee::whereIn('name', self::COMMITTEES)->get() as $committee) {
                $members = $pool->shuffle()->take(9);
                $previous = null;

                // Four held meetings, a month apart, then one coming up.
                foreach ([-16, -12, -8, -4, 3] as $weeks) {
                    $date = today()->addWeeks($weeks)->next(CarbonInterface::THURSDAY);
                    $held = $date->lt(today());
                    $meeting = Meeting::create([
                        'committee_id' => $committee->id,
                        'meeting_date' => $date->toDateString(),
                        'starts_at' => '18:00', 'ends_at' => '19:45',
                        'venue' => Arr::random(['Main Chapel', 'JY Chapel', 'Manse']),
                        'status' => $held ? 'held' : 'scheduled',
                        'chairperson_member_id' => $members[0]->id,
                        'secretary_member_id' => $members[1]->id,
                        'agenda' => self::AGENDA,
                        'is_sample' => true,
                    ]);
                    $count++;

                    if (! $held) {
                        continue;
                    }

                    foreach ($members as $member) {
                        $meeting->attendees()->create(['member_id' => $member->id, 'name' => $member->full_name, 'attendance' => match (true) {
                            ($roll = random_int(1, 100)) <= 75 => 'present',
                            $roll <= 90 => 'apologies',
                            default => 'absent',
                        }]);
                    }

                    $this->decisions($meeting, $members);

                    // Minutes are written after the meeting, and the last meeting's are adopted at this one.
                    $meeting->update(['minutes' => $this->minutes($committee->name, $date), 'minutes_status' => 'draft']);

                    if ($previous) {
                        $previous->update(['minutes_status' => 'confirmed', 'minutes_confirmed_on' => $date->toDateString(), 'minutes_confirmed_at_meeting_id' => $meeting->id]);
                    }

                    $previous = $meeting;
                }
            }
        });

        $this->info("Created {$count} sample meetings.");
        $this->line('Remove them with: php artisan meetings:sample --purge');

        return self::SUCCESS;
    }

    /** Two to four decisions, each with its actions: the older the meeting, the more are done. */
    private function decisions(Meeting $meeting, $members): void
    {
        foreach (Arr::random(self::DECISIONS, random_int(2, 4)) as $i => [$kind, $text, $actions]) {
            $decision = MeetingDecision::create(['meeting_id' => $meeting->id, 'kind' => $kind, 'text' => $text, 'sort_order' => $i + 1]);

            foreach ($actions as $description) {
                $deadline = $meeting->meeting_date->copy()->addDays(random_int(10, 45));
                $late = $deadline->lt(today());
                $status = match (true) {
                    $late && ($roll = random_int(1, 100)) <= 55 => 'completed',
                    $late && $roll <= 80 => 'in_progress',
                    $late && $roll <= 95 => 'pending',
                    $late => 'cancelled',
                    random_int(1, 100) <= 30 => 'in_progress',
                    default => 'pending',
                };

                MeetingAction::create([
                    'decision_id' => $decision->id,
                    'description' => $description,
                    'responsible_member_id' => random_int(1, 100) <= 90 ? $members->random()->id : null,
                    'responsible_name' => null,
                    'deadline' => random_int(1, 100) <= 92 ? $deadline->toDateString() : null,
                    'status' => $status,
                    'completed_on' => $status === 'completed' ? min($deadline, today())->subDays(random_int(0, 6))->toDateString() : null,
                    'note' => $status === 'in_progress' ? Arr::random(['Started; waiting on the second quotation.', 'Half done.', 'Follow-up call made.']) : null,
                ]);
            }
        }
    }

    private function minutes(string $committee, CarbonInterface $date): string
    {
        return "MINUTES OF THE MEETING OF THE {$committee} HELD ON {$date->format('j F Y')}\n\n"
            .'The meeting opened with prayer. The minutes of the last meeting were read, corrected and adopted. '
            ."Matters arising were dealt with. The reports were received and the decisions taken are recorded on this page.\n\n"
            .'There being no other business, the meeting closed with the Grace.';
    }
}
