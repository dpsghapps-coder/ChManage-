<?php

namespace Tests\Feature;

use App\Models\Committee;
use App\Models\Meeting;
use App\Models\MeetingAction;
use App\Models\MeetingDecision;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MeetingsTest extends TestCase
{
    use RefreshDatabase;

    private function committee(string $name = 'Committee on Finance'): Committee
    {
        return Committee::where('name', $name)->firstOrFail();
    }

    private function member(string $number = 'M1', string $name = 'Mensah Efua'): Member
    {
        return Member::create(['member_number' => $number, 'full_name' => $name, 'status' => 'active', 'sex' => 'female', 'mobile' => '0244000111', 'date_of_birth' => today()->subYears(40)]);
    }

    private function meeting(array $attributes = []): Meeting
    {
        return Meeting::create(['committee_id' => $this->committee()->id, 'meeting_date' => today()->subWeek(), ...$attributes]);
    }

    private function decision(?Meeting $meeting = null, array $attributes = []): MeetingDecision
    {
        return MeetingDecision::create(['meeting_id' => ($meeting ?? $this->meeting())->id, 'text' => 'Repair the roof.', ...$attributes]);
    }

    private function action(MeetingDecision $decision, array $attributes = []): MeetingAction
    {
        return MeetingAction::create(['decision_id' => $decision->id, 'description' => 'Get three quotations', ...$attributes]);
    }

    public function test_the_pages_follow_the_meeting_permissions(): void
    {
        $meeting = $this->meeting();
        $decision = $this->decision($meeting);
        $action = $this->action($decision);
        $viewer = $this->userWith(['meetings.view']);

        foreach (['meetings.index', 'decisions.index', 'actions.index'] as $route) {
            $this->actingAs($this->userWith([]))->get(route($route))->assertForbidden();
            $this->actingAs($viewer)->get(route($route))->assertOk();
        }
        $this->actingAs($viewer)->get(route('meetings.show', $meeting))->assertOk();
        $this->actingAs($viewer)->get(route('meetings.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('meetings.store'), ['committee_id' => $this->committee()->id, 'meeting_date' => '2026-10-01'])->assertForbidden();
        $this->actingAs($viewer)->put(route('meetings.minutes', $meeting), ['minutes_status' => 'draft'])->assertForbidden();
        $this->actingAs($viewer)->post(route('meetings.attendees.store', $meeting), ['name' => 'X', 'attendance' => 'present'])->assertForbidden();
        $this->actingAs($viewer)->post(route('meetings.decisions.store', $meeting), ['kind' => 'decision', 'text' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->post(route('actions.store', $decision), ['description' => 'X', 'status' => 'pending'])->assertForbidden();
        $this->actingAs($viewer)->put(route('actions.update', $action), ['status' => 'completed'])->assertForbidden();
        $this->actingAs($viewer)->delete(route('meetings.destroy', $meeting))->assertForbidden();
    }

    public function test_a_meeting_is_added_with_its_officers_and_checked(): void
    {
        $user = $this->userWith(['meetings.view', 'meetings.manage']);
        $chair = $this->member('M1', 'Mensah Efua');
        $form = ['committee_id' => $this->committee()->id, 'meeting_date' => '2026-10-01', 'starts_at' => '18:00', 'ends_at' => '19:30', 'venue' => 'Manse', 'agenda' => "1. Prayer\n2. Reports"];

        $this->actingAs($user)->post(route('meetings.store'), [...$form, 'chairperson_member_id' => $chair->id, 'chairperson_name' => 'ignored', 'secretary_name' => 'The Clerk'])->assertSessionHasNoErrors();
        $meeting = Meeting::firstOrFail();
        $this->assertSame(['scheduled', 'Mensah Efua', 'The Clerk', 'Committee on Finance'], [$meeting->status, $meeting->chairpersonName(), $meeting->secretaryName(), $meeting->heading()]);
        $this->assertNull($meeting->chairperson_name);

        $this->actingAs($user)->post(route('meetings.store'), [...$form, 'committee_id' => ''])->assertSessionHasErrors('committee_id');
        $this->actingAs($user)->post(route('meetings.store'), [...$form, 'ends_at' => '17:00'])->assertSessionHasErrors('ends_at');
        $this->actingAs($user)->post(route('meetings.store'), [...$form, 'title' => '  Roof   repairs '])->assertSessionHasNoErrors();
        $this->assertSame('Roof repairs', Meeting::where('title', 'like', 'Roof%')->firstOrFail()->heading());

        $this->actingAs($user)->put(route('meetings.status', $meeting), ['status' => 'held'])->assertSessionHasNoErrors();
        $this->assertSame('held', $meeting->fresh()->status);
        $this->actingAs($user)->put(route('meetings.status', $meeting), ['status' => 'bogus'])->assertSessionHasErrors('status');
    }

    public function test_attendees_are_members_or_typed_names_marked_present_apologies_or_absent(): void
    {
        $user = $this->userWith(['meetings.view', 'meetings.manage']);
        $meeting = $this->meeting();
        $member = $this->member();

        $this->actingAs($user)->post(route('meetings.attendees.store', $meeting), ['member_id' => $member->id, 'attendance' => 'present'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('meetings.attendees.store', $meeting), ['member_id' => $member->id, 'attendance' => 'present'])->assertSessionHasErrors('member_id');
        $this->actingAs($user)->post(route('meetings.attendees.store', $meeting), ['name' => 'Rev. Guest', 'attendance' => 'apologies'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('meetings.attendees.store', $meeting), ['attendance' => 'present'])->assertSessionHasErrors('member_id');
        $this->actingAs($user)->post(route('meetings.attendees.store', $meeting), ['name' => 'X', 'attendance' => 'late'])->assertSessionHasErrors('attendance');

        $attendee = $meeting->attendees()->where('member_id', $member->id)->firstOrFail();
        $this->assertSame('Mensah Efua', $attendee->name);
        $this->actingAs($user)->put(route('meetings.attendees.update', [$meeting, $attendee]), ['attendance' => 'absent'])->assertSessionHasNoErrors();
        $this->assertSame('absent', $attendee->fresh()->attendance);

        // An attendee of another meeting cannot be reached through this one.
        $other = $this->meeting()->attendees()->create(['name' => 'Elsewhere', 'attendance' => 'present']);
        $this->actingAs($user)->delete(route('meetings.attendees.destroy', [$meeting, $other]))->assertNotFound();

        $this->actingAs($user)->get(route('meetings.show', $meeting))->assertInertia(fn (Assert $page) => $page->component('meetings/show')->has('attendees', 2));
        $this->actingAs($user)->delete(route('meetings.attendees.destroy', [$meeting, $attendee]))->assertSessionHasNoErrors();
        $this->assertSame(1, $meeting->attendees()->count());
    }

    public function test_minutes_are_a_draft_until_confirmed(): void
    {
        $user = $this->userWith(['meetings.view', 'meetings.manage']);
        $meeting = $this->meeting();
        $next = $this->meeting(['meeting_date' => today()->addMonth()]);
        $elsewhere = $this->meeting(['committee_id' => $this->committee('Harvest Committee')->id]);

        $this->actingAs($user)->put(route('meetings.minutes', $meeting), ['minutes' => 'The meeting opened with prayer.', 'minutes_status' => 'draft'])->assertSessionHasNoErrors();
        $this->assertSame(['The meeting opened with prayer.', 'draft'], [$meeting->fresh()->minutes, $meeting->fresh()->minutes_status]);

        // Confirming needs the minutes and the date; the adopting meeting must be this committee's own.
        $this->actingAs($user)->put(route('meetings.minutes', $meeting), ['minutes_status' => 'confirmed', 'minutes_confirmed_on' => today()->toDateString(), 'minutes' => ''])->assertSessionHasErrors('minutes');
        $this->actingAs($user)->put(route('meetings.minutes', $meeting), ['minutes' => 'Text', 'minutes_status' => 'confirmed'])->assertSessionHasErrors('minutes_confirmed_on');
        $this->actingAs($user)->put(route('meetings.minutes', $meeting), ['minutes' => 'Text', 'minutes_status' => 'confirmed', 'minutes_confirmed_on' => today()->toDateString(), 'minutes_confirmed_at_meeting_id' => $elsewhere->id])
            ->assertSessionHasErrors('minutes_confirmed_at_meeting_id');
        $this->actingAs($user)->put(route('meetings.minutes', $meeting), ['minutes' => 'Text', 'minutes_status' => 'confirmed', 'minutes_confirmed_on' => today()->toDateString(), 'minutes_confirmed_at_meeting_id' => $next->id])
            ->assertSessionHasNoErrors();
        $this->assertSame(['confirmed', $next->id], [$meeting->fresh()->minutes_status, $meeting->fresh()->minutes_confirmed_at_meeting_id]);
        $this->actingAs($user)->get(route('meetings.show', $meeting))->assertInertia(fn (Assert $page) => $page->where('meeting.minutes_status', 'confirmed')->where('meeting.confirmed_at.id', $next->id));

        // Back to a draft forgets the confirmation.
        $this->actingAs($user)->put(route('meetings.minutes', $meeting), ['minutes' => 'Text', 'minutes_status' => 'draft']);
        $this->assertSame(['draft', null, null], [$meeting->fresh()->minutes_status, $meeting->fresh()->minutes_confirmed_on, $meeting->fresh()->minutes_confirmed_at_meeting_id]);
    }

    public function test_decisions_carry_actions_and_overdue_follows_the_deadline(): void
    {
        $user = $this->userWith(['meetings.view', 'meetings.manage']);
        $meeting = $this->meeting();
        $owner = $this->member();

        $this->actingAs($user)->post(route('meetings.decisions.store', $meeting), ['kind' => 'resolution', 'text' => 'RESOLVED that the budget be approved.'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('meetings.decisions.store', $meeting), ['kind' => 'nonsense', 'text' => 'X'])->assertSessionHasErrors('kind');
        $decision = $meeting->decisions()->firstOrFail();

        $this->actingAs($user)->post(route('actions.store', $decision), ['description' => 'Send it to the Session', 'responsible_member_id' => $owner->id, 'responsible_name' => 'ignored', 'deadline' => today()->addWeek()->toDateString(), 'status' => 'pending'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('actions.store', $decision), ['description' => 'Late one', 'responsible_name' => 'Treasurer', 'deadline' => today()->subDays(3)->toDateString(), 'status' => 'in_progress'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('actions.store', $decision), ['description' => '', 'status' => 'pending'])->assertSessionHasErrors('description');
        $this->actingAs($user)->post(route('actions.store', $decision), ['description' => 'X', 'status' => 'overdue'])->assertSessionHasErrors('status');

        [$soon, $late] = [MeetingAction::where('description', 'Send it to the Session')->firstOrFail(), MeetingAction::where('description', 'Late one')->firstOrFail()];
        $this->assertSame(['Mensah Efua', null, 'pending'], [$soon->responsibleName(), $soon->responsible_name, $soon->shownStatus()]);
        $this->assertSame(['Treasurer', 'overdue', true], [$late->responsibleName(), $late->shownStatus(), $late->isOverdue()]);

        $this->actingAs($user)->get(route('meetings.show', $meeting))->assertInertia(fn (Assert $page) => $page
            ->where('decisions.0.actions.0.shown', 'overdue')->where('decisions.0.actions.0.days_overdue', 3));

        // Completing stops it being overdue and stamps the date; a cancelled action is not overdue either.
        $this->actingAs($user)->put(route('actions.update', $late), ['status' => 'completed'])->assertSessionHasNoErrors();
        $this->assertSame(['completed', today()->toDateString(), 'completed'], [$late->fresh()->status, $late->fresh()->completed_on->toDateString(), $late->fresh()->shownStatus()]);
        $this->actingAs($user)->put(route('actions.update', $late), ['status' => 'cancelled']);
        $this->assertSame([null, 'cancelled'], [$late->fresh()->completed_on, $late->fresh()->shownStatus()]);

        // Editing all the fields keeps them in step.
        $this->actingAs($user)->put(route('actions.update', $soon), ['description' => 'Send it on Monday', 'responsible_name' => 'The Clerk', 'deadline' => today()->addDays(2)->toDateString(), 'status' => 'in_progress'])->assertSessionHasNoErrors();
        $this->assertSame(['Send it on Monday', 'The Clerk', null], [$soon->fresh()->description, $soon->fresh()->responsibleName(), $soon->fresh()->responsible_member_id]);
    }

    public function test_the_action_list_counts_and_filters_by_status_and_committee(): void
    {
        $user = $this->userWith(['meetings.view']);
        $finance = $this->decision($this->meeting());
        $harvest = $this->decision($this->meeting(['committee_id' => $this->committee('Harvest Committee')->id]));
        $this->action($finance, ['description' => 'Pending soon', 'deadline' => today()->addWeek()]);
        $this->action($finance, ['description' => 'Overdue one', 'status' => 'in_progress', 'deadline' => today()->subWeek()]);
        $this->action($finance, ['description' => 'Done', 'status' => 'completed', 'deadline' => today()->subWeek(), 'completed_on' => today()]);
        $this->action($harvest, ['description' => 'Harvest task', 'status' => 'cancelled']);

        $this->actingAs($user)->get(route('actions.index'))->assertInertia(fn (Assert $page) => $page
            ->component('meetings/actions')
            ->where('counts', ['open' => 2, 'overdue' => 1, 'pending' => 1, 'in_progress' => 1, 'completed' => 1, 'cancelled' => 1, 'all' => 4])
            ->where('actions.data', fn ($rows) => collect($rows)->pluck('description')->all() === ['Overdue one', 'Pending soon']));
        $this->actingAs($user)->get(route('actions.index', ['view' => 'overdue']))->assertInertia(fn (Assert $page) => $page->has('actions.data', 1)->where('actions.data.0.shown', 'overdue'));
        $this->actingAs($user)->get(route('actions.index', ['view' => 'completed']))->assertInertia(fn (Assert $page) => $page->has('actions.data', 1));
        $this->actingAs($user)->get(route('actions.index', ['view' => 'all', 'committee' => $this->committee('Harvest Committee')->id]))->assertInertia(fn (Assert $page) => $page->has('actions.data', 1)->where('actions.data.0.description', 'Harvest task'));
        $this->actingAs($user)->get(route('actions.index', ['view' => 'all', 'q' => 'harvest']))->assertInertia(fn (Assert $page) => $page->has('actions.data', 1));
    }

    public function test_the_decision_register_filters_and_deleting_cascades(): void
    {
        $user = $this->userWith(['meetings.view', 'meetings.manage']);
        $meeting = $this->meeting();
        $resolution = $this->decision($meeting, ['kind' => 'resolution', 'text' => 'RESOLVED to buy a generator.']);
        $plain = $this->decision($meeting, ['text' => 'Repaint the hall.']);
        $this->action($resolution);
        $this->action($resolution, ['status' => 'completed']);

        $this->actingAs($user)->get(route('decisions.index'))->assertInertia(fn (Assert $page) => $page->component('meetings/decisions')->has('decisions.data', 2));
        $this->actingAs($user)->get(route('decisions.index', ['kind' => 'resolution']))->assertInertia(fn (Assert $page) => $page->has('decisions.data', 1)
            ->where('decisions.data.0.actions_total', 2)->where('decisions.data.0.actions_open', 1));
        $this->actingAs($user)->get(route('decisions.index', ['q' => 'repaint']))->assertInertia(fn (Assert $page) => $page->has('decisions.data', 1));
        $this->actingAs($user)->get(route('decisions.index', ['committee' => $this->committee('Harvest Committee')->id]))->assertInertia(fn (Assert $page) => $page->has('decisions.data', 0));

        $this->actingAs($user)->put(route('decisions.update', $plain), ['kind' => 'decision', 'text' => 'Repaint the chapel.'])->assertSessionHasNoErrors();
        $this->assertSame('Repaint the chapel.', $plain->fresh()->text);

        $this->actingAs($user)->delete(route('decisions.destroy', $resolution));
        $this->assertSame(0, MeetingAction::count());
        $this->actingAs($user)->delete(route('meetings.destroy', $meeting))->assertRedirect(route('meetings.index'));
        $this->assertSame(0, MeetingDecision::count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'meeting.deleted']);
    }

    public function test_session_and_the_district_church_council_can_hold_meetings(): void
    {
        $this->assertSame(2, Committee::whereIn('name', ['Session', 'District Church Council'])->count());

        $user = $this->userWith(['meetings.manage']);
        $this->actingAs($user)->post(route('meetings.store'), ['committee_id' => $this->committee('Session')->id, 'meeting_date' => '2026-10-01'])->assertSessionHasNoErrors();
        $this->assertSame('Session', Meeting::firstOrFail()->heading());
    }

    public function test_a_repeating_meeting_is_created_once_for_each_date(): void
    {
        $user = $this->userWith(['meetings.view', 'meetings.manage']);
        $form = ['committee_id' => $this->committee('Session')->id, 'starts_at' => '18:00', 'venue' => 'Manse', 'agenda' => 'Prayer'];

        $this->actingAs($user)->post(route('meetings.store'), [...$form, 'meeting_date' => '2026-10-01', 'repeat' => 'monthly', 'repeat_until' => '2026-12-31'])->assertSessionHasNoErrors()
            ->assertRedirect(route('meetings.index', ['committee' => $form['committee_id'], 'when' => 'upcoming']));
        $this->assertSame(['2026-10-01', '2026-11-01', '2026-12-01'], Meeting::orderBy('meeting_date')->get()->map(fn ($m) => $m->meeting_date->toDateString())->all());
        $this->assertSame(['18:00:00', 'Manse', 'scheduled'], [Meeting::first()->starts_at, Meeting::first()->venue, Meeting::first()->status]);

        // Each is its own meeting afterwards, with its own minutes.
        $second = Meeting::orderBy('meeting_date')->skip(1)->firstOrFail();
        $this->actingAs($user)->put(route('meetings.minutes', $second), ['minutes' => 'Only this one.', 'minutes_status' => 'draft']);
        $this->assertSame(1, Meeting::whereNotNull('minutes')->count());

        $this->actingAs($user)->post(route('meetings.store'), [...$form, 'meeting_date' => '2026-10-01', 'repeat' => 'weekly'])->assertSessionHasErrors('repeat_until');
    }

    public function test_the_meeting_list_filters(): void
    {
        $user = $this->userWith(['meetings.view']);
        $this->meeting(['meeting_date' => today()->subWeek(), 'status' => 'held']);
        $this->meeting(['meeting_date' => today()->addWeek(), 'committee_id' => $this->committee('Harvest Committee')->id]);

        $this->actingAs($user)->get(route('meetings.index'))->assertInertia(fn (Assert $page) => $page->has('meetings.data', 2));
        $this->actingAs($user)->get(route('meetings.index', ['when' => 'upcoming']))->assertInertia(fn (Assert $page) => $page->has('meetings.data', 1)->where('meetings.data.0.committee', 'Harvest Committee'));
        $this->actingAs($user)->get(route('meetings.index', ['when' => 'past']))->assertInertia(fn (Assert $page) => $page->has('meetings.data', 1));
        $this->actingAs($user)->get(route('meetings.index', ['status' => 'held']))->assertInertia(fn (Assert $page) => $page->has('meetings.data', 1));
        $this->actingAs($user)->get(route('meetings.index', ['committee' => $this->committee('Harvest Committee')->id]))->assertInertia(fn (Assert $page) => $page->has('meetings.data', 1));
        $this->actingAs($user)->get(route('meetings.index', ['q' => 'harvest']))->assertInertia(fn (Assert $page) => $page->has('meetings.data', 1));
    }

    public function test_sample_meetings_are_created_and_purged_without_touching_real_ones(): void
    {
        foreach (range(1, 10) as $i) {
            $this->member("M{$i}", "Member {$i}");
        }
        $real = $this->meeting();
        $this->decision($real);

        $this->artisan('meetings:sample')->assertSuccessful();
        $this->assertGreaterThan(20, Meeting::where('is_sample', true)->count());
        $this->assertTrue(MeetingAction::overdue()->exists() || MeetingAction::count() > 0);
        $this->assertSame(0, Meeting::where('is_sample', true)->where('status', 'held')->whereDoesntHave('attendees')->count());

        $this->artisan('meetings:sample', ['--purge' => true])->assertSuccessful();
        $this->assertSame(0, Meeting::where('is_sample', true)->count());
        $this->assertSame(1, Meeting::count());
        $this->assertSame(1, MeetingDecision::count());
    }
}
