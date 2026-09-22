<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\YoungMember;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fills Children Service and Junior Youth with believable sample children so the tabs, classes, guardians and
 * maps can be tried out. Every generated row is flagged `is_sample`, so --purge removes exactly those.
 */
class SeedSampleYoungMembers extends Command
{
    protected $signature = 'young-members:sample
        {--count=40 : Children to create for each of Children Service and Junior Youth}
        {--purge : Delete the sample children instead (real registrations are never touched)}';

    protected $description = 'Create (or remove) sample Children Service and Junior Youth records';

    private const MALE = ['Kwame', 'Kofi', 'Kwabena', 'Yaw', 'Kwaku', 'Kwesi', 'Nana', 'Daniel', 'Samuel', 'Emmanuel', 'Joshua', 'Michael', 'David', 'Isaac', 'Nathaniel', 'Ebenezer', 'Prince', 'Jeremiah'];

    private const FEMALE = ['Ama', 'Akosua', 'Abena', 'Yaa', 'Afia', 'Esi', 'Adwoa', 'Grace', 'Gifty', 'Esther', 'Mercy', 'Priscilla', 'Abigail', 'Naomi', 'Rebecca', 'Joyce', 'Deborah', 'Comfort'];

    private const OTHER = ['Nhyira', 'Nyame', 'Papa', 'Owusua', 'Kojo', 'Yeboah', 'Manu', 'Sarpong', 'Serwaa', 'Twumasi', 'Aidoo'];

    private const SURNAMES = ['Mensah', 'Owusu', 'Boateng', 'Asante', 'Appiah', 'Agyeman', 'Ofori', 'Quaye', 'Tetteh', 'Ankrah', 'Addo', 'Amoah', 'Osei', 'Darko', 'Acheampong', 'Nartey', 'Lamptey', 'Sackey'];

    private const PREFIXES = ['024', '054', '055', '020', '050', '027', '057', '026'];

    public function handle(): int
    {
        if ($this->option('purge')) {
            $removed = YoungMember::where('is_sample', true)->delete(); // guardians go with them

            $this->info("Removed {$removed} sample children.");

            return self::SUCCESS;
        }

        $perDepartment = max(1, (int) $this->option('count'));
        $mothers = $this->adults('female');
        $fathers = $this->adults('male');

        DB::transaction(function () use ($perDepartment, $mothers, $fathers) {
            foreach ([['CS', 0, YoungMember::CHILDREN_UNDER - 1], ['JY', YoungMember::CHILDREN_UNDER, YoungMember::REGISTER_UNDER - 1]] as [$department, $youngest, $oldest]) {
                for ($i = 0; $i < $perDepartment; $i++) {
                    $this->child(random_int($youngest, $oldest), $mothers, $fathers);
                }
            }
        });

        $this->info('Created '.($perDepartment * 2)." sample children ({$perDepartment} Children Service, {$perDepartment} Junior Youth).");
        $this->line('Remove them with: php artisan young-members:sample --purge');

        return self::SUCCESS;
    }

    /** Active adult members with a phone, to act as real guardians. */
    private function adults(string $sex): Collection
    {
        return Member::query()
            ->where('status', 'active')
            ->where('sex', $sex)
            ->whereNotNull('mobile')->where('mobile', '<>', '')
            ->where('date_of_birth', '<=', today()->subYears(25))
            ->inRandomOrder()->limit(150)
            ->get();
    }

    private function child(int $age, Collection $mothers, Collection $fathers): void
    {
        $mother = $mothers->isNotEmpty() && random_int(1, 100) <= 70 ? $mothers->random() : null;
        $father = $fathers->isNotEmpty() && random_int(1, 100) <= 40 ? $fathers->random() : null;

        // A child usually carries a parent's surname.
        $surname = $this->surnameOf($father) ?? $this->surnameOf($mother) ?? $this->pick(self::SURNAMES);
        $male = random_int(0, 1) === 1;

        $born = today()->subYears($age)->subDays(random_int(0, 364));
        $joined = random_int(1, 100) <= 85 ? $born->copy()->addDays(random_int(0, max(0, $born->diffInDays(today())))) : null;
        $ownPhone = random_int(1, 100) <= ($age >= YoungMember::CHILDREN_UNDER ? 60 : 10);
        $located = random_int(1, 100) <= 75;

        $status = match (true) {
            ($roll = random_int(1, 100)) <= 92 => 'active',
            $roll <= 97 => 'transferred',
            default => 'invalid',
        };

        $young = YoungMember::create([
            'number_year' => now()->year,
            'number_seq' => YoungMember::nextSequence(),
            'first_name' => $this->pick($male ? self::MALE : self::FEMALE),
            'last_name' => $surname,
            'other_names' => random_int(1, 100) <= 50 ? $this->pick(self::OTHER) : null,
            'date_of_birth' => $born->toDateString(),
            'joined_on' => $joined?->toDateString(),
            'mobile' => $ownPhone ? $this->phone() : null,
            'telephone' => $ownPhone && random_int(1, 100) <= 15 ? $this->phone() : null,
            'latitude' => $located ? round(5.55 + random_int(0, 17000) / 100000, 7) : null,
            'longitude' => $located ? round(-0.30 + random_int(0, 22000) / 100000, 7) : null,
            'location_accuracy' => $located ? random_int(5, 60) : null,
            'status' => $status,
            'is_sample' => true,
        ]);

        // Mother is always there and is the primary contact; a father and one relative are optional.
        $this->guardian($young, 'mother', $mother, "{$this->pick(self::FEMALE)} {$surname}", true);

        if ($father || random_int(1, 100) <= 30) {
            $this->guardian($young, 'father', $father, "{$this->pick(self::MALE)} {$surname}", false);
        }

        if (random_int(1, 100) <= 20) {
            $relative = $this->pick(['aunt', 'uncle', 'grandmother', 'grandfather']);
            $name = $this->pick(in_array($relative, ['aunt', 'grandmother'], true) ? self::FEMALE : self::MALE);
            $this->guardian($young, $relative, null, "{$name} {$this->pick(self::SURNAMES)}", false);
        }
    }

    private function guardian(YoungMember $young, string $relationship, ?Member $member, string $typedName, bool $primary): void
    {
        $young->guardians()->create([
            'relationship' => $relationship,
            'member_id' => $member?->id,
            'name' => $member?->full_name ?? $typedName,
            'phone' => $member ? ($member->phoneNumbers()[0] ?? null) : $this->phone(),
            'is_primary' => $primary,
        ]);
    }

    /** The surname of a register entry, which is written "Surname Firstname Othernames". */
    private function surnameOf(?Member $member): ?string
    {
        $first = $member ? strtok(trim($member->full_name), ' ') : null;

        return $first ? ucwords(strtolower($first)) : null;
    }

    private function phone(): string
    {
        return $this->pick(self::PREFIXES).str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
    }

    private function pick(array $items): string
    {
        return $items[array_rand($items)];
    }
}
