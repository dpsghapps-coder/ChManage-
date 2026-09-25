<?php

namespace App\Http\Controllers;

use App\Models\ChurchSetting;
use App\Models\Committee;
use App\Models\CommitteeMember;
use App\Models\Member;
use App\Models\ServicePosition;
use App\Support\Audit;
use App\Support\CommitteeTerms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who serves on each committee, term by term. Terms can end (they then move to past members), be renewed, or be
 * ended early. A committee's term length and maximum number of terms are guidance: the list warns, it never blocks.
 */
class CommitteeMemberController extends Controller
{
    /** Every committee with how many serve now, and the terms that are ending. */
    public function overview(): Response
    {
        $current = CommitteeMember::current()->selectRaw('committee_id, count(*) as n')->groupBy('committee_id')->pluck('n', 'committee_id');
        $ending = CommitteeTerms::endingSoon();

        return Inertia::render('committees/overview', [
            'committees' => Committee::orderBy('sort_order')->orderBy('name')->get()->map(fn (Committee $c) => [
                'id' => $c->id, 'name' => $c->name, 'term_years' => $c->term_years, 'max_terms' => $c->max_terms,
                'members' => (int) ($current[$c->id] ?? 0), 'ending' => $ending->where('committee_id', $c->id)->count(),
            ])->values(),
            'ending' => $ending->take(25)->map(fn (CommitteeMember $m) => $this->endingRow($m))->values(),
            'endingTotal' => $ending->count(),
            'warnDays' => ChurchSetting::termWarningDays(),
        ]);
    }

    public function show(Committee $committee): Response
    {
        $memberships = $committee->members()->with(['member:id,member_number,full_name,mobile', 'successor:id,renewed_from_id'])->get();
        $warn = ChurchSetting::termWarningDays();
        $row = fn (CommitteeMember $m) => [
            'id' => $m->id,
            'member_id' => $m->member_id,
            'name' => $m->member?->full_name ?? $m->name,
            'member_number' => $m->member?->member_number,
            'phone' => $m->member?->mobile,
            'position' => $m->position,
            'started_on' => $m->started_on->toDateString(),
            'ends_on' => $m->ends_on?->toDateString(),
            'end_note' => $m->end_note,
            'days_left' => $m->daysLeft(),
            'ending' => $m->daysLeft() !== null && $m->daysLeft() >= 0 && $m->daysLeft() <= $warn,
            'term_number' => CommitteeTerms::termNumber($m),
            'over_limit' => CommitteeTerms::overLimit($committee, $m),
            'renewed' => $m->successor !== null,
        ];

        [$current, $past] = $memberships->partition(fn (CommitteeMember $m) => $m->ends_on === null || $m->ends_on->gte(today()));

        return Inertia::render('committees/members', [
            'committee' => $committee->only(['id', 'name', 'term_years', 'max_terms']),
            'current' => $current->sortBy(fn ($m) => [$m->ends_on?->toDateString() ?? '9999', $m->name])->map($row)->values(),
            'past' => $past->sortByDesc('ends_on')->map($row)->values(),
            'positions' => ServicePosition::byType()['committee'] ?? [],
            'warnDays' => $warn,
        ]);
    }

    /** The committee's term length and the most terms one person should serve. */
    public function updateRules(Request $request, Committee $committee): RedirectResponse
    {
        $data = $request->validate([
            'term_years' => ['nullable', 'integer', 'between:1,10'],
            'max_terms' => ['nullable', 'integer', 'between:1,10'],
        ], ['term_years.between' => 'Choose between 1 and 10 years.', 'max_terms.between' => 'Choose between 1 and 10 terms.']);

        $committee->update(['term_years' => $data['term_years'] ?? null, 'max_terms' => $data['max_terms'] ?? null]);

        Audit::record('committee.rules', "Set the term rules of {$committee->name}", null, $data);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Term rules saved.']);

        return back();
    }

    public function store(Request $request, Committee $committee): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['nullable', 'integer', 'exists:members,id'],
            'name' => ['nullable', 'string', 'max:150'],
            'position' => ['nullable', 'string', 'max:100'],
            'started_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after:started_on'],
        ], ['started_on.required' => 'Say when the term starts.', 'ends_on.after' => 'The term has to end after it starts.']);

        $member = filled($data['member_id'] ?? null) ? Member::find($data['member_id']) : null;
        $name = $member?->full_name ?? trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages(['member_id' => 'Search for a member, or type a name.']);
        }

        if ($member && $committee->members()->current()->where('member_id', $member->id)->exists()) {
            throw ValidationException::withMessages(['member_id' => "{$member->full_name} is already serving on {$committee->name}."]);
        }

        $membership = $committee->members()->create([
            'member_id' => $member?->id, 'name' => $name, 'position' => $this->clean($data['position'] ?? null),
            'started_on' => $data['started_on'], 'ends_on' => $data['ends_on'] ?? CommitteeTerms::endFor($committee, $data['started_on']),
        ]);

        Audit::record('committee.member_added', "Added {$name} to {$committee->name}", null, ['committee' => $committee->name, 'member_id' => $member?->id]);
        $this->flashAfterAdding($committee, $membership);

        return back();
    }

    public function update(Request $request, CommitteeMember $membership): RedirectResponse
    {
        $data = $request->validate([
            'position' => ['nullable', 'string', 'max:100'],
            'started_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after:started_on'],
        ], ['ends_on.after' => 'The term has to end after it starts.']);

        $membership->update(['position' => $this->clean($data['position'] ?? null), 'started_on' => $data['started_on'], 'ends_on' => $data['ends_on'] ?? null]);

        Audit::record('committee.member_updated', "Updated {$membership->name} on {$membership->committee->name}", null, ['membership' => $membership->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Term saved.']);

        return back();
    }

    /** The next term, from the day this one ends (or today when it has already ended), with the same position. */
    public function renew(CommitteeMember $membership): RedirectResponse
    {
        if ($membership->successor()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'This term has already been renewed.']);

            return back();
        }

        $committee = $membership->committee;
        $start = $membership->ends_on && $membership->ends_on->gte(today()) ? $membership->ends_on : today();

        $next = $committee->members()->create([
            'member_id' => $membership->member_id, 'name' => $membership->name, 'position' => $membership->position,
            'started_on' => $start->toDateString(), 'ends_on' => CommitteeTerms::endFor($committee, $start->toDateString()), 'renewed_from_id' => $membership->id,
        ]);

        Audit::record('committee.member_renewed', "Renewed {$membership->name} on {$committee->name}", null, ['membership' => $next->id]);
        $this->flashAfterAdding($committee, $next, 'renewed');

        return back();
    }

    /** Ends the term now (or on the date given), for someone who resigns or steps down early. */
    public function end(Request $request, CommitteeMember $membership): RedirectResponse
    {
        $data = $request->validate([
            'ends_on' => ['nullable', 'date', 'after_or_equal:'.$membership->started_on->toDateString()],
            'end_note' => ['nullable', 'string', 'max:250'],
        ], ['ends_on.after_or_equal' => 'The term cannot end before it started.']);

        $membership->update(['ends_on' => $data['ends_on'] ?? today()->toDateString(), 'end_note' => $this->clean($data['end_note'] ?? null)]);

        Audit::record('committee.member_ended', "Ended the term of {$membership->name} on {$membership->committee->name}", null, ['membership' => $membership->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Term ended.']);

        return back();
    }

    public function destroy(CommitteeMember $membership): RedirectResponse
    {
        $membership->delete();

        Audit::record('committee.member_removed', "Removed {$membership->name} from {$membership->committee->name}", null, ['membership' => $membership->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Removed from the list.']);

        return back();
    }

    /** Success, or a warning when the person is now past the committee's maximum number of terms. */
    private function flashAfterAdding(Committee $committee, CommitteeMember $membership, string $verb = 'added'): void
    {
        if (CommitteeTerms::overLimit($committee, $membership)) {
            $number = CommitteeTerms::termNumber($membership);
            Inertia::flash('toast', ['type' => 'warning', 'message' => "{$membership->name} {$verb}, but this is term {$number} and {$committee->name} allows {$committee->max_terms}."]);

            return;
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$membership->name} {$verb}."]);
    }

    /** @return array<string, mixed> */
    private function endingRow(CommitteeMember $m): array
    {
        return [
            'id' => $m->id, 'committee_id' => $m->committee_id, 'committee' => $m->committee->name,
            'member_id' => $m->member_id, 'name' => $m->member?->full_name ?? $m->name, 'position' => $m->position,
            'ends_on' => $m->ends_on->toDateString(), 'days_left' => $m->daysLeft(),
        ];
    }

    private function clean(?string $value): ?string
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) $value));

        return $value === '' ? null : $value;
    }
}
