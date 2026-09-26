<?php

namespace App\Http\Controllers;

use App\Models\CommunionService;
use App\Models\Member;
use App\Models\SpeakingNote;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Notes of speaking to members before communion. Everything here needs the Speaking permission. The list shows who was
 * spoken to and the outcome but not the notes; opening a note is written to the audit log.
 */
class SpeakingController extends Controller
{
    public function index(Request $request): Response
    {
        $notes = SpeakingNote::query()
            ->with(['member:id,member_number,full_name', 'service:id,title,held_on', 'spokenBy:id,full_name'])
            ->when($request->integer('service'), fn ($q, $id) => $q->where('communion_service_id', $id))
            ->when($request->input('outcome') && array_key_exists($request->input('outcome'), SpeakingNote::OUTCOMES), fn ($q) => $q->where('outcome', $request->input('outcome')))
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->whereHas('member', fn ($m) => $m->where('full_name', 'like', "%{$term}%")->orWhere('member_number', 'like', "%{$term}%")))
            ->orderByDesc('spoken_on')->orderByDesc('id')
            ->paginate(20)->withQueryString()
            ->through(fn (SpeakingNote $n) => $this->row($n));

        // Reading the list is not reading the notes, but it does say who was counselled: keep a record of it.
        Audit::record('speaking.list_viewed', 'Viewed the list of speaking notes');

        return Inertia::render('speaking/index', [
            'notes' => $notes,
            'filters' => $request->only('service', 'outcome', 'q'),
            'services' => $this->services(),
            'outcomes' => SpeakingNote::OUTCOMES,
        ]);
    }

    public function create(Request $request): Response
    {
        $member = $request->integer('member') ? Member::find($request->integer('member')) : null;

        return Inertia::render('speaking/form', [
            'note' => null,
            'presetMember' => $member ? ['id' => $member->id, 'full_name' => $member->full_name, 'member_number' => $member->member_number] : null,
            'presetService' => $request->integer('service') ?: null,
            'services' => $this->services(),
            'outcomes' => SpeakingNote::OUTCOMES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $note = SpeakingNote::create([...$this->validated($request), 'created_by' => $request->user()->id]);

        Audit::record('speaking.created', "Wrote a speaking note for {$note->member->full_name}", $note, ['member_id' => $note->member_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Note saved.']);

        return to_route('speaking.show', $note);
    }

    public function show(SpeakingNote $note): Response
    {
        $note->load(['member:id,member_number,full_name', 'service:id,title,held_on', 'spokenBy:id,full_name']);
        Audit::record('speaking.viewed', "Read the speaking note for {$note->member->full_name}", $note, ['member_id' => $note->member_id]);

        return Inertia::render('speaking/show', ['note' => $this->row($note) + ['notes' => $note->notes], 'outcomes' => SpeakingNote::OUTCOMES]);
    }

    public function edit(SpeakingNote $note): Response
    {
        $note->load(['member:id,member_number,full_name', 'spokenBy:id,member_number,full_name']);
        Audit::record('speaking.viewed', "Opened the speaking note for {$note->member->full_name} to edit", $note, ['member_id' => $note->member_id]);

        return Inertia::render('speaking/form', [
            'note' => [
                'id' => $note->id,
                'member_id' => $note->member_id,
                'member' => ['id' => $note->member->id, 'full_name' => $note->member->full_name, 'member_number' => $note->member->member_number],
                'communion_service_id' => $note->communion_service_id,
                'spoken_on' => $note->spoken_on->toDateString(),
                'spoken_by_member_id' => $note->spoken_by_member_id ?? '',
                'spoken_by' => $note->spokenBy ? ['full_name' => $note->spokenBy->full_name, 'member_number' => $note->spokenBy->member_number] : null,
                'spoken_by_name' => $note->spoken_by_name ?? '',
                'outcome' => $note->outcome,
                'notes' => $note->notes,
            ],
            'presetMember' => null,
            'presetService' => null,
            'services' => $this->services(),
            'outcomes' => SpeakingNote::OUTCOMES,
        ]);
    }

    public function update(Request $request, SpeakingNote $note): RedirectResponse
    {
        $note->update($this->validated($request));
        Audit::record('speaking.updated', "Changed the speaking note for {$note->member->full_name}", $note, ['member_id' => $note->member_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Note saved.']);

        return to_route('speaking.show', $note);
    }

    public function destroy(SpeakingNote $note): RedirectResponse
    {
        $name = $note->member->full_name;
        $note->delete();
        Audit::record('speaking.deleted', "Deleted a speaking note for {$name}", null, ['member_id' => $note->member_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Note deleted.']);

        return to_route('speaking.index');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'member_id' => ['required', 'integer', Rule::exists('members', 'id')],
            'communion_service_id' => ['required', 'integer', Rule::exists('communion_services', 'id')],
            'spoken_on' => ['required', 'date', 'before_or_equal:today'],
            'spoken_by_member_id' => ['nullable', 'integer', Rule::exists('members', 'id')],
            'spoken_by_name' => ['nullable', 'string', 'max:150'],
            'outcome' => ['required', Rule::in(array_keys(SpeakingNote::OUTCOMES))],
            'notes' => ['required', 'string', 'max:10000'],
        ]);

        $data['spoken_by_member_id'] = filled($data['spoken_by_member_id'] ?? null) ? (int) $data['spoken_by_member_id'] : null;
        // A member is linked by record; a name is kept only for someone outside the church.
        $data['spoken_by_name'] = $data['spoken_by_member_id'] ? null : (filled($data['spoken_by_name'] ?? null) ? $data['spoken_by_name'] : null);

        return $data;
    }

    /** @return array<int, array<string, mixed>> */
    private function services(): array
    {
        return CommunionService::orderByDesc('held_on')->limit(60)->get(['id', 'title', 'held_on'])
            ->map(fn (CommunionService $s) => ['id' => $s->id, 'title' => $s->title, 'held_on' => $s->held_on->toDateString()])->all();
    }

    /** @return array<string, mixed> */
    private function row(SpeakingNote $n): array
    {
        return [
            'id' => $n->id,
            'member' => ['id' => $n->member->id, 'full_name' => $n->member->full_name, 'member_number' => $n->member->member_number],
            'service' => $n->service ? ['id' => $n->service->id, 'title' => $n->service->title, 'held_on' => $n->service->held_on->toDateString()] : null,
            'spoken_on' => $n->spoken_on->toDateString(),
            'spoken_by' => $n->spokenByName(),
            'outcome' => $n->outcome,
        ];
    }
}
