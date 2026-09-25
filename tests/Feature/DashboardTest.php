<?php

namespace Tests\Feature;

use App\Models\Committee;
use App\Models\CommitteeMember;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\MeetingAction;
use App\Models\MeetingDecision;
use App\Models\Member;
use App\Models\Newcomer;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    private function committee(): Committee
    {
        return Committee::where('name', 'Committee on Finance')->firstOrFail();
    }

    private function action(array $attributes = [], ?Member $owner = null): MeetingAction
    {
        $meeting = Meeting::create(['committee_id' => $this->committee()->id, 'meeting_date' => today()->subWeek()]);
        $decision = MeetingDecision::create(['meeting_id' => $meeting->id, 'text' => 'Repair the roof.']);

        return MeetingAction::create(['decision_id' => $decision->id, 'description' => 'Get quotations', 'responsible_member_id' => $owner?->id, ...$attributes]);
    }

    public function test_each_card_is_only_sent_to_people_who_may_see_it(): void
    {
        $this->action(['deadline' => today()->subDays(2), 'status' => 'pending']);
        Event::create(['title' => 'Harvest', 'starts_on' => today()->addDays(3), 'ends_on' => today()->addDays(3)]);

        // No permissions, no cards; the page still opens.
        $this->actingAs($this->userWith([]))->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('dashboard')->where('cards', []));

        $this->actingAs($this->userWith(['events.view']))->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->has('cards.events', 1)->where('cards.events.0.title', 'Harvest')->missing('cards.actions')->missing('cards.meetings')->missing('cards.terms')->missing('cards.newcomers'));

        $this->actingAs($this->userWith(['meetings.view']))->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('cards.actions.overdue', 1)->where('cards.actions.list.0.shown', 'overdue')->has('cards.meetings')->missing('cards.events')->missing('cards.my_actions'));

        $this->actingAs($this->userWith(['committees.view']))->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->missing('cards.terms'));
        $this->actingAs($this->userWith(['committees.manage']))->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('cards.terms.total', 0));
        $this->actingAs($this->userWith(['newcomers.view']))->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('cards.newcomers.follow_up.total', 0)->where('cards.newcomers.waiting', 0)->where('cards.newcomers.ready', 0));
    }

    public function test_upcoming_events_leave_out_the_past_and_the_cancelled(): void
    {
        Event::create(['title' => 'Over', 'starts_on' => today()->subDays(3), 'ends_on' => today()->subDays(3)]);
        Event::create(['title' => 'Cancelled', 'starts_on' => today()->addDay(), 'ends_on' => today()->addDay(), 'status' => 'cancelled']);
        Event::create(['title' => 'Running', 'starts_on' => today()->subDay(), 'ends_on' => today()->addDay()]);
        Event::create(['title' => 'Later', 'starts_on' => today()->addWeek(), 'ends_on' => today()->addWeek()]);

        $this->actingAs($this->userWith(['events.view']))->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('cards.events', fn ($events) => collect($events)->pluck('title')->all() === ['Running', 'Later']));
    }

    public function test_the_action_cards_show_the_urgent_ones_and_the_persons_own(): void
    {
        $mine = Member::create(['member_number' => 'M1', 'full_name' => 'Mensah Efua', 'status' => 'active', 'sex' => 'female']);
        $other = Member::create(['member_number' => 'M2', 'full_name' => 'Owusu Kofi', 'status' => 'active', 'sex' => 'male']);
        $late = $this->action(['deadline' => today()->subDays(4), 'status' => 'in_progress', 'description' => 'Mine late'], $mine);
        $this->action(['deadline' => today()->addDays(30), 'description' => 'Mine far'], $mine);
        $this->action(['description' => 'Mine undated'], $mine);
        $this->action(['deadline' => today()->addDays(5), 'description' => 'Theirs soon'], $other);
        $this->action(['deadline' => today()->subDay(), 'status' => 'completed', 'description' => 'Done'], $mine);

        // Church-wide: overdue or due within two weeks, soonest first; the far-off and undated ones are left out.
        $viewer = $this->userWith(['meetings.view']);
        $this->actingAs($viewer)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('cards.actions.overdue', 1)->where('cards.actions.open', 4)
            ->where('cards.actions.list', fn ($rows) => collect($rows)->pluck('description')->all() === ['Mine late', 'Theirs soon']));

        // "My actions" needs the account linked to a member, through the staff record; it lists all of theirs, dated first.
        $staff = Staff::create(['full_name' => 'Mensah Efua', 'member_id' => $mine->id]);
        $linked = $this->userWith(['meetings.view'], ['staff_id' => $staff->id]);
        $this->actingAs($linked)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('cards.my_actions.overdue', 1)->where('cards.my_actions.open', 3)
            ->where('cards.my_actions.list', fn ($rows) => collect($rows)->pluck('description')->all() === ['Mine late', 'Mine far', 'Mine undated']));
        $this->assertNotNull($late);
    }

    public function test_terms_ending_and_newcomers_needing_follow_up_reach_those_who_manage_them(): void
    {
        $member = Member::create(['member_number' => 'M1', 'full_name' => 'Mensah Efua', 'status' => 'active', 'sex' => 'female']);
        CommitteeMember::create(['committee_id' => $this->committee()->id, 'member_id' => $member->id, 'name' => $member->full_name, 'started_on' => today()->subYears(3), 'ends_on' => today()->addDays(20)]);
        Newcomer::create(['first_visit_on' => today()->subDays(100), 'surname' => 'Boateng', 'first_name' => 'Ama']);

        $user = $this->userWith(['committees.manage', 'newcomers.view']);
        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('cards.terms.total', 1)->where('cards.terms.people.0.days_left', 20)
            ->where('cards.newcomers.follow_up.total', 1)->where('cards.newcomers.follow_up.people.0.name', 'Ama Boateng')->where('cards.newcomers.waiting', 1));
    }
}
