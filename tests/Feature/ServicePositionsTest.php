<?php

namespace Tests\Feature;

use App\Models\MemberGroup;
use App\Models\ServicePosition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServicePositionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_church_lists_are_seeded(): void
    {
        $this->assertSame(['Committee Chairperson', 'Committee Secretary', 'Committee Member'], ServicePosition::byType()['committee']);
        $this->assertSame(['Presbyter', 'Senior Presbyter', 'Session Clerk', 'Protocol Officer', 'Church Treasurer'], ServicePosition::byType()['leadership']);
        $this->assertCount(14, ServicePosition::byType()['executive']);
        $this->assertSame('President', ServicePosition::byType()['executive'][0]);

        $this->assertSame(11, MemberGroup::count());
        $this->assertTrue(MemberGroup::where('name', 'Prayer Team/Tower')->exists());
    }

    public function test_the_member_form_offers_positions_by_type_and_groups_for_executives(): void
    {
        $this->actingAs($this->userWith(['members.create']))->get(route('members.adult.create'))->assertInertia(fn (Assert $page) => $page
            ->where('positions.committee', ['Committee Chairperson', 'Committee Secretary', 'Committee Member'])
            ->where('serviceTypes', fn ($types) => collect($types)->pluck('label', 'value')->all() === ['committee' => 'Committee', 'executive' => 'Executive', 'leadership' => 'Session'])
            ->where('executiveGroups', fn ($groups) => collect($groups)->contains('Singing Band')
                && collect($groups)->contains("Young People's Guild (YPG)") && ! collect($groups)->contains('Other')));
    }

    public function test_positions_can_be_added_renamed_and_removed_by_settings_managers(): void
    {
        $viewer = $this->userWith(['members.view']);
        $this->actingAs($viewer)->get(route('admin.service-positions.index'))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.service-positions.store'), ['type' => 'executive', 'name' => 'X'])->assertForbidden();

        $user = $this->userWith(['settings.manage']);
        $this->actingAs($user)->get(route('admin.service-positions.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/service-positions/index')->has('types', 3));

        $this->actingAs($user)->post(route('admin.service-positions.store'), ['type' => 'executive', 'name' => ' Welfare  Officer '])->assertSessionHasNoErrors();
        $welfare = ServicePosition::where('name', 'Welfare Officer')->firstOrFail();
        $this->assertSame('executive', $welfare->type);
        $this->assertSame('Welfare Officer', last(ServicePosition::byType()['executive']), 'added at the end of its list');

        // The same name may sit under another type, but not twice in one.
        $this->actingAs($user)->post(route('admin.service-positions.store'), ['type' => 'executive', 'name' => 'welfare officer'])->assertSessionHasErrors('name');
        $this->actingAs($user)->post(route('admin.service-positions.store'), ['type' => 'committee', 'name' => 'Welfare Officer'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('admin.service-positions.store'), ['type' => 'bogus', 'name' => 'X'])->assertSessionHasErrors('type');

        $this->actingAs($user)->put(route('admin.service-positions.update', $welfare), ['name' => 'Welfare Secretary'])->assertSessionHasNoErrors();
        $this->assertSame('Welfare Secretary', $welfare->fresh()->name);

        $this->actingAs($user)->delete(route('admin.service-positions.destroy', $welfare))->assertRedirect();
        $this->assertNull($welfare->fresh());
        $this->assertDatabaseHas('audit_logs', ['event' => 'settings.position_removed']);
    }
}
