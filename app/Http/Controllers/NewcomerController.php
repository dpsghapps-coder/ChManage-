<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MemberNextOfKin;
use App\Models\Newcomer;
use App\Models\NewcomerCounsellor;
use App\Models\NewcomerLesson;
use App\Models\NewcomerLessonProgress;
use App\Models\NewcomerOption;
use App\Models\NewcomerVisit;
use App\Support\Audit;
use App\Support\NewcomerPromotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Visitors, newcomers and catechumens: the register of people on the way to membership, modelled on the
 * "First Time Worshippers / New Comers" form.
 */
class NewcomerController extends Controller
{
    public function index(Request $request): Response
    {
        $stage = array_key_exists($request->string('stage')->value(), Newcomer::STAGES) ? $request->string('stage')->value() : null;
        $status = array_key_exists($request->string('status')->value(), Newcomer::STATUSES) ? $request->string('status')->value() : null;
        $mine = $request->boolean('mine');
        $counsellorId = $request->string('counsellor')->value();
        $myCounsellorId = $this->myCounsellorId($request);

        // Inactive people are hidden unless asked for, so the working lists stay short.
        $base = fn () => Newcomer::query()->search($request->string('q')->value())
            ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->where('status', '!=', 'inactive'))
            ->when($mine, fn ($q) => $q->where('counsellor_id', $myCounsellorId ?? 0))
            ->when($counsellorId === 'none', fn ($q) => $q->whereNull('counsellor_id'))
            ->when(ctype_digit($counsellorId), fn ($q) => $q->where('counsellor_id', (int) $counsellorId));

        $counts = $base()->selectRaw('stage, count(*) as n')->groupBy('stage')->pluck('n', 'stage');

        $people = $base()->when($stage, fn ($q) => $q->where('stage', $stage))
            ->with('counsellor.member:id,full_name')
            ->withCount([
                'progress as lessons_done' => fn ($q) => $q->where('status', 'completed'),
                'progress as lessons_total' => fn ($q) => $q->where('status', '!=', 'skipped'),
            ])
            ->orderByDesc('first_visit_on')->orderByDesc('id')
            ->paginate(25)->withQueryString()
            ->through(fn (Newcomer $n) => $this->row($n));

        return Inertia::render('newcomers/index', [
            'people' => $people,
            'stages' => Newcomer::STAGES,
            'statuses' => Newcomer::STATUSES,
            'counts' => collect(array_keys(Newcomer::STAGES))->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)]),
            'counsellors' => $this->counsellorOptions(),
            'myCounsellorId' => $myCounsellorId,
            'filters' => ['q' => $request->string('q')->value(), 'stage' => $stage, 'status' => $status, 'counsellor' => $counsellorId ?: null, 'mine' => $mine],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('newcomers/form', ['newcomer' => null, ...$this->formOptions()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $newcomer = DB::transaction(function () use ($data, $request) {
            $newcomer = Newcomer::create([...$data, 'stage' => 'visitor', 'status' => 'active', 'created_by' => auth()->id()]);
            $newcomer->log('stage', null, 'visitor', 'Registered');
            NewcomerVisit::create([
                'newcomer_id' => $newcomer->id, 'visited_on' => $data['first_visit_on'], 'service' => $data['first_service'] ?? null,
                'note' => 'First visit', 'recorded_by' => auth()->id(),
            ]);
            $this->assignCounsellorEffects($newcomer);
            $this->keepPhoto($request, $newcomer);

            return $newcomer;
        });

        Audit::record('newcomer.registered', "Registered {$newcomer->fullName()} as a visitor", $newcomer);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$newcomer->fullName()} registered."]);

        return to_route('newcomers.show', $newcomer);
    }

    public function show(Newcomer $newcomer): Response
    {
        if ($newcomer->stage === 'catechumen' && $newcomer->status !== 'inactive') {
            $newcomer->syncLessons();
        }

        $newcomer->load(['counsellor.member:id,full_name,mobile', 'member:id,member_number,full_name', 'youngMember', 'visits', 'changes.user:id,first_name,last_name']);

        return Inertia::render('newcomers/show', [
            'newcomer' => [
                ...$this->row($newcomer),
                ...$newcomer->only([
                    'title', 'surname', 'first_name', 'middle_name', 'sex', 'other_numbers', 'whatsapp', 'residential_address', 'postal_address',
                    'emergency_number', 'email', 'current_status', 'marital_status', 'marriage_type', 'religious_background', 'religious_other',
                    'former_church', 'is_baptized', 'is_confirmed', 'guardian_name', 'guardian_relationship', 'guardian_phone', 'purpose',
                    'heard_via', 'heard_contact', 'first_service', 'remarks', 'inactive_reason',
                ]),
                'date_of_birth' => $newcomer->date_of_birth?->toDateString(),
                'is_minor' => $newcomer->isMinor(),
                ...$newcomer->only(['latitude', 'longitude', 'location_accuracy']),
                'counsellor_phone' => $newcomer->counsellor?->member?->mobile,
                'member' => $newcomer->member ? ['id' => $newcomer->member->id, 'member_number' => $newcomer->member->member_number, 'full_name' => $newcomer->member->full_name] : null,
                'young_member' => $newcomer->youngMember ? ['id' => $newcomer->youngMember->id, 'member_number' => $newcomer->youngMember->member_number, 'full_name' => $newcomer->youngMember->fullName()] : null,
                'made_member_on' => $newcomer->made_member_on?->toDateString(),
            ],
            'lessons' => $this->lessonRows($newcomer),
            'promotion' => [
                'target' => NewcomerPromotion::target($newcomer)['label'] ?? null,
                'blockers' => NewcomerPromotion::blockers($newcomer),
            ],
            'visits' => $newcomer->visits->map(fn ($v) => ['id' => $v->id, 'visited_on' => $v->visited_on->toDateString(), 'service' => $v->service, 'note' => $v->note])->values(),
            'history' => $newcomer->changes->map(fn ($c) => [
                'id' => $c->id, 'kind' => $c->kind, 'from' => $c->from_value, 'to' => $c->to_value, 'on' => $c->changed_on->toDateString(),
                'note' => $c->note, 'by' => $c->user ? trim("{$c->user->first_name} {$c->user->last_name}") : null,
            ])->values(),
            'stages' => Newcomer::STAGES,
            'statuses' => Newcomer::STATUSES,
            'inactiveReasons' => Newcomer::INACTIVE_REASONS,
            'services' => NewcomerOption::lists()['service'],
        ]);
    }

    public function edit(Newcomer $newcomer): Response
    {
        return Inertia::render('newcomers/form', [
            'newcomer' => [
                ...$newcomer->only([
                    'id', 'title', 'surname', 'first_name', 'middle_name', 'sex', 'mobile', 'other_numbers', 'whatsapp', 'residential_address',
                    'postal_address', 'emergency_number', 'email', 'current_status', 'marital_status', 'marriage_type', 'religious_background',
                    'religious_other', 'former_church', 'is_baptized', 'is_confirmed', 'guardian_name', 'guardian_relationship', 'guardian_phone',
                    'purpose', 'heard_via', 'heard_contact', 'first_service', 'counsellor_id', 'remarks',
                ]),
                'first_visit_on' => $newcomer->first_visit_on->toDateString(),
                'date_of_birth' => $newcomer->date_of_birth?->toDateString(),
                'photo_url' => $newcomer->photoUrl(),
                ...$newcomer->only(['latitude', 'longitude', 'location_accuracy']),
            ],
            ...$this->formOptions($newcomer),
        ]);
    }

    public function update(Request $request, Newcomer $newcomer): RedirectResponse
    {
        $data = $this->validated($request, $newcomer);

        DB::transaction(function () use ($newcomer, $data, $request) {
            $newcomer->update($data);
            $this->assignCounsellorEffects($newcomer);
            $this->keepPhoto($request, $newcomer);
        });

        Audit::record('newcomer.updated', "Updated {$newcomer->fullName()}", $newcomer);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$newcomer->fullName()} saved."]);

        return to_route('newcomers.show', $newcomer);
    }

    /** Someone who returns: another visit on the record, no new form. */
    public function addVisit(Request $request, Newcomer $newcomer): RedirectResponse
    {
        $data = $request->validate([
            'visited_on' => ['required', 'date', 'before_or_equal:today'],
            'service' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:250'],
        ]);

        NewcomerVisit::create([...$data, 'newcomer_id' => $newcomer->id, 'recorded_by' => auth()->id()]);

        Audit::record('newcomer.visit', "Recorded a visit by {$newcomer->fullName()}", $newcomer, ['visited_on' => $data['visited_on']]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Visit recorded.']);

        return back();
    }

    /** Active, on hold or inactive; going inactive says why. Nothing is deleted, and they can be reactivated. */
    public function setStatus(Request $request, Newcomer $newcomer): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Newcomer::STATUSES))],
            'reason' => [Rule::requiredIf($request->input('status') === 'inactive'), 'nullable', 'string', 'max:200'],
        ], ['reason.required' => 'Say why they are no longer active.']);

        if ($data['status'] !== $newcomer->status) {
            $newcomer->log('status', $newcomer->status, $data['status'], $data['status'] === 'inactive' ? $data['reason'] : null);
            $newcomer->update(['status' => $data['status'], 'inactive_reason' => $data['status'] === 'inactive' ? $data['reason'] : null]);

            Audit::record('newcomer.status', "{$newcomer->fullName()} is now ".Newcomer::STATUSES[$data['status']], $newcomer, $data);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Status updated.']);

        return back();
    }

    public function photo(Newcomer $newcomer): BinaryFileResponse
    {
        $path = $newcomer->photoPath();

        abort_unless($path, 404);

        return response()->file($path, ['Cache-Control' => 'private, max-age=86400']);
    }

    /** Into the class: a newcomer becomes a catechumen and gets every lesson in use, not yet started. */
    public function enrol(Newcomer $newcomer): RedirectResponse
    {
        if ($newcomer->stage !== 'newcomer') {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Only a newcomer can be enrolled in the class.']);

            return back();
        }

        DB::transaction(function () use ($newcomer) {
            $newcomer->log('stage', 'newcomer', 'catechumen', 'Enrolled in the class');
            $newcomer->update(['stage' => 'catechumen']);
            $newcomer->syncLessons();
        });

        Audit::record('newcomer.enrolled', "Enrolled {$newcomer->fullName()} in the class", $newcomer);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$newcomer->fullName()} is now in the class."]);

        return back();
    }

    /** A leader sets where someone stands in a lesson. Completed carries the date, which can be changed. */
    public function updateProgress(Request $request, Newcomer $newcomer, NewcomerLesson $lesson): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(NewcomerLessonProgress::STATUSES))],
            'completed_on' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:250'],
        ]);

        $row = $newcomer->progress()->where('lesson_id', $lesson->id)->first();

        if (! $row) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'That lesson is not part of their class yet.']);

            return back();
        }

        $row->update([
            'status' => $data['status'],
            'completed_on' => $data['status'] === 'completed' ? ($data['completed_on'] ?? today()->toDateString()) : null,
            'note' => filled($data['note'] ?? null) ? trim($data['note']) : null,
            'updated_by' => auth()->id(),
        ]);

        Audit::record('newcomer.lesson', "{$newcomer->fullName()}: {$lesson->title} is now ".NewcomerLessonProgress::STATUSES[$data['status']], $newcomer);

        return back();
    }

    /** Makes a catechumen a member and opens the new record to fill in what the newcomer form did not ask for. */
    public function promote(Request $request, Newcomer $newcomer): RedirectResponse
    {
        $data = $request->validate(['joined_on' => ['required', 'date', 'before_or_equal:today']]);

        if ($blockers = NewcomerPromotion::blockers($newcomer)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $blockers[0]]);

            return back();
        }

        [$kind, $id] = NewcomerPromotion::promote($newcomer, $data['joined_on']);

        Audit::record('newcomer.promoted', "Made {$newcomer->fullName()} a member", $newcomer, ['kind' => $kind, 'id' => $id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$newcomer->fullName()} is now a member. Fill in the rest of their details."]);

        return $kind === 'adult' ? to_route('members.edit', $id) : to_route('members.young.edit', $id);
    }

    /** Warns while the form is filled if this person may already be on the books, as a newcomer or as a member. */
    public function duplicates(Request $request): JsonResponse
    {
        $mobile = preg_replace('/\s+/', '', (string) $request->input('mobile'));
        $first = trim((string) $request->input('first_name'));
        $surname = trim((string) $request->input('surname'));
        $dob = $request->input('date_of_birth');
        $ignore = (int) $request->input('ignore');

        $hits = collect();

        if (strlen($mobile) >= 7) {
            $digits = fn (string $column) => "replace({$column}, ' ', '')";
            $newcomers = Newcomer::whereRaw($digits('mobile').' = ?', [$mobile])->orWhereRaw($digits('whatsapp').' = ?', [$mobile]);
            $members = Member::whereRaw($digits('mobile').' = ?', [$mobile])->orWhereRaw($digits('telephone').' = ?', [$mobile]);
        } else {
            $newcomers = Newcomer::whereRaw('1 = 0');
            $members = Member::whereRaw('1 = 0');
        }

        if ($first !== '' && $surname !== '' && filled($dob)) {
            $newcomers->orWhere(fn ($q) => $q->where('first_name', $first)->where('surname', $surname)->whereDate('date_of_birth', $dob));
            $members->orWhere(fn ($q) => $q->where('first_name', $first)->where('last_name', $surname)->whereDate('date_of_birth', $dob));
        }

        foreach ($newcomers->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->limit(5)->get() as $n) {
            $hits->push(['kind' => 'newcomer', 'id' => $n->id, 'name' => $n->fullName(), 'detail' => Newcomer::STAGES[$n->stage], 'url' => route('newcomers.show', $n)]);
        }

        foreach ($members->limit(5)->get() as $m) {
            $hits->push(['kind' => 'member', 'id' => $m->id, 'name' => $m->full_name, 'detail' => "Member {$m->member_number}", 'url' => route('members.show', $m)]);
        }

        return response()->json($hits->values());
    }

    /** Saves an uploaded photo into the newcomer photos folder; the previous file stays on disk and only the link moves. */
    private function keepPhoto(Request $request, Newcomer $newcomer): void
    {
        /** @var UploadedFile|null $file */
        $file = $request->file('photo');

        if (! $file) {
            return;
        }

        $name = Str::uuid().'.'.$file->extension();
        $file->move(config('church.newcomer_photos'), $name);
        $newcomer->update(['photo_path' => $name]);
    }

    /** A counsellor on a visitor makes them a newcomer: the church has taken them on. */
    private function assignCounsellorEffects(Newcomer $newcomer): void
    {
        if ($newcomer->counsellor_id && $newcomer->stage === 'visitor') {
            $newcomer->log('stage', 'visitor', 'newcomer', 'Counsellor assigned');
            $newcomer->update(['stage' => 'newcomer']);
        }
    }

    /** The class lessons in teaching order with this person's standing; a lesson taken out of use shows only if they have started it. */
    private function lessonRows(Newcomer $newcomer): array
    {
        return $newcomer->progress()->with('lesson')->get()
            ->filter(fn ($row) => $row->lesson->is_active || $row->status !== 'not_started')
            ->sortBy(fn ($row) => [$row->lesson->sort_order, $row->lesson->id])
            ->map(fn ($row) => [
                'id' => $row->lesson_id,
                'title' => $row->lesson->title,
                'description' => $row->lesson->description,
                'status' => $row->status,
                'completed_on' => $row->completed_on?->toDateString(),
                'note' => $row->note,
            ])->values()->all();
    }

    /** The counsellor record of the signed-in user, when their account is linked to a member who counsels. */
    private function myCounsellorId(Request $request): ?int
    {
        $memberId = $request->user()?->staff?->member_id;

        return $memberId ? NewcomerCounsellor::where('member_id', $memberId)->value('id') : null;
    }

    private function counsellorOptions(?Newcomer $current = null): array
    {
        return NewcomerCounsellor::with('member:id,full_name')
            ->where(fn ($q) => $q->where('is_active', true)->when($current?->counsellor_id, fn ($q, $id) => $q->orWhere('id', $id)))
            ->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->member->full_name])->sortBy('name')->values()->all();
    }

    private function formOptions(?Newcomer $current = null): array
    {
        return [
            'lists' => NewcomerOption::lists(),
            'counsellors' => $this->counsellorOptions($current),
            'relationships' => MemberNextOfKin::relationships(),
            'backgrounds' => Newcomer::BACKGROUNDS,
        ];
    }

    private function row(Newcomer $n): array
    {
        return [
            'id' => $n->id,
            'name' => $n->fullName(),
            'title' => $n->title,
            'sex' => $n->sex,
            'age' => $n->date_of_birth?->age,
            'date_of_birth' => $n->date_of_birth?->toDateString(),
            'mobile' => $n->mobile,
            'email' => $n->email,
            'photo_url' => $n->photoUrl(),
            'stage' => $n->stage,
            'status' => $n->status,
            'first_visit_on' => $n->first_visit_on?->toDateString(),
            'counsellor_id' => $n->counsellor_id,
            'counsellor' => $n->counsellor?->member?->full_name,
            'lessons_done' => (int) ($n->lessons_done ?? 0),
            'lessons_total' => (int) ($n->lessons_total ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Newcomer $current = null): array
    {
        $text = fn (int $max = 200) => ['nullable', 'string', 'max:'.$max];
        $dob = $request->input('date_of_birth');
        $minor = filled($dob) && ($born = strtotime((string) $dob)) !== false && $born > strtotime('-18 years');

        $data = $request->validate([
            'first_visit_on' => ['required', 'date', 'before_or_equal:today'],
            'first_service' => $text(100),
            'purpose' => $text(100),
            'heard_via' => $text(150),
            'heard_contact' => $text(),
            'title' => $text(50),
            'surname' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => $text(100),
            'sex' => ['nullable', Rule::in(Newcomer::SEXES)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'mobile' => $text(30),
            'other_numbers' => $text(100),
            'whatsapp' => $text(30),
            'residential_address' => $text(250),
            'postal_address' => $text(250),
            'emergency_number' => $text(30),
            'email' => ['nullable', 'email', 'max:150'],
            'current_status' => $text(100),
            'marital_status' => ['nullable', Rule::in(Newcomer::MARITAL_STATUSES)],
            'marriage_type' => ['nullable', Rule::in(Newcomer::MARRIAGE_TYPES)],
            'religious_background' => ['nullable', Rule::in(array_keys(Newcomer::BACKGROUNDS))],
            'religious_other' => $text(150),
            'former_church' => $text(),
            'is_baptized' => ['boolean'],
            'is_confirmed' => ['boolean'],
            'guardian_name' => [Rule::requiredIf($minor), 'nullable', 'string', 'max:200'],
            'guardian_relationship' => $text(100),
            'guardian_phone' => $text(30),
            'counsellor_id' => ['nullable', Rule::exists('newcomer_counsellors', 'id')->where(fn ($q) => $q->where('is_active', true)
                ->when($current?->counsellor_id, fn ($q, $id) => $q->orWhere('id', $id)))],
            'remarks' => ['nullable', 'string', 'max:2000'],
            // The browser shrinks photos to about 800px before sending; the limit is a safety net.
            'photo' => ['nullable', File::image(allowSvg: false)->types(['jpg', 'jpeg', 'png', 'webp'])->max(4096)],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0', 'max:65535'],
        ], ['guardian_name.required' => 'Someone under 18 needs a parent or guardian.']);

        // Only what applies is kept: marriage type for the married, guardian details for a minor.
        if (($data['marital_status'] ?? null) !== 'married') {
            $data['marriage_type'] = null;
        }

        if (($data['religious_background'] ?? null) !== 'other') {
            $data['religious_other'] = null;
        }

        if (! $minor) {
            $data['guardian_name'] = $data['guardian_relationship'] = $data['guardian_phone'] = null;
        }

        foreach (['surname', 'first_name', 'middle_name'] as $name) {
            $data[$name] = isset($data[$name]) ? trim(preg_replace('/\s+/', ' ', $data[$name])) : null;
        }

        unset($data['photo']);
        $data['location_accuracy'] = isset($data['location_accuracy']) ? (int) round($data['location_accuracy']) : null;
        $data['is_baptized'] = (bool) ($data['is_baptized'] ?? false);
        $data['is_confirmed'] = (bool) ($data['is_confirmed'] ?? false);

        return $data;
    }
}
