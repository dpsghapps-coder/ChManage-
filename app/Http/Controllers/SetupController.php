<?php

namespace App\Http\Controllers;

use App\Models\ChurchSetting;
use App\Models\City;
use App\Models\Committee;
use App\Models\EventVenue;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberServiceRecord;
use App\Models\NewcomerOption;
use App\Models\Profession;
use App\Models\Role;
use App\Models\ServicePosition;
use App\Models\User;
use App\Support\Audit;
use App\Support\Presbyteries;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * First-run setup, open only while there are no user accounts: the administrator, who the church is, and which of the
 * standard lists (service groups, positions, committees, occupations, venues, newcomer lists) this congregation uses.
 * The lists come from the migrations, so a fresh install starts with the standard entries ticked.
 */
class SetupController extends Controller
{
    public function show(): Response|RedirectResponse
    {
        if (User::count() > 0) {
            return to_route('login');
        }

        return Inertia::render('setup/index', [
            'presbyteries' => Presbyteries::options(),
            'cities' => City::orderBy('name')->pluck('name'),
            'defaults' => [
                'followup_days' => ChurchSetting::followUpDays(),
                'term_warning_days' => ChurchSetting::termWarningDays(),
            ],
            'lists' => [
                'groups' => MemberGroup::orderBy('id')->pluck('name'),
                'positions' => ServicePosition::byType(),
                'committees' => Committee::orderBy('sort_order')->orderBy('name')->pluck('name'),
                'occupations' => collect(Profession::grouped())->mapWithKeys(fn ($g) => [$g['category'] => collect($g['options'])->pluck('name')->all()]),
                'venues' => EventVenue::orderBy('sort_order')->orderBy('name')->pluck('name'),
                'options' => NewcomerOption::lists(),
            ],
            'labels' => [
                'positions' => MemberServiceRecord::TYPES,
                'options' => NewcomerOption::KINDS,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (User::count() > 0) {
            return to_route('login');
        }

        $name = ['string', 'max:150'];
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],

            'church_name' => ['required', ...$name],
            'presbytery' => ['required', ...$name],
            'district' => ['required', ...$name],
            'congregation' => ['required', ...$name],
            'city' => ['nullable', ...$name],
            'followup_days' => ['required', 'integer', 'between:7,365'],
            'term_warning_days' => ['required', 'integer', 'between:7,365'],

            'groups' => ['present', 'array'],
            'groups.*' => ['string', 'max:150'],
            'committees' => ['present', 'array'],
            'committees.*' => $name,
            'venues' => ['present', 'array'],
            'venues.*' => $name,
            'positions' => ['present', 'array'],
            'positions.*' => ['array'],
            'positions.*.*' => ['string', 'max:100'],
            'occupations' => ['present', 'array'],
            'occupations.*' => ['array'],
            'occupations.*.*' => ['string', 'max:150'],
            'options' => ['present', 'array'],
            'options.*' => ['array'],
            'options.*.*' => ['string', 'max:150'],
        ], [
            'username.regex' => 'Use letters, numbers, dots, dashes and underscores only.',
            'followup_days.between' => 'Choose between 7 and 365 days.',
            'term_warning_days.between' => 'Choose between 7 and 365 days.',
        ]);

        $kept = DB::transaction(function () use ($data) {
            // A fresh install has no roles until the seeder has run.
            (new RolesAndPermissionsSeeder)->run();

            $admin = User::create([
                'username' => $data['username'],
                'first_name' => $data['first_name'],
                'last_name' => filled($data['last_name'] ?? null) ? $data['last_name'] : null,
                'email' => filled($data['email'] ?? null) ? $data['email'] : null,
                'role_id' => Role::where('slug', Role::ADMIN)->value('id'),
                'password' => $data['password'],
                'is_active' => true,
                'must_reset_password' => false,
                'password_changed_at' => now(),
            ]);

            foreach (ChurchSetting::IDENTITY as $field => $key) {
                ChurchSetting::put($key, trim((string) ($data[$field] ?? '')));
            }

            ChurchSetting::put(ChurchSetting::FOLLOWUP_DAYS, (string) (int) $data['followup_days']);
            ChurchSetting::put(ChurchSetting::TERM_WARNING_DAYS, (string) (int) $data['term_warning_days']);

            $kept = [
                ...$this->syncGroups($data['groups']),
                ...$this->syncFlat(Committee::class, $data['committees']),
                ...$this->syncFlat(EventVenue::class, $data['venues']),
                ...$this->syncPositions($data['positions']),
                ...$this->syncOccupations($data['occupations']),
                ...$this->syncOptions($data['options']),
            ];

            Audit::record('setup.completed', "First-time setup completed by {$admin->username}", $admin, [], $admin->id);

            return [$admin, $kept];
        });

        [$admin, $inUse] = $kept;

        Auth::login($admin);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Setup complete. Welcome to ChManage+.'.($inUse ? ' '.count($inUse).' list entries were kept because members already use them.' : '')]);

        return to_route('dashboard');
    }

    /** Standard entries that were unticked are removed and new ones added, by name. @param  list<string>  $names */
    private function syncFlat(string $model, array $names): array
    {
        $names = $this->clean($names);

        $model::whereNotIn('name', $names)->delete();

        $have = $model::pluck('name')->map(fn ($n) => mb_strtolower($n))->all();
        foreach ($names as $i => $name) {
            if (! in_array(mb_strtolower($name), $have, true)) {
                $model::create(['name' => $name, 'sort_order' => 1000 + $i]);
            }
        }
        $model::orderBy('sort_order')->orderBy('name')->get()->each(fn ($row, $i) => $row->update(['sort_order' => $i + 1]));

        return [];
    }

    /** Groups carry members, so one that people already belong to is never removed. @param  list<string>  $names @return list<string> */
    private function syncGroups(array $names): array
    {
        $names = $this->clean($names);
        $kept = [];

        foreach (MemberGroup::whereNotIn('name', $names)->get() as $group) {
            if (DB::table('member_group_memberships')->where('member_group_id', $group->id)->exists()) {
                $kept[] = $group->name;
            } else {
                $group->delete();
            }
        }

        $have = MemberGroup::pluck('name')->map(fn ($n) => mb_strtolower($n))->all();
        foreach ($names as $name) {
            if (! in_array(mb_strtolower($name), $have, true)) {
                MemberGroup::create(['name' => $name]);
            }
        }

        return $kept;
    }

    /** @param  array<string, list<string>>  $byType */
    private function syncPositions(array $byType): array
    {
        foreach (array_keys(MemberServiceRecord::TYPES) as $type) {
            $names = $this->clean($byType[$type] ?? []);

            ServicePosition::where('type', $type)->whereNotIn('name', $names)->delete();
            $have = ServicePosition::where('type', $type)->pluck('name')->map(fn ($n) => mb_strtolower($n))->all();

            foreach ($names as $i => $name) {
                if (! in_array(mb_strtolower($name), $have, true)) {
                    ServicePosition::create(['type' => $type, 'name' => $name, 'sort_order' => 1000 + $i]);
                }
            }
            ServicePosition::where('type', $type)->orderBy('sort_order')->orderBy('name')->get()->each(fn ($row, $i) => $row->update(['sort_order' => $i + 1]));
        }

        return [];
    }

    /** Members link to an occupation, so one that members already have is never removed. @param  array<string, list<string>>  $byCategory */
    private function syncOccupations(array $byCategory): array
    {
        $wanted = [];
        foreach ($byCategory as $category => $names) {
            foreach ($this->clean($names) as $name) {
                $wanted[mb_strtolower($name)] = [(string) $category, $name];
            }
        }

        $kept = [];
        foreach (Profession::all() as $occupation) {
            if (isset($wanted[mb_strtolower($occupation->name)])) {
                continue;
            }
            if (Member::where('profession_id', $occupation->id)->exists()) {
                $kept[] = $occupation->name;
            } else {
                $occupation->delete();
            }
        }

        $have = Profession::pluck('name')->map(fn ($n) => mb_strtolower($n))->all();
        foreach ($wanted as $key => [$category, $name]) {
            if (! in_array($key, $have, true)) {
                Profession::create([
                    'name' => $name,
                    'category' => $category === Profession::UNCATEGORISED ? null : $category,
                    'sort_order' => (int) Profession::where('category', $category)->max('sort_order') + 1,
                ]);
            }
        }

        return $kept;
    }

    /** @param  array<string, list<string>>  $byKind */
    private function syncOptions(array $byKind): array
    {
        foreach (array_keys(NewcomerOption::KINDS) as $kind) {
            $names = $this->clean($byKind[$kind] ?? []);

            NewcomerOption::where('kind', $kind)->whereNotIn('name', $names)->delete();
            $have = NewcomerOption::where('kind', $kind)->pluck('name')->map(fn ($n) => mb_strtolower($n))->all();

            foreach ($names as $i => $name) {
                if (! in_array(mb_strtolower($name), $have, true)) {
                    NewcomerOption::create(['kind' => $kind, 'name' => $name, 'sort_order' => 1000 + $i]);
                }
            }
            NewcomerOption::where('kind', $kind)->orderBy('sort_order')->orderBy('name')->get()->each(fn ($row, $i) => $row->update(['sort_order' => $i + 1]));
        }

        return [];
    }

    /** Trimmed, blank and repeated names dropped (repeats compared without regard to case). @param  array<int, mixed>  $names @return list<string> */
    private function clean(array $names): array
    {
        return collect($names)->map(fn ($n) => trim((string) $n))->filter()
            ->unique(fn ($n) => mb_strtolower($n))->values()->all();
    }
}
