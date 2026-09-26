<?php

namespace Tests\Feature;

use App\Models\ChurchSetting;
use App\Models\Committee;
use App\Models\CommitteeMember;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\Member;
use App\Support\PortalSignIn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('portal-login-ip:127.0.0.1');
    }

    private function member(array $attributes = []): Member
    {
        static $n = 0;
        $n++;

        return Member::create([
            'member_number' => "M{$n}",
            'first_name' => 'Efua',
            'last_name' => 'Mensah',
            'full_name' => 'Mensah Efua',
            'status' => 'active',
            'sex' => 'female',
            'date_of_birth' => '1985-03-14',
            'mobile' => '0244123456',
            ...$attributes,
        ]);
    }

    /** Signs the member in on the portal guard, leaving the staff guard as the default the way a real request has it. */
    private function asMember(Member $member): static
    {
        $this->actingAs($member, 'member');
        auth()->shouldUse('web');

        return $this;
    }

    private function signIn(array $override = [])
    {
        return $this->post(route('portal.login.store'), ['phone' => '0244123456', 'date_of_birth' => '1985-03-14', ...$override]);
    }

    public function test_numbers_are_matched_however_they_were_written(): void
    {
        $this->assertSame('0244123456', PortalSignIn::local('+233 24 412 3456'));
        $this->assertSame('0244123456', PortalSignIn::local('244123456'));
        $this->assertSame('0244123456', PortalSignIn::local('024-412-3456'));
        $this->assertNull(PortalSignIn::local('12345'));

        $member = $this->member(['mobile' => '024 412 3456']);
        $this->signIn(['phone' => '+233244123456'])->assertRedirect(route('portal.home'));
        $this->assertAuthenticatedAs($member, 'member');
    }

    public function test_the_wrong_date_of_birth_or_number_says_only_that_nothing_matched(): void
    {
        $this->member();

        $this->signIn(['date_of_birth' => '1985-03-15'])->assertSessionHasErrors(['phone' => "We couldn't match those details. Check the number and date of birth and try again."]);
        $this->signIn(['phone' => '0200000000'])->assertSessionHasErrors('phone');
        $this->assertGuest('member');
    }

    public function test_only_active_adults_can_sign_in(): void
    {
        $this->member(['status' => 'transferred']);
        $this->signIn()->assertSessionHasErrors('phone');

        Member::query()->delete();
        $this->member(['date_of_birth' => today()->subYears(12)->toDateString()]);
        $this->signIn(['date_of_birth' => today()->subYears(12)->toDateString()])->assertSessionHasErrors('phone');
        $this->assertGuest('member');
    }

    public function test_a_number_shared_by_two_members_with_the_same_birthday_is_refused(): void
    {
        $this->member();
        $this->member(['first_name' => 'Ama', 'full_name' => 'Mensah Ama']);

        $this->signIn()->assertSessionHasErrors(['phone' => 'This number is shared by more than one member. Please contact the church office.']);
        $this->assertGuest('member');
    }

    public function test_the_date_of_birth_tells_relatives_who_share_a_number_apart(): void
    {
        $this->member();
        $other = $this->member(['first_name' => 'Kofi', 'full_name' => 'Mensah Kofi', 'date_of_birth' => '1960-01-02']);

        $this->signIn(['date_of_birth' => '1960-01-02'])->assertRedirect(route('portal.home'));
        $this->assertAuthenticatedAs($other, 'member');
    }

    public function test_the_primary_number_setting_chooses_which_phone_is_used(): void
    {
        $member = $this->member(['mobile' => '0200000001', 'telephone' => '0244123456']);

        $this->signIn()->assertSessionHasErrors('phone');

        ChurchSetting::put(ChurchSetting::PORTAL_PHONE, 'telephone');
        $this->signIn()->assertRedirect(route('portal.home'));
        $this->assertAuthenticatedAs($member, 'member');
    }

    public function test_too_many_wrong_tries_lock_the_number_out(): void
    {
        $this->member();

        foreach (range(1, 5) as $ignored) {
            $this->signIn(['date_of_birth' => '1990-01-01'])->assertSessionHasErrors('phone');
        }

        // Even the right details are refused while locked out.
        $this->signIn()->assertSessionHasErrors('phone');
        $this->assertGuest('member');
        RateLimiter::clear('portal-login:0244123456|127.0.0.1');
        RateLimiter::clear('portal-login-ip:127.0.0.1');
    }

    public function test_a_member_never_gets_into_the_staff_side_and_staff_do_not_get_the_portal(): void
    {
        $member = $this->member();

        $this->asMember($member)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('members.index'))->assertRedirect(route('login'));

        auth('member')->logout();
        $this->actingAs($this->adminUser())->get(route('portal.home'))->assertRedirect(route('portal.login'));
    }

    public function test_the_portal_needs_a_sign_in_and_signing_out_ends_it(): void
    {
        $member = $this->member();

        $this->get(route('portal.home'))->assertRedirect(route('portal.login'));

        $this->asMember($member)->post(route('portal.logout'))->assertRedirect(route('portal.login'));
        $this->assertGuest('member');
    }

    public function test_a_member_who_is_no_longer_active_loses_the_portal(): void
    {
        $member = $this->member();
        $this->asMember($member)->get(route('portal.home'))->assertOk();

        $member->update(['status' => 'transferred']);

        $this->get(route('portal.home'))->assertRedirect(route('portal.login'));
        $this->assertGuest('member');
    }

    public function test_the_portal_shows_only_the_members_own_record_committees_programmes_and_attendance(): void
    {
        $member = $this->member();
        $other = $this->member(['first_name' => 'Yaw', 'full_name' => 'Boateng Yaw', 'mobile' => '0200000009', 'date_of_birth' => '1970-01-01']);
        $committee = Committee::firstOrFail();

        CommitteeMember::create(['committee_id' => $committee->id, 'member_id' => $member->id, 'name' => $member->full_name, 'position' => 'Secretary', 'started_on' => today()->subYear()->toDateString(), 'ends_on' => today()->addYear()->toDateString()]);
        CommitteeMember::create(['committee_id' => $committee->id, 'member_id' => $other->id, 'name' => $other->full_name, 'position' => 'Chair', 'started_on' => today()->subYear()->toDateString()]);

        $makeEvent = fn (array $a) => Event::create(['title' => 'X', 'host_type' => 'church', 'scope' => 'internal', 'visibility' => 'public', 'status' => 'scheduled', 'starts_on' => today()->addDays(3)->toDateString(), 'ends_on' => today()->addDays(3)->toDateString(), ...$a]);
        $makeEvent(['title' => 'Harvest Service']);
        $makeEvent(['title' => 'Private Retreat', 'visibility' => 'private']);
        $invited = $makeEvent(['title' => 'Family Day', 'visibility' => 'private']);
        $invited->participants()->create(['member_id' => $member->id, 'name' => $member->full_name]);
        $makeEvent(['title' => 'Last Year', 'starts_on' => today()->subYear()->toDateString(), 'ends_on' => today()->subYear()->toDateString()]);
        $makeEvent(['title' => 'Called Off', 'status' => 'cancelled']);

        $upcoming = Meeting::create(['committee_id' => $committee->id, 'meeting_date' => today()->addDays(5)->toDateString(), 'status' => 'scheduled']);
        $past = Meeting::create(['committee_id' => $committee->id, 'meeting_date' => today()->subDays(9)->toDateString(), 'status' => 'held']);
        $past->attendees()->create(['member_id' => $member->id, 'name' => $member->full_name, 'attendance' => 'apologies']);
        $past->attendees()->create(['member_id' => $other->id, 'name' => $other->full_name, 'attendance' => 'present']);

        $this->asMember($member)->get(route('portal.home'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('portal/home')
                ->where('member.full_name', 'Mensah Efua')
                ->missing('member.photo_url')
                ->where('committees.0.position', 'Secretary')
                ->has('committees', 1)
                ->where('programmes', fn ($items) => collect($items)->pluck('title')->sort()->values()->all() === collect(['Family Day', 'Harvest Service', $committee->name])->sort()->values()->all())
                ->where('attendance.counts', ['present' => 0, 'apologies' => 1, 'absent' => 0])
                ->has('attendance.meetings', 1));

        $this->assertNotNull($upcoming);
    }

    public function test_the_primary_number_setting_is_saved_from_church_settings(): void
    {
        $user = $this->userWith(['settings.manage']);
        $church = ['church_name' => 'PCG', 'presbytery' => 'Ga', 'district' => 'Mamprobi', 'congregation' => 'Ebenezer'];

        $this->actingAs($user)->get(route('admin.church.edit'))->assertInertia(fn (Assert $page) => $page->where('settings.portal_phone_field', 'mobile'));
        $this->actingAs($user)->put(route('admin.church.update'), [...$church, 'portal_phone_field' => 'fax'])->assertSessionHasErrors('portal_phone_field');
        $this->actingAs($user)->put(route('admin.church.update'), [...$church, 'portal_phone_field' => 'office_phone'])->assertSessionHasNoErrors();

        $this->assertSame('office_phone', ChurchSetting::portalPhoneField());
    }
}
