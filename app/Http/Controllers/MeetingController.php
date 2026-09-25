<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Models\EventVenue;
use App\Models\Meeting;
use App\Models\MeetingAction;
use App\Models\MeetingAttendee;
use App\Models\MeetingDecision;
use App\Models\Member;
use App\Support\Audit;
use App\Support\Recurrence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Committee meetings: when and where, who chaired and took the minutes, who came, the agenda and the minutes. */
class MeetingController extends Controller
{
    private const WHEN = ['upcoming', 'past', 'all'];

    public function index(Request $request): Response
    {
        $when = in_array($request->string('when')->value(), self::WHEN, true) ? $request->string('when')->value() : 'all';
        $committee = ctype_digit($request->string('committee')->value()) ? (int) $request->string('committee')->value() : null;
        $status = array_key_exists($request->string('status')->value(), Meeting::STATUSES) ? $request->string('status')->value() : null;
        $term = trim($request->string('q')->value());

        $meetings = Meeting::query()->with(['committee:id,name', 'chairperson:id,full_name'])
            ->withCount([
                'attendees as present' => fn ($q) => $q->where('attendance', 'present'),
                'decisions',
            ])
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', '%'.addcslashes($term, '\\%_').'%')
                ->orWhereHas('committee', fn ($c) => $c->where('name', 'like', '%'.addcslashes($term, '\\%_').'%'))))
            ->when($committee, fn ($q) => $q->where('committee_id', $committee))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($when === 'upcoming', fn ($q) => $q->where('meeting_date', '>=', today())->orderBy('meeting_date'))
            ->when($when !== 'upcoming', fn ($q) => $q->when($when === 'past', fn ($q) => $q->where('meeting_date', '<', today()))->orderByDesc('meeting_date'))
            ->paginate(25)->withQueryString()
            ->through(fn (Meeting $m) => [...$this->row($m), 'present' => $m->present, 'decisions' => $m->decisions_count]);

        return Inertia::render('meetings/index', [
            'meetings' => $meetings,
            'filters' => ['q' => $term, 'when' => $when, 'committee' => $committee, 'status' => $status],
            'committees' => Committee::orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'statuses' => Meeting::STATUSES,
        ]);
    }

    public function create(Request $request): Response
    {
        $date = $request->string('date')->value();
        $committee = ctype_digit($request->string('committee')->value()) ? (int) $request->string('committee')->value() : null;

        return Inertia::render('meetings/form', [
            'meeting' => null,
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) ? $date : today()->toDateString(),
            'committeeId' => $committee,
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $dates = Recurrence::datesFrom($request, 'meeting_date');

        $meetings = collect($dates)->map(fn (string $date) => Meeting::create([...$data, 'meeting_date' => $date, 'status' => 'scheduled', 'created_by' => auth()->id()]));
        $meeting = $meetings->first();

        Audit::record('meeting.created', "Added a meeting of {$meeting->committee->name} on {$meeting->meeting_date->toDateString()}".($meetings->count() > 1 ? " and {$meetings->count()} dates in all" : ''), $meeting);
        Inertia::flash('toast', ['type' => 'success', 'message' => $meetings->count() > 1 ? "{$meetings->count()} meetings added." : 'Meeting added.']);

        return $meetings->count() > 1 ? to_route('meetings.index', ['committee' => $meeting->committee_id, 'when' => 'upcoming']) : to_route('meetings.show', $meeting);
    }

    public function show(Meeting $meeting): Response
    {
        $meeting->load(['committee:id,name', 'chairperson:id,member_number,full_name', 'secretary:id,member_number,full_name', 'confirmedAt', 'attendees',
            'decisions.actions.responsible:id,full_name']);

        return Inertia::render('meetings/show', [
            'meeting' => [
                ...$this->row($meeting),
                'agenda' => $meeting->agenda,
                'minutes' => $meeting->minutes,
                'minutes_status' => $meeting->minutes_status,
                'minutes_confirmed_on' => $meeting->minutes_confirmed_on?->toDateString(),
                'confirmed_at' => $meeting->confirmedAt ? ['id' => $meeting->confirmedAt->id, 'date' => $meeting->confirmedAt->meeting_date->toDateString()] : null,
                'chairperson_member_id' => $meeting->chairperson_member_id,
                'secretary_member_id' => $meeting->secretary_member_id,
                'secretary' => $meeting->secretaryName(),
            ],
            'attendees' => $meeting->attendees->map(fn (MeetingAttendee $a) => ['id' => $a->id, 'member_id' => $a->member_id, 'name' => $a->name, 'attendance' => $a->attendance])->values(),
            'decisions' => $meeting->decisions->map(fn ($d) => [
                'id' => $d->id, 'kind' => $d->kind, 'text' => $d->text,
                'actions' => $d->actions->map(fn (MeetingAction $a) => MeetingActionController::row($a))->values(),
            ])->values(),
            // The committee's other meetings, for saying which one adopted these minutes.
            'otherMeetings' => Meeting::where('committee_id', $meeting->committee_id)->where('id', '!=', $meeting->id)->orderByDesc('meeting_date')->limit(20)->get(['id', 'meeting_date'])
                ->map(fn ($m) => ['id' => $m->id, 'date' => $m->meeting_date->toDateString()])->values(),
            'statuses' => Meeting::STATUSES,
            'attendance' => Meeting::ATTENDANCE,
            'minutesStatuses' => Meeting::MINUTES,
            'kinds' => MeetingDecision::KINDS,
            'actionStatuses' => MeetingAction::STATUSES,
        ]);
    }

    public function edit(Meeting $meeting): Response
    {
        $meeting->load(['chairperson:id,member_number,full_name', 'secretary:id,member_number,full_name']);

        return Inertia::render('meetings/form', [
            'meeting' => [
                ...$meeting->only(['id', 'committee_id', 'title', 'venue', 'chairperson_member_id', 'chairperson_name', 'secretary_member_id', 'secretary_name', 'agenda']),
                'meeting_date' => $meeting->meeting_date->toDateString(),
                'starts_at' => $this->time($meeting->starts_at),
                'ends_at' => $this->time($meeting->ends_at),
                'chairperson' => $meeting->chairperson ? ['member_number' => $meeting->chairperson->member_number, 'full_name' => $meeting->chairperson->full_name] : null,
                'secretary' => $meeting->secretary ? ['member_number' => $meeting->secretary->member_number, 'full_name' => $meeting->secretary->full_name] : null,
            ],
            'date' => $meeting->meeting_date->toDateString(),
            'committeeId' => $meeting->committee_id,
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, Meeting $meeting): RedirectResponse
    {
        $meeting->update($this->validated($request));

        Audit::record('meeting.updated', "Updated the meeting of {$meeting->committee->name} on {$meeting->meeting_date->toDateString()}", $meeting);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Meeting saved.']);

        return to_route('meetings.show', $meeting);
    }

    public function setStatus(Request $request, Meeting $meeting): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Meeting::STATUSES))]]);
        $meeting->update($data);

        Audit::record('meeting.status', 'A meeting is now '.strtolower(Meeting::STATUSES[$data['status']]), $meeting);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Meeting marked '.strtolower(Meeting::STATUSES[$data['status']]).'.']);

        return back();
    }

    /** The minutes: written text that starts as a draft and is confirmed once adopted, usually at the next meeting. */
    public function updateMinutes(Request $request, Meeting $meeting): RedirectResponse
    {
        $data = $request->validate([
            'minutes' => ['nullable', 'string', 'max:100000'],
            'minutes_status' => ['required', Rule::in(array_keys(Meeting::MINUTES))],
            'minutes_confirmed_on' => [Rule::requiredIf($request->input('minutes_status') === 'confirmed'), 'nullable', 'date', 'before_or_equal:today'],
            'minutes_confirmed_at_meeting_id' => ['nullable', 'integer', Rule::exists('meetings', 'id')->where('committee_id', $meeting->committee_id)->whereNot('id', $meeting->id)],
        ], ['minutes_confirmed_on.required' => 'Say when the minutes were confirmed.']);

        if ($data['minutes_status'] === 'confirmed' && blank($data['minutes'] ?? null)) {
            throw ValidationException::withMessages(['minutes' => 'Write the minutes before confirming them.']);
        }

        $confirmed = $data['minutes_status'] === 'confirmed';
        $meeting->update([
            'minutes' => filled($data['minutes'] ?? null) ? $data['minutes'] : null,
            'minutes_status' => $data['minutes_status'],
            'minutes_confirmed_on' => $confirmed ? $data['minutes_confirmed_on'] : null,
            'minutes_confirmed_at_meeting_id' => $confirmed ? ($data['minutes_confirmed_at_meeting_id'] ?? null) : null,
        ]);

        Audit::record('meeting.minutes', 'Saved the minutes ('.Meeting::MINUTES[$data['minutes_status']].') of the meeting on '.$meeting->meeting_date->toDateString(), $meeting);
        Inertia::flash('toast', ['type' => 'success', 'message' => $confirmed ? 'Minutes confirmed.' : 'Minutes saved.']);

        return back();
    }

    public function storeAttendee(Request $request, Meeting $meeting): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['nullable', 'integer', Rule::exists('members', 'id')],
            'name' => ['nullable', 'string', 'max:150'],
            'attendance' => ['required', Rule::in(array_keys(Meeting::ATTENDANCE))],
        ]);

        $member = filled($data['member_id'] ?? null) ? Member::find($data['member_id']) : null;
        $name = $member?->full_name ?? trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages(['member_id' => 'Search for a member, or type a name.']);
        }

        if ($member && $meeting->attendees()->where('member_id', $member->id)->exists()) {
            throw ValidationException::withMessages(['member_id' => "{$member->full_name} is already on the list."]);
        }

        $meeting->attendees()->create(['member_id' => $member?->id, 'name' => $name, 'attendance' => $data['attendance']]);

        return back();
    }

    public function updateAttendee(Request $request, Meeting $meeting, MeetingAttendee $attendee): RedirectResponse
    {
        abort_unless($attendee->meeting_id === $meeting->id, 404);

        $attendee->update($request->validate(['attendance' => ['required', Rule::in(array_keys(Meeting::ATTENDANCE))]]));

        return back();
    }

    public function destroyAttendee(Meeting $meeting, MeetingAttendee $attendee): RedirectResponse
    {
        abort_unless($attendee->meeting_id === $meeting->id, 404);
        $attendee->delete();

        return back();
    }

    public function destroy(Meeting $meeting): RedirectResponse
    {
        $meeting->delete(); // its attendees, decisions and actions go with it

        Audit::record('meeting.deleted', "Deleted the meeting of {$meeting->committee->name} on {$meeting->meeting_date->toDateString()}", null, ['committee' => $meeting->committee->name]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Meeting deleted.']);

        return to_route('meetings.index');
    }

    private function formOptions(): array
    {
        return [
            'committees' => Committee::orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'venues' => EventVenue::orderBy('sort_order')->orderBy('name')->pluck('name'),
        ];
    }

    private function time(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }

    /** @return array<string, mixed> */
    private function row(Meeting $m): array
    {
        return [
            'id' => $m->id,
            'heading' => $m->heading(),
            'committee_id' => $m->committee_id,
            'committee' => $m->committee?->name,
            'meeting_date' => $m->meeting_date->toDateString(),
            'starts_at' => $this->time($m->starts_at),
            'ends_at' => $this->time($m->ends_at),
            'venue' => $m->venue,
            'status' => $m->status,
            'chairperson' => $m->chairpersonName(),
            'minutes_status' => $m->minutes_status,
            'has_minutes' => filled($m->minutes),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $request->merge(['title' => trim(preg_replace('/\s+/', ' ', (string) $request->input('title')))]);

        $data = $request->validate([
            'committee_id' => ['required', 'integer', Rule::exists('committees', 'id')],
            'title' => ['nullable', 'string', 'max:200'],
            'meeting_date' => ['required', 'date'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after:starts_at'],
            'venue' => ['nullable', 'string', 'max:150'],
            'chairperson_member_id' => ['nullable', 'integer', Rule::exists('members', 'id')],
            'chairperson_name' => ['nullable', 'string', 'max:150'],
            'secretary_member_id' => ['nullable', 'integer', Rule::exists('members', 'id')],
            'secretary_name' => ['nullable', 'string', 'max:150'],
            'agenda' => ['nullable', 'string', 'max:10000'],
        ], ['committee_id.required' => 'Choose the committee.', 'ends_at.after' => 'It cannot end before it starts.']);

        // A chairperson or secretary who is a member is read from their own record.
        foreach (['chairperson', 'secretary'] as $role) {
            if (filled($data["{$role}_member_id"] ?? null)) {
                $data["{$role}_name"] = null;
            }
        }

        return $data;
    }
}
