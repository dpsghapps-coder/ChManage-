<?php

namespace Tests\Feature;

use App\Models\Committee;
use App\Models\Member;
use App\Models\MemberServiceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommitteesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_committees_are_seeded_and_offered_on_the_member_form(): void
    {
        $this->assertSame(15, Committee::count());

        $this->actingAs($this->userWith(['members.create']))->get(route('members.adult.create'))->assertInertia(fn (Assert $page) => $page
            ->where('committees.0', 'Administration and Human Resource Management')
            ->where('committees', fn ($names) => collect($names)->contains('Church Life and Nurture (CLAN)')));
    }

    public function test_committees_are_managed_by_settings_managers_and_renames_carry_over(): void
    {
        $finance = Committee::where('name', 'Committee on Finance')->firstOrFail();
        $viewer = $this->userWith(['members.view']);
        $this->actingAs($viewer)->get(route('admin.committees.index'))->assertForbidden();
        $this->actingAs($viewer)->put(route('admin.committees.update', $finance), ['name' => 'X'])->assertForbidden();

        $user = $this->userWith(['settings.manage']);
        $member = Member::create(['member_number' => 'M1', 'full_name' => 'Mensah Efua', 'status' => 'active', 'sex' => 'female']);
        $member->serviceRecords()->create(['type' => 'committee', 'name' => 'Committee on Finance', 'position' => 'Committee Member', 'started_on' => '2020-01-01']);
        $member->serviceRecords()->create(['type' => 'executive', 'name' => 'Committee on Finance', 'position' => 'Secretary', 'started_on' => '2020-01-01']);

        $this->actingAs($user)->get(route('admin.committees.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/committees/index')->has('committees', 15)
            ->where('committees', fn ($list) => collect($list)->firstWhere('name', 'Committee on Finance')['records'] === 1));

        $this->actingAs($user)->post(route('admin.committees.store'), ['name' => '  Media  Committee '])->assertSessionHasNoErrors();
        $this->assertTrue(Committee::where('name', 'Media Committee')->exists());
        $this->actingAs($user)->post(route('admin.committees.store'), ['name' => 'media committee'])->assertSessionHasErrors('name');

        // A rename is carried over to committee service records, and only to those.
        $this->actingAs($user)->put(route('admin.committees.update', $finance), ['name' => 'Finance Committee'])->assertSessionHasNoErrors();
        $this->assertSame('Finance Committee', $finance->fresh()->name);
        $this->assertSame(['committee' => 'Finance Committee', 'executive' => 'Committee on Finance'],
            MemberServiceRecord::where('member_id', $member->id)->pluck('name', 'type')->all());

        // Removing one leaves the service records as they are.
        $this->actingAs($user)->delete(route('admin.committees.destroy', $finance))->assertRedirect();
        $this->assertNull($finance->fresh());
        $this->assertSame(2, MemberServiceRecord::where('member_id', $member->id)->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'settings.committee_renamed']);
    }
}
