<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\YoungMember;
use App\Models\YoungMemberGuardian;
use App\Rules\PhoneNumber;
use App\Support\Audit;
use App\Support\NameFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Junior Youth and Children Service: the simplified register with guardians. */
class YoungMemberController extends Controller
{
    private function relationships(): array
    {
        return collect(YoungMember::RELATIONSHIPS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    }

    public function create(): Response
    {
        return Inertia::render('members/form', ['relationships' => $this->relationships(), 'child' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $young = DB::transaction(function () use ($data) {
            $young = YoungMember::create([
                'number_year' => now()->year,
                'number_seq' => YoungMember::nextSequence(),
                ...$this->attributes($data),
            ]);

            $this->saveGuardians($young, $data['guardians']);

            return $young;
        });

        if ($request->hasFile('photo')) {
            $young->update(['photo_path' => $this->storePhoto($request->file('photo'))]);
        }

        Audit::record('member.created', "Registered {$young->fullName()} ({$young->member_number})", $young);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$young->fullName()} registered as {$young->member_number}."]);

        return to_route('members.index', ['category' => $this->tab($young)]);
    }

    public function show(YoungMember $youngMember): Response
    {
        $youngMember->load(['guardians.member.photo', 'member.photo']);

        return Inertia::render('members/young-show', [
            'child' => [
                ...$this->details($youngMember),
                'class' => $youngMember->ageClass(),
                'age' => $youngMember->date_of_birth?->age,
                'guardians' => $youngMember->guardians->map->toRow()->values(),
            ],
        ]);
    }

    public function edit(YoungMember $youngMember): Response
    {
        $youngMember->load(['guardians.member.photo', 'member.photo']);

        return Inertia::render('members/form', [
            'relationships' => $this->relationships(),
            'child' => [
                ...$this->details($youngMember),
                'guardians' => $youngMember->guardians->map(fn (YoungMemberGuardian $g) => [
                    'relationship' => $g->relationship,
                    'relationship_other' => $g->relationship_other ?? '',
                    'is_member' => $g->member_id !== null,
                    'member' => $g->member ? [
                        'id' => $g->member->id,
                        'member_number' => $g->member->member_number,
                        'full_name' => $g->member->full_name,
                        'phones' => $g->member->phoneNumbers(),
                        'photo_url' => $g->member->photoUrl(),
                    ] : null,
                    'name' => $g->member_id ? '' : $g->name,
                    'phone' => $g->member_id ? '' : ($g->phone ?? ''),
                    'is_primary' => $g->is_primary,
                ])->values(),
            ],
        ]);
    }

    public function update(Request $request, YoungMember $youngMember): RedirectResponse
    {
        $data = $this->validated($request, $youngMember->guardians()->pluck('member_id')->filter()->all());

        DB::transaction(function () use ($youngMember, $data) {
            $youngMember->update($this->attributes($data));

            // Guardians are replaced as a set: nothing else points at them.
            $youngMember->guardians()->delete();
            $this->saveGuardians($youngMember, $data['guardians']);
        });

        if ($request->hasFile('photo')) {
            // The previous photo file stays on disk; only the link moves to the new one.
            $youngMember->update(['photo_path' => $this->storePhoto($request->file('photo'))]);
        }

        Audit::record('member.updated', "Updated {$youngMember->fullName()} ({$youngMember->member_number})", $youngMember);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$youngMember->fullName()} updated."]);

        return to_route('members.young.show', $youngMember);
    }

    /** Soft delete: the record stays, marked Deleted, and can be restored. */
    public function destroy(YoungMember $youngMember): RedirectResponse
    {
        $youngMember->update(['status' => 'deleted']);

        Audit::record('member.deleted', "Deleted {$youngMember->fullName()} ({$youngMember->member_number})", $youngMember);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$youngMember->fullName()} moved to Deleted."]);

        return back();
    }

    public function restore(YoungMember $youngMember): RedirectResponse
    {
        $youngMember->update(['status' => 'active']);

        Audit::record('member.restored', "Restored {$youngMember->fullName()} ({$youngMember->member_number})", $youngMember);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$youngMember->fullName()} restored."]);

        return back();
    }

    /** The photo taken at registration, else the photo of the main-register record this was copied from. */
    public function photo(YoungMember $youngMember): BinaryFileResponse
    {
        $path = $youngMember->load('member.photo')->photoPath();

        abort_unless($path, 404);

        return response()->file($path, ['Cache-Control' => 'private, max-age=86400']);
    }

    /** @param  list<int>  $alreadyLinked  Members already linked as guardians, still allowed if they are no longer active. */
    private function validated(Request $request, array $alreadyLinked = []): array
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'other_names' => ['nullable', 'string', 'max:150'],
            'sex' => ['required', Rule::in(['male', 'female'])],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today', Rule::date()->after(today()->subYears(YoungMember::REGISTER_UNDER))],
            'joined_on' => ['nullable', 'date', 'before_or_equal:today'],
            'mobile' => ['nullable', new PhoneNumber],
            'telephone' => ['nullable', new PhoneNumber],
            // The browser shrinks photos to about 800px before sending; the limit is a safety net.
            'photo' => ['nullable', File::image(allowSvg: false)->types(['jpg', 'jpeg', 'png', 'webp'])->max(4096)],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0', 'max:65535'],
            'guardians' => ['required', 'array', 'min:1', 'max:10'],
            'guardians.*.relationship' => ['required', Rule::in(array_keys(YoungMember::RELATIONSHIPS))],
            'guardians.*.relationship_other' => ['nullable', 'required_if:guardians.*.relationship,other', 'string', 'max:100'],
            'guardians.*.is_member' => ['boolean'],
            'guardians.*.member_id' => ['nullable', 'required_if:guardians.*.is_member,true', 'integer',
                Rule::exists('members', 'id')->where(fn ($q) => $q->where('status', 'active')->orWhereIn('id', $alreadyLinked ?: [0]))],
            'guardians.*.name' => ['nullable', 'required_unless:guardians.*.is_member,true', 'string', 'max:150'],
            'guardians.*.phone' => ['nullable', new PhoneNumber],
            'guardians.*.is_primary' => ['boolean'],
        ], [
            'date_of_birth.after' => 'Only children and youth under '.YoungMember::REGISTER_UNDER.' are registered with this form.',
            'guardians.required' => 'Add at least one guardian.',
            'guardians.*.member_id.required_if' => 'Search for and select the member.',
            'guardians.*.member_id.exists' => 'Choose an active member of the church.',
            'guardians.*.name.required_unless' => 'Enter the guardian’s name.',
            'guardians.*.relationship_other.required_if' => 'Say how this guardian is related.',
        ]);

        if (collect($data['guardians'])->filter(fn ($g) => $g['is_primary'] ?? false)->count() !== 1) {
            throw ValidationException::withMessages(['guardians' => 'Mark exactly one guardian as the primary contact.']);
        }

        return $data;
    }

    /** The columns the form controls (not the number, photo or status). */
    private function attributes(array $data): array
    {
        return [
            'first_name' => NameFormatter::titleCase($data['first_name']),
            'last_name' => NameFormatter::titleCase($data['last_name']),
            'other_names' => NameFormatter::titleCase($data['other_names'] ?? null),
            'sex' => $data['sex'],
            'date_of_birth' => $data['date_of_birth'],
            'joined_on' => $data['joined_on'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'telephone' => $data['telephone'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'location_accuracy' => isset($data['location_accuracy']) ? (int) round($data['location_accuracy']) : null,
        ];
    }

    private function saveGuardians(YoungMember $young, array $guardians): void
    {
        foreach ($guardians as $guardian) {
            // A member's name and phone come from the member record, never from what the browser sent.
            $member = ($guardian['is_member'] ?? false) ? Member::find($guardian['member_id']) : null;

            $young->guardians()->create([
                'relationship' => $guardian['relationship'],
                'relationship_other' => $guardian['relationship'] === 'other' ? $guardian['relationship_other'] : null,
                'member_id' => $member?->id,
                'name' => $member?->full_name ?? NameFormatter::titleCase($guardian['name']),
                'phone' => $member ? ($member->phoneNumbers()[0] ?? null) : ($guardian['phone'] ?? null),
                'is_primary' => $guardian['is_primary'] ?? false,
            ]);
        }
    }

    /** Saves an uploaded photo into the young member photos folder and returns its file name. */
    private function storePhoto($file): string
    {
        $name = Str::uuid().'.'.$file->extension();
        $file->move(config('church.young_member_photos'), $name);

        return $name;
    }

    private function tab(YoungMember $young): string
    {
        return $young->date_of_birth->age < YoungMember::CHILDREN_UNDER ? 'children' : 'junior_youth';
    }

    /** @return array<string, mixed> */
    private function details(YoungMember $young): array
    {
        return [
            'id' => $young->id,
            'member_number' => $young->member_number,
            'first_name' => $young->first_name,
            'last_name' => $young->last_name,
            'other_names' => $young->other_names,
            'sex' => $young->sex,
            'date_of_birth' => $young->date_of_birth?->toDateString(),
            'joined_on' => $young->joined_on?->toDateString(),
            'mobile' => $young->mobile,
            'telephone' => $young->telephone,
            'status' => $young->status,
            'photo_url' => $young->photoUrl(),
            'latitude' => $young->latitude,
            'longitude' => $young->longitude,
            'location_accuracy' => $young->location_accuracy,
        ];
    }
}
