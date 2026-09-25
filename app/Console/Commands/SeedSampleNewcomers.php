<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Newcomer;
use App\Models\NewcomerCounsellor;
use App\Models\NewcomerLesson;
use App\Models\NewcomerLessonProgress;
use App\Models\NewcomerOption;
use App\Models\NewcomerStageChange;
use App\Models\NewcomerVisit;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Fills the Newcomers pages with believable sample people at every stage, with visits, history, counsellors,
 * lessons, class progress and GPS locations, so the lists, filters and person pages can be tried out. Every generated row is
 * flagged `is_sample`, so --purge removes exactly those. Sample phone numbers all start 02000, which is not in use.
 */
class SeedSampleNewcomers extends Command
{
    protected $signature = 'newcomers:sample
        {--count=36 : People to create}
        {--purge : Delete the sample people, counsellors and lessons instead (real records are never touched)}';

    protected $description = 'Create (or remove) sample visitors, newcomers and catechumens';

    private const MALE = ['Kwame', 'Kofi', 'Kwabena', 'Yaw', 'Kwaku', 'Kwesi', 'Nii', 'Daniel', 'Samuel', 'Emmanuel', 'Joshua', 'Michael', 'David', 'Isaac', 'Nathaniel', 'Ebenezer', 'Prince', 'Jeremiah', 'Kojo', 'Mawuli'];

    private const FEMALE = ['Ama', 'Akosua', 'Abena', 'Yaa', 'Afia', 'Esi', 'Adwoa', 'Grace', 'Gifty', 'Esther', 'Mercy', 'Priscilla', 'Abigail', 'Naomi', 'Rebecca', 'Joyce', 'Deborah', 'Comfort', 'Naa', 'Efua'];

    private const SURNAMES = ['Mensah', 'Owusu', 'Boateng', 'Asante', 'Appiah', 'Agyeman', 'Ofori', 'Quaye', 'Tetteh', 'Ankrah', 'Addo', 'Amoah', 'Osei', 'Darko', 'Acheampong', 'Nartey', 'Lamptey', 'Sackey', 'Amankwah', 'Adjei', 'Kotey', 'Annan'];

    private const AREAS = ['Mamprobi', 'Korle-Gonno', 'Chorkor', 'Kaneshie', 'Dansoman', 'Odorkor', 'Lartebiokorshie', 'Abossey Okai', 'Jamestown', 'Ussher Town', 'Kokomlemle', 'Odawna'];

    private const CHURCHES = ['Presbyterian Church of Ghana, Ussher Fort', 'Methodist Church Ghana, Mamprobi', 'Catholic Church, Chorkor', 'Church of Pentecost, Korle-Gonno', 'Anglican Church, Jamestown', 'Assemblies of God, Kaneshie'];

    private const INACTIVE_REASONS = ['Moved away', 'Lost contact', 'Joined another church', 'Not interested'];

    private const LESSONS = [
        ['Who is God?', 'The nature and character of God, and how He makes Himself known.'],
        ['The Bible: God\'s Word', 'How we got the Bible, its two Testaments, and how to read it.'],
        ['Jesus Christ, Lord and Saviour', 'His life, death and resurrection, and what they mean for us.'],
        ['The Holy Spirit', 'Who the Spirit is and how He works in the believer and the church.'],
        ['Repentance and Faith', 'Turning to God and trusting in Christ.'],
        ['Baptism', 'What baptism means, and who is baptised.'],
        ['The Lord\'s Supper', 'Holy Communion: its meaning, and preparing to receive it.'],
        ['Prayer and Worship', 'Praying, and worshipping together as the family of God.'],
        ['The Presbyterian Church of Ghana', 'Its history, beliefs and way of worship.'],
        ['How the Church Is Governed', 'The Session, the District Church Council, Presbytery and Synod.'],
        ['Stewardship', 'Giving our time, talents and money to God.'],
        ['Christian Living and Service', 'Living the faith at home and at work, and serving through the church\'s groups.'],
    ];

    public function handle(): int
    {
        if ($this->option('purge')) {
            $people = Newcomer::where('is_sample', true)->delete(); // visits and history go with them
            NewcomerCounsellor::where('is_sample', true)->delete();
            NewcomerLesson::where('is_sample', true)->delete();

            $this->info("Removed {$people} sample people, with their sample counsellors and lessons.");

            return self::SUCCESS;
        }

        $count = max(1, (int) $this->option('count'));
        $lists = NewcomerOption::lists();

        DB::transaction(function () use ($count, $lists) {
            $this->lessons();
            $counsellors = $this->counsellors();

            if ($counsellors->isEmpty()) {
                $this->warn('No active adult members with a phone were found to act as counsellors, so everyone is a visitor.');
            }

            foreach ($this->plan($count, $counsellors->isNotEmpty()) as [$stage, $status]) {
                $this->person($stage, $status, $lists, $counsellors);
            }
        });

        $this->info("Created {$count} sample people.");
        $this->line('Remove them with: php artisan newcomers:sample --purge');

        return self::SUCCESS;
    }

    /** The class's lessons, only when none exist yet. */
    private function lessons(): void
    {
        if (NewcomerLesson::exists()) {
            return;
        }

        foreach (self::LESSONS as $i => [$title, $description]) {
            NewcomerLesson::create(['title' => $title, 'description' => $description, 'sort_order' => $i + 1, 'is_sample' => true]);
        }
    }

    /** Four active adult members with a phone, made counsellors; members who already counsel are left as they are. */
    private function counsellors()
    {
        $existing = NewcomerCounsellor::where('is_active', true)->pluck('id');

        if ($existing->count() >= 3) {
            return NewcomerCounsellor::whereIn('id', $existing)->get();
        }

        $taken = NewcomerCounsellor::pluck('member_id');

        $created = Member::query()
            ->where('status', 'active')->whereNotNull('mobile')->where('mobile', '<>', '')
            ->where('date_of_birth', '<=', today()->subYears(30))
            ->whereNotIn('id', $taken)
            ->inRandomOrder()->limit(4)->get()
            ->map(fn (Member $m) => NewcomerCounsellor::create(['member_id' => $m->id, 'is_sample' => true]));

        return NewcomerCounsellor::whereIn('id', $existing->merge($created->pluck('id')))->get();
    }

    /** @return list<array{string, string}> stage and status of each person to create, mixed up */
    private function plan(int $count, bool $hasCounsellors): array
    {
        if (! $hasCounsellors) {
            return array_fill(0, $count, ['visitor', 'active']);
        }

        $plan = [];

        for ($i = 0; $i < $count; $i++) {
            $roll = random_int(1, 100);
            $plan[] = match (true) {
                $roll <= 30 => ['visitor', 'active'],
                $roll <= 34 => ['visitor', 'inactive'],
                $roll <= 62 => ['newcomer', 'active'],
                $roll <= 67 => ['newcomer', 'on_hold'],
                $roll <= 71 => ['newcomer', 'inactive'],
                default => ['catechumen', 'active'],
            };
        }

        return $plan;
    }

    private function person(string $stage, string $status, array $lists, $counsellors): void
    {
        $female = (bool) random_int(0, 1);
        $minor = random_int(1, 100) <= 10;
        $first = Arr::random($female ? self::FEMALE : self::MALE);
        $surname = Arr::random(self::SURNAMES);
        $dob = $minor ? today()->subYears(random_int(9, 17))->subDays(random_int(0, 300)) : today()->subYears(random_int(19, 68))->subDays(random_int(0, 300));

        // How long ago they first came depends on how far they have got.
        $first_visit = match ($stage) {
            'visitor' => today()->subDays(random_int(0, 40)),
            'newcomer' => today()->subDays(random_int(35, 120)),
            default => today()->subDays(random_int(100, 210)),
        };
        $first_visit = $this->onSunday($first_visit);

        $married = ! $minor && random_int(1, 100) <= 40;
        $marital = $minor ? 'single' : ($married ? 'married' : Arr::random(['single', 'single', 'single', 'divorced', 'widowed']));
        $christian = random_int(1, 100) <= 85;
        $baptized = $christian && random_int(1, 100) <= 60;
        $source = Arr::random($lists['source'] ?: ['Friend']);
        $known = in_array($source, ['Friend', 'Family', 'Church member'], true);
        $mobile = '02000'.random_int(10000, 99999);
        $home = Arr::random(self::AREAS);

        $newcomer = Newcomer::create([
            'stage' => $stage,
            'status' => $status,
            'inactive_reason' => $status === 'inactive' ? Arr::random(self::INACTIVE_REASONS) : null,
            'first_visit_on' => $first_visit,
            'first_service' => Arr::random($lists['service'] ?: ['Morning']),
            'purpose' => Arr::random($lists['purpose'] ?: ['Visitation']),
            'heard_via' => $source,
            'heard_contact' => $known ? Arr::random(array_merge(self::MALE, self::FEMALE)).' '.Arr::random(self::SURNAMES).', 02000'.random_int(10000, 99999) : null,
            'title' => $minor ? null : ($female ? ($married ? 'Mrs.' : 'Miss') : (random_int(1, 100) <= 8 ? 'Dr.' : 'Mr.')),
            'surname' => $surname,
            'first_name' => $first,
            'middle_name' => random_int(1, 100) <= 35 ? Arr::random($female ? self::FEMALE : self::MALE) : null,
            'sex' => $female ? 'female' : 'male',
            'date_of_birth' => $dob,
            'mobile' => $mobile,
            'whatsapp' => random_int(1, 100) <= 60 ? $mobile : null,
            'other_numbers' => null,
            'residential_address' => 'House No. '.random_int(1, 240).', '.$home.', Accra',
            'postal_address' => random_int(1, 100) <= 30 ? 'P. O. Box '.random_int(10, 900).', Accra' : null,
            'emergency_number' => '02000'.random_int(10000, 99999),
            'email' => random_int(1, 100) <= 50 ? strtolower($first.'.'.$surname).random_int(1, 99).'@example.com' : null,
            'latitude' => random_int(1, 100) <= 70 ? round(5.5359 + random_int(-200, 200) / 10000, 7) : null,
            'longitude' => null,
            'current_status' => $minor ? 'Student' : Arr::random($lists['current_status'] ?: ['Worker']),
            'marital_status' => $marital,
            'marriage_type' => $married ? Arr::random(['ordinance', 'customary']) : null,
            'religious_background' => $christian ? 'christian' : Arr::random(['non_christian', 'other']),
            'religious_other' => null,
            'former_church' => $christian ? Arr::random(self::CHURCHES) : null,
            'is_baptized' => $baptized,
            'is_confirmed' => $baptized && random_int(1, 100) <= 50,
            'guardian_name' => $minor ? Arr::random(self::FEMALE).' '.$surname : null,
            'guardian_relationship' => $minor ? Arr::random(['Mother', 'Father', 'Grandmother', 'Aunt']) : null,
            'guardian_phone' => $minor ? '02000'.random_int(10000, 99999) : null,
            'counsellor_id' => $stage === 'visitor' ? null : $counsellors->random()->id,
            'remarks' => random_int(1, 100) <= 25 ? Arr::random(['Came with family.', 'Asked about the choir.', 'Interested in the Bible study group.', 'Lives close to the church.']) : null,
            'is_sample' => true,
        ]);

        // A location goes with a point, so the longitude follows the latitude.
        if ($newcomer->latitude !== null) {
            $newcomer->update([
                'longitude' => round(-0.2400 + random_int(-200, 200) / 10000, 7),
                'location_accuracy' => random_int(5, 40),
            ]);
        }

        $this->history($newcomer, $stage, $status, $first_visit);
        $this->visits($newcomer, $stage, $first_visit);

        if ($stage === 'catechumen') {
            $this->progress($newcomer, $first_visit);
        }
    }

    /** Some lessons done a week apart, one under way, the rest not started; now and then one skipped. */
    private function progress(Newcomer $n, CarbonInterface $first_visit): void
    {
        $lessons = NewcomerLesson::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $done = random_int(0, $lessons->count());
        $skipped = $lessons->count() > 4 && random_int(1, 100) <= 15 ? random_int(0, $lessons->count() - 1) : null;

        foreach ($lessons as $i => $lesson) {
            $completed_on = $first_visit->copy()->addDays(42 + $i * 7);
            $status = $i === $skipped ? 'skipped' : match (true) {
                $i < $done => 'completed',
                $i === $done && random_int(1, 100) <= 60 => 'in_progress',
                default => 'not_started',
            };

            NewcomerLessonProgress::create([
                'newcomer_id' => $n->id,
                'lesson_id' => $lesson->id,
                'status' => $status,
                'completed_on' => $status === 'completed' ? ($completed_on->greaterThan(today()) ? today() : $completed_on) : null,
                'note' => $status === 'skipped' ? 'Took this lesson at their former church.' : null,
            ]);
        }
    }

    /** Registered, then counsellor assigned, then into the class, each some weeks after the last; then any status change. */
    private function history(Newcomer $n, string $stage, string $status, CarbonInterface $first_visit): void
    {
        $line = fn (string $kind, ?string $from, string $to, CarbonInterface $on, ?string $note) => NewcomerStageChange::create([
            'newcomer_id' => $n->id, 'kind' => $kind, 'from_value' => $from, 'to_value' => $to,
            'changed_on' => $on->greaterThan(today()) ? today() : $on, 'note' => $note,
        ]);

        $line('stage', null, 'visitor', $first_visit, 'Registered');
        $last = $first_visit;

        if ($stage !== 'visitor') {
            $last = $first_visit->copy()->addDays(random_int(7, 28));
            $line('stage', 'visitor', 'newcomer', $last, 'Counsellor assigned');
        }

        if ($stage === 'catechumen') {
            $last = $last->copy()->addDays(random_int(21, 56));
            $line('stage', 'newcomer', 'catechumen', $last, 'Enrolled in the class');
        }

        if ($status !== 'active') {
            $line('status', 'active', $status, $last->copy()->addDays(random_int(7, 30)), $status === 'inactive' ? $n->inactive_reason : null);
        }
    }

    /** A visit most Sundays after the first, more of them the further they have got, and none after they went inactive. */
    private function visits(Newcomer $n, string $stage, CarbonInterface $first_visit): void
    {
        $max = match ($stage) {
            'visitor' => random_int(1, 2),
            'newcomer' => random_int(3, 8),
            default => random_int(8, 16),
        };

        if ($n->status === 'inactive') {
            $max = min($max, random_int(1, 3));
        }

        $on = $first_visit->copy();

        for ($i = 0; $i < $max && $on->lessThanOrEqualTo(today()); $i++) {
            NewcomerVisit::create([
                'newcomer_id' => $n->id,
                'visited_on' => $on->toDateString(),
                'service' => Arr::random(['Morning', 'Morning', 'Afternoon']),
                'note' => $i === 0 ? 'First visit' : null,
            ]);

            // Now and then a Sunday is missed.
            $on = $on->copy()->addWeeks(random_int(1, 10) <= 8 ? 1 : 2);
        }
    }

    private function onSunday(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->previous('Sunday');
    }
}
