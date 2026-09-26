<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\MemberRequest;
use App\Models\User;
use App\Support\RequestTypes;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Fills the member portal requests inbox with believable requests of every type and status, so the list, the
 * per-type answers, and the approve/decline flow can all be tried out. Every row is flagged `is_sample`, so
 * --purge removes exactly those.
 */
class SeedSampleRequests extends Command
{
    protected $signature = 'requests:sample
        {--count=40 : Requests to create}
        {--purge : Delete the sample requests instead (real ones are never touched)}';

    protected $description = 'Create (or remove) sample member portal requests';

    private const RESPONSES = [
        'approved' => ['Approved. Please see the church office to collect it.', 'Approved as requested.', 'Approved; the office will be in touch to confirm the date.'],
        'declined' => ['We are unable to process this at the moment; please see the office for details.', 'Declined for now; please provide more information at the office.'],
    ];

    public function handle(): int
    {
        if ($this->option('purge')) {
            $this->info('Removed '.MemberRequest::where('is_sample', true)->delete().' sample requests.');

            return self::SUCCESS;
        }

        $count = max(1, (int) $this->option('count'));
        $members = Member::query()->where('status', 'active')->inRandomOrder()->limit($count)->get(['id', 'mobile', 'telephone', 'email', 'residence', 'hometown']);

        if ($members->isEmpty()) {
            $this->warn('No active members were found, so nothing was created.');

            return self::FAILURE;
        }

        $admin = User::query()->orderBy('id')->value('id');
        $types = RequestTypes::keys();
        $created = 0;

        DB::transaction(function () use ($members, $admin, $types, &$created) {
            foreach ($members as $member) {
                $type = Arr::random($types);
                $status = $this->status();
                $handled = in_array($status, ['approved', 'declined', 'cancelled'], true);

                MemberRequest::create([
                    'member_id' => $member->id,
                    'type' => $type,
                    'status' => $status,
                    'details' => $type === RequestTypes::CHANGE_DETAILS ? null : $this->details($type),
                    'changes' => $type === RequestTypes::CHANGE_DETAILS ? $this->changes($member) : null,
                    'member_note' => random_int(1, 100) <= 30 ? 'Please treat this as urgent, thank you.' : null,
                    'response' => $handled && $status !== 'cancelled' ? Arr::random(self::RESPONSES[$status] ?? ['Noted.']) : null,
                    'handled_by' => $handled && $status !== 'cancelled' ? $admin : null,
                    'handled_at' => $handled ? now()->subDays(random_int(1, 20)) : null,
                    'is_sample' => true,
                ]);
                $created++;
            }
        });

        $this->info("Created {$created} sample requests.");
        $this->line('Remove them with: php artisan requests:sample --purge');

        return self::SUCCESS;
    }

    private function status(): string
    {
        return match (true) {
            ($roll = random_int(1, 100)) <= 25 => 'submitted',
            $roll <= 40 => 'in_review',
            $roll <= 75 => 'approved',
            $roll <= 90 => 'declined',
            default => 'cancelled',
        };
    }

    /** @return array<string, mixed> */
    private function details(string $type): array
    {
        return match ($type) {
            'transfer' => ['destination' => 'Emmanuel Congregation', 'place' => 'Tema', 'move_date' => today()->addMonths(2)->toDateString(), 'reason' => 'Relocating for work.'],
            'issue' => ['category' => 'An error in my record', 'subject' => 'Wrong date of birth', 'description' => 'My date of birth on the record is incorrect; please correct it to match my ID.'],
            'discipleship' => ['course' => 'Foundations of the Faith', 'note' => 'I would like to join the next available class.'],
            'baptism' => ['candidate' => 'My child', 'candidate_name' => 'Baby Mensah', 'candidate_dob' => today()->subMonths(4)->toDateString(), 'preferred_date' => today()->addWeeks(6)->toDateString(), 'note' => null],
            'naming' => ['child_name' => 'Nhyira Owusu', 'child_dob' => today()->subDays(10)->toDateString(), 'father_name' => 'Kofi Owusu', 'mother_name' => 'Abena Owusu', 'preferred_date' => today()->addWeeks(2)->toDateString(), 'note' => null],
            'marriage' => ['partner_name' => 'Efua Asante', 'partner_congregation' => 'Bethel Congregation', 'kind' => 'A church wedding', 'intended_date' => today()->addMonths(4)->toDateString(), 'note' => null],
            'bus' => ['event' => 'Another trip (say which below)', 'seats' => (string) random_int(1, 4), 'pickup' => 'Mamprobi Junction', 'note' => 'For the harvest thanksgiving trip.'],
            'facility' => ['facility' => 'Main Chapel', 'date' => today()->addWeeks(3)->toDateString(), 'starts_at' => '10:00', 'ends_at' => '14:00', 'purpose' => 'A family thanksgiving service', 'expected' => (string) random_int(20, 100)],
            default => [],
        };
    }

    /** @return array<string, array{from: mixed, to: mixed}> */
    private function changes(Member $member): array
    {
        $field = Arr::random(['mobile', 'email', 'residence', 'hometown']);
        $to = match ($field) {
            'mobile' => '024'.random_int(1000000, 9999999),
            'email' => 'updated'.random_int(1, 999).'@example.com',
            'residence' => 'House No. '.random_int(1, 300).', Dansoman, Accra',
            'hometown' => 'Cape Coast',
        };

        return [$field => ['from' => $member->{$field}, 'to' => $to]];
    }
}
