<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MemberRequest;
use App\Models\Position;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Support\RequestAccess;
use App\Support\RequestTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MemberRequestsTest extends TestCase
{
    use RefreshDatabase;

    private function member(array $attributes = []): Member
    {
        static $n = 0;
        $n++;

        return Member::create([
            'member_number' => "R{$n}",
            'first_name' => 'Efua',
            'last_name' => 'Mensah',
            'full_name' => 'Mensah Efua',
            'status' => 'active',
            'sex' => 'female',
            'date_of_birth' => '1985-03-14',
            'mobile' => '0244123456',
            'residence' => 'Mamprobi',
            ...$attributes,
        ]);
    }

    private function asMember(Member $member): static
    {
        $this->actingAs($member, 'member');
        auth()->shouldUse('web');

        return $this;
    }

    private function make(Member $member, string $type = 'issue', array $details = []): MemberRequest
    {
        return MemberRequest::create(['member_id' => $member->id, 'type' => $type, 'status' => 'submitted', 'details' => $details ?: ['subject' => 'Roof', 'description' => 'Leaking']]);
    }

    public function test_a_member_asks_for_a_change_and_the_record_stays_as_it_was(): void
    {
        $member = $this->member();

        $this->asMember($member)->post(route('portal.requests.store', 'change_details'), [
            'values' => ['mobile' => '0200000001', 'residence' => 'Mamprobi', 'email' => 'efua@example.org'],
            'note' => 'Also my surname changed',
        ])->assertRedirect();

        $request = MemberRequest::firstOrFail();
        $this->assertSame('submitted', $request->status);
        $this->assertEquals(['mobile' => ['from' => '0244123456', 'to' => '0200000001'], 'email' => ['from' => null, 'to' => 'efua@example.org']], $request->changes); // MySQL keeps JSON keys in its own order

        $member->refresh();
        $this->assertSame('0244123456', $member->mobile);
        $this->assertNull($member->email);
    }

    public function test_a_change_that_changes_nothing_is_refused_and_a_bad_number_is_checked(): void
    {
        $member = $this->member();

        $this->asMember($member)->post(route('portal.requests.store', 'change_details'), ['values' => ['residence' => 'Mamprobi']])->assertSessionHasErrors('values');
        $this->asMember($member)->post(route('portal.requests.store', 'change_details'), ['values' => ['mobile' => '12345']])->assertSessionHasErrors('values.mobile');
        $this->assertSame(0, MemberRequest::count());
    }

    public function test_each_kind_of_request_is_checked_against_its_own_questions(): void
    {
        $member = $this->member();

        $this->asMember($member)->post(route('portal.requests.store', 'transfer'), ['details' => ['destination' => 'Ebenezer, Kumasi']])->assertSessionHasErrors('details.reason');
        $this->asMember($member)->post(route('portal.requests.store', 'transfer'), ['details' => ['destination' => 'Ebenezer, Kumasi', 'reason' => 'Work', 'move_date' => '2026-12-01']])->assertSessionHasNoErrors();
        $this->asMember($member)->post(route('portal.requests.store', 'issue'), ['details' => ['category' => 'Not a choice', 'subject' => 'x', 'description' => 'y']])->assertSessionHasErrors('details.category');
        $this->asMember($member)->post(route('portal.requests.store', 'nonsense'), [])->assertNotFound();

        $this->assertSame(['transfer'], MemberRequest::pluck('type')->all());
        $this->assertCount(9, RequestTypes::all());
    }

    public function test_a_member_sees_only_their_own_requests_and_can_withdraw_before_it_is_started(): void
    {
        $member = $this->member();
        $other = $this->member(['first_name' => 'Yaw', 'full_name' => 'Boateng Yaw', 'mobile' => '0200000009']);
        $mine = $this->make($member);
        $theirs = $this->make($other);

        $this->asMember($member)->get(route('portal.home'))->assertInertia(fn (Assert $page) => $page->has('requests', 1)->where('requests.0.id', $mine->id)->has('requestTypes', 9));

        $this->asMember($member)->post(route('portal.requests.cancel', $theirs))->assertNotFound();
        $this->asMember($member)->post(route('portal.requests.cancel', $mine))->assertRedirect();
        $this->assertSame('cancelled', $mine->fresh()->status);

        $started = $this->make($member);
        $started->update(['status' => 'in_review']);
        $this->asMember($member)->post(route('portal.requests.cancel', $started))->assertRedirect();
        $this->assertSame('in_review', $started->fresh()->status);
    }

    public function test_only_the_role_a_type_is_routed_to_receives_it(): void
    {
        $member = $this->member();
        $issue = $this->make($member);
        $transfer = $this->make($member, 'transfer', ['destination' => 'Kumasi', 'reason' => 'Work']);

        $secretary = $this->userWith(['requests.manage']);
        RequestAccess::save(['issue' => $secretary->role_id]);

        $this->assertSame(['issue'], RequestAccess::types($secretary->fresh()));
        $this->actingAs($secretary)->get(route('requests.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('requests.data', 1)->where('requests.data.0.type', 'issue'));
        $this->actingAs($secretary)->get(route('requests.show', $issue))->assertOk();
        $this->actingAs($secretary)->get(route('requests.show', $transfer))->assertForbidden();
        $this->actingAs($secretary)->post(route('requests.decide', $transfer), ['decision' => 'approved'])->assertForbidden();

        // A role without the permission gets nothing, even when a type is routed to it.
        $clerk = $this->userWith(['members.view']);
        RequestAccess::save(['issue' => $clerk->role_id]);
        $this->actingAs($clerk)->get(route('requests.index'))->assertForbidden();
    }

    public function test_administrators_see_everything_and_staff_whose_position_is_administrator_get_change_requests(): void
    {
        $member = $this->member();
        $this->make($member);
        $change = MemberRequest::create(['member_id' => $member->id, 'type' => 'change_details', 'status' => 'submitted', 'changes' => ['residence' => ['from' => 'Mamprobi', 'to' => 'Kaneshie']]]);

        $this->actingAs($this->adminUser())->get(route('requests.index'))->assertInertia(fn (Assert $page) => $page->has('requests.data', 2));

        $position = Position::firstOrCreate(['name' => 'Administrator']);
        $staff = Staff::create(['staff_number' => 'S1', 'full_name' => 'Ama Office', 'position_id' => $position->id, 'status' => 'active']);
        $officer = $this->userWith(['members.view'], ['staff_id' => $staff->id]);

        $this->assertSame(['change_details'], RequestAccess::types($officer->fresh()));
        $this->actingAs($officer)->get(route('requests.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('requests.data', 1)->where('requests.data.0.type', 'change_details'));
        $this->actingAs($officer)->get(route('requests.show', $change))->assertOk();

        // The menu item and dashboard card appear for them, with the count.
        $this->actingAs($officer)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('cards.requests.total', 1)->where('auth.permissions', fn ($p) => ! collect($p)->contains('requests.manage')));
    }

    public function test_approving_a_change_writes_it_to_the_record_and_declining_needs_a_reason(): void
    {
        $member = $this->member();
        $change = MemberRequest::create(['member_id' => $member->id, 'type' => 'change_details', 'status' => 'submitted', 'changes' => ['residence' => ['from' => 'Mamprobi', 'to' => 'Kaneshie'], 'email' => ['from' => null, 'to' => 'efua@example.org']]]);
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('requests.decide', $change), ['decision' => 'declined'])->assertSessionHasErrors('response');
        $this->assertSame('Mamprobi', $member->fresh()->residence);

        $this->actingAs($admin)->post(route('requests.decide', $change), ['decision' => 'approved', 'response' => 'Updated, thank you.'])->assertRedirect(route('requests.show', $change));

        $member->refresh();
        $this->assertSame('Kaneshie', $member->residence);
        $this->assertSame('efua@example.org', $member->email);
        $change->refresh();
        $this->assertSame('approved', $change->status);
        $this->assertSame($admin->id, $change->handled_by);
        $this->assertSame('Updated, thank you.', $change->response);

        // Once decided it cannot be decided again.
        $this->actingAs($admin)->post(route('requests.decide', $change), ['decision' => 'declined', 'response' => 'No'])->assertRedirect();
        $this->assertSame('approved', $change->fresh()->status);
    }

    public function test_declining_leaves_the_record_alone_and_the_member_sees_the_reply(): void
    {
        $member = $this->member();
        $change = MemberRequest::create(['member_id' => $member->id, 'type' => 'change_details', 'status' => 'submitted', 'changes' => ['residence' => ['from' => 'Mamprobi', 'to' => 'Kaneshie']]]);

        $this->actingAs($this->adminUser())->post(route('requests.decide', $change), ['decision' => 'declined', 'response' => 'Please bring proof of address.']);

        $this->assertSame('Mamprobi', $member->fresh()->residence);
        auth()->logout();
        $this->asMember($member)->get(route('portal.home'))->assertInertia(fn (Assert $page) => $page->where('requests.0.status', 'declined')->where('requests.0.response', 'Please bring proof of address.'));
    }

    public function test_routing_is_saved_from_church_settings(): void
    {
        $user = $this->userWith(['settings.manage']);
        $role = Role::create(['name' => 'Office', 'slug' => 'office']);
        $church = ['church_name' => 'PCG', 'presbytery' => 'Ga', 'district' => 'Mamprobi', 'congregation' => 'Ebenezer'];

        $this->actingAs($user)->put(route('admin.church.update'), [...$church, 'request_handlers' => ['bus' => $role->id, 'issue' => '']])->assertSessionHasNoErrors();
        $this->assertSame($role->id, RequestAccess::handlers()['bus']);
        $this->assertNull(RequestAccess::handlers()['issue']);

        $this->actingAs($user)->put(route('admin.church.update'), [...$church, 'request_handlers' => ['bus' => 99999]])->assertSessionHasErrors('request_handlers.bus');
        $this->actingAs($user)->get(route('admin.church.edit'))->assertInertia(fn (Assert $page) => $page->has('requestTypes', 9)->where('requestHandlers.bus', (string) $role->id));
    }

    public function test_a_member_cannot_reach_the_staff_inbox(): void
    {
        $member = $this->member();
        $request = $this->make($member);

        $this->asMember($member)->get(route('requests.index'))->assertRedirect(route('login'));
        $this->asMember($member)->get(route('requests.show', $request))->assertRedirect(route('login'));
        $this->assertInstanceOf(User::class, $this->adminUser());
    }
}
