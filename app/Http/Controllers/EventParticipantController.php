<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Models\CommitteeMember;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Who an event is for: added one by one, or a whole service group or committee at once. A group's members are those
 * who have it ticked on their bio (active members only); a committee's are those serving on the day of the event.
 */
class EventParticipantController extends Controller
{
    public function store(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['nullable', 'integer', Rule::exists('members', 'id')],
            'name' => ['nullable', 'string', 'max:150'],
            'group_id' => ['nullable', 'integer', Rule::exists('member_groups', 'id')],
            'committee_id' => ['nullable', 'integer', Rule::exists('committees', 'id')],
        ]);

        if (filled($data['group_id'] ?? null)) {
            $group = MemberGroup::findOrFail($data['group_id']);
            $people = Member::query()->where('status', 'active')->whereIn('id', fn ($q) => $q->select('member_id')->from('member_group_memberships')->where('member_group_id', $group->id))
                ->get(['id', 'full_name'])->map(fn (Member $m) => [$m->id, $m->full_name]);

            return $this->add($event, $people, $group->name, 'Nobody in '.$group->name.' is on the members list. Tick the group on their bios first.');
        }

        if (filled($data['committee_id'] ?? null)) {
            $committee = Committee::findOrFail($data['committee_id']);
            $day = $event->starts_on->toDateString();
            $people = CommitteeMember::query()->where('committee_id', $committee->id)->where('started_on', '<=', $day)
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $day))
                ->get(['member_id', 'name'])->map(fn (CommitteeMember $t) => [$t->member_id, $t->name]);

            return $this->add($event, $people, $committee->name, "Nobody was serving on {$committee->name} on {$day}. Add its members on the Committees page first.");
        }

        $member = filled($data['member_id'] ?? null) ? Member::find($data['member_id']) : null;
        $name = $member?->full_name ?? trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages(['member_id' => 'Search for a member, or type a name.']);
        }

        if ($member && $event->participants()->where('member_id', $member->id)->exists()) {
            throw ValidationException::withMessages(['member_id' => "{$member->full_name} is already on the list."]);
        }

        $event->participants()->create(['member_id' => $member?->id, 'name' => $name]);
        Audit::record('event.participant_added', "Added {$name} to {$event->title}", $event);

        return back();
    }

    public function destroy(Event $event, EventParticipant $participant): RedirectResponse
    {
        abort_unless($participant->event_id === $event->id, 404);
        $participant->delete();

        return back();
    }

    /** Takes everyone off the list, for starting again. */
    public function clear(Event $event): RedirectResponse
    {
        $count = $event->participants()->delete();

        Audit::record('event.participants_cleared', "Cleared the participants of {$event->title}", $event, ['removed' => $count]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$count} removed."]);

        return back();
    }

    /**
     * Adds people from a group or committee, leaving out anyone already on the list.
     *
     * @param  Collection<int, array{0: ?int, 1: string}>  $people  member id (or null) and name
     */
    private function add(Event $event, $people, string $source, string $nobody): RedirectResponse
    {
        if ($people->isEmpty()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $nobody]);

            return back();
        }

        $already = $event->participants()->whereNotNull('member_id')->pluck('member_id')->all();
        $guests = $event->participants()->whereNull('member_id')->pluck('name')->map(fn ($n) => mb_strtolower($n))->all();
        $added = 0;

        foreach ($people as [$memberId, $name]) {
            // A member is matched by their record, a guest (who has none) by name, so adding twice never repeats anyone.
            if ($memberId ? in_array($memberId, $already, true) : in_array(mb_strtolower($name), $guests, true)) {
                continue;
            }

            $event->participants()->create(['member_id' => $memberId, 'name' => $name, 'source' => $source]);
            $memberId ? $already[] = $memberId : $guests[] = mb_strtolower($name);
            $added++;
        }

        $skipped = $people->count() - $added;
        Audit::record('event.participants_added', "Added {$added} from {$source} to {$event->title}", $event, ['source' => $source, 'skipped' => $skipped]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$added} added from {$source}".($skipped ? " ({$skipped} already on the list)." : '.')]);

        return back();
    }
}
