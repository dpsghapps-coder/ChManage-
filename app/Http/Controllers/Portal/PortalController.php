<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CommitteeMember;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Models\Member;
use App\Models\MemberRequest;
use App\Support\MemberProfile;
use App\Support\RequestTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/** A signed-in member's own page: their record, what is coming up, their committees and their meeting attendance. */
class PortalController extends Controller
{
    public function home(Request $request): Response
    {
        /** @var Member $member */
        $member = Auth::guard('member')->user();

        $profile = MemberProfile::adult($member);
        $related = MemberProfile::related($member);

        $terms = CommitteeMember::where('member_id', $member->id)->get(['committee_id', 'started_on', 'ends_on']);
        $serving = $terms->filter(fn ($t) => $t->started_on->lte(today()) && ($t->ends_on === null || $t->ends_on->gte(today())))->pluck('committee_id')->all();
        $groupIds = $related['group_ids'];

        return Inertia::render('portal/home', [
            'member' => [
                // The photo is served by a staff-only route, so the portal shows initials instead.
                ...collect($profile)->except(['photo_url', 'latitude', 'longitude', 'location_accuracy', 'status', 'profession_id'])->all(),
                'next_of_kin' => $related['next_of_kin'],
                'emergency_contact' => $related['emergency_contact'],
                'sacraments' => $related['sacraments'],
                'groups' => collect($related['groups'])->pluck('name')->all(),
                'service_records' => $related['service_records'],
                'children' => collect($related['children'])->map(fn ($c) => collect($c)->only(['name', 'member_number', 'class', 'relationship'])->all())->all(),
            ],
            'committees' => $related['committee_terms'],
            'programmes' => $this->programmes($member, $groupIds, $serving),
            'attendance' => $this->attendance($member),
            'requestTypes' => collect(RequestTypes::all())->map(fn ($t, $key) => ['key' => $key, 'label' => $t['label'], 'description' => $t['description']])->values(),
            'requests' => MemberRequest::where('member_id', $member->id)->orderByDesc('id')->limit(30)->get()->map(fn (MemberRequest $r) => [
                'id' => $r->id,
                'reference' => $r->reference(),
                'type_label' => RequestTypes::label($r->type),
                'status' => $r->status,
                'status_label' => MemberRequest::STATUSES[$r->status] ?? $r->status,
                'made_on' => $r->created_at->toDateString(),
                'answers' => $r->answers(),
                'response' => $r->response,
                'can_withdraw' => $r->status === 'submitted',
            ])->all(),
            'tab' => $request->input('tab'),
        ]);
    }

    /**
     * What is coming up for this member: church-wide public events, public events of their groups, anything they are
     * listed on, events of committees they serve on, and meetings they are down for or whose committee they serve on.
     *
     * @param  list<int>  $groupIds
     * @param  list<int>  $serving  committee ids
     * @return array<int, array<string, mixed>>
     */
    private function programmes(Member $member, array $groupIds, array $serving): array
    {
        $events = Event::query()
            ->where('status', 'scheduled')
            ->whereDate('starts_on', '>=', today())
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('host_type', 'church')->where('visibility', 'public'))
                ->orWhere(fn ($w) => $w->where('host_type', 'group')->where('visibility', 'public')->whereIn('member_group_id', $groupIds))
                ->orWhere(fn ($w) => $w->where('host_type', 'committee')->whereIn('committee_id', $serving))
                ->orWhereHas('participants', fn ($p) => $p->where('member_id', $member->id)))
            ->with(['group:id,name', 'committee:id,name'])
            ->orderBy('starts_on')->orderBy('starts_at')
            ->limit(25)->get()
            ->map(fn (Event $e) => [
                'kind' => 'event',
                'title' => $e->title,
                'date' => $e->starts_on->toDateString(),
                'time' => $e->is_all_day ? null : substr((string) $e->starts_at, 0, 5),
                'venue' => $e->venue,
                'note' => $e->hostName(),
                'invited' => $e->participants()->where('member_id', $member->id)->exists(),
            ]);

        $meetings = Meeting::query()
            ->where('status', 'scheduled')
            ->whereDate('meeting_date', '>=', today())
            ->where(fn ($q) => $q->whereIn('committee_id', $serving)->orWhereHas('attendees', fn ($a) => $a->where('member_id', $member->id)))
            ->with('committee:id,name')
            ->orderBy('meeting_date')->orderBy('starts_at')
            ->limit(25)->get()
            ->map(fn (Meeting $m) => [
                'kind' => 'meeting',
                'title' => $m->heading(),
                'date' => $m->meeting_date->toDateString(),
                'time' => substr((string) $m->starts_at, 0, 5) ?: null,
                'venue' => $m->venue,
                'note' => 'Meeting',
                'invited' => true,
            ]);

        return $events->concat($meetings)->sortBy([['date', 'asc'], ['time', 'asc']])->values()->take(30)->all();
    }

    /**
     * Meeting attendance is the only attendance recorded so far. Events show only who was invited: nobody has been
     * checked in to them yet, so nothing is claimed about turning up.
     *
     * @return array<string, mixed>
     */
    private function attendance(Member $member): array
    {
        $rows = MeetingAttendee::query()
            ->where('member_id', $member->id)
            ->whereHas('meeting', fn ($m) => $m->whereDate('meeting_date', '<', today())->where('status', '!=', 'cancelled'))
            ->with('meeting.committee:id,name')
            ->get()
            ->sortByDesc(fn (MeetingAttendee $a) => $a->meeting->meeting_date)
            ->values();

        return [
            'meetings' => $rows->take(15)->map(fn (MeetingAttendee $a) => [
                'title' => $a->meeting->heading(),
                'date' => $a->meeting->meeting_date->toDateString(),
                'mark' => $a->attendance,
            ])->all(),
            'counts' => [
                'present' => $rows->where('attendance', 'present')->count(),
                'apologies' => $rows->where('attendance', 'apologies')->count(),
                'absent' => $rows->where('attendance', 'absent')->count(),
            ],
        ];
    }
}
