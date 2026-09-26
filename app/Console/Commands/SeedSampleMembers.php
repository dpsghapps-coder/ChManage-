<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Profession;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Gives the register believable adult members, on a fresh install with none, so committees, meetings, events,
 * newcomers, service records and communion have someone to draw on. Every generated row is flagged `is_sample`,
 * so --purge removes exactly those. Sample mobiles all start 02000, which is not in use.
 */
class SeedSampleMembers extends Command
{
    protected $signature = 'members:sample
        {--count=150 : Members to create}
        {--purge : Delete the sample members instead (real members are never touched)}';

    protected $description = 'Create (or remove) sample adult members for the main register';

    private const MALE = ['Kwame', 'Kofi', 'Kwabena', 'Yaw', 'Kwaku', 'Kwesi', 'Nii', 'Daniel', 'Samuel', 'Emmanuel', 'Joshua', 'Michael', 'David', 'Isaac', 'Nathaniel', 'Ebenezer', 'Prince', 'Jeremiah', 'Kojo', 'Mawuli'];

    private const FEMALE = ['Ama', 'Akosua', 'Abena', 'Yaa', 'Afia', 'Esi', 'Adwoa', 'Grace', 'Gifty', 'Esther', 'Mercy', 'Priscilla', 'Abigail', 'Naomi', 'Rebecca', 'Joyce', 'Deborah', 'Comfort', 'Naa', 'Efua'];

    private const SURNAMES = ['Mensah', 'Owusu', 'Boateng', 'Asante', 'Appiah', 'Agyeman', 'Ofori', 'Quaye', 'Tetteh', 'Ankrah', 'Addo', 'Amoah', 'Osei', 'Darko', 'Acheampong', 'Nartey', 'Lamptey', 'Sackey', 'Amankwah', 'Adjei'];

    private const AREAS = ['Mamprobi', 'Korle-Gonno', 'Chorkor', 'Kaneshie', 'Dansoman', 'Odorkor', 'Lartebiokorshie', 'Abossey Okai', 'Jamestown', 'Ussher Town', 'Kokomlemle', 'Odawna'];

    private const TOWNS = ['Winneba', 'Cape Coast', 'Kumasi', 'Koforidua', 'Ho', 'Sunyani', 'Tamale', 'Tema', 'Techiman', 'Elmina'];

    public function handle(): int
    {
        if ($this->option('purge')) {
            $this->info('Removed '.Member::where('is_sample', true)->delete().' sample members.');

            return self::SUCCESS;
        }

        $count = max(1, (int) $this->option('count'));
        $professions = Profession::pluck('id');
        $created = 0;

        DB::transaction(function () use ($count, $professions, &$created) {
            for ($i = 0; $i < $count; $i++) {
                $this->member($professions);
                $created++;
            }
        });

        $this->info("Created {$created} sample members.");
        $this->line('Remove them with: php artisan members:sample --purge');

        return self::SUCCESS;
    }

    private function member($professions): void
    {
        $female = (bool) random_int(0, 1);
        $first = Arr::random($female ? self::FEMALE : self::MALE);
        $surname = Arr::random(self::SURNAMES);
        $dob = today()->subYears(random_int(19, 78))->subDays(random_int(0, 364));
        $married = random_int(1, 100) <= 55;
        $marital = $married ? 'married' : Arr::random(['single', 'single', 'single', 'divorced', 'widowed']);
        $joined = today()->subYears(random_int(0, 25))->subDays(random_int(0, 364));
        $mobile = '02000'.random_int(10000, 99999);

        Member::create([
            'member_number' => Member::nextMemberNumber(),
            'title' => $female ? ($married ? 'Mrs.' : 'Miss') : (random_int(1, 100) <= 10 ? 'Dr.' : 'Mr.'),
            'full_name' => "{$surname} {$first}",
            'first_name' => $first,
            'last_name' => $surname,
            'sex' => $female ? 'female' : 'male',
            'date_of_birth' => $dob->toDateString(),
            'marital_status' => $marital,
            'hometown' => Arr::random(self::TOWNS),
            'residence' => 'House No. '.random_int(1, 240).', '.Arr::random(self::AREAS).', Accra',
            'profession_id' => $professions->isNotEmpty() && random_int(1, 100) <= 80 ? $professions->random() : null,
            'mobile' => $mobile,
            'email' => random_int(1, 100) <= 40 ? strtolower($first.'.'.$surname).random_int(1, 99).'@example.com' : null,
            'joined_on' => $joined->toDateString(),
            'is_communicant' => random_int(1, 100) <= 85,
            'generational_group' => Member::generationalGroupFor($dob, $female ? 'female' : 'male'),
            'status' => 'active',
            'is_verified' => true,
            'is_sample' => true,
        ]);
    }
}
