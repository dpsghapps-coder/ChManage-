<?php

namespace Tests\Feature\Members;

use App\Models\MediaFile;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberNextOfKin;
use App\Models\MemberSacrament;
use App\Models\YoungMember;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MemberDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $number, string $name, string $status = 'active', string $sex = 'female', ?int $age = null): Member
    {
        return Member::create([
            'member_number' => $number, 'full_name' => $name, 'status' => $status, 'sex' => $sex,
            'date_of_birth' => $age === null ? null : today()->subYears($age),
        ]);
    }

    private function young(string $label, string $first, int $age, array $extra = []): YoungMember
    {
        return YoungMember::create([
            'number_year' => now()->year, 'number_seq' => YoungMember::nextSequence(), 'first_name' => $first, 'last_name' => 'Mensah',
            'date_of_birth' => today()->subYears($age), ...$extra,
        ]);
    }

    public function test_it_requires_the_members_view_permission(): void
    {
        $this->actingAs($this->userWith([]))->get('/members')->assertForbidden();
        $this->actingAs($this->userWith(['members.view']))->get('/members')->assertOk();
    }

    public function test_adults_hide_deleted_members_by_default_and_filter_by_search_status_and_sex(): void
    {
        $this->member('M1', 'Ama Mensah');
        $this->member('M2', 'Kofi Boateng', 'transferred', 'male');
        $this->member('M3', 'Yaw Deleted', 'deleted', 'male');
        $viewer = $this->userWith(['members.view']);

        $this->actingAs($viewer)->get('/members')
            ->assertInertia(fn (Assert $page) => $page->component('members/index')->has('members.data', 2));
        $this->actingAs($viewer)->get('/members?q=Kofi')
            ->assertInertia(fn (Assert $page) => $page->has('members.data', 1)->where('members.data.0.member_number', 'M2'));
        $this->actingAs($viewer)->get('/members?status=transferred')
            ->assertInertia(fn (Assert $page) => $page->has('members.data', 1)->where('members.data.0.member_number', 'M2'));
        $this->actingAs($viewer)->get('/members?sex=female')
            ->assertInertia(fn (Assert $page) => $page->has('members.data', 1)->where('members.data.0.member_number', 'M1'));
    }

    public function test_the_tabs_read_the_adult_register_and_the_separate_young_members_table(): void
    {
        $this->member('M1', 'Adult Person', age: 40);
        $this->member('M2', 'Undated Person');
        $this->member('M3', 'Old Kid Row', age: 15); // under 18 on the main register: shown on the youth tabs, not here
        $this->young('Y1', 'Teen', 15);
        $this->young('Y2', 'Child', 7);
        $viewer = $this->userWith(['members.view']);

        $this->actingAs($viewer)->get('/members')
            ->assertInertia(fn (Assert $page) => $page->where('category', 'adults')
                ->where('counts', ['adults' => 2, 'junior_youth' => 1, 'children' => 1])->has('members.data', 2));
        $this->actingAs($viewer)->get('/members?category=junior_youth')
            ->assertInertia(fn (Assert $page) => $page->has('members.data', 1)->where('members.data.0.first_name', 'Teen')->where('members.data.0.class', 'JY Intermediate Class (Ages 14–15)'));
        $this->actingAs($viewer)->get('/members?category=children')
            ->assertInertia(fn (Assert $page) => $page->has('members.data', 1)->where('members.data.0.first_name', 'Child')->where('members.data.0.class', 'CS Class 2 (Ages 6–8)'));
    }

    public function test_registering_needs_the_create_permission(): void
    {
        $this->actingAs($this->userWith(['members.view']))->get('/members/create')->assertForbidden();
        $this->actingAs($this->userWith(['members.view']))->post('/members', [])->assertForbidden();
        $this->actingAs($this->userWith(['members.create']))->get('/members/create')->assertOk();
    }

    private function registration(array $overrides = []): array
    {
        return [
            'first_name' => 'Nana', 'last_name' => 'Mensah', 'other_names' => 'Kofi',
            'date_of_birth' => today()->subYears(8)->toDateString(), 'joined_on' => today()->toDateString(),
            'mobile' => '0244000000', 'telephone' => '0201000000',
            'guardians' => [['relationship' => 'aunt', 'is_member' => false, 'name' => 'Auntie Ama', 'phone' => '0277000000', 'is_primary' => true]],
            ...$overrides,
        ];
    }

    public function test_it_registers_a_child_with_several_guardians_some_of_them_members(): void
    {
        $mother = $this->member('PCG/ECM/2016/000011', 'Efua Mensah', age: 42);
        $mother->update(['mobile' => '0244111222']);

        $this->actingAs($this->userWith(['members.create']))->post('/members', $this->registration(['guardians' => [
            ['relationship' => 'mother', 'is_member' => true, 'member_id' => $mother->id, 'name' => 'ignored', 'phone' => '', 'is_primary' => true],
            ['relationship' => 'other', 'relationship_other' => 'Family friend', 'is_member' => false, 'name' => 'Mr Kojo', 'phone' => '0277000000', 'is_primary' => false],
        ]]))->assertRedirect('/members?category=children');

        $child = YoungMember::firstOrFail();
        $this->assertSame('PCG/ECM/'.now()->year.'/CS/000001', $child->member_number);
        $this->assertSame('0244000000', $child->mobile);
        $this->assertSame('Mensah Nana Kofi', $child->fullName());
        $this->assertDatabaseHas('audit_logs', ['event' => 'member.created', 'subject_id' => $child->id]);
        $this->assertSame(0, Member::where('first_name', 'Nana')->count(), 'kept out of the main register');

        [$first, $second] = $child->guardians->all();
        $this->assertTrue($first->is_primary);
        $this->assertSame($mother->id, $first->member_id);
        $this->assertSame('Efua Mensah', $first->name, 'the name comes from the member record, not the browser');
        $this->assertSame('0244111222', $first->phone);
        $this->assertSame('Family friend', $second->relationshipLabel());
        $this->assertNull($second->member_id);
    }

    public function test_the_tab_lists_guardians_with_a_members_current_details(): void
    {
        $mother = $this->member('M9', 'Efua Mensah', age: 40);
        $child = $this->young('Y1', 'Kojo', 10);
        $child->guardians()->create(['relationship' => 'mother', 'member_id' => $mother->id, 'name' => 'Efua Old', 'is_primary' => true]);
        $mother->update(['full_name' => 'Efua Renamed']);

        $this->actingAs($this->userWith(['members.view']))->get('/members?category=children')
            ->assertInertia(fn (Assert $page) => $page->where('members.data.0.guardians.0.name', 'Efua Renamed')
                ->where('members.data.0.guardians.0.member_number', 'M9')->where('members.data.0.guardians.0.relationship', 'Mother'));
    }

    public function test_it_requires_a_guardian_and_exactly_one_primary_contact(): void
    {
        $user = $this->userWith(['members.create']);

        $this->actingAs($user)->post('/members', $this->registration(['guardians' => []]))->assertSessionHasErrors('guardians');

        $twoPrimary = $this->registration(['guardians' => [
            ['relationship' => 'aunt', 'name' => 'A', 'is_primary' => true],
            ['relationship' => 'uncle', 'name' => 'B', 'is_primary' => true],
        ]]);
        $this->actingAs($user)->post('/members', $twoPrimary)->assertSessionHasErrors('guardians');

        $nonePrimary = $this->registration(['guardians' => [['relationship' => 'aunt', 'name' => 'A', 'is_primary' => false]]]);
        $this->actingAs($user)->post('/members', $nonePrimary)->assertSessionHasErrors('guardians');

        $this->assertSame(0, YoungMember::count());
    }

    public function test_it_rejects_adults_and_guardians_that_are_incomplete_or_not_active_members(): void
    {
        $inactive = $this->member('M5', 'Gone Person', 'transferred', age: 50);

        $this->actingAs($this->userWith(['members.create']))->post('/members', $this->registration([
            'first_name' => '', 'date_of_birth' => today()->subYears(25)->toDateString(),
            'guardians' => [
                ['relationship' => 'father', 'is_member' => true, 'member_id' => $inactive->id, 'is_primary' => true],
                ['relationship' => 'other', 'is_member' => false, 'name' => '', 'is_primary' => false],
            ],
        ]))->assertSessionHasErrors(['first_name', 'date_of_birth', 'guardians.0.member_id', 'guardians.1.name', 'guardians.1.relationship_other']);

        $this->assertSame(0, YoungMember::count());
    }

    public function test_member_search_finds_only_active_members_and_needs_the_create_permission(): void
    {
        $this->member('M1', 'Efua Mensah');
        $this->member('M2', 'Efua Gone', 'transferred');

        $this->actingAs($this->userWith(['members.view']))->getJson('/members/search?q=Efua')->assertForbidden();

        $creator = $this->userWith(['members.create']);
        $this->actingAs($creator)->getJson('/members/search?q=Efua')->assertOk()->assertJsonCount(1)->assertJsonPath('0.member_number', 'M1');
        $this->actingAs($creator)->getJson('/members/search?q=E')->assertOk()->assertJsonCount(0);
    }

    public function test_it_saves_the_uploaded_photo_and_gps_location_with_the_registration(): void
    {
        $folder = sys_get_temp_dir().'/ecm-young-'.uniqid();
        config(['church.young_member_photos' => $folder]);
        $user = $this->userWith(['members.create', 'members.view']);

        $this->actingAs($user)->post('/members', $this->registration([
            'photo' => UploadedFile::fake()->image('camera.jpg', 600, 800),
            'latitude' => '5.6037123', 'longitude' => '-0.1870456', 'location_accuracy' => '12.4',
        ]))->assertRedirect();

        $child = YoungMember::firstOrFail();
        $this->assertNotNull($child->photo_path);
        $this->assertFileExists($folder.'/'.$child->photo_path);
        $this->assertEqualsWithDelta(5.6037123, $child->latitude, 0.0000001);
        $this->assertEqualsWithDelta(-0.1870456, $child->longitude, 0.0000001);
        $this->assertSame(12, $child->location_accuracy);

        $this->actingAs($user)->get(route('members.young.photo', $child))->assertOk();
        $this->actingAs($user)->get('/members?category=children')->assertInertia(fn (Assert $page) => $page
            ->where('members.data.0.photo_url', route('members.young.photo', $child))
            ->where('members.data.0.latitude', 5.6037123));
    }

    public function test_a_child_without_a_photo_falls_back_to_the_photo_of_the_member_record_it_was_copied_from(): void
    {
        $folder = sys_get_temp_dir().'/ecm-photos-'.uniqid();
        mkdir($folder);
        file_put_contents($folder.'/old.jpg', 'jpeg-bytes');
        config(['church.member_photos' => $folder]);

        $member = $this->member('M1', 'Old Row', age: 12);
        $member->update(['photo_id' => MediaFile::create(['filename' => 'old.jpg', 'mime_type' => 'image/jpeg'])->id]);
        $child = $this->young('Y1', 'Old', 12, ['member_id' => $member->id]);

        $this->actingAs($this->userWith(['members.view']))->get(route('members.young.photo', $child))->assertOk();
        $this->actingAs($this->userWith(['members.view']))->get(route('members.young.photo', $this->young('Y2', 'None', 9)))->assertNotFound();
    }

    public function test_it_rejects_a_bad_photo_and_an_impossible_or_half_given_location(): void
    {
        config(['church.young_member_photos' => sys_get_temp_dir().'/ecm-young-'.uniqid()]);
        $user = $this->userWith(['members.create']);

        $this->actingAs($user)->post('/members', $this->registration([
            'photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
            'latitude' => '95', 'longitude' => '-0.18',
        ]))->assertSessionHasErrors(['photo', 'latitude']);

        $this->actingAs($user)->post('/members', $this->registration(['latitude' => '5.6']))->assertSessionHasErrors('longitude');

        $this->assertSame(0, YoungMember::count());
    }

    public function test_children_service_is_0_to_11_and_junior_youth_is_12_to_17_with_18_and_over_as_adults(): void
    {
        $this->member('M1', 'Just Eighteen', age: 18);
        $this->young('Y1', 'Eleven', 11);
        $this->young('Y2', 'Twelve', 12);
        $this->young('Y3', 'Seventeen', 17);

        $this->actingAs($this->userWith(['members.view']))->get('/members')
            ->assertInertia(fn (Assert $page) => $page->where('counts', ['adults' => 1, 'junior_youth' => 2, 'children' => 1]));

        $user = $this->userWith(['members.create']);
        $this->actingAs($user)->post('/members', $this->registration(['date_of_birth' => today()->subYears(18)->toDateString()]))
            ->assertSessionHasErrors('date_of_birth');
        $this->actingAs($user)->post('/members', $this->registration(['date_of_birth' => today()->subYears(17)->toDateString()]))
            ->assertSessionHasNoErrors();
    }

    public function test_the_sample_command_fills_both_tabs_and_purge_removes_only_the_samples(): void
    {
        $mother = $this->member('M1', 'Mensah Efua', age: 40);
        $mother->update(['mobile' => '0244111222']);
        $real = $this->young('a', 'Real', 9);

        $this->artisan('young-members:sample', ['--count' => 5])->assertSuccessful();

        $samples = YoungMember::where('is_sample', true)->with('guardians')->get();
        $this->assertCount(10, $samples);
        $this->assertSame(5, $samples->filter(fn ($y) => YoungMember::departmentFor($y->date_of_birth) === 'CS')->count());
        $this->assertSame(5, $samples->filter(fn ($y) => YoungMember::departmentFor($y->date_of_birth) === 'JY')->count());
        $this->assertSame(11, YoungMember::pluck('number_seq')->unique()->count(), 'one shared sequence, no repeats');

        foreach ($samples as $child) {
            $this->assertGreaterThanOrEqual(1, $child->guardians->count());
            $this->assertSame(1, $child->guardians->where('is_primary', true)->count(), 'exactly one primary contact');
        }

        $this->artisan('young-members:sample', ['--purge' => true])->assertSuccessful();

        $this->assertSame(0, YoungMember::where('is_sample', true)->count());
        $this->assertTrue(YoungMember::whereKey($real->id)->exists(), 'real registrations are never purged');
    }

    public function test_it_registers_an_adult_with_photo_and_location_and_refuses_anyone_under_18(): void
    {
        config(['church.member_photos' => sys_get_temp_dir().'/ecm-adult-'.uniqid()]);
        $this->member('PCG/ECM/2025/000100', 'Existing Person', age: 50);
        $user = $this->userWith(['members.create', 'members.view']);
        $adult = [
            'first_name' => 'Efua', 'last_name' => 'Mensah', 'other_names' => 'Ama', 'sex' => 'female', 'title' => 'Mrs',
            'date_of_birth' => today()->subYears(30)->toDateString(), 'is_communicant' => true, 'mobile' => '0244000000',
            'photo' => UploadedFile::fake()->image('me.jpg', 300, 300), 'latitude' => '5.6', 'longitude' => '-0.19',
        ];

        $this->actingAs($user)->get(route('members.adult.create'))->assertOk();
        $this->actingAs($user)->post(route('members.adult.store'), $adult)->assertRedirect('/members?category=adults');

        $member = Member::where('first_name', 'Efua')->firstOrFail();
        $this->assertSame('PCG/ECM/'.now()->year.'/000101', $member->member_number);
        $this->assertSame('Mensah Efua Ama', $member->full_name);
        $this->assertNotNull($member->photo_id);
        $this->assertEqualsWithDelta(5.6, $member->latitude, 0.0001);
        $this->assertDatabaseHas('audit_logs', ['event' => 'member.created', 'subject_id' => $member->id]);

        $this->actingAs($user)->post(route('members.adult.store'), [...$adult, 'first_name' => 'Teen', 'date_of_birth' => today()->subYears(17)->toDateString(), 'photo' => null])
            ->assertSessionHasErrors('date_of_birth');
        $this->actingAs($this->userWith(['members.view']))->get(route('members.adult.create'))->assertForbidden();
    }

    public function test_phone_numbers_must_be_ten_digits_starting_with_zero_everywhere(): void
    {
        $user = $this->userWith(['members.create', 'members.edit']);
        $good = ['0244123456', '0302214490'];
        $bad = ['244123456', '02441234567', '024412345a', '+233244123456', '0244 123 456'];

        foreach ($bad as $number) {
            $this->actingAs($user)->post('/members', $this->registration([
                'mobile' => $number, 'telephone' => $number,
                'guardians' => [['relationship' => 'aunt', 'is_member' => false, 'name' => 'A', 'phone' => $number, 'is_primary' => true]],
            ]))->assertSessionHasErrors(['mobile', 'telephone', 'guardians.0.phone']);

            $this->actingAs($user)->post(route('members.adult.store'), $this->adultRegistration(['mobile' => $number, 'telephone' => $number]))
                ->assertSessionHasErrors(['mobile', 'telephone']);
        }

        $this->actingAs($user)->post(route('members.adult.store'), $this->adultRegistration(['mobile' => $good[0], 'telephone' => $good[1]]))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('members', ['mobile' => '0244123456', 'telephone' => '0302214490']);
    }

    private function adultRegistration(array $overrides = []): array
    {
        return [
            'first_name' => 'Efua', 'last_name' => 'Mensah', 'sex' => 'female', 'date_of_birth' => today()->subYears(30)->toDateString(),
            'has_related' => true,
            ...$overrides,
        ];
    }

    public function test_an_adult_can_be_viewed_edited_soft_deleted_and_restored(): void
    {
        $folder = sys_get_temp_dir().'/ecm-adult-'.uniqid();
        config(['church.member_photos' => $folder]);
        $member = $this->member('M1', 'Mensah Efua Ama', age: 40);
        $member->update(['first_name' => 'Efua', 'last_name' => 'Mensah']);
        $editor = $this->userWith(['members.view', 'members.edit', 'members.delete']);

        $this->actingAs($editor)->get(route('members.show', $member))->assertInertia(fn (Assert $page) => $page
            ->component('members/show')->where('member.full_name', 'Mensah Efua Ama'));
        $this->actingAs($editor)->get(route('members.edit', $member))->assertInertia(fn (Assert $page) => $page
            ->component('members/adult-form')->where('member.other_names', 'Ama'));

        // Saving without touching the name keeps the register's full name; the photo and GPS are added.
        $this->actingAs($editor)->put(route('members.update', $member), $this->adultRegistration([
            'other_names' => 'Ama', 'mobile' => '0244000000',
            'photo' => UploadedFile::fake()->image('me.jpg', 300, 300), 'latitude' => '5.6', 'longitude' => '-0.19', 'location_accuracy' => '9',
        ]))->assertRedirect(route('members.show', $member));

        $member->refresh();
        $this->assertSame('Mensah Efua Ama', $member->full_name);
        $this->assertSame('0244000000', $member->mobile);
        $this->assertNotNull($member->photo_id);
        $this->assertEqualsWithDelta(5.6, $member->latitude, 0.0001);
        $this->assertDatabaseHas('audit_logs', ['event' => 'member.updated', 'subject_id' => $member->id]);

        // Changing the name rebuilds it.
        $this->actingAs($editor)->put(route('members.update', $member), $this->adultRegistration(['first_name' => 'Esi']))->assertRedirect();
        $this->assertSame('Mensah Esi', $member->fresh()->full_name);

        // Soft delete: hidden by default, still there, listed under the Deleted filter, and restorable.
        $this->actingAs($editor)->delete(route('members.destroy', $member))->assertRedirect();
        $this->assertSame('deleted', $member->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'member.deleted', 'subject_id' => $member->id]);
        $this->actingAs($editor)->get('/members')->assertInertia(fn (Assert $page) => $page->has('members.data', 0));
        $this->actingAs($editor)->get('/members?status=deleted')->assertInertia(fn (Assert $page) => $page->has('members.data', 1));

        $this->actingAs($editor)->post(route('members.restore', $member))->assertRedirect();
        $this->assertSame('active', $member->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'member.restored', 'subject_id' => $member->id]);
    }

    public function test_view_edit_and_delete_each_need_their_own_permission(): void
    {
        $member = $this->member('M1', 'Mensah Efua', age: 40);
        $child = $this->young('a', 'Kojo', 9);
        $viewer = $this->userWith(['members.view']);
        $editor = $this->userWith(['members.view', 'members.edit']);

        $this->actingAs($viewer)->get(route('members.show', $member))->assertOk();
        $this->actingAs($viewer)->get(route('members.young.show', $child))->assertOk();

        foreach ([
            fn () => $this->get(route('members.edit', $member)),
            fn () => $this->get(route('members.young.edit', $child)),
            fn () => $this->delete(route('members.destroy', $member)),
            fn () => $this->delete(route('members.young.destroy', $child)),
            fn () => $this->post(route('members.restore', $member)),
            fn () => $this->post(route('members.young.restore', $child)),
        ] as $request) {
            $this->actingAs($viewer);
            $request()->assertForbidden();
        }

        // Editing is not deleting.
        $this->actingAs($editor)->get(route('members.edit', $member))->assertOk();
        $this->actingAs($editor)->delete(route('members.destroy', $member))->assertForbidden();
        $this->actingAs($editor)->delete(route('members.young.destroy', $child))->assertForbidden();
        $this->assertSame('active', $member->fresh()->status);
    }

    public function test_a_child_can_be_viewed_edited_with_new_guardians_soft_deleted_and_restored(): void
    {
        $folder = sys_get_temp_dir().'/ecm-young-'.uniqid();
        config(['church.young_member_photos' => $folder]);
        $mother = $this->member('M1', 'Mensah Efua', age: 40);
        $mother->update(['mobile' => '0244111222']);
        $child = $this->young('a', 'Kojo', 9);
        $child->guardians()->create(['relationship' => 'aunt', 'name' => 'Auntie', 'phone' => '0277000000', 'is_primary' => true]);
        $user = $this->userWith(['members.view', 'members.edit', 'members.delete']);

        $this->actingAs($user)->get(route('members.young.show', $child))->assertInertia(fn (Assert $page) => $page
            ->component('members/young-show')->where('child.first_name', 'Kojo')->where('child.class', 'CS Class 3 (Ages 9–11)')
            ->has('child.guardians', 1)->where('child.guardians.0.name', 'Auntie'));
        $this->actingAs($user)->get(route('members.young.edit', $child))->assertInertia(fn (Assert $page) => $page
            ->component('members/form')->where('child.guardians.0.is_member', false)->where('child.guardians.0.name', 'Auntie'));

        $this->actingAs($user)->put(route('members.young.update', $child), $this->registration([
            'first_name' => 'Kojo', 'photo' => UploadedFile::fake()->image('kojo.jpg', 400, 400),
            'guardians' => [
                ['relationship' => 'mother', 'is_member' => true, 'member_id' => $mother->id, 'name' => '', 'phone' => '', 'is_primary' => true],
                ['relationship' => 'uncle', 'is_member' => false, 'name' => 'Uncle Joe', 'phone' => '0201234567', 'is_primary' => false],
            ],
        ]))->assertRedirect(route('members.young.show', $child));

        $child->refresh()->load('guardians');
        $this->assertNotNull($child->photo_path);
        $this->assertFileExists($folder.'/'.$child->photo_path);
        $this->assertSame(['mother', 'uncle'], $child->guardians->pluck('relationship')->all());
        $this->assertSame($mother->id, $child->guardians[0]->member_id);
        $this->assertSame(1, $child->guardians->where('is_primary', true)->count());
        $this->assertSame(YoungMember::find($child->id)->number_seq, $child->number_seq, 'the number never changes');
        $this->assertDatabaseHas('audit_logs', ['event' => 'member.updated', 'subject_id' => $child->id]);

        // An update still needs exactly one primary contact.
        $this->actingAs($user)->put(route('members.young.update', $child), $this->registration([
            'guardians' => [['relationship' => 'aunt', 'name' => 'A', 'is_primary' => false]],
        ]))->assertSessionHasErrors('guardians');

        $this->actingAs($user)->delete(route('members.young.destroy', $child))->assertRedirect();
        $this->assertSame('deleted', $child->fresh()->status);
        $this->actingAs($user)->get('/members?category=children')->assertInertia(fn (Assert $page) => $page->has('members.data', 0));
        $this->actingAs($user)->post(route('members.young.restore', $child))->assertRedirect();
        $this->assertSame('active', $child->fresh()->status);
    }

    public function test_an_adult_saved_without_a_full_name_still_shows_and_is_found_by_name(): void
    {
        Member::create([
            'member_number' => 'M1', 'full_name' => '', 'first_name' => 'Nicolina', 'last_name' => 'Bortey', 'status' => 'active',
            'date_of_birth' => today()->subYears(40),
        ]);
        $viewer = $this->userWith(['members.view', 'members.create']);

        $this->actingAs($viewer)->get('/members')->assertInertia(fn (Assert $page) => $page->where('members.data.0.full_name', 'Bortey Nicolina'));
        $this->actingAs($viewer)->get('/members?q=Nicolina')->assertInertia(fn (Assert $page) => $page->has('members.data', 1));
        $this->actingAs($viewer)->getJson('/members/search?q=Bortey')->assertJsonPath('0.full_name', 'Bortey Nicolina');
    }

    public function test_the_lists_carry_everything_the_view_modal_shows(): void
    {
        $member = $this->member('M1', 'Mensah Efua', age: 40);
        $member->update(['mobile' => '0244111222', 'telephone' => '0302000000', 'email' => 'efua@example.com', 'hometown' => 'Winneba', 'marital_status' => 'married']);
        $child = $this->young('a', 'Kojo', 9, ['mobile' => '0201234567', 'telephone' => '0277000000']);
        $child->guardians()->create(['relationship' => 'mother', 'member_id' => $member->id, 'name' => 'Mensah Efua', 'is_primary' => true]);
        $viewer = $this->userWith(['members.view']);

        $this->actingAs($viewer)->get('/members')->assertInertia(fn (Assert $page) => $page
            ->where('members.data.0.telephone', '0302000000')->where('members.data.0.email', 'efua@example.com')
            ->where('members.data.0.hometown', 'Winneba')->where('members.data.0.marital_status', 'married')
            ->where('members.data.0.date_of_birth', $member->date_of_birth->toDateString()));

        $this->actingAs($viewer)->get('/members?category=children')->assertInertia(fn (Assert $page) => $page
            ->where('members.data.0.mobile', '0201234567')->where('members.data.0.telephone', '0277000000')
            ->where('members.data.0.guardians.0.phones', ['0244111222', '0302000000']));
    }

    public function test_next_of_kin_sacraments_and_groups_are_saved_replaced_and_cleared_with_the_member(): void
    {
        $choir = MemberGroup::create(['name' => 'Church Choir', 'short_name' => 'C. Choir']);
        $brigade = MemberGroup::create(['name' => 'Brigade']);
        $user = $this->userWith(['members.view', 'members.create', 'members.edit']);

        $this->actingAs($user)->post(route('members.adult.store'), $this->adultRegistration([
            'has_related' => true,
            'next_of_kin' => ['name' => 'Kofi Mensah', 'phone' => '0244111222', 'residential_address' => 'Mamprobi', 'postal_address' => 'Box 1, Accra'],
            'sacraments' => [
                'baptism' => ['date' => '1990-05-01', 'place' => 'Mamprobi', 'minister' => 'Rev. Ayeh'],
                'confirmation' => ['date' => '', 'place' => '', 'minister' => ''],
            ],
            'group_ids' => [$choir->id, $brigade->id],
        ]))->assertSessionHasNoErrors();

        $member = Member::where('first_name', 'Efua')->firstOrFail();
        $this->assertSame('Kofi Mensah', $member->nextOfKin->name);
        $this->assertSame('Box 1, Accra', $member->nextOfKin->postal_address);
        $this->assertSame(['baptism'], $member->sacraments()->pluck('kind')->all(), 'an empty sacrament is not stored');
        $this->assertSame('Rev. Ayeh', $member->sacraments()->where('kind', 'baptism')->value('minister'));
        $this->assertEqualsCanonicalizing([$choir->id, $brigade->id], $member->groups()->pluck('member_groups.id')->all());

        // An update that carries the steps replaces them...
        $this->actingAs($user)->put(route('members.update', $member), $this->adultRegistration([
            'has_related' => true,
            'next_of_kin' => ['name' => '', 'phone' => '', 'residential_address' => '', 'postal_address' => ''],
            'sacraments' => [
                'baptism' => ['date' => '1990-05-01', 'place' => 'Winneba', 'minister' => ''],
                'confirmation' => ['date' => '2000-06-01', 'place' => 'Accra', 'minister' => 'Rev. Kofi'],
            ],
            'group_ids' => [$brigade->id],
        ]))->assertSessionHasNoErrors();

        $member->refresh();
        $this->assertNull($member->nextOfKin()->first(), 'an emptied step removes its record');
        $this->assertSame('Winneba', $member->sacraments()->where('kind', 'baptism')->value('place'));
        $this->assertSame(2, $member->sacraments()->count());
        $this->assertSame([$brigade->id], $member->groups()->pluck('member_groups.id')->all());

        // ...an empty list of groups clears them...
        $this->actingAs($user)->put(route('members.update', $member), $this->adultRegistration(['has_related' => true]))->assertSessionHasNoErrors();
        $this->assertSame(0, $member->groups()->count());
        $this->assertSame(0, $member->sacraments()->count());

        // ...and one that does not carry them leaves everything alone.
        MemberSacrament::create(['member_id' => $member->id, 'kind' => 'baptism', 'place' => 'Kept']);
        $this->actingAs($user)->put(route('members.update', $member), $this->adultRegistration(['has_related' => false, 'mobile' => '0244000000']))->assertSessionHasNoErrors();
        $this->assertSame(1, $member->sacraments()->count());
    }

    public function test_the_related_steps_are_validated(): void
    {
        $user = $this->userWith(['members.create']);

        $this->actingAs($user)->post(route('members.adult.store'), $this->adultRegistration([
            'has_related' => true,
            'next_of_kin' => ['name' => 'Kofi', 'phone' => '12345', 'residential_address' => str_repeat('x', 201), 'postal_address' => ''],
            'sacraments' => ['baptism' => ['date' => today()->addDay()->toDateString(), 'place' => '', 'minister' => ''], 'confirmation' => ['date' => 'nope']],
            'group_ids' => [999],
        ]))->assertSessionHasErrors(['next_of_kin.phone', 'next_of_kin.residential_address', 'sacraments.baptism.date', 'sacraments.confirmation.date', 'group_ids.0']);

        $this->assertSame(0, Member::where('first_name', 'Efua')->count());
    }

    public function test_the_member_tabs_show_related_records_and_children_come_from_the_young_register(): void
    {
        $choir = MemberGroup::create(['name' => 'Church Choir']);
        $mother = $this->member('M1', 'Mensah Efua', age: 40);
        $mother->groups()->attach($choir->id);
        MemberNextOfKin::create(['member_id' => $mother->id, 'name' => 'Kofi Mensah', 'phone' => '0244111222']);
        MemberSacrament::create(['member_id' => $mother->id, 'kind' => 'baptism', 'sacrament_date' => '1990-05-01', 'place' => 'Mamprobi']);

        $son = $this->young('a', 'Kojo', 9);
        $son->guardians()->create(['relationship' => 'mother', 'member_id' => $mother->id, 'name' => 'Mensah Efua', 'is_primary' => true]);
        $gone = $this->young('b', 'Deleted', 5, ['status' => 'deleted']);
        $gone->guardians()->create(['relationship' => 'mother', 'member_id' => $mother->id, 'name' => 'Mensah Efua', 'is_primary' => true]);
        $other = $this->young('c', 'Stranger', 7);
        $other->guardians()->create(['relationship' => 'aunt', 'name' => 'Someone', 'is_primary' => true]);
        $viewer = $this->userWith(['members.view']);

        $expect = fn (Assert $page) => $page
            ->where('next_of_kin.name', 'Kofi Mensah')->where('sacraments.baptism.place', 'Mamprobi')->where('sacraments.confirmation.place', '')
            ->has('groups', 1)->where('groups.0.name', 'Church Choir')
            ->has('children', 1)->where('children.0.name', 'Kojo Mensah')->where('children.0.relationship', 'Mother')
            ->where('children.0.class', 'CS Class 3 (Ages 9–11)')->etc();

        $this->actingAs($viewer)->get(route('members.show', $mother))->assertInertia(fn (Assert $page) => $page->component('members/show')->has('related', $expect));
        $this->actingAs($viewer)->getJson(route('members.related', $mother))->assertOk()
            ->assertJsonPath('related.next_of_kin.phone', '0244111222')->assertJsonCount(1, 'related.children')->assertJsonPath('related.children.0.member_number', $son->member_number);

        // The edit form gets the same data plus the list of groups to pick from.
        $this->actingAs($this->userWith(['members.view', 'members.edit']))->get(route('members.edit', $mother))->assertInertia(fn (Assert $page) => $page
            ->component('members/adult-form')->where('member.group_ids', [$choir->id])->where('member.next_of_kin.name', 'Kofi Mensah')->has('groups', 1));

        $this->actingAs($this->userWith([]))->getJson(route('members.related', $mother))->assertForbidden();
    }

    public function test_search_ahead_suggests_from_every_register_and_hides_deleted_records(): void
    {
        $this->member('M1', 'Mensah Kojo', age: 40)->update(['mobile' => '0244111222']);
        $this->member('M2', 'Mensah Deleted', 'deleted', age: 40);
        $this->member('M3', 'Mensah Undated');
        $this->member('M4', 'Mensah Under Eighteen', age: 15); // lives on the youth register, not here
        $this->young('a', 'Kojo', 9);
        $this->young('b', 'Kwesi', 15);
        $this->young('c', 'Gone', 6, ['status' => 'deleted']);
        $viewer = $this->userWith(['members.view']);

        $found = $this->actingAs($viewer)->getJson('/members/suggest?q=Mensah')->assertOk()->json();

        $this->assertEqualsCanonicalizing(['Mensah Kojo', 'Mensah Undated', 'Kojo Mensah', 'Kwesi Mensah'], collect($found)->pluck('name')->all());
        $categories = collect($found)->pluck('category', 'name');
        $this->assertSame('adults', $categories['Mensah Kojo']);
        $this->assertSame('children', $categories['Kojo Mensah']);
        $this->assertSame('junior_youth', $categories['Kwesi Mensah']);
        $this->assertSame('CS Class 3 (Ages 9–11)', collect($found)->firstWhere('name', 'Kojo Mensah')['detail']);

        // By number and by mobile; too short a search suggests nothing; the permission is needed.
        $this->assertSame('Mensah Kojo', $this->actingAs($viewer)->getJson('/members/suggest?q=0244111')->json('0.name'));
        $this->actingAs($viewer)->getJson('/members/suggest?q=M')->assertExactJson([]);
        $this->actingAs($this->userWith([]))->getJson('/members/suggest?q=Mensah')->assertForbidden();
    }

    public function test_searching_by_first_name_and_surname_together_finds_the_member(): void
    {
        $adult = Member::create([
            'member_number' => 'M1', 'full_name' => 'Mensah Kojo Ama', 'first_name' => 'Kojo', 'last_name' => 'Mensah',
            'status' => 'active', 'date_of_birth' => today()->subYears(40),
        ]);
        $this->young('a', 'Kojo', 9);
        $viewer = $this->userWith(['members.view']);

        $this->actingAs($viewer)->get('/members?q=kojo+mensah')->assertInertia(fn (Assert $page) => $page->has('members.data', 1)->where('members.data.0.id', $adult->id));
        $this->actingAs($viewer)->get('/members?category=children&q=mensah+kojo')->assertInertia(fn (Assert $page) => $page->has('members.data', 1));
        $this->actingAs($viewer)->get('/members?q=kojo+nobody')->assertInertia(fn (Assert $page) => $page->has('members.data', 0));
        $this->actingAs($viewer)->getJson('/members/suggest?q=kojo+mensah')->assertJsonCount(2);
    }

    public function test_names_are_saved_in_title_case_however_they_are_typed(): void
    {
        $user = $this->userWith(['members.create', 'members.view']);

        $this->actingAs($user)->post(route('members.adult.store'), $this->adultRegistration([
            'first_name' => 'EFUA  ama', 'last_name' => 'OWUSU-ansah', 'other_names' => 'nana',
            'has_related' => true, 'next_of_kin' => ['name' => 'KOFI MENSAH', 'phone' => '', 'residential_address' => '', 'postal_address' => ''],
            'sacraments' => ['baptism' => ['date' => '', 'place' => '', 'minister' => 'REV. DR. S. AYEYE'], 'confirmation' => []],
        ]))->assertSessionHasNoErrors();

        $member = Member::where('last_name', 'Owusu-Ansah')->firstOrFail();
        $this->assertSame('Efua Ama', $member->first_name);
        $this->assertSame('Owusu-Ansah Efua Ama Nana', $member->full_name);
        $this->assertSame('Kofi Mensah', $member->nextOfKin->name);
        $this->assertSame('Rev. Dr. S. Ayeye', $member->sacraments()->first()->minister);

        $this->actingAs($user)->post('/members', $this->registration([
            'first_name' => 'KOJO', 'last_name' => 'boateng', 'other_names' => 'YAW',
            'guardians' => [['relationship' => 'aunt', 'is_member' => false, 'name' => 'AUNTIE AMA', 'phone' => '0277000000', 'is_primary' => true]],
        ]))->assertSessionHasNoErrors();

        $child = YoungMember::firstOrFail();
        $this->assertSame(['Kojo', 'Boateng', 'Yaw'], [$child->first_name, $child->last_name, $child->other_names]);
        $this->assertSame('Auntie Ama', $child->guardians->first()->name);
    }

    public function test_the_names_clean_command_fixes_the_database_and_is_safe_to_repeat(): void
    {
        $member = Member::create(['member_number' => 'M1', 'full_name' => 'OWUSU  agnes', 'first_name' => 'AGNES', 'last_name' => 'owusu', 'father_name' => 'JOSEPH OWUSU-SEKYERE', 'status' => 'active']);
        MemberNextOfKin::create(['member_id' => $member->id, 'name' => 'SAMUEL KOFI']);
        $kept = Member::create(['member_number' => 'M2', 'full_name' => 'McDonald Kwame', 'first_name' => 'Kwame', 'last_name' => 'McDonald', 'status' => 'active']);

        $this->artisan('names:clean', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('OWUSU  agnes', $member->fresh()->getRawOriginal('full_name'), 'a dry run changes nothing');

        $this->artisan('names:clean')->assertSuccessful();
        $member->refresh();
        $this->assertSame('Owusu Agnes', $member->full_name);
        $this->assertSame(['Agnes', 'Owusu', 'Joseph Owusu-Sekyere'], [$member->first_name, $member->last_name, $member->father_name]);
        $this->assertSame('Samuel Kofi', $member->nextOfKin->name);
        $this->assertSame('McDonald Kwame', $kept->fresh()->full_name, 'names that are already right are left alone');

        $this->artisan('names:clean')->expectsOutputToContain('0 name values were updated')->assertSuccessful();
    }

    public function test_the_chms_fields_are_saved_and_the_spouse_is_read_from_their_member_record(): void
    {
        $folder = sys_get_temp_dir().'/ecm-adult-'.uniqid();
        config(['church.member_photos' => $folder]);
        $spouse = $this->member('M1', 'Boateng Kofi', 'active', 'male', 45);
        $user = $this->userWith(['members.create', 'members.view', 'members.edit']);

        $this->actingAs($user)->post(route('members.adult.store'), $this->adultRegistration([
            'place_of_birth' => 'Winneba', 'residence' => 'Mamprobi', 'instagram_id' => '@efua', 'facebook_id' => 'efua.mensah',
            'marriage_type' => 'ordinance', 'maiden_name' => 'Owusu', 'spouse_member_id' => $spouse->id, 'spouse_name' => 'ignored',
            'father_name' => 'JOSEPH mensah', 'generational_group' => "Women's Fellowship",
            'non_communicant' => true, 'non_communicant_reason' => 'Not yet confirmed',
        ]))->assertSessionHasNoErrors();

        $member = Member::where('first_name', 'Efua')->firstOrFail();
        $this->assertSame($spouse->id, $member->spouse_member_id);
        $this->assertSame('Boateng Kofi', $member->fresh()->spouse->full_name);
        $this->assertSame('Mamprobi', $member->residence);
        $this->assertSame('Joseph Mensah', $member->father_name);
        $this->assertFalse($member->is_communicant);
        $this->assertSame('Not yet confirmed', $member->non_communicant_reason);

        // A spouse who is not a member is stored by name and formatted in Title Case.
        $this->actingAs($user)->put(route('members.update', $member), $this->adultRegistration([
            'spouse_member_id' => null, 'spouse_name' => 'KWAME owusu', 'non_communicant' => false,
        ]))->assertSessionHasNoErrors();
        $member->refresh();
        $this->assertNull($member->spouse_member_id);
        $this->assertSame('Kwame Owusu', $member->spouse_name);
        $this->assertTrue($member->is_communicant);
        $this->assertNull($member->non_communicant_reason, 'the reason is cleared once the member is a communicant');
    }

    public function test_service_records_are_saved_replaced_and_validated(): void
    {
        $user = $this->userWith(['members.create', 'members.edit']);

        $this->actingAs($user)->post(route('members.adult.store'), $this->adultRegistration([
            'service_records' => [
                ['type' => 'committee', 'name' => 'Finance Committee', 'position' => 'Secretary', 'started_on' => '2019-01-05', 'ended_on' => ''],
                ['type' => 'leadership', 'name' => 'Session', 'position' => 'Elder', 'started_on' => '2015-06-01', 'ended_on' => '2018-12-31'],
            ],
        ]))->assertSessionHasNoErrors();

        $member = Member::where('first_name', 'Efua')->firstOrFail();
        $this->assertSame(2, $member->serviceRecords()->count());
        $this->assertSame('Secretary', $member->serviceRecords()->where('name', 'Finance Committee')->value('position'));

        $this->actingAs($user)->put(route('members.update', $member), $this->adultRegistration(['service_records' => []]))->assertSessionHasNoErrors();
        $this->assertSame(0, $member->serviceRecords()->count());

        $this->actingAs($user)->post(route('members.adult.store'), $this->adultRegistration([
            'first_name' => 'Second',
            'service_records' => [['type' => 'bogus', 'name' => '', 'position' => '', 'started_on' => today()->addDay()->toDateString()]],
        ]))->assertSessionHasErrors(['service_records.0.type', 'service_records.0.name', 'service_records.0.position', 'service_records.0.started_on']);
    }

    public function test_generate_report_needs_the_export_permission_and_produces_a_pdf(): void
    {
        $adult = $this->member('M1', 'Mensah Efua', age: 40);
        $child = $this->young('a', 'Kojo', 9);

        $this->actingAs($this->userWith(['members.view']))->get(route('members.report', $adult))->assertForbidden();
        $this->actingAs($this->userWith(['members.view']))->get(route('members.young.report', $child))->assertForbidden();

        $exporter = $this->userWith(['members.view', 'members.export']);
        $adultResponse = $this->actingAs($exporter)->get(route('members.report', $adult));
        $adultResponse->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $adultResponse->getContent());
        $this->assertDatabaseHas('audit_logs', ['event' => 'member.report', 'subject_id' => $adult->id]);

        $this->actingAs($exporter)->get(route('members.young.report', $child))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_class_follows_the_age(): void
    {
        $expected = [
            0 => 'CS Class 1 (Ages 0–5)', 5 => 'CS Class 1 (Ages 0–5)', 6 => 'CS Class 2 (Ages 6–8)', 8 => 'CS Class 2 (Ages 6–8)',
            9 => 'CS Class 3 (Ages 9–11)', 11 => 'CS Class 3 (Ages 9–11)', 12 => 'JY Junior Class (Ages 12–13)',
            13 => 'JY Junior Class (Ages 12–13)', 14 => 'JY Intermediate Class (Ages 14–15)', 15 => 'JY Intermediate Class (Ages 14–15)',
            16 => 'JY Senior Class (Ages 16–17)', 17 => 'JY Senior Class (Ages 16–17)', 18 => null,
        ];

        foreach ($expected as $age => $class) {
            $this->assertSame($class, $this->young("Y{$age}", 'Kid', $age)->ageClass(), "age {$age}");
        }
    }

    public function test_membership_numbers_share_one_sequence_and_show_the_department_for_the_age(): void
    {
        $user = $this->userWith(['members.create']);
        $year = now()->year;
        $register = fn (int $age) => $this->actingAs($user)->post('/members', $this->registration([
            'first_name' => "Kid{$age}", 'date_of_birth' => today()->subYears($age)->toDateString(),
        ]))->assertSessionHasNoErrors();

        $register(7);  // CS
        $register(15); // JY
        $register(9);  // CS
        $register(12); // JY

        $this->assertSame([
            "PCG/ECM/{$year}/CS/000001", "PCG/ECM/{$year}/JY/000002", "PCG/ECM/{$year}/CS/000003", "PCG/ECM/{$year}/JY/000004",
        ], YoungMember::orderBy('id')->get()->pluck('member_number')->all());
    }

    public function test_the_number_switches_from_cs_to_jy_at_twelve_and_nothing_else_changes(): void
    {
        $child = YoungMember::create([
            'number_year' => 2026, 'number_seq' => 7, 'first_name' => 'Kojo', 'last_name' => 'Mensah',
            'date_of_birth' => today()->subYears(12)->addMonth()->toDateString(), // twelve in a month
        ]);
        $this->assertSame('PCG/ECM/2026/CS/000007', $child->member_number);

        $this->travelTo(now()->addMonths(2));

        $this->assertSame('PCG/ECM/2026/JY/000007', $child->fresh()->member_number);
        $this->assertSame(7, $child->fresh()->number_seq);
    }

    public function test_young_members_can_be_searched_by_their_full_number(): void
    {
        $this->young('a', 'Teen', 15);
        $this->young('b', 'Child', 7);
        $viewer = $this->userWith(['members.view']);
        $year = now()->year;

        $this->actingAs($viewer)->get('/members?category=children&q=CS/000002')
            ->assertInertia(fn (Assert $page) => $page->has('members.data', 1)->where('members.data.0.first_name', 'Child'));
        $this->actingAs($viewer)->get('/members?category=junior_youth&q='.urlencode("{$year}/JY/000001"))
            ->assertInertia(fn (Assert $page) => $page->has('members.data', 1)->where('members.data.0.first_name', 'Teen'));
        $this->actingAs($viewer)->get('/members?category=children&q=JY/000001')
            ->assertInertia(fn (Assert $page) => $page->has('members.data', 0));
    }

    public function test_the_database_allows_only_one_primary_guardian_per_child(): void
    {
        $child = $this->young('Y1', 'Kojo', 10);
        $child->guardians()->create(['relationship' => 'mother', 'name' => 'A', 'is_primary' => true]);
        $child->guardians()->create(['relationship' => 'father', 'name' => 'B', 'is_primary' => false]);
        $other = $this->young('Y2', 'Esi', 9);
        $other->guardians()->create(['relationship' => 'aunt', 'name' => 'C', 'is_primary' => true]);

        $this->expectException(UniqueConstraintViolationException::class);
        $child->guardians()->create(['relationship' => 'uncle', 'name' => 'D', 'is_primary' => true]);
    }

    public function test_guardian_photos_and_contacts_show_for_members_and_fall_back_when_the_file_is_missing(): void
    {
        $folder = sys_get_temp_dir().'/ecm-photos-'.uniqid();
        mkdir($folder);
        file_put_contents($folder.'/mum.jpg', 'jpeg-bytes');
        config(['church.member_photos' => $folder]);

        $withPhoto = $this->member('M1', 'Efua Mensah', age: 40);
        $withPhoto->update(['mobile' => '0244111222', 'telephone' => '0302000000', 'photo_id' => MediaFile::create(['filename' => 'mum.jpg', 'mime_type' => 'image/jpeg'])->id]);
        $missingFile = $this->member('M2', 'Kojo Mensah', age: 45);
        $missingFile->update(['photo_id' => MediaFile::create(['filename' => 'gone.jpg', 'mime_type' => 'image/jpeg'])->id]);

        $child = $this->young('Y1', 'Kojo', 10);
        $child->guardians()->create(['relationship' => 'mother', 'member_id' => $withPhoto->id, 'name' => 'Efua Mensah', 'is_primary' => true]);
        $child->guardians()->create(['relationship' => 'father', 'member_id' => $missingFile->id, 'name' => 'Kojo Mensah']);
        $viewer = $this->userWith(['members.view', 'members.create']);

        $this->actingAs($viewer)->get('/members?category=children')->assertInertia(fn (Assert $page) => $page
            ->where('members.data.0.guardians.0.phones', ['0244111222', '0302000000'])
            ->where('members.data.0.guardians.0.photo_url', route('members.photo', $withPhoto))
            ->where('members.data.0.guardians.1.photo_url', null));

        $this->actingAs($viewer)->getJson('/members/search?q=Efua')->assertJsonPath('0.photo_url', route('members.photo', $withPhoto))->assertJsonPath('0.phones', ['0244111222', '0302000000']);

        $this->actingAs($viewer)->get(route('members.photo', $withPhoto))->assertOk()->assertHeader('content-length', (string) strlen('jpeg-bytes'));
        $this->actingAs($viewer)->get(route('members.photo', $missingFile))->assertNotFound();
    }
}
