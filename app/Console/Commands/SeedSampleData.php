<?php

namespace App\Console\Commands;

use App\Models\CommitteeMember;
use App\Models\CommunionService;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Member;
use App\Models\MemberRequest;
use App\Models\MemberServiceRecord;
use App\Models\Meeting;
use App\Models\Newcomer;
use App\Models\YoungMember;
use Illuminate\Console\Command;

/**
 * Runs every `*:sample` generator in one go, so a fresh install (or a testing instance) can be populated with a
 * believable congregation and everything built on it in a single step. Each step is skipped if that area already
 * has sample data, so running this more than once never doubles up. --purge removes everything it created, members
 * last since committees, meetings, events and the rest refer to them.
 */
class SeedSampleData extends Command
{
    protected $signature = 'sample:seed {--purge : Remove all sample data instead (real data is never touched)}';

    protected $description = 'Populate (or clear) the whole app with sample data for testing';

    public function handle(): int
    {
        if ($this->option('purge')) {
            foreach (['requests', 'communion', 'event-participants', 'service-records', 'newcomers', 'young-members', 'meetings', 'events', 'committees', 'members'] as $command) {
                $this->call("{$command}:sample", ['--purge' => true]);
            }

            $this->info('All sample data removed.');

            return self::SUCCESS;
        }

        $this->step('members', fn () => Member::where('is_sample', true)->count(), 'members:sample', ['--count' => 150]);
        $this->step('committee members', fn () => CommitteeMember::where('is_sample', true)->count(), 'committees:sample');
        $this->step('meetings', fn () => Meeting::where('is_sample', true)->count(), 'meetings:sample');
        $this->step('events', fn () => Event::where('is_sample', true)->count(), 'events:sample');
        $this->step('event participants', fn () => EventParticipant::where('is_sample', true)->count(), 'event-participants:sample');
        $this->step('newcomers', fn () => Newcomer::where('is_sample', true)->count(), 'newcomers:sample');
        $this->step('service records', fn () => MemberServiceRecord::where('is_sample', true)->count(), 'service-records:sample');
        $this->step('young members', fn () => YoungMember::where('is_sample', true)->count(), 'young-members:sample');
        $this->step('communion', fn () => CommunionService::where('is_sample', true)->count(), 'communion:sample');
        $this->step('requests', fn () => MemberRequest::where('is_sample', true)->count(), 'requests:sample');

        $this->info('Sample data is ready.');

        return self::SUCCESS;
    }

    /** @param  array<string, mixed>  $arguments */
    private function step(string $label, \Closure $already, string $command, array $arguments = []): void
    {
        if ($already() > 0) {
            $this->line("Skipped {$label}: sample data already exists.");

            return;
        }

        $this->call($command, $arguments);
    }
}
