<?php

namespace Tests\Feature;

use App\Models\Committee;
use App\Models\CommitteeMember;
use App\Models\Event;
use App\Models\EventVenue;
use App\Models\Meeting;
use App\Models\Member;
use App\Models\MemberGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventsTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $overrides = []): array
    {
        return [
            'title' => 'Harvest Thanksgiving', 'host_type' => 'church', 'scope' => 'internal', 'visibility' => 'public',
            'starts_on' => '2026-10-11', 'starts_at' => '08:00', 'ends_at' => '10:30', 'venue' => 'Main Chapel', ...$overrides,
        ];
    }

    private function event(array $attributes = []): Event
    {
        return Event::create(['title' => 'Sunday Service', 'starts_on' => '2026-10-04', 'ends_on' => '2026-10-04', ...$attributes]);
    }

    public function test_the_pages_follow_the_event_permissions(): void
    {
        $event = $this->event();
        $viewer = $this->userWith(['events.view']);

        $this->actingAs($this->userWith([]))->get(route('events.index'))->assertForbidden();
        $this->actingAs($this->userWith([]))->get(route('events.calendar'))->assertForbidden();
        $this->actingAs($viewer)->get(route('events.index'))->assertOk();
        $this->actingAs($viewer)->get(route('events.calendar'))->assertOk();
        $this->actingAs($viewer)->get(route('events.show', $event))->assertOk();
        $this->actingAs($viewer)->get(route('events.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('events.store'), $this->form())->assertForbidden();
        $this->actingAs($viewer)->put(route('events.update', $event), $this->form())->assertForbidden();
        $this->actingAs($viewer)->put(route('events.status', $event), ['status' => 'cancelled'])->assertForbidden();
        $this->actingAs($viewer)->delete(route('events.destroy', $event))->assertForbidden();
        $this->actingAs($viewer)->get(route('events.venues.index'))->assertForbidden();
    }

    public function test_an_event_is_added_with_its_host_and_organizer(): void
    {
        $user = $this->userWith(['events.view', 'events.manage']);
        $choir = MemberGroup::where('name', 'Church Choir')->firstOrFail();
        $committee = Committee::where('name', 'Harvest Committee')->firstOrFail();
        $organizer = Member::create(['member_number' => 'M1', 'full_name' => 'Mensah Efua', 'status' => 'active', 'sex' => 'female']);

        $this->actingAs($user)->post(route('events.store'), $this->form(['title' => '  Choir   Concert ', 'host_type' => 'group', 'member_group_id' => $choir->id, 'organizer_member_id' => $organizer->id, 'organizer_name' => 'ignored']))
            ->assertSessionHasNoErrors();
        $concert = Event::firstOrFail();
        $this->assertSame(['Choir Concert', 'group', $choir->id, null, '2026-10-11', 'scheduled', null], [$concert->title, $concert->host_type, $concert->member_group_id, $concert->committee_id, $concert->ends_on->toDateString(), $concert->status, $concert->organizer_name]);
        $this->assertSame(['Church Choir', 'Mensah Efua'], [$concert->hostName(), $concert->organizerName()]);

        // A group or committee host needs its group or committee; the other host's link is dropped.
        $this->actingAs($user)->post(route('events.store'), $this->form(['host_type' => 'group']))->assertSessionHasErrors('member_group_id');
        $this->actingAs($user)->post(route('events.store'), $this->form(['host_type' => 'committee']))->assertSessionHasErrors('committee_id');
        $this->actingAs($user)->post(route('events.store'), $this->form(['title' => 'Planning', 'host_type' => 'committee', 'committee_id' => $committee->id, 'member_group_id' => $choir->id, 'organizer_name' => 'The Clerk']))->assertSessionHasNoErrors();
        $planning = Event::where('title', 'Planning')->firstOrFail();
        $this->assertSame([null, $committee->id, 'Harvest Committee', 'The Clerk'], [$planning->member_group_id, $planning->committee_id, $planning->hostName(), $planning->organizerName()]);
    }

    public function test_dates_and_times_are_checked_and_all_day_events_have_no_times(): void
    {
        $user = $this->userWith(['events.manage']);

        $this->actingAs($user)->post(route('events.store'), $this->form(['starts_on' => '2026-10-11', 'ends_on' => '2026-10-10']))->assertSessionHasErrors('ends_on');
        $this->actingAs($user)->post(route('events.store'), $this->form(['starts_at' => '10:00', 'ends_at' => '09:00']))->assertSessionHasErrors('ends_at');
        $this->actingAs($user)->post(route('events.store'), $this->form(['starts_at' => '25:99']))->assertSessionHasErrors('starts_at');
        // On a several-day event the end time may be earlier than the start time.
        $this->actingAs($user)->post(route('events.store'), $this->form(['title' => 'Retreat', 'ends_on' => '2026-10-13', 'starts_at' => '18:00', 'ends_at' => '12:00']))->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('events.store'), $this->form(['title' => 'Convention', 'is_all_day' => true]))->assertSessionHasNoErrors();
        $convention = Event::where('title', 'Convention')->firstOrFail();
        $this->assertSame([true, null, null], [$convention->is_all_day, $convention->starts_at, $convention->ends_at]);
    }

    public function test_an_event_is_edited_cancelled_reinstated_and_deleted(): void
    {
        $user = $this->userWith(['events.view', 'events.manage']);
        $event = $this->event();

        $this->actingAs($user)->put(route('events.update', $event), $this->form(['title' => 'Harvest Service', 'venue' => 'Manse']))->assertSessionHasNoErrors()->assertRedirect(route('events.show', $event));
        $this->assertSame(['Harvest Service', 'Manse'], [$event->fresh()->title, $event->fresh()->venue]);

        $this->actingAs($user)->put(route('events.status', $event), ['status' => 'cancelled'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $event->fresh()->status);
        $this->actingAs($user)->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page->where('event.status', 'cancelled'));
        $this->actingAs($user)->put(route('events.status', $event), ['status' => 'scheduled']);
        $this->assertSame('scheduled', $event->fresh()->status);
        $this->actingAs($user)->put(route('events.status', $event), ['status' => 'bogus'])->assertSessionHasErrors('status');

        $this->actingAs($user)->delete(route('events.destroy', $event))->assertRedirect(route('events.index'));
        $this->assertNull($event->fresh());
        $this->assertDatabaseHas('audit_logs', ['event' => 'event.deleted']);
    }

    public function test_the_calendar_shows_a_month_with_multi_day_events_on_each_of_their_days(): void
    {
        $user = $this->userWith(['events.view']);
        $this->event(['title' => 'Service', 'starts_on' => '2026-10-04', 'ends_on' => '2026-10-04']);
        $this->event(['title' => 'Retreat', 'starts_on' => '2026-10-30', 'ends_on' => '2026-11-02']);
        $this->event(['title' => 'Earlier', 'starts_on' => '2026-08-01', 'ends_on' => '2026-08-01']);

        $this->actingAs($user)->get(route('events.calendar', ['month' => '2026-10']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('events/calendar')->where('month', '2026-10')->where('label', 'October 2026')->where('previous', '2026-09')->where('next', '2026-11')
            ->where('from', '2026-09-27')->where('to', '2026-10-31')
            ->where('events', fn ($events) => collect($events)->pluck('title')->sort()->values()->all() === ['Retreat', 'Service']));

        // November's grid still shows the end of the retreat, and a nonsense month falls back to this one.
        $this->actingAs($user)->get(route('events.calendar', ['month' => '2026-11']))->assertInertia(fn (Assert $page) => $page
            ->where('events', fn ($events) => collect($events)->pluck('title')->all() === ['Retreat']));
        $this->actingAs($user)->get(route('events.calendar', ['month' => 'nonsense']))->assertInertia(fn (Assert $page) => $page->where('month', today()->format('Y-m')));
    }

    public function test_the_list_filters_and_defaults_to_what_is_coming_up(): void
    {
        $user = $this->userWith(['events.view']);
        $group = MemberGroup::where('name', 'Brigade')->firstOrFail();
        $this->event(['title' => 'Past service', 'starts_on' => today()->subWeek(), 'ends_on' => today()->subWeek()]);
        $this->event(['title' => 'Parade', 'host_type' => 'group', 'member_group_id' => $group->id, 'starts_on' => today()->addDay(), 'ends_on' => today()->addDay(), 'venue' => 'Main Compound']);
        $this->event(['title' => 'Presbytery', 'scope' => 'external', 'visibility' => 'private', 'starts_on' => today()->addWeek(), 'ends_on' => today()->addWeek()]);
        $this->event(['title' => 'Running now', 'starts_on' => today()->subDay(), 'ends_on' => today()->addDay()]);

        $this->actingAs($user)->get(route('events.index'))->assertInertia(fn (Assert $page) => $page
            ->where('events.data', fn ($rows) => collect($rows)->pluck('title')->all() === ['Running now', 'Parade', 'Presbytery']));
        $this->actingAs($user)->get(route('events.index', ['when' => 'past']))->assertInertia(fn (Assert $page) => $page->where('events.data.0.title', 'Past service')->has('events.data', 1));
        $this->actingAs($user)->get(route('events.index', ['when' => 'all']))->assertInertia(fn (Assert $page) => $page->has('events.data', 4));
        $this->actingAs($user)->get(route('events.index', ['host' => 'group']))->assertInertia(fn (Assert $page) => $page->has('events.data', 1)->where('events.data.0.host', 'Brigade'));
        $this->actingAs($user)->get(route('events.index', ['scope' => 'external']))->assertInertia(fn (Assert $page) => $page->has('events.data', 1)->where('events.data.0.title', 'Presbytery'));
        $this->actingAs($user)->get(route('events.index', ['visibility' => 'private']))->assertInertia(fn (Assert $page) => $page->has('events.data', 1));
        $this->actingAs($user)->get(route('events.index', ['venue' => 'Main Compound']))->assertInertia(fn (Assert $page) => $page->has('events.data', 1)->where('events.data.0.title', 'Parade'));
        $this->actingAs($user)->get(route('events.index', ['q' => 'parad']))->assertInertia(fn (Assert $page) => $page->has('events.data', 1));
    }

    public function test_a_repeating_event_is_created_once_for_each_date(): void
    {
        $user = $this->userWith(['events.view', 'events.manage']);

        // Every week, the same length each time.
        $this->actingAs($user)->post(route('events.store'), $this->form(['title' => 'Prayer Meeting', 'starts_on' => '2026-10-07', 'ends_on' => '2026-10-08', 'repeat' => 'weekly', 'repeat_until' => '2026-10-28']))
            ->assertSessionHasNoErrors()->assertRedirect(route('events.calendar', ['month' => '2026-10']));
        $this->assertSame(['2026-10-07', '2026-10-14', '2026-10-21', '2026-10-28'], Event::orderBy('starts_on')->get()->map(fn ($e) => $e->starts_on->toDateString())->all());
        $this->assertSame(['2026-10-08', '2026-10-29'], [Event::orderBy('starts_on')->first()->ends_on->toDateString(), Event::orderBy('starts_on')->get()->last()->ends_on->toDateString()]);

        // Every month keeps to the day of the month, without spilling over a short one.
        Event::query()->delete();
        $this->actingAs($user)->post(route('events.store'), $this->form(['starts_on' => '2026-01-31', 'repeat' => 'monthly', 'repeat_until' => '2026-04-30']))->assertSessionHasNoErrors();
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30'], Event::orderBy('starts_on')->get()->map(fn ($e) => $e->starts_on->toDateString())->all());

        // Every two weeks; each one stands alone afterwards.
        Event::query()->delete();
        $this->actingAs($user)->post(route('events.store'), $this->form(['starts_on' => '2026-10-03', 'repeat' => 'fortnightly', 'repeat_until' => '2026-10-31']))->assertSessionHasNoErrors();
        $this->assertSame(3, Event::count());
        $second = Event::orderBy('starts_on')->skip(1)->firstOrFail();
        $this->actingAs($user)->put(route('events.status', $second), ['status' => 'cancelled']);
        $this->assertSame(1, Event::where('status', 'cancelled')->count());
    }

    public function test_repeating_needs_an_end_date_that_is_sensible(): void
    {
        $user = $this->userWith(['events.manage']);

        $this->actingAs($user)->post(route('events.store'), $this->form(['repeat' => 'weekly']))->assertSessionHasErrors('repeat_until');
        $this->actingAs($user)->post(route('events.store'), $this->form(['repeat' => 'weekly', 'repeat_until' => '2026-10-01']))->assertSessionHasErrors('repeat_until');
        $this->actingAs($user)->post(route('events.store'), $this->form(['repeat' => 'yearly', 'repeat_until' => '2030-01-01']))->assertSessionHasErrors('repeat');
        // No more than the limit, and nothing is created when it is exceeded.
        $this->actingAs($user)->post(route('events.store'), $this->form(['repeat' => 'weekly', 'repeat_until' => '2030-01-01']))->assertSessionHasErrors('repeat_until');
        $this->assertSame(0, Event::count());
        $this->actingAs($user)->post(route('events.store'), $this->form(['repeat' => 'none', 'repeat_until' => '']))->assertSessionHasNoErrors();
        $this->assertSame(1, Event::count());
    }

    public function test_committee_meetings_share_the_calendar_for_those_who_may_see_them(): void
    {
        $committee = Committee::where('name', 'Session')->firstOrFail();
        $meeting = Meeting::create(['committee_id' => $committee->id, 'meeting_date' => '2026-10-15', 'starts_at' => '18:00', 'venue' => 'Manse']);
        $this->event(['title' => 'Service', 'starts_on' => '2026-10-04']);

        $both = $this->userWith(['events.view', 'meetings.view']);
        $this->actingAs($both)->get(route('events.calendar', ['month' => '2026-10']))->assertInertia(fn (Assert $page) => $page
            ->where('events', fn ($rows) => collect($rows)->pluck('kind', 'title')->all() === ['Service' => 'event', 'Session' => 'meeting'])
            ->where('events.1.href', route('meetings.show', $meeting, false))->where('events.1.host_type', 'committee'));

        // Without permission to see meetings, only the events show.
        $this->actingAs($this->userWith(['events.view']))->get(route('events.calendar', ['month' => '2026-10']))->assertInertia(fn (Assert $page) => $page
            ->where('events', fn ($rows) => collect($rows)->pluck('title')->all() === ['Service']));

        $meeting->update(['status' => 'cancelled']);
        $this->actingAs($both)->get(route('events.calendar', ['month' => '2026-10']))->assertInertia(fn (Assert $page) => $page->where('events.1.status', 'cancelled'));
    }

    public function test_participants_are_added_one_by_one_and_removed(): void
    {
        $user = $this->userWith(['events.view', 'events.manage']);
        $event = $this->event();
        $member = Member::create(['member_number' => 'M1', 'full_name' => 'Mensah Efua', 'status' => 'active', 'sex' => 'female']);

        $this->actingAs($user)->post(route('events.participants.store', $event), ['member_id' => $member->id])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('events.participants.store', $event), ['member_id' => $member->id])->assertSessionHasErrors('member_id');
        $this->actingAs($user)->post(route('events.participants.store', $event), ['name' => 'Rev. Guest'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('events.participants.store', $event), [])->assertSessionHasErrors('member_id');
        $this->assertSame(['Mensah Efua', 'Rev. Guest'], $event->participants()->pluck('name')->all());

        $this->actingAs($user)->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page
            ->has('participants', 2)->where('participants.0.member_id', $member->id)->has('groups')->has('committees'));

        // A participant of another event cannot be reached through this one.
        $otherEvent = $this->event(['title' => 'Other']);
        $other = $otherEvent->participants()->create(['name' => 'Elsewhere']);
        $this->actingAs($user)->delete(route('events.participants.destroy', [$event, $other]))->assertNotFound();
        $this->actingAs($user)->delete(route('events.participants.destroy', [$event, $event->participants()->firstOrFail()]))->assertSessionHasNoErrors();
        $this->assertSame(1, $event->participants()->count());
        $this->actingAs($user)->delete(route('events.participants.clear', $event));
        $this->assertSame(0, $event->participants()->count());
        $this->assertSame(1, $otherEvent->participants()->count());

        $viewer = $this->userWith(['events.view']);
        $this->actingAs($viewer)->post(route('events.participants.store', $event), ['name' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->delete(route('events.participants.clear', $event))->assertForbidden();
    }

    public function test_a_group_is_added_as_its_active_members_and_never_twice(): void
    {
        $user = $this->userWith(['events.manage']);
        $choir = MemberGroup::where('name', 'Church Choir')->firstOrFail();
        $event = $this->event();
        $one = Member::create(['member_number' => 'M1', 'full_name' => 'Mensah Efua', 'status' => 'active', 'sex' => 'female']);
        $two = Member::create(['member_number' => 'M2', 'full_name' => 'Owusu Kofi', 'status' => 'active', 'sex' => 'male']);
        $gone = Member::create(['member_number' => 'M3', 'full_name' => 'Left Church', 'status' => 'transferred', 'sex' => 'male']);
        foreach ([$one, $two, $gone] as $member) {
            $member->groups()->attach($choir->id);
        }

        $this->actingAs($user)->post(route('events.participants.store', $event), ['group_id' => $choir->id])->assertSessionHasNoErrors();
        $this->assertSame([['Mensah Efua', 'Church Choir'], ['Owusu Kofi', 'Church Choir']], $event->participants()->get()->map(fn ($p) => [$p->name, $p->source])->all());

        // Adding again, or after adding someone by hand, leaves no repeats; later changes to the group do not touch the event.
        $this->actingAs($user)->post(route('events.participants.store', $event), ['group_id' => $choir->id])->assertSessionHasNoErrors();
        $this->assertSame(2, $event->participants()->count());
        $one->groups()->detach($choir->id);
        $this->assertSame(2, $event->participants()->count());

        // A group nobody is in says so instead of adding nothing silently.
        $empty = MemberGroup::where('name', 'Brigade')->firstOrFail();
        $this->actingAs($user)->post(route('events.participants.store', $event), ['group_id' => $empty->id])->assertSessionHasNoErrors();
        $this->assertSame(2, $event->participants()->count());
    }

    public function test_a_committee_is_added_as_those_serving_on_the_day_of_the_event(): void
    {
        $user = $this->userWith(['events.manage']);
        $committee = Committee::where('name', 'Session')->firstOrFail();
        $event = $this->event(['starts_on' => '2026-10-04', 'ends_on' => '2026-10-04']);
        $serving = Member::create(['member_number' => 'M1', 'full_name' => 'Serving Now', 'status' => 'active', 'sex' => 'male']);
        $ended = Member::create(['member_number' => 'M2', 'full_name' => 'Ended Before', 'status' => 'active', 'sex' => 'male']);
        $later = Member::create(['member_number' => 'M3', 'full_name' => 'Starts After', 'status' => 'active', 'sex' => 'male']);
        $term = fn (Member $m, string $from, ?string $to) => CommitteeMember::create(['committee_id' => $committee->id, 'member_id' => $m->id, 'name' => $m->full_name, 'started_on' => $from, 'ends_on' => $to]);
        $term($serving, '2025-01-01', '2028-01-01');
        $term($ended, '2020-01-01', '2026-09-30');
        $term($later, '2026-11-01', null);
        CommitteeMember::create(['committee_id' => $committee->id, 'name' => 'Rev. Guest', 'started_on' => '2025-01-01']);

        $this->actingAs($user)->post(route('events.participants.store', $event), ['committee_id' => $committee->id])->assertSessionHasNoErrors();
        $this->assertSame(['Rev. Guest', 'Serving Now'], $event->participants()->pluck('name')->all());
        $this->assertSame(['Session'], $event->participants()->pluck('source')->unique()->all());
    }

    public function test_venues_are_seeded_and_managed_by_settings_managers(): void
    {
        $user = $this->userWith(['settings.manage']);

        $this->assertSame(['Main Chapel', 'JY Chapel', "Children's Chapel", 'Main Compound', 'Volley Ball Court', 'Manse'], EventVenue::orderBy('sort_order')->pluck('name')->all());

        $this->actingAs($user)->post(route('events.venues.store'), ['name' => ' Youth   Hall '])->assertSessionHasNoErrors();
        $hall = EventVenue::where('name', 'Youth Hall')->firstOrFail();
        $this->actingAs($user)->post(route('events.venues.store'), ['name' => 'youth hall'])->assertSessionHasErrors('name');

        // The event form offers it straight away; events keep the wording they were saved with.
        $this->actingAs($this->userWith(['events.manage']))->get(route('events.create'))->assertInertia(fn (Assert $page) => $page
            ->where('venues', fn ($venues) => collect($venues)->contains('Youth Hall')));
        $this->event(['venue' => 'Youth Hall']);
        $this->actingAs($user)->get(route('events.venues.index'))->assertInertia(fn (Assert $page) => $page->where('venues', fn ($venues) => collect($venues)->firstWhere('name', 'Youth Hall')['events'] === 1));

        $this->actingAs($user)->put(route('events.venues.update', $hall), ['name' => 'Youth Centre'])->assertSessionHasNoErrors();
        $this->assertSame('Youth Hall', Event::firstOrFail()->venue);
        $this->actingAs($user)->delete(route('events.venues.destroy', $hall));
        $this->assertNull($hall->fresh());
        $this->assertSame('Youth Hall', Event::firstOrFail()->venue);
    }

    public function test_sample_events_are_created_and_purged_without_touching_real_ones(): void
    {
        $real = $this->event(['title' => 'Real event']);

        $this->artisan('events:sample')->assertSuccessful();
        $samples = Event::where('is_sample', true);
        $this->assertGreaterThan(30, $samples->count());
        $this->assertTrue(Event::where('is_sample', true)->where('scope', 'external')->exists());
        $this->assertTrue(Event::where('is_sample', true)->whereColumn('starts_on', '<', 'ends_on')->exists());
        $this->assertSame(0, Event::where('is_sample', true)->whereColumn('ends_on', '<', 'starts_on')->count());

        $this->artisan('events:sample', ['--purge' => true])->assertSuccessful();
        $this->assertSame(0, Event::where('is_sample', true)->count());
        $this->assertNotNull($real->fresh());
    }
}
