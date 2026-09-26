<?php

namespace App\Http\Controllers;

use App\Models\CommunionAttendee;
use App\Models\CommunionService;
use App\Models\Member;
use App\Models\SpeakingNote;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Communion services and who received communion at each. The list of a service is started from the active
 * communicants; members marked as non-communicants are never on it, so they are never counted as absent.
 */
class CommunionController extends Controller
{
    public function index(): Response
    {
        $services = CommunionService::query()
            ->withCount([
                'attendees as expected',
                'attendees as received' => fn ($q) => $q->where('status', 'present'),
            ])
            ->orderByDesc('held_on')->orderByDesc('id')
            ->paginate(20)
            ->through(fn (CommunionService $s) => [
                ...$this->row($s),
                'expected' => $s->expected,
                'received' => $s->received,
            ]);

        return Inertia::render('communion/index', ['services' => $services, 'statuses' => CommunionService::STATUSES]);
    }

    public function create(): Response
    {
        return Inertia::render('communion/form', ['service' => null, 'statuses' => CommunionService::STATUSES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $service = CommunionService::create([...$this->validated($request), 'created_by' => $request->user()->id]);

        Audit::record('communion.created', "Created the communion service {$service->title}", $service);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Communion service created. Load the communicants to take attendance.']);

        return to_route('communion.show', $service);
    }

    public function show(Request $request, CommunionService $service): Response
    {
        $sees = $request->user()->hasPermission('speaking.view');

        // Only people who may read the Speaking notes are told who was spoken to; the notes themselves stay there.
        $spoken = $sees
            ? SpeakingNote::where('communion_service_id', $service->id)->pluck('outcome', 'member_id')
            : collect();

        $attendees = $service->attendees()->with('member:id,member_number,full_name,generational_group')->get()
            ->sortBy(fn (CommunionAttendee $a) => mb_strtolower((string) $a->member?->full_name))->values();

        return Inertia::render('communion/show', [
            'service' => $this->row($service) + ['note' => $service->note],
            'attendees' => $attendees->map(fn (CommunionAttendee $a) => [
                'id' => $a->id,
                'member_id' => $a->member_id,
                'member_number' => $a->member?->member_number,
                'name' => $a->member?->full_name,
                'group' => $a->member?->generational_group,
                'status' => $a->status,
                'spoken' => $sees ? ($spoken[$a->member_id] ?? null) : false,
            ])->all(),
            'attendance' => CommunionService::ATTENDANCE,
            'statuses' => CommunionService::STATUSES,
            'communicants' => Member::where('status', 'active')->where('is_communicant', true)->count(),
            'showsSpeaking' => $sees,
        ]);
    }

    public function edit(CommunionService $service): Response
    {
        return Inertia::render('communion/form', ['service' => $this->row($service) + ['note' => $service->note], 'statuses' => CommunionService::STATUSES]);
    }

    public function update(Request $request, CommunionService $service): RedirectResponse
    {
        $service->update($this->validated($request));
        Audit::record('communion.updated', "Updated the communion service {$service->title}", $service);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Saved.']);

        return to_route('communion.show', $service);
    }

    public function destroy(CommunionService $service): RedirectResponse
    {
        if ($service->speakingNotes()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Speaking notes were written for this service, so it cannot be deleted. Cancel it instead.']);

            return back();
        }

        $service->delete();
        Audit::record('communion.deleted', "Deleted the communion service {$service->title}", null, ['held_on' => $service->held_on->toDateString()]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Communion service deleted.']);

        return to_route('communion.index');
    }

    /** Puts every active communicant on the list, leaving out anyone already on it. */
    public function communicants(CommunionService $service): RedirectResponse
    {
        $already = $service->attendees()->pluck('member_id')->all();
        $add = Member::where('status', 'active')->where('is_communicant', true)->whereNotIn('id', $already)->pluck('id');

        foreach ($add->chunk(500) as $ids) {
            CommunionAttendee::insert($ids->map(fn ($id) => ['communion_service_id' => $service->id, 'member_id' => $id, 'status' => 'absent', 'created_at' => now(), 'updated_at' => now()])->all());
        }

        Audit::record('communion.communicants_loaded', "Loaded {$add->count()} communicants into {$service->title}", $service);
        Inertia::flash('toast', ['type' => $add->isEmpty() ? 'info' : 'success', 'message' => $add->isEmpty() ? 'Every active communicant is already on the list.' : "{$add->count()} communicants added."]);

        return back();
    }

    /** Adds one member, for someone who is not marked as a communicant but received communion. */
    public function addAttendee(Request $request, CommunionService $service): RedirectResponse
    {
        $data = $request->validate(['member_id' => ['required', 'integer', Rule::exists('members', 'id')]]);

        if ($service->attendees()->where('member_id', $data['member_id'])->exists()) {
            throw ValidationException::withMessages(['member_id' => 'That member is already on the list.']);
        }

        $service->attendees()->create(['member_id' => $data['member_id'], 'status' => 'present']);

        return back();
    }

    /** Saves the whole sheet at once: `statuses` maps each row to received, absent or excused. */
    public function attendance(Request $request, CommunionService $service): RedirectResponse
    {
        $data = $request->validate([
            'statuses' => ['required', 'array'],
            'statuses.*' => ['required', Rule::in(array_keys(CommunionService::ATTENDANCE))],
        ]);

        $rows = $service->attendees()->whereIn('id', array_keys($data['statuses']))->get();

        foreach ($rows as $row) {
            if ($row->status !== $data['statuses'][$row->id]) {
                $row->update(['status' => $data['statuses'][$row->id]]);
            }
        }

        $received = $service->attendees()->where('status', 'present')->count();
        Audit::record('communion.attendance_saved', "Saved attendance for {$service->title}", $service, ['received' => $received]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Attendance saved. {$received} received communion."]);

        return back();
    }

    public function removeAttendee(CommunionService $service, CommunionAttendee $attendee): RedirectResponse
    {
        abort_unless($attendee->communion_service_id === $service->id, 404);
        $attendee->delete();

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'held_on' => ['required', 'date'],
            'venue' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in(array_keys(CommunionService::STATUSES))],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        foreach (['venue', 'note'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? $data[$field] : null;
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function row(CommunionService $s): array
    {
        return ['id' => $s->id, 'title' => $s->title, 'held_on' => $s->held_on->toDateString(), 'venue' => $s->venue, 'status' => $s->status];
    }
}
