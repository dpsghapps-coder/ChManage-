<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Models\Event;
use App\Models\EventVenue;
use App\Models\Meeting;
use App\Models\MemberGroup;
use App\Support\Audit;
use App\Support\Recurrence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Church events and the calendar: what is held, when, where, by whom. */
class EventController extends Controller
{
    private const WHEN = ['upcoming', 'past', 'all'];

    public function index(Request $request): Response
    {
        $when = in_array($request->string('when')->value(), self::WHEN, true) ? $request->string('when')->value() : 'upcoming';
        $filter = fn (string $key, array $allowed) => in_array($request->string($key)->value(), $allowed, true) ? $request->string($key)->value() : null;
        $host = $filter('host', array_keys(Event::HOSTS));
        $scope = $filter('scope', array_keys(Event::SCOPES));
        $visibility = $filter('visibility', array_keys(Event::VISIBILITIES));
        $venue = $request->string('venue')->trim()->value() ?: null;

        $events = Event::query()->with(['group:id,name', 'committee:id,name', 'organizer:id,full_name'])
            ->search($request->string('q')->value())
            ->when($when === 'upcoming', fn ($q) => $q->where('ends_on', '>=', today())->orderBy('starts_on')->orderBy('starts_at'))
            ->when($when !== 'upcoming', fn ($q) => $q->when($when === 'past', fn ($q) => $q->where('ends_on', '<', today()))->orderByDesc('starts_on')->orderByDesc('starts_at'))
            ->when($host, fn ($q) => $q->where('host_type', $host))
            ->when($scope, fn ($q) => $q->where('scope', $scope))
            ->when($visibility, fn ($q) => $q->where('visibility', $visibility))
            ->when($venue, fn ($q) => $q->where('venue', $venue))
            ->paginate(25)->withQueryString()
            ->through(fn (Event $e) => $this->row($e));

        return Inertia::render('events/index', [
            'events' => $events,
            'filters' => ['q' => $request->string('q')->value(), 'when' => $when, 'host' => $host, 'scope' => $scope, 'visibility' => $visibility, 'venue' => $venue],
            'hosts' => Event::HOSTS,
            'scopes' => Event::SCOPES,
            'visibilities' => Event::VISIBILITIES,
            'venues' => EventVenue::orderBy('sort_order')->orderBy('name')->pluck('name'),
        ]);
    }

    /** A month at a time, from the Sunday on or before the 1st to the Saturday on or after the last day. */
    public function calendar(Request $request): Response
    {
        $requested = $request->string('month')->value();
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requested) ? Carbon::createFromFormat('Y-m-d', "{$requested}-01")->startOfDay() : today()->startOfMonth();

        $from = $month->copy()->startOfWeek(0);
        $to = $month->copy()->endOfMonth()->endOfWeek(6);

        $events = Event::query()->with(['group:id,name', 'committee:id,name'])
            ->between($from->toDateString(), $to->toDateString())
            ->orderBy('starts_on')->orderBy('starts_at')->get()
            ->map(fn (Event $e) => [...$this->row($e), 'kind' => 'event', 'href' => route('events.show', $e, false)]);

        // Committee meetings share the calendar, for those who may see them.
        if (auth()->user()?->hasAnyPermission(['meetings.view'])) {
            $events = $events->concat(Meeting::query()->with(['committee:id,name', 'chairperson:id,full_name'])
                ->whereBetween('meeting_date', [$from->toDateString(), $to->toDateString()])->orderBy('meeting_date')->orderBy('starts_at')->get()
                ->map(fn (Meeting $m) => [
                    'id' => $m->id, 'title' => $m->heading(), 'host_type' => 'committee', 'host' => (string) $m->committee?->name, 'scope' => 'internal', 'visibility' => 'private',
                    'status' => $m->status === 'cancelled' ? 'cancelled' : 'scheduled', 'starts_on' => $m->meeting_date->toDateString(), 'ends_on' => $m->meeting_date->toDateString(),
                    'is_all_day' => false, 'starts_at' => $m->starts_at ? substr($m->starts_at, 0, 5) : null, 'ends_at' => $m->ends_at ? substr($m->ends_at, 0, 5) : null,
                    'venue' => $m->venue, 'organizer' => $m->chairpersonName(), 'kind' => 'meeting', 'href' => route('meetings.show', $m, false),
                ]))->sortBy(fn ($e) => [$e['starts_on'], $e['starts_at'] ?? ''])->values();
        }

        return Inertia::render('events/calendar', [
            'events' => $events->values(),
            'month' => $month->format('Y-m'),
            'label' => $month->format('F Y'),
            'previous' => $month->copy()->subMonth()->format('Y-m'),
            'next' => $month->copy()->addMonth()->format('Y-m'),
            'thisMonth' => today()->format('Y-m'),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'today' => today()->toDateString(),
            'hosts' => Event::HOSTS,
        ]);
    }

    public function create(Request $request): Response
    {
        $date = $request->string('date')->value();
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) ? $date : today()->toDateString();

        return Inertia::render('events/form', ['event' => null, 'date' => $date, ...$this->formOptions()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $dates = Recurrence::datesFrom($request, 'starts_on');
        $span = $data['starts_on'] === $data['ends_on'] ? 0 : (int) Carbon::parse($data['starts_on'])->diffInDays($data['ends_on']);

        // One event for each date; a several-day event keeps its length on every date.
        $events = collect($dates)->map(fn (string $date) => Event::create([
            ...$data, 'starts_on' => $date, 'ends_on' => Carbon::parse($date)->addDays($span)->toDateString(), 'status' => 'scheduled', 'created_by' => auth()->id(),
        ]));
        $event = $events->first();

        Audit::record('event.created', "Added the event {$event->title}".($events->count() > 1 ? " on {$events->count()} dates" : ''), $event);
        Inertia::flash('toast', ['type' => 'success', 'message' => $events->count() > 1 ? "{$event->title} added on {$events->count()} dates." : "{$event->title} added."]);

        return $events->count() > 1 ? to_route('events.calendar', ['month' => Carbon::parse($dates[0])->format('Y-m')]) : to_route('events.show', $event);
    }

    public function show(Event $event): Response
    {
        $event->load(['group:id,name', 'committee:id,name', 'organizer:id,member_number,full_name,mobile']);

        return Inertia::render('events/show', [
            'event' => [
                ...$this->row($event),
                'description' => $event->description,
                'purpose' => $event->purpose,
                'notes' => $event->notes,
                'organizer_phone' => $event->organizer?->mobile,
                'organizer_member_id' => $event->organizer_member_id,
            ],
            'hosts' => Event::HOSTS,
            'scopes' => Event::SCOPES,
            'visibilities' => Event::VISIBILITIES,
        ]);
    }

    public function edit(Event $event): Response
    {
        $event->load('organizer:id,member_number,full_name');

        return Inertia::render('events/form', [
            'event' => [
                ...$event->only([
                    'id', 'title', 'description', 'purpose', 'host_type', 'member_group_id', 'committee_id', 'scope', 'visibility', 'is_all_day',
                    'venue', 'organizer_member_id', 'organizer_name', 'notes',
                ]),
                'starts_on' => $event->starts_on->toDateString(),
                'ends_on' => $event->ends_on->toDateString(),
                'starts_at' => $this->time($event->starts_at),
                'ends_at' => $this->time($event->ends_at),
                'organizer' => $event->organizer ? ['id' => $event->organizer->id, 'member_number' => $event->organizer->member_number, 'full_name' => $event->organizer->full_name] : null,
            ],
            'date' => $event->starts_on->toDateString(),
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $event->update($this->validated($request));

        Audit::record('event.updated', "Updated the event {$event->title}", $event);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$event->title} saved."]);

        return to_route('events.show', $event);
    }

    /** Cancelling keeps the event on the calendar, struck through; it can be reinstated. */
    public function setStatus(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Event::STATUSES))]]);
        $event->update($data);

        Audit::record('event.status', "{$event->title} is now ".strtolower(Event::STATUSES[$data['status']]), $event);
        Inertia::flash('toast', ['type' => 'success', 'message' => $data['status'] === 'cancelled' ? 'Event cancelled.' : 'Event reinstated.']);

        return back();
    }

    public function destroy(Event $event): RedirectResponse
    {
        $event->delete();

        Audit::record('event.deleted', "Deleted the event {$event->title}", null, ['title' => $event->title, 'starts_on' => $event->starts_on->toDateString()]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$event->title} deleted."]);

        return to_route('events.index');
    }

    private function formOptions(): array
    {
        return [
            'hosts' => Event::HOSTS,
            'scopes' => Event::SCOPES,
            'visibilities' => Event::VISIBILITIES,
            'venues' => EventVenue::orderBy('sort_order')->orderBy('name')->pluck('name'),
            'groups' => MemberGroup::orderBy('name')->get(['id', 'name']),
            'committees' => Committee::orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
        ];
    }

    /** "08:30:00" as "08:30". */
    private function time(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }

    /** @return array<string, mixed> */
    private function row(Event $e): array
    {
        return [
            'id' => $e->id,
            'title' => $e->title,
            'host_type' => $e->host_type,
            'host' => $e->hostName(),
            'scope' => $e->scope,
            'visibility' => $e->visibility,
            'status' => $e->status,
            'starts_on' => $e->starts_on->toDateString(),
            'ends_on' => $e->ends_on->toDateString(),
            'is_all_day' => $e->is_all_day,
            'starts_at' => $this->time($e->starts_at),
            'ends_at' => $this->time($e->ends_at),
            'venue' => $e->venue,
            'organizer' => $e->organizerName(),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $request->merge(['title' => trim(preg_replace('/\s+/', ' ', (string) $request->input('title')))]);
        $host = (string) $request->input('host_type');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'purpose' => ['nullable', 'string', 'max:250'],
            'host_type' => ['required', Rule::in(array_keys(Event::HOSTS))],
            'member_group_id' => [Rule::requiredIf($host === 'group'), 'nullable', 'integer', Rule::exists('member_groups', 'id')],
            'committee_id' => [Rule::requiredIf($host === 'committee'), 'nullable', 'integer', Rule::exists('committees', 'id')],
            'scope' => ['required', Rule::in(array_keys(Event::SCOPES))],
            'visibility' => ['required', Rule::in(array_keys(Event::VISIBILITIES))],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_all_day' => ['boolean'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i'],
            'venue' => ['nullable', 'string', 'max:150'],
            'organizer_member_id' => ['nullable', 'integer', Rule::exists('members', 'id')],
            'organizer_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'member_group_id.required' => 'Choose the group hosting it.',
            'committee_id.required' => 'Choose the committee hosting it.',
            'ends_on.after_or_equal' => 'It cannot end before it starts.',
        ]);

        $data['ends_on'] = $data['ends_on'] ?? $data['starts_on'];
        $data['is_all_day'] = (bool) ($data['is_all_day'] ?? false);

        // The end time has to come after the start on a one-day event.
        if (! $data['is_all_day'] && $data['starts_on'] === $data['ends_on'] && filled($data['starts_at'] ?? null) && filled($data['ends_at'] ?? null) && $data['ends_at'] <= $data['starts_at']) {
            throw ValidationException::withMessages(['ends_at' => 'It cannot end before it starts.']);
        }

        // Only what applies is kept.
        if ($data['is_all_day']) {
            $data['starts_at'] = $data['ends_at'] = null;
        }

        $data['member_group_id'] = $data['host_type'] === 'group' ? $data['member_group_id'] : null;
        $data['committee_id'] = $data['host_type'] === 'committee' ? $data['committee_id'] : null;

        if (filled($data['organizer_member_id'] ?? null)) {
            $data['organizer_name'] = null;
        }

        return $data;
    }
}
