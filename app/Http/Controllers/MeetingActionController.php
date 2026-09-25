<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Models\MeetingAction;
use App\Models\MeetingDecision;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Action management: the actions that follow from decisions, across every committee.
 * Decision → Action → Person responsible → Deadline → Status.
 */
class MeetingActionController extends Controller
{
    /** What the list can be narrowed to; "open" is everything still to be done, late or not. */
    private const VIEWS = ['open', 'overdue', 'pending', 'in_progress', 'completed', 'cancelled', 'all'];

    public function index(Request $request): Response
    {
        $view = in_array($request->string('view')->value(), self::VIEWS, true) ? $request->string('view')->value() : 'open';
        $committee = ctype_digit($request->string('committee')->value()) ? (int) $request->string('committee')->value() : null;
        $term = trim($request->string('q')->value());

        $base = fn () => MeetingAction::query()
            ->when($committee, fn ($q) => $q->whereHas('decision.meeting', fn ($m) => $m->where('committee_id', $committee)))
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('description', 'like', '%'.addcslashes($term, '\\%_').'%')
                ->orWhere('responsible_name', 'like', '%'.addcslashes($term, '\\%_').'%')
                ->orWhereHas('responsible', fn ($m) => $m->where('full_name', 'like', '%'.addcslashes($term, '\\%_').'%'))));

        $counts = [
            'open' => $base()->open()->count(),
            'overdue' => $base()->overdue()->count(),
            'pending' => $base()->where('status', 'pending')->count(),
            'in_progress' => $base()->where('status', 'in_progress')->count(),
            'completed' => $base()->where('status', 'completed')->count(),
            'cancelled' => $base()->where('status', 'cancelled')->count(),
            'all' => $base()->count(),
        ];

        $actions = $base()->with(['decision.meeting.committee:id,name', 'responsible:id,full_name'])
            ->when($view === 'open', fn ($q) => $q->open())
            ->when($view === 'overdue', fn ($q) => $q->overdue())
            ->when(in_array($view, ['pending', 'in_progress', 'completed', 'cancelled'], true), fn ($q) => $q->where('status', $view))
            ->orderByRaw('deadline is null')->orderBy('deadline')->orderBy('id')
            ->paginate(25)->withQueryString()
            ->through(fn (MeetingAction $a) => [...self::row($a), 'decision_text' => $a->decision->text, 'meeting' => [
                'id' => $a->decision->meeting_id, 'date' => $a->decision->meeting->meeting_date->toDateString(), 'committee' => $a->decision->meeting->committee->name,
            ]]);

        return Inertia::render('meetings/actions', [
            'actions' => $actions,
            'counts' => $counts,
            'filters' => ['view' => $view, 'committee' => $committee, 'q' => $term],
            'committees' => Committee::orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'statuses' => MeetingAction::STATUSES,
            'shown' => MeetingAction::SHOWN,
        ]);
    }

    public function store(Request $request, MeetingDecision $decision): RedirectResponse
    {
        $data = $this->validated($request);
        $action = $decision->actions()->create([...$data, 'created_by' => auth()->id()]);

        Audit::record('meeting.action_added', 'Added an action: '.str($action->description)->limit(80), $decision->meeting, ['decision' => $decision->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Action added.']);

        return back();
    }

    public function update(Request $request, MeetingAction $action): RedirectResponse
    {
        // A status-only change (from the list) leaves the rest alone.
        $data = $request->has('description') ? $this->validated($request) : $request->validate([
            'status' => ['required', Rule::in(array_keys(MeetingAction::STATUSES))],
            'completed_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $completed = $data['status'] === 'completed';
        $action->update([...$data, 'completed_on' => $completed ? ($data['completed_on'] ?? $action->completed_on ?? today()) : null]);

        Audit::record('meeting.action_updated', 'An action is now '.strtolower(MeetingAction::STATUSES[$action->status]), $action->decision->meeting, ['action' => $action->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Action saved.']);

        return back();
    }

    public function destroy(MeetingAction $action): RedirectResponse
    {
        $action->delete();

        Audit::record('meeting.action_removed', 'Removed an action', $action->decision->meeting);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Action removed.']);

        return back();
    }

    /** @return array<string, mixed> */
    public static function row(MeetingAction $a): array
    {
        return [
            'id' => $a->id,
            'decision_id' => $a->decision_id,
            'description' => $a->description,
            'responsible' => $a->responsibleName(),
            'responsible_member_id' => $a->responsible_member_id,
            'deadline' => $a->deadline?->toDateString(),
            'status' => $a->status,
            'shown' => $a->shownStatus(),
            'days_overdue' => $a->isOverdue() ? (int) $a->deadline->diffInDays(today()) : null,
            'completed_on' => $a->completed_on?->toDateString(),
            'note' => $a->note,
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
            'responsible_member_id' => ['nullable', 'integer', Rule::exists('members', 'id')],
            'responsible_name' => ['nullable', 'string', 'max:150'],
            'deadline' => ['nullable', 'date'],
            'status' => ['required', Rule::in(array_keys(MeetingAction::STATUSES))],
            'completed_on' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['description.required' => 'Say what has to be done.']);

        // A responsible person who is a member is read from their own record.
        if (filled($data['responsible_member_id'] ?? null)) {
            $data['responsible_name'] = null;
        } else {
            $data['responsible_member_id'] = null;
        }

        return $data;
    }
}
