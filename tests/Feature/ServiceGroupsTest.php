<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Member;
use App\Models\MemberGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServiceGroupsTest extends TestCase
{
    use RefreshDatabase;

    private function member(): Member
    {
        return Member::create(['member_number' => 'M1', 'full_name' => 'Mensah Efua', 'status' => 'active', 'sex' => 'female']);
    }

    public function test_only_people_who_manage_settings_reach_the_page(): void
    {
        $group = MemberGroup::where('name', 'Church Choir')->firstOrFail();
        $viewer = $this->userWith(['members.view']);

        $this->actingAs($viewer)->get(route('admin.service-groups.index'))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.service-groups.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->put(route('admin.service-groups.update', $group), ['name' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->delete(route('admin.service-groups.destroy', $group))->assertForbidden();
    }

    public function test_groups_are_listed_with_their_member_counts(): void
    {
        $this->member()->groups()->attach(MemberGroup::where('name', 'Church Choir')->value('id'));

        $this->actingAs($this->userWith(['settings.manage']))->get(route('admin.service-groups.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/service-groups/index')->has('groups', 11)
                ->where('groups', fn ($groups) => collect($groups)->pluck('members', 'name')->all()['Church Choir'] === 1
                    && collect($groups)->pluck('members', 'name')->all()['Brigade'] === 0));
    }

    public function test_a_group_is_added_renamed_and_removed_once_empty(): void
    {
        $user = $this->userWith(['settings.manage', 'members.create']);

        $this->actingAs($user)->post(route('admin.service-groups.store'), ['name' => '  Media   Team ', 'short_name' => ''])->assertSessionHasNoErrors();
        $group = MemberGroup::where('name', 'Media Team')->firstOrFail();
        $this->assertNull($group->short_name);
        $this->actingAs($user)->post(route('admin.service-groups.store'), ['name' => 'media team'])->assertSessionHasErrors('name');

        // New groups are offered on the member form straight away.
        $this->actingAs($user)->get(route('members.adult.create'))->assertInertia(fn (Assert $page) => $page
            ->where('groups', fn ($groups) => collect($groups)->pluck('name')->contains('Media Team')));

        $this->actingAs($user)->put(route('admin.service-groups.update', $group), ['name' => 'Media & Sound', 'short_name' => 'MST'])->assertSessionHasNoErrors();
        $this->assertSame(['Media & Sound', 'MST'], [$group->fresh()->name, $group->fresh()->short_name]);

        // A group with members stays.
        $member = $this->member();
        $member->groups()->attach($group->id);
        $this->actingAs($user)->delete(route('admin.service-groups.destroy', $group))->assertRedirect();
        $this->assertNotNull($group->fresh());

        $member->groups()->detach();
        $this->actingAs($user)->delete(route('admin.service-groups.destroy', $group))->assertRedirect();
        $this->assertNull($group->fresh());

        $this->assertSame(
            ['settings.group_added', 'settings.group_updated', 'settings.group_removed'],
            AuditLog::where('event', 'like', 'settings.group_%')->orderBy('id')->pluck('event')->all(),
        );
    }
}
