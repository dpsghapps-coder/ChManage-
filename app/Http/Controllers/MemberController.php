<?php

namespace App\Http\Controllers;

use App\Models\ChurchSetting;
use App\Models\MediaFile;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberNextOfKin;
use App\Models\MemberSacrament;
use App\Models\MemberServiceRecord;
use App\Models\YoungMember;
use App\Rules\PhoneNumber;
use App\Support\Audit;
use App\Support\MemberProfile;
use App\Support\NameFormatter;
use App\Support\Neighbourhoods;
use App\Support\Presbyteries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** The membership register. Adults live in `members`; Junior Youth and Children Service are handled by YoungMemberController. */
class MemberController extends Controller
{
    private const STATUSES = ['active', 'invalid', 'transferred', 'deceased', 'deleted'];

    private const CATEGORIES = ['adults', 'junior_youth', 'children'];

    private const MARITAL_STATUSES = ['single', 'married', 'divorced', 'widowed'];

    private const MARRIAGE_TYPES = ['customary', 'ordinance', 'islamic', 'traditional'];

    public function index(Request $request): Response
    {
        $category = in_array($request->string('category')->value(), self::CATEGORIES, true) ? $request->string('category')->value() : 'adults';

        $counts = [
            'adults' => $this->adults($request)->count(),
            'junior_youth' => $this->young($request, 'junior_youth')->count(),
            'children' => $this->young($request, 'children')->count(),
        ];

        $members = $category === 'adults'
            ? $this->adults($request)->with('photo')->orderBy('full_name')->paginate(25)->withQueryString()->through(fn (Member $m) => [
                'id' => $m->id,
                'member_number' => $m->member_number,
                'title' => $m->title,
                'full_name' => $m->full_name,
                'sex' => $m->sex,
                'age' => $m->date_of_birth?->age,
                'date_of_birth' => $m->date_of_birth?->toDateString(),
                'marital_status' => $m->marital_status,
                'hometown' => $m->hometown,
                'email' => $m->email,
                'mobile' => $m->mobile,
                'telephone' => $m->telephone,
                'joined_on' => $m->joined_on?->toDateString(),
                'status' => $m->status,
                'is_communicant' => $m->is_communicant,
                'photo_url' => $m->photoUrl(),
                'latitude' => $m->latitude,
                'longitude' => $m->longitude,
                'location_accuracy' => $m->location_accuracy,
            ])
            : $this->young($request, $category)
                ->with(['guardians.member.photo', 'member.photo'])
                ->orderBy('last_name')->orderBy('first_name')
                ->paginate(25)->withQueryString()->through(fn (YoungMember $y) => [
                    'id' => $y->id,
                    'member_number' => $y->member_number,
                    'first_name' => $y->first_name,
                    'last_name' => $y->last_name,
                    'other_names' => $y->other_names,
                    'date_of_birth' => $y->date_of_birth?->toDateString(),
                    'joined_on' => $y->joined_on?->toDateString(),
                    'class' => $y->ageClass(),
                    'mobile' => $y->mobile,
                    'telephone' => $y->telephone,
                    'status' => $y->status,
                    'photo_url' => $y->photoUrl(),
                    'latitude' => $y->latitude,
                    'longitude' => $y->longitude,
                    'location_accuracy' => $y->location_accuracy,
                    'guardians' => $y->guardians->map->toRow()->values(),
                ]);

        return Inertia::render('members/index', [
            'members' => $members,
            'category' => $category,
            'counts' => $counts,
            'filters' => $request->only('q', 'status', 'sex'),
            'statuses' => self::STATUSES,
        ]);
    }

    /** Type-ahead for "guardian is a member": active members matching a name, membership number or mobile. */
    public function search(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->value();

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        return response()->json(
            Member::query()
                ->where('status', 'active')
                ->tap(fn ($q) => $this->matching($q, $term, ['full_name', 'first_name', 'last_name'], 'member_number'))
                ->orderBy('full_name')
                ->with('photo')
                ->limit(10)
                ->get()
                ->map(fn (Member $m) => [
                    'id' => $m->id,
                    'member_number' => $m->member_number,
                    'full_name' => $m->full_name,
                    'phones' => $m->phoneNumbers(),
                    'photo_url' => $m->photoUrl(),
                ]),
        );
    }

    /**
     * Search-ahead for the members list: the best few matches from each register, so one search finds anyone.
     * Deleted records are left out.
     */
    public function suggest(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->value();

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $adults = $this->matching(Member::query(), $term, ['full_name', 'first_name', 'last_name'], 'member_number')
            ->where('status', '!=', 'deleted')
            ->where(fn ($w) => $w->whereNull('date_of_birth')->orWhere('date_of_birth', '<=', today()->subYears(YoungMember::REGISTER_UNDER)))
            ->with('photo')->orderBy('full_name')->limit(6)->get()
            ->map(fn (Member $m) => [
                'key' => "adult-{$m->id}",
                'name' => trim(($m->title ? "{$m->title} " : '').$m->full_name),
                'member_number' => $m->member_number,
                'phone' => $m->mobile ?: $m->telephone,
                'photo_url' => $m->photoUrl(),
                'category' => 'adults',
                'detail' => $m->date_of_birth ? $m->date_of_birth->age.' years' : null,
                'status' => $m->status,
            ]);

        $young = $this->matching(YoungMember::query(), $term, ['first_name', 'last_name', 'other_names'], YoungMember::memberNumberSql())
            ->where('status', '!=', 'deleted')
            ->with('member.photo')->orderBy('last_name')->orderBy('first_name')->limit(6)->get()
            ->map(fn (YoungMember $y) => [
                'key' => "young-{$y->id}",
                'name' => trim("{$y->first_name} {$y->last_name}"),
                'member_number' => $y->member_number,
                'phone' => $y->mobile,
                'photo_url' => $y->photoUrl(),
                'category' => YoungMember::departmentFor($y->date_of_birth) === 'CS' ? 'children' : 'junior_youth',
                'detail' => $y->ageClass(),
                'status' => $y->status,
            ]);

        return response()->json($adults->concat($young)->values());
    }

    /**
     * Every word typed must appear in one of the name columns ("kojo mensah" finds first name Kojo, surname Mensah);
     * the whole text may instead match the membership number or the mobile.
     */
    private function matching(Builder $query, string $term, array $nameColumns, string $numberSql): Builder
    {
        $words = preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY);

        return $query->where(fn ($any) => $any
            ->where(function ($names) use ($words, $nameColumns) {
                foreach ($words as $word) {
                    $names->where(fn ($column) => collect($nameColumns)->each(fn ($name) => $column->orWhere($name, 'like', "%{$word}%")));
                }
            })
            ->orWhereRaw("{$numberSql} like ?", ["%{$term}%"])
            ->orWhere('mobile', 'like', "%{$term}%"));
    }

    /** A member's photo, served from the folder set in config/church.php. */
    public function photo(Member $member): BinaryFileResponse
    {
        $path = $member->load('photo')->photoPath();

        abort_unless($path, 404);

        return response()->file($path, ['Cache-Control' => 'private, max-age=86400']);
    }

    /** The registration form for an adult member (18 and over). */
    public function createAdult(): Response
    {
        return Inertia::render('members/adult-form', [...$this->formOptions(), 'member' => null]);
    }

    public function storeAdult(Request $request): RedirectResponse
    {
        $data = $request->validate($this->adultRules(), $this->adultMessages());

        $member = Member::create([
            'member_number' => Member::nextMemberNumber(),
            'status' => 'active',
            ...$this->adultAttributes($data),
            'photo_id' => $request->hasFile('photo') ? $this->storePhoto($request->file('photo'))->id : null,
        ]);

        $this->saveRelated($member, $data);

        Audit::record('member.created', "Registered {$member->full_name} ({$member->member_number})", $member);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$member->full_name} registered as {$member->member_number}."]);

        return $this->continueEditing($request, $member) ?? to_route('members.index', ['category' => 'adults']);
    }

    public function showAdult(Member $member): Response
    {
        return Inertia::render('members/show', [
            'member' => MemberProfile::adult($member),
            'related' => MemberProfile::related($member),
        ]);
    }

    public function editAdult(Request $request, Member $member): Response
    {
        return Inertia::render('members/adult-form', [
            ...$this->formOptions(),
            'member' => [...MemberProfile::adult($member), ...MemberProfile::related($member)],
            // The step to open on, after "Save" on a step (see continueEditing).
            'initialStep' => $request->integer('step'),
        ]);
    }

    public function updateAdult(Request $request, Member $member): RedirectResponse
    {
        $data = $request->validate($this->adultRules($member), $this->adultMessages());

        $changes = $this->adultAttributes($data);

        if ($request->hasFile('photo')) {
            // The previous photo file stays on disk; only the link moves to the new one.
            $changes['photo_id'] = $this->storePhoto($request->file('photo'))->id;
        }

        // Older records were typed in by hand: only rebuild the full name when the name parts were actually changed.
        if (($data['first_name'] ?? '') === ($member->first_name ?? '')
            && ($data['last_name'] ?? '') === ($member->last_name ?? '')
            && ($data['other_names'] ?? '') === MemberProfile::otherNames($member)) {
            unset($changes['full_name']);
        }

        $member->update($changes);
        $this->saveRelated($member, $data);

        Audit::record('member.updated', "Updated {$member->full_name} ({$member->member_number})", $member);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$member->full_name} updated."]);

        return $this->continueEditing($request, $member) ?? to_route('members.show', $member);
    }

    /**
     * "Save" on a step of the form saves and stays: back to the member's edit page, on the same step. A new member
     * becomes an edit of the saved record, so saving again updates it rather than registering them twice.
     * Someone who may register but not edit members is sent on as after a normal save.
     */
    private function continueEditing(Request $request, Member $member): ?RedirectResponse
    {
        if (! $request->boolean('continue') || ! $request->user()->hasPermission('members.edit')) {
            return null;
        }

        return to_route('members.edit', ['member' => $member, 'step' => max(0, $request->integer('step'))]);
    }

    /** Soft delete: the record stays, marked Deleted, and can be restored. */
    public function destroy(Member $member): RedirectResponse
    {
        $member->update(['status' => 'deleted']);

        Audit::record('member.deleted', "Deleted {$member->full_name} ({$member->member_number})", $member);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$member->full_name} moved to Deleted."]);

        return back();
    }

    public function restore(Member $member): RedirectResponse
    {
        $member->update(['status' => 'active']);

        Audit::record('member.restored', "Restored {$member->full_name} ({$member->member_number})", $member);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$member->full_name} restored."]);

        return back();
    }

    /** JSON for the View modal: everything on the member's extra tabs. */
    public function related(Member $member): JsonResponse
    {
        return response()->json(['member' => MemberProfile::adult($member), 'related' => MemberProfile::related($member)]);
    }

    /** What the form's pick-lists need. @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'maritalStatuses' => self::MARITAL_STATUSES,
            'marriageTypes' => self::MARRIAGE_TYPES,
            // Stored value => name. The group itself is worked out from age and sex (Member::generationalGroupFor).
            'generationalGroups' => Member::GENERATIONAL_GROUPS,
            'serviceTypes' => collect(MemberServiceRecord::TYPES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
            'groups' => $this->groupOptions(),
            // Suggestions for the Residence box: places already recorded.
            'residences' => Member::whereNotNull('residence')->where('residence', '!=', '')->distinct()->orderBy('residence')->pluck('residence')->all(),
            'emergencyRelationships' => MemberNextOfKin::emergencyRelationships(),
            // The church's city (Church Settings) narrows Residence to its neighbourhoods and the towns of its region.
            'residenceArea' => Neighbourhoods::forCity(ChurchSetting::values(['city_name'])['city_name'] ?? null),
            // The church where a baptism or confirmation took place: presbytery → district → congregation.
            'presbyteries' => Presbyteries::options(),
            'congregations' => MemberSacrament::whereNotNull('place')->where('place', '!=', '')->distinct()->orderBy('place')->pluck('place')->all(),
            'church' => Presbyteries::church(),
        ];
    }

    /** @return list<array{id: int, name: string, short_name: ?string}> */
    private function groupOptions(): array
    {
        return MemberGroup::orderBy('name')->get(['id', 'name', 'short_name'])->toArray();
    }

    /** Validation for the form's Next of Kin, Sacraments and Groups steps. @return array<string, mixed> */
    private function relatedRules(?Member $member = null): array
    {
        $sacrament = fn (string $kind) => [
            "sacraments.{$kind}.date" => ['nullable', 'date', 'before_or_equal:today'],
            "sacraments.{$kind}.presbytery" => ['nullable', 'string', 'max:150'],
            "sacraments.{$kind}.district" => ['nullable', 'string', 'max:150'],
            // The congregation (the column predates the presbytery and district).
            "sacraments.{$kind}.place" => ['nullable', 'string', 'max:150'],
            "sacraments.{$kind}.minister" => ['nullable', 'string', 'max:150'],
        ];

        $notSelf = Rule::notIn([$member?->id ?? 0]);

        return [
            // The form always sends this, so an empty list of groups can be told apart from "not sent".
            'has_related' => ['boolean'],
            'next_of_kin.name' => ['nullable', 'string', 'max:150'],
            'next_of_kin.phone' => ['nullable', new PhoneNumber],
            'next_of_kin.residential_address' => ['nullable', 'string', 'max:200'],
            'next_of_kin.postal_address' => ['nullable', 'string', 'max:200'],
            'next_of_kin.member_id' => ['nullable', 'integer', Rule::exists('members', 'id'), $notSelf],
            'emergency_contact.name' => ['nullable', 'string', 'max:150'],
            'emergency_contact.phone' => ['nullable', new PhoneNumber],
            'emergency_contact.relationship' => ['nullable', 'string', 'max:100'],
            'emergency_contact.member_id' => ['nullable', 'integer', Rule::exists('members', 'id'), $notSelf],
            ...$sacrament('baptism'),
            ...$sacrament('confirmation'),
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['integer', Rule::exists('member_groups', 'id')],
            'service_records' => ['nullable', 'array', 'max:30'],
            'service_records.*.type' => ['required', Rule::in(array_keys(MemberServiceRecord::TYPES))],
            'service_records.*.name' => ['required', 'string', 'max:150'],
            'service_records.*.position' => ['required', 'string', 'max:150'],
            'service_records.*.started_on' => ['required', 'date', 'before_or_equal:today'],
            'service_records.*.ended_on' => ['nullable', 'date', 'after_or_equal:service_records.*.started_on'],
        ];
    }

    /** Saves next of kin, sacraments and groups as the form sent them (an empty section removes the record). */
    private function saveRelated(Member $member, array $data): void
    {
        if (! ($data['has_related'] ?? false)) {
            return;
        }

        DB::transaction(function () use ($member, $data) {
            // A next of kin or emergency contact who is also a member is read from their own
            // record, so the name can never drift out of step (the same rule spouse follows).
            $kinMember = filled($data['next_of_kin']['member_id'] ?? null) ? Member::find($data['next_of_kin']['member_id']) : null;
            $contactMember = filled($data['emergency_contact']['member_id'] ?? null) ? Member::find($data['emergency_contact']['member_id']) : null;

            $kin = array_map(
                fn ($value) => filled($value) ? trim($value) : null,
                Arr::only($data['next_of_kin'] ?? [], ['name', 'phone', 'residential_address', 'postal_address']),
            );
            $kin['related_member_id'] = $kinMember?->id;
            $kin['name'] = $kinMember?->full_name ?? NameFormatter::titleCase($kin['name'] ?? null);
            $kin['phone'] = $kinMember ? ($kinMember->phoneNumbers()[0] ?? $kin['phone'] ?? null) : ($kin['phone'] ?? null);

            $contact = array_map(
                fn ($value) => filled($value) ? trim($value) : null,
                Arr::only($data['emergency_contact'] ?? [], ['name', 'phone', 'relationship']),
            );
            $row = [
                ...$kin,
                'emergency_contact_member_id' => $contactMember?->id,
                'emergency_contact_name' => $contactMember?->full_name ?? NameFormatter::titleCase($contact['name'] ?? null),
                'emergency_contact_phone' => $contactMember ? ($contactMember->phoneNumbers()[0] ?? $contact['phone'] ?? null) : ($contact['phone'] ?? null),
                'emergency_contact_relationship' => $contact['relationship'] ?? null,
            ];

            if (array_filter($row)) {
                MemberNextOfKin::updateOrCreate(['member_id' => $member->id], $row);
            } else {
                MemberNextOfKin::where('member_id', $member->id)->delete();
            }

            foreach (array_keys(MemberSacrament::KINDS) as $kind) {
                $given = $data['sacraments'][$kind] ?? [];
                $fields = [
                    'sacrament_date' => $given['date'] ?? null,
                    'presbytery' => filled($given['presbytery'] ?? null) ? trim($given['presbytery']) : null,
                    'district' => filled($given['district'] ?? null) ? trim($given['district']) : null,
                    'place' => filled($given['place'] ?? null) ? trim($given['place']) : null,
                    'minister' => NameFormatter::titleCase($given['minister'] ?? null),
                ];

                if (array_filter($fields)) {
                    MemberSacrament::updateOrCreate(['member_id' => $member->id, 'kind' => $kind], $fields);
                } else {
                    MemberSacrament::where(['member_id' => $member->id, 'kind' => $kind])->delete();
                }
            }

            $member->groups()->sync($data['group_ids'] ?? []);

            $member->serviceRecords()->delete();

            foreach ($data['service_records'] ?? [] as $record) {
                $member->serviceRecords()->create([
                    'type' => $record['type'],
                    'name' => trim($record['name']),
                    'position' => trim($record['position']),
                    'started_on' => $record['started_on'],
                    'ended_on' => $record['ended_on'] ?? null,
                ]);
            }
        });
    }

    /** @return array<string, mixed> */
    private function adultRules(?Member $member = null): array
    {
        return [
            'title' => ['nullable', 'string', 'max:20'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'other_names' => ['nullable', 'string', 'max:150'],
            'sex' => ['required', Rule::in(['male', 'female'])],
            'date_of_birth' => ['required', 'date', 'before_or_equal:'.today()->subYears(YoungMember::REGISTER_UNDER)->toDateString()],
            'marital_status' => ['nullable', Rule::in(self::MARITAL_STATUSES)],
            'joined_on' => ['nullable', 'date', 'before_or_equal:today'],
            'place_of_birth' => ['nullable', 'string', 'max:150'],
            'hometown' => ['nullable', 'string', 'max:150'],
            'mobile' => ['nullable', new PhoneNumber],
            'telephone' => ['nullable', new PhoneNumber],
            'email' => ['nullable', 'email', 'max:150'],
            'facebook_id' => ['nullable', 'string', 'max:150'],
            'instagram_id' => ['nullable', 'string', 'max:150'],
            'twitter_id' => ['nullable', 'string', 'max:150'],
            'tiktok_id' => ['nullable', 'string', 'max:150'],
            'residence' => ['nullable', 'string', 'max:150'],
            'marriage_type' => ['nullable', Rule::in(self::MARRIAGE_TYPES)],
            'maiden_name' => ['nullable', 'string', 'max:100'],
            'marriage_date' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:date_of_birth'],
            'marriage_church' => ['nullable', 'string', 'max:150'],
            'spouse_name' => ['nullable', 'string', 'max:150'],
            'spouse_member_id' => ['nullable', 'integer', Rule::exists('members', 'id'), Rule::notIn([$member?->id ?? 0])],
            'father_name' => ['nullable', 'string', 'max:150'],
            'father_member_id' => ['nullable', 'integer', Rule::exists('members', 'id'), Rule::notIn([$member?->id ?? 0])],
            'mother_name' => ['nullable', 'string', 'max:150'],
            'mother_member_id' => ['nullable', 'integer', Rule::exists('members', 'id'), Rule::notIn([$member?->id ?? 0]), 'different:father_member_id'],
            'non_communicant' => ['boolean'],
            'non_communicant_reason' => ['nullable', 'string', 'max:500'],
            'photo' => ['nullable', File::image(allowSvg: false)->types(['jpg', 'jpeg', 'png', 'webp'])->max(4096)],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0', 'max:65535'],
            ...$this->relatedRules($member),
        ];
    }

    /** @return array<string, string> */
    private function adultMessages(): array
    {
        return [
            'date_of_birth.before_or_equal' => 'Members under '.YoungMember::REGISTER_UNDER.' are registered from the Junior Youth or Children Service tab.',
        ];
    }

    /** The columns the form controls (not the number, photo or status). @return array<string, mixed> */
    private function adultAttributes(array $data): array
    {
        foreach (['first_name', 'last_name', 'other_names', 'maiden_name', 'father_name', 'mother_name'] as $field) {
            $data[$field] = NameFormatter::titleCase($data[$field] ?? null);
        }

        // A spouse or parent who is a member is read from their own record, so the name can never drift out of step.
        $linked = fn (string $field) => filled($data[$field] ?? null) ? Member::find($data[$field]) : null;
        $spouse = $linked('spouse_member_id');
        $father = $linked('father_member_id');
        $mother = $linked('mother_member_id');

        $attributes = [
            'title' => $data['title'] ?? null,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            // Registered as "Surname Firstname Othernames", like the rest of the register.
            'full_name' => collect([$data['last_name'], $data['first_name'], $data['other_names'] ?? null])->filter()->implode(' '),
            'sex' => $data['sex'],
            'date_of_birth' => $data['date_of_birth'],
            'marital_status' => $data['marital_status'] ?? null,
            'joined_on' => $data['joined_on'] ?? null,
            'generational_group' => Member::generationalGroupFor($data['date_of_birth'], $data['sex']),
            'place_of_birth' => $data['place_of_birth'] ?? null,
            'hometown' => $data['hometown'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'telephone' => $data['telephone'] ?? null,
            'email' => $data['email'] ?? null,
            'facebook_id' => $data['facebook_id'] ?? null,
            'instagram_id' => $data['instagram_id'] ?? null,
            'twitter_id' => $data['twitter_id'] ?? null,
            'tiktok_id' => $data['tiktok_id'] ?? null,
            'residence' => $data['residence'] ?? null,
            'marriage_type' => $data['marriage_type'] ?? null,
            'maiden_name' => $data['maiden_name'] ?? null,
            'marriage_date' => $data['marriage_date'] ?? null,
            'marriage_church' => filled($data['marriage_church'] ?? null) ? trim($data['marriage_church']) : null,
            'spouse_member_id' => $spouse?->id,
            'spouse_name' => $spouse?->full_name ?? NameFormatter::titleCase($data['spouse_name'] ?? null),
            'father_member_id' => $father?->id,
            'father_name' => $father?->full_name ?? ($data['father_name'] ?? null),
            'mother_member_id' => $mother?->id,
            'mother_name' => $mother?->full_name ?? ($data['mother_name'] ?? null),
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'location_accuracy' => isset($data['location_accuracy']) ? (int) round($data['location_accuracy']) : null,
        ];

        // A single member has no marriage to record: the form hides those fields and anything left from before is cleared.
        // (With no status stated they are kept, so older records are not emptied by an edit.)
        if ($attributes['marital_status'] === 'single') {
            foreach (['marriage_type', 'marriage_date', 'marriage_church', 'maiden_name', 'spouse_member_id', 'spouse_name'] as $field) {
                $attributes[$field] = null;
            }
        }

        // Communicant status is only touched when the form sent it. Someone who is a communicant has no reason to record.
        if (array_key_exists('non_communicant', $data)) {
            $attributes['is_communicant'] = ! $data['non_communicant'];
            $attributes['non_communicant_reason'] = $data['non_communicant'] ? ($data['non_communicant_reason'] ?? null) : null;
        }

        return $attributes;
    }

    /** Saves an uploaded photo into the member photos folder and records it in media_files. */
    private function storePhoto($file): MediaFile
    {
        $mime = $file->getMimeType() ?: 'image/jpeg';
        $size = $file->getSize();
        $name = Str::uuid().'.'.$file->extension();
        $file->move(config('church.member_photos'), $name);

        return MediaFile::create([
            'filename' => $name, 'mime_type' => $mime, 'size_bytes' => $size, 'directory' => 'img/members/', 'created_at' => now(),
        ]);
    }

    /** The main register: 18 and over, or no date of birth on file. Under-18s are on the Junior Youth / Children Service tabs. */
    private function adults(Request $request): Builder
    {
        return $this->filtered(Member::query(), $request, ['full_name', 'first_name', 'last_name'])
            ->when(in_array($request->string('sex')->value(), ['male', 'female'], true), fn ($q) => $q->where('sex', $request->string('sex')->value()))
            ->where(fn ($w) => $w->whereNull('date_of_birth')->orWhere('date_of_birth', '<=', today()->subYears(YoungMember::REGISTER_UNDER)));
    }

    private function young(Request $request, string $category): Builder
    {
        $twelfth = today()->subYears(YoungMember::CHILDREN_UNDER);

        return $this->filtered(YoungMember::query(), $request, ['first_name', 'last_name', 'other_names'], YoungMember::memberNumberSql())
            ->when($category === 'children', fn ($q) => $q->where('date_of_birth', '>', $twelfth), fn ($q) => $q->where(fn ($w) => $w->where('date_of_birth', '<=', $twelfth)->orWhereNull('date_of_birth')));
    }

    /** Search box and status filter, shared by every tab. Deleted records are hidden unless asked for. */
    private function filtered(Builder $query, Request $request, array $nameColumns, string $numberSql = 'member_number'): Builder
    {
        $status = $request->string('status')->value();

        return $query
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $this->matching($q, $term, $nameColumns, $numberSql))
            ->when(in_array($status, self::STATUSES, true), fn ($q) => $q->where('status', $status), fn ($q) => $q->where('status', '!=', 'deleted'));
    }
}
