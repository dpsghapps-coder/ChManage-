<?php

namespace Tests\Feature;

use App\Models\ChurchSetting;
use App\Models\Committee;
use App\Models\CommunionService;
use App\Models\EventVenue;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberRequest;
use App\Models\NewcomerOption;
use App\Models\Profession;
use App\Models\Role;
use App\Models\ServicePosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    /** The submitted form: everything the migrations list stays ticked unless a test changes it. */
    private function payload(array $override = []): array
    {
        return [
            'username' => 'admin',
            'first_name' => 'Kwame',
            'last_name' => 'Mensah',
            'email' => 'kwame@example.org',
            'password' => 'a-good-password',
            'password_confirmation' => 'a-good-password',
            'church_name' => 'Presbyterian Church of Ghana',
            'presbytery' => 'Ga',
            'district' => 'Mamprobi',
            'congregation' => 'Ebenezer Congregation, Mamprobi',
            'city' => 'Accra',
            'followup_days' => 45,
            'term_warning_days' => 60,
            'groups' => MemberGroup::pluck('name')->all(),
            'committees' => Committee::pluck('name')->all(),
            'venues' => EventVenue::pluck('name')->all(),
            'positions' => ServicePosition::byType(),
            'occupations' => collect(Profession::grouped())->mapWithKeys(fn ($g) => [$g['category'] => collect($g['options'])->pluck('name')->all()])->all(),
            'options' => NewcomerOption::lists(),
            ...$override,
        ];
    }

    public function test_the_sign_in_page_sends_a_new_install_to_setup(): void
    {
        $this->get(route('login'))->assertRedirect(route('setup.show'));
    }

    public function test_setup_lists_the_standard_entries_to_choose_from(): void
    {
        $this->get(route('setup.show'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('setup/index')
                ->where('lists.committees', fn ($names) => collect($names)->contains('Session'))
                ->where('lists.venues', fn ($names) => collect($names)->contains('Main Chapel'))
                ->has('lists.positions.committee')
                ->has('lists.options.title')
                ->where('defaults.followup_days', 60));
    }

    public function test_setup_creates_the_administrator_saves_the_settings_and_signs_them_in(): void
    {
        $this->post(route('setup.store'), $this->payload())->assertRedirect(route('dashboard'));

        $admin = User::where('username', 'admin')->firstOrFail();
        $this->assertSame(Role::ADMIN, $admin->role->slug);
        $this->assertFalse($admin->must_reset_password);
        $this->assertAuthenticatedAs($admin);

        $settings = ChurchSetting::values(['congregation_name', 'district_name', 'newcomer_followup_days', 'committee_term_warning_days']);
        $this->assertSame('Ebenezer Congregation, Mamprobi', $settings['congregation_name']);
        $this->assertSame('Mamprobi', $settings['district_name']);
        $this->assertSame('45', $settings['newcomer_followup_days']);
        $this->assertSame('60', $settings['committee_term_warning_days']);
    }

    public function test_unticked_entries_are_removed_and_new_ones_added(): void
    {
        $committees = Committee::pluck('name')->all();
        $left = array_slice($committees, 1);
        $options = NewcomerOption::lists();
        $options['source'] = ['Friend', 'A crusade'];

        $this->post(route('setup.store'), $this->payload([
            'committees' => [...$left, 'Building Committee'],
            'venues' => ['Main Chapel', 'The Hall'],
            'options' => $options,
        ]))->assertRedirect(route('dashboard'));

        $this->assertFalse(Committee::where('name', $committees[0])->exists());
        $this->assertTrue(Committee::where('name', 'Building Committee')->exists());
        $this->assertSame(['Main Chapel', 'The Hall'], EventVenue::orderBy('sort_order')->pluck('name')->all());
        $this->assertSame(['Friend', 'A crusade'], NewcomerOption::where('kind', 'source')->orderBy('sort_order')->pluck('name')->all());
        $this->assertSame(['Dr.', 'Mr.', 'Mrs.', 'Miss'], NewcomerOption::where('kind', 'title')->orderBy('sort_order')->pluck('name')->all());
    }

    public function test_an_occupation_is_filed_under_its_category_and_one_members_have_is_kept(): void
    {
        $taken = Profession::orderBy('id')->firstOrFail();
        Member::create(['member_number' => 'M1', 'full_name' => 'Mensah Efua', 'status' => 'active', 'sex' => 'female', 'profession_id' => $taken->id]);

        $this->post(route('setup.store'), $this->payload([
            'occupations' => ['Trades' => ['Welder']],
        ]))->assertRedirect(route('dashboard'));

        $this->assertSame('Trades', Profession::where('name', 'Welder')->value('category'));
        $this->assertTrue(Profession::whereKey($taken->id)->exists());
        $this->assertSame(2, Profession::count());
    }

    public function test_a_group_people_belong_to_is_never_removed(): void
    {
        $group = MemberGroup::where('name', 'Church Choir')->firstOrFail();
        Member::create(['member_number' => 'M1', 'full_name' => 'Mensah Efua', 'status' => 'active', 'sex' => 'female'])->groups()->attach($group->id);

        $this->post(route('setup.store'), $this->payload(['groups' => ['BSPG']]))->assertRedirect(route('dashboard'));

        $this->assertTrue(MemberGroup::where('name', 'Church Choir')->exists());
        $this->assertFalse(MemberGroup::where('name', 'Brigade')->exists());
    }

    public function test_choosing_sample_data_populates_the_app_for_testing(): void
    {
        $this->post(route('setup.store'), $this->payload(['sample_data' => true]))->assertRedirect(route('dashboard'));

        $this->assertTrue(Member::where('is_sample', true)->exists());
        $this->assertTrue(CommunionService::where('is_sample', true)->exists());
        $this->assertTrue(MemberRequest::where('is_sample', true)->exists());
    }

    public function test_starting_clean_adds_no_sample_data(): void
    {
        $this->post(route('setup.store'), $this->payload())->assertRedirect(route('dashboard'));

        $this->assertFalse(Member::where('is_sample', true)->exists());
    }

    public function test_the_administrator_details_are_checked(): void
    {
        $this->post(route('setup.store'), $this->payload(['password_confirmation' => 'different', 'followup_days' => 3, 'username' => 'bad name']))
            ->assertSessionHasErrors(['password', 'followup_days', 'username']);

        $this->assertSame(0, User::count());
    }

    public function test_setup_closes_once_there_is_a_user(): void
    {
        $this->post(route('setup.store'), $this->payload())->assertRedirect(route('dashboard'));
        auth()->logout();

        $this->get(route('setup.show'))->assertRedirect(route('login'));
        $this->post(route('setup.store'), $this->payload(['username' => 'second']))->assertRedirect(route('login'));
        $this->assertSame(1, User::count());
        $this->get(route('login'))->assertOk();
    }
}
