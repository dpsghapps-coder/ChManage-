<?php

namespace App\Console\Commands;

use App\Models\Committee;
use App\Models\CommitteeMember;
use App\Models\Member;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Gives committees believable members and terms: most serving mid-term, some due to end soon or just ended,
 * a renewed second term, and past members. Session and the District Church Council get the church's officers.
 * Every row is flagged `is_sample`, so --purge removes exactly those, and sets the term rules it added back.
 */
class SeedSampleCommitteeMembers extends Command
{
    protected $signature = 'committees:sample
        {--purge : Delete the sample committee members instead (real ones are never touched)}';

    protected $description = 'Create (or remove) sample committee members and terms';

    private const COMMITTEES = [
        'Committee on Finance', 'Committee on Welfare', 'Harvest Committee', 'Physical Development / Building and Property',
        'Church Life and Nurture (CLAN)', 'Committee on Education', 'Session', 'District Church Council',
    ];

    private const POSITIONS = ['Committee Chairperson', 'Committee Secretary', 'Committee Member', 'Committee Member', 'Committee Member', 'Committee Member'];

    public function handle(): int
    {
        if ($this->option('purge')) {
            $this->info('Removed '.CommitteeMember::where('is_sample', true)->delete().' sample committee members.');

            return self::SUCCESS;
        }

        $pool = Member::query()->where('status', 'active')->where('date_of_birth', '<=', today()->subYears(25))->inRandomOrder()->limit(80)->get(['id', 'full_name']);

        if ($pool->count() < 8) {
            $this->warn('Fewer than 8 active adult members were found, so nothing was created.');

            return self::FAILURE;
        }

        $count = 0;

        DB::transaction(function () use ($pool, &$count) {
            foreach (Committee::whereIn('name', self::COMMITTEES)->get() as $committee) {
                // Rules only where none are set yet: three-year terms, at most two.
                $committee->update(['term_years' => $committee->term_years ?? 3, 'max_terms' => $committee->max_terms ?? 2]);
                $years = $committee->term_years;

                foreach ($pool->shuffle()->take(random_int(5, 7)) as $i => $member) {
                    // Where in its term each one is: most are mid-term, a few near or past the end.
                    $left = match (true) {
                        $i === 0 => random_int(10, 40),
                        $i === 1 => -random_int(3, 20),
                        default => random_int(90, $years * 365 - 30),
                    };
                    $ends = today()->addDays($left);
                    $start = $ends->copy()->subYears($years);
                    $position = self::POSITIONS[$i] ?? 'Committee Member';

                    // One person is on a second term, renewed from an earlier one.
                    $first = $i === 3 ? CommitteeMember::create($this->row($committee, $member, $position, $start->copy()->subYears($years), $start->copy())) : null;
                    CommitteeMember::create([...$this->row($committee, $member, $position, $start, $ends), 'renewed_from_id' => $first?->id]);
                    $count += $first ? 2 : 1;
                }

                // Someone who served a term and left.
                $gone = $pool->random();
                CommitteeMember::create($this->row($committee, $gone, 'Committee Member', today()->subYears($years + 2), today()->subYears(2)));
                $count++;
            }
        });

        $this->info("Created {$count} sample committee terms.");
        $this->line('Remove them with: php artisan committees:sample --purge (the term rules stay).');

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function row(Committee $committee, Member $member, string $position, $start, $end): array
    {
        return [
            'committee_id' => $committee->id, 'member_id' => $member->id, 'name' => $member->full_name, 'position' => Arr::first([$position]),
            'started_on' => $start->toDateString(), 'ends_on' => $end->toDateString(), 'is_sample' => true,
        ];
    }
}
