<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Meeting;
use App\Models\MeetingAction;
use App\Models\MemberRequest;
use App\Support\CommitteeTerms;
use App\Support\NewcomerInsights;
use App\Support\RequestAccess;
use App\Support\RequestTypes;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The home page: cards of what needs attention, each shown only to people who may see that information.
 * Nothing is configured per role; the permissions decide. A card that is not offered is left out of the props
 * altogether (its key is absent), so the page never carries information the person may not open.
 */
class DashboardController extends Controller
{
    /** How far ahead "due soon" looks for actions. */
    private const ACTIONS_DAYS = 14;

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $can = fn (string $permission) => $user->hasAnyPermission([$permission]);
        $cards = [];

        if ($can('events.view')) {
            $cards['events'] = Event::query()->with(['group:id,name', 'committee:id,name'])
                ->where('ends_on', '>=', today())->where('status', 'scheduled')
                ->orderBy('starts_on')->orderBy('starts_at')->limit(6)->get()
                ->map(fn (Event $e) => [
                    'id' => $e->id, 'title' => $e->title, 'host' => $e->hostName(), 'host_type' => $e->host_type, 'venue' => $e->venue,
                    'starts_on' => $e->starts_on->toDateString(), 'ends_on' => $e->ends_on->toDateString(), 'is_all_day' => $e->is_all_day,
                    'starts_at' => $e->starts_at ? substr($e->starts_at, 0, 5) : null,
                ])->values();
        }

        if ($can('meetings.view')) {
            $cards['meetings'] = Meeting::query()->with('committee:id,name')
                ->where('meeting_date', '>=', today())->where('status', '!=', 'cancelled')
                ->orderBy('meeting_date')->orderBy('starts_at')->limit(5)->get()
                ->map(fn (Meeting $m) => [
                    'id' => $m->id, 'heading' => $m->heading(), 'meeting_date' => $m->meeting_date->toDateString(),
                    'starts_at' => $m->starts_at ? substr($m->starts_at, 0, 5) : null, 'venue' => $m->venue,
                ])->values();

            $cards['actions'] = $this->actions();

            // "My actions" needs the account to be linked to a member, which it is through the staff record.
            $memberId = $user->linkedMemberId();

            if ($memberId) {
                $cards['my_actions'] = $this->actions($memberId);
            }
        }

        if ($can('committees.manage')) {
            $ending = CommitteeTerms::endingSoon();
            $cards['terms'] = [
                'total' => $ending->count(),
                'people' => $ending->take(6)->map(fn ($t) => [
                    'id' => $t->id, 'committee_id' => $t->committee_id, 'committee' => $t->committee->name, 'name' => $t->member?->full_name ?? $t->name,
                    'position' => $t->position, 'ends_on' => $t->ends_on->toDateString(), 'days_left' => $t->daysLeft(),
                ])->values(),
            ];
        }

        if ($can('newcomers.view')) {
            $overview = NewcomerInsights::overview();
            $cards['newcomers'] = [
                'follow_up' => ['days' => $overview['follow_up']['days'], 'total' => $overview['follow_up']['total'], 'people' => $overview['follow_up']['people']->take(5)->values()],
                'waiting' => $overview['waiting']['total'],
                'ready' => $overview['ready']['total'],
            ];
        }

        // Member requests waiting for this person, for the types routed to them.
        $handled = RequestAccess::types($user);

        if ($handled !== []) {
            $open = MemberRequest::whereIn('type', $handled)->whereIn('status', MemberRequest::OPEN);
            $cards['requests'] = [
                'total' => (clone $open)->count(),
                'list' => (clone $open)->with('member:id,full_name')->orderBy('id')->limit(6)->get()->map(fn (MemberRequest $r) => [
                    'id' => $r->id, 'type_label' => RequestTypes::label($r->type), 'member' => $r->member->full_name,
                    'status' => $r->status, 'made_on' => $r->created_at->toDateString(),
                ])->values(),
            ];
        }

        return Inertia::render('dashboard', ['cards' => $cards, 'actionsDays' => self::ACTIONS_DAYS]);
    }

    /**
     * The actions still to do: overdue ones and those due soon, or just one member's. Totals count every overdue and
     * every open one; the list shows the most urgent few.
     *
     * @return array<string, mixed>
     */
    private function actions(?int $memberId = null): array
    {
        $scope = fn () => MeetingAction::query()->when($memberId, fn ($q) => $q->where('responsible_member_id', $memberId));
        $urgent = $scope()->open()->whereNotNull('deadline')->where('deadline', '<=', today()->addDays(self::ACTIONS_DAYS));

        // A member sees all of their open actions, undated ones too; the church-wide card shows only the urgent.
        $list = $memberId
            ? $scope()->open()->orderByRaw('deadline is null')->orderBy('deadline')
            : $urgent->orderBy('deadline');

        return [
            'overdue' => $scope()->overdue()->count(),
            'open' => $scope()->open()->count(),
            'list' => $list->with(['decision.meeting.committee:id,name', 'responsible:id,full_name'])->limit(6)->get()->map(fn (MeetingAction $a) => [
                ...MeetingActionController::row($a),
                'meeting_id' => $a->decision->meeting_id,
                'committee' => $a->decision->meeting->committee->name,
            ])->values(),
        ];
    }
}
