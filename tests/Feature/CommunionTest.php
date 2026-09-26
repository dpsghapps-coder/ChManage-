<?php

namespace Tests\Feature;

use App\Models\CommunionAttendee;
use App\Models\CommunionService;
use App\Models\Member;
use App\Models\SpeakingNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommunionTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name, ?bool $communicant = true, string $status = 'active'): Member
    {
        static $n = 0;
        $n++;

        return Member::create(['member_number' => "C{$n}", 'full_name' => $name, 'status' => $status, 'sex' => 'female', 'date_of_birth' => '1980-01-01', 'is_communicant' => $communicant]);
    }

    private function service(array $attributes = []): CommunionService
    {
        return CommunionService::create(['title' => 'Harvest Communion', 'held_on' => today()->addWeek()->toDateString(), 'status' => 'scheduled', ...$attributes]);
    }

    public function test_permissions_keep_speaking_apart_from_communion(): void
    {
        $service = $this->service();
        $clerk = $this->userWith(['communion.view', 'communion.manage']);

        $this->actingAs($clerk)->get(route('communion.index'))->assertOk();
        $this->actingAs($clerk)->get(route('communion.show', $service))->assertOk();
        $this->actingAs($clerk)->get(route('speaking.index'))->assertForbidden();
        $this->actingAs($clerk)->get(route('speaking.create'))->assertForbidden();

        $viewer = $this->userWith(['communion.view']);
        $this->actingAs($viewer)->post(route('communion.store'), ['title' => 'X', 'held_on' => today()->toDateString(), 'status' => 'scheduled'])->assertForbidden();
        $this->actingAs($viewer)->put(route('communion.attendance', $service), ['statuses' => []])->assertForbidden();
    }

    public function test_a_service_is_created_and_its_list_starts_from_the_active_communicants(): void
    {
        $admin = $this->adminUser();
        $one = $this->member('Mensah Efua');
        $two = $this->member('Boateng Yaw');
        $this->member('Owusu Ama', false);            // a non-communicant: never listed
        $this->member('Unknown Status', null);        // not recorded either way
        $this->member('Left Church', true, 'transferred');

        $this->actingAs($admin)->post(route('communion.store'), ['title' => 'Easter Communion', 'held_on' => today()->addDays(10)->toDateString(), 'status' => 'scheduled', 'venue' => 'Main Chapel'])->assertRedirect();
        $service = CommunionService::firstOrFail();

        $this->actingAs($admin)->post(route('communion.communicants', $service))->assertRedirect();
        $this->assertEqualsCanonicalizing([$one->id, $two->id], $service->attendees()->pluck('member_id')->all());
        $this->assertSame(['absent'], $service->attendees()->pluck('status')->unique()->values()->all());

        // Loading again adds nobody twice.
        $this->actingAs($admin)->post(route('communion.communicants', $service));
        $this->assertSame(2, $service->attendees()->count());
    }

    public function test_attendance_is_saved_for_the_whole_sheet_and_counted(): void
    {
        $admin = $this->adminUser();
        $service = $this->service();
        $a = $service->attendees()->create(['member_id' => $this->member('A One')->id]);
        $b = $service->attendees()->create(['member_id' => $this->member('B Two')->id]);
        $c = $service->attendees()->create(['member_id' => $this->member('C Three')->id]);

        $this->actingAs($admin)->put(route('communion.attendance', $service), ['statuses' => [$a->id => 'present', $b->id => 'excused', $c->id => 'absent']])->assertRedirect();

        $this->assertSame(['present', 'excused', 'absent'], [$a->fresh()->status, $b->fresh()->status, $c->fresh()->status]);
        $this->actingAs($admin)->put(route('communion.attendance', $service), ['statuses' => [$a->id => 'sleeping']])->assertSessionHasErrors('statuses.'.$a->id);

        $this->actingAs($admin)->get(route('communion.index'))->assertInertia(fn (Assert $page) => $page->where('services.data.0.received', 1)->where('services.data.0.expected', 3));
    }

    public function test_someone_not_marked_as_a_communicant_can_be_added_once(): void
    {
        $admin = $this->adminUser();
        $service = $this->service();
        $visitor = $this->member('Visitor Kofi', false);

        $this->actingAs($admin)->post(route('communion.attendees.store', $service), ['member_id' => $visitor->id])->assertRedirect();
        $this->assertSame('present', CommunionAttendee::where('member_id', $visitor->id)->value('status'));

        $this->actingAs($admin)->post(route('communion.attendees.store', $service), ['member_id' => $visitor->id])->assertSessionHasErrors('member_id');
        $this->assertSame(1, $service->attendees()->count());
    }

    public function test_a_row_of_another_service_cannot_be_removed_through_this_one(): void
    {
        $admin = $this->adminUser();
        $mine = $this->service();
        $other = $this->service(['title' => 'Other']);
        $row = $other->attendees()->create(['member_id' => $this->member('Someone')->id]);

        $this->actingAs($admin)->delete(route('communion.attendees.destroy', [$mine, $row]))->assertNotFound();
        $this->assertNotNull($row->fresh());
    }

    public function test_speaking_notes_are_stored_encrypted_and_only_readers_see_them(): void
    {
        $service = $this->service();
        $member = $this->member('Mensah Efua');
        $writer = $this->userWith(['speaking.view', 'speaking.manage']);

        $this->actingAs($writer)->post(route('speaking.store'), [
            'member_id' => $member->id, 'communion_service_id' => $service->id, 'spoken_on' => today()->toDateString(),
            'spoken_by_name' => 'Elder Mensah', 'outcome' => 'follow_up', 'notes' => 'Reconciling with a neighbour.',
        ])->assertRedirect();

        $note = SpeakingNote::firstOrFail();
        $this->assertSame('Reconciling with a neighbour.', $note->notes);
        $this->assertStringNotContainsString('Reconciling', (string) DB::table('speaking_notes')->value('notes'));

        $this->actingAs($writer)->get(route('speaking.show', $note))->assertOk()->assertInertia(fn (Assert $page) => $page->where('note.notes', 'Reconciling with a neighbour.'));

        $reader = $this->userWith(['communion.view', 'communion.manage', 'members.view']);
        $this->actingAs($reader)->get(route('speaking.show', $note))->assertForbidden();
        $this->actingAs($reader)->put(route('speaking.update', $note), ['notes' => 'x'])->assertForbidden();
        $this->actingAs($reader)->delete(route('speaking.destroy', $note))->assertForbidden();

        // The communion sheet tells only Speaking readers who was spoken to, and never shows a note.
        $service->attendees()->create(['member_id' => $member->id]);
        $this->actingAs($reader)->get(route('communion.show', $service))->assertInertia(fn (Assert $page) => $page->where('showsSpeaking', false)->where('attendees.0.spoken', false));
        $this->actingAs($writer)->get(route('communion.show', $service))->assertForbidden();
        $both = $this->userWith(['communion.view', 'speaking.view']);
        $this->actingAs($both)->get(route('communion.show', $service))->assertInertia(fn (Assert $page) => $page->where('showsSpeaking', true)->where('attendees.0.spoken', 'follow_up')->missing('attendees.0.notes'));
    }

    public function test_reading_and_changing_notes_is_written_to_the_audit_log(): void
    {
        $service = $this->service();
        $member = $this->member('Mensah Efua');
        $writer = $this->userWith(['speaking.view', 'speaking.manage']);
        $note = SpeakingNote::create(['member_id' => $member->id, 'communion_service_id' => $service->id, 'spoken_on' => today()->toDateString(), 'outcome' => 'cleared', 'notes' => 'Fine.']);

        $this->actingAs($writer)->get(route('speaking.index'))->assertOk();
        $this->actingAs($writer)->get(route('speaking.show', $note));
        $this->actingAs($writer)->put(route('speaking.update', $note), ['member_id' => $member->id, 'communion_service_id' => $service->id, 'spoken_on' => today()->toDateString(), 'outcome' => 'deferred', 'notes' => 'Later.']);

        foreach (['speaking.list_viewed', 'speaking.viewed', 'speaking.updated'] as $event) {
            $this->assertDatabaseHas('audit_logs', ['event' => $event, 'user_id' => $writer->id]);
        }
        // The audit log never carries the text of a note.
        $this->assertDatabaseMissing('audit_logs', ['description' => 'Fine.']);
        $this->assertSame(0, DB::table('audit_logs')->where('properties', 'like', '%Later.%')->count());
    }

    public function test_a_note_is_checked_and_the_list_can_be_filtered(): void
    {
        $service = $this->service();
        $member = $this->member('Mensah Efua');
        $other = $this->member('Boateng Yaw');
        $writer = $this->userWith(['speaking.view', 'speaking.manage']);

        $this->actingAs($writer)->post(route('speaking.store'), ['member_id' => $member->id, 'communion_service_id' => $service->id, 'spoken_on' => today()->addDay()->toDateString(), 'outcome' => 'nope', 'notes' => ''])
            ->assertSessionHasErrors(['spoken_on', 'outcome', 'notes']);

        SpeakingNote::create(['member_id' => $member->id, 'communion_service_id' => $service->id, 'spoken_on' => today()->toDateString(), 'outcome' => 'cleared', 'notes' => 'a']);
        SpeakingNote::create(['member_id' => $other->id, 'communion_service_id' => $service->id, 'spoken_on' => today()->toDateString(), 'outcome' => 'deferred', 'notes' => 'b']);

        $this->actingAs($writer)->get(route('speaking.index', ['outcome' => 'deferred']))->assertInertia(fn (Assert $page) => $page->has('notes.data', 1)->where('notes.data.0.member.full_name', 'Boateng Yaw')->missing('notes.data.0.notes'));
        $this->actingAs($writer)->get(route('speaking.index', ['q' => 'Mensah']))->assertInertia(fn (Assert $page) => $page->has('notes.data', 1));
    }

    public function test_a_service_with_speaking_notes_cannot_be_deleted(): void
    {
        $admin = $this->adminUser();
        $service = $this->service();
        SpeakingNote::create(['member_id' => $this->member('Mensah Efua')->id, 'communion_service_id' => $service->id, 'spoken_on' => today()->toDateString(), 'outcome' => 'cleared', 'notes' => 'a']);

        $this->actingAs($admin)->delete(route('communion.destroy', $service))->assertRedirect();
        $this->assertNotNull($service->fresh());

        $empty = $this->service(['title' => 'Empty']);
        $this->actingAs($admin)->delete(route('communion.destroy', $empty))->assertRedirect(route('communion.index'));
        $this->assertNull($empty->fresh());
    }
}
