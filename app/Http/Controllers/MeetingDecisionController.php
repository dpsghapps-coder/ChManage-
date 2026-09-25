<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Models\Meeting;
use App\Models\MeetingDecision;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Decisions and resolutions taken at meetings: the register across every committee, and their upkeep on a meeting. */
class MeetingDecisionController extends Controller
{
    public function index(Request $request): Response
    {
        $committee = ctype_digit($request->string('committee')->value()) ? (int) $request->string('committee')->value() : null;
        $kind = array_key_exists($request->string('kind')->value(), MeetingDecision::KINDS) ? $request->string('kind')->value() : null;
        $term = trim($request->string('q')->value());

        $decisions = MeetingDecision::query()->select('meeting_decisions.*')->with('meeting.committee:id,name')
            ->withCount(['actions as actions_total', 'actions as actions_open' => fn ($q) => $q->open()])
            ->when($committee, fn ($q) => $q->whereHas('meeting', fn ($m) => $m->where('committee_id', $committee)))
            ->when($kind, fn ($q) => $q->where('kind', $kind))
            ->when($term !== '', fn ($q) => $q->where('text', 'like', '%'.addcslashes($term, '\\%_').'%'))
            ->join('meetings', 'meetings.id', '=', 'meeting_decisions.meeting_id')
            ->orderByDesc('meetings.meeting_date')->orderBy('meeting_decisions.sort_order')
            ->paginate(25)->withQueryString()
            ->through(fn (MeetingDecision $d) => [
                'id' => $d->id, 'kind' => $d->kind, 'text' => $d->text, 'actions_total' => $d->actions_total, 'actions_open' => $d->actions_open,
                'meeting' => ['id' => $d->meeting_id, 'date' => $d->meeting->meeting_date->toDateString(), 'committee' => $d->meeting->committee->name],
            ]);

        return Inertia::render('meetings/decisions', [
            'decisions' => $decisions,
            'filters' => ['committee' => $committee, 'kind' => $kind, 'q' => $term],
            'committees' => Committee::orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'kinds' => MeetingDecision::KINDS,
        ]);
    }

    public function store(Request $request, Meeting $meeting): RedirectResponse
    {
        $decision = $meeting->decisions()->create([...$this->validated($request), 'sort_order' => (int) $meeting->decisions()->max('sort_order') + 1]);

        Audit::record('meeting.decision_added', 'Recorded a '.strtolower(MeetingDecision::KINDS[$decision->kind]).': '.str($decision->text)->limit(80), $meeting);
        Inertia::flash('toast', ['type' => 'success', 'message' => MeetingDecision::KINDS[$decision->kind].' recorded.']);

        return back();
    }

    public function update(Request $request, MeetingDecision $decision): RedirectResponse
    {
        $decision->update($this->validated($request));

        Audit::record('meeting.decision_updated', 'Updated a decision', $decision->meeting);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Saved.']);

        return back();
    }

    public function destroy(MeetingDecision $decision): RedirectResponse
    {
        $decision->delete(); // its actions go with it

        Audit::record('meeting.decision_removed', 'Removed a '.strtolower(MeetingDecision::KINDS[$decision->kind]), $decision->meeting);
        Inertia::flash('toast', ['type' => 'success', 'message' => MeetingDecision::KINDS[$decision->kind].' removed.']);

        return back();
    }

    /** @return array{kind: string, text: string} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'kind' => ['required', Rule::in(array_keys(MeetingDecision::KINDS))],
            'text' => ['required', 'string', 'max:5000'],
        ], ['text.required' => 'Write what was decided.']);
    }
}
