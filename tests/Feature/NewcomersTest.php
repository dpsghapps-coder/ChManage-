<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MemberNextOfKin;
use App\Models\Newcomer;
use App\Models\NewcomerCounsellor;
use App\Models\NewcomerLesson;
use App\Models\NewcomerOption;
use App\Models\YoungMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NewcomersTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $number = 'M1', array $attributes = []): Member
    {
        return Member::create(['member_number' => $number, 'full_name' => 'Mensah Efua', 'status' => 'active', 'sex' => 'female', ...$attributes]);
    }

    private function counsellor(): NewcomerCounsellor
    {
        return NewcomerCounsellor::create(['member_id' => $this->member('C'.uniqid(), ['full_name' => 'Owusu Kofi'])->id]);
    }

    private function form(array $overrides = []): array
    {
        return [
            'first_visit_on' => today()->toDateString(), 'first_service' => 'Morning', 'purpose' => 'Visitation',
            'surname' => 'Boateng', 'first_name' => 'Ama', 'sex' => 'female', 'mobile' => '0244000111',
            'date_of_birth' => today()->subYears(25)->toDateString(), ...$overrides,
        ];
    }

    public function test_the_pages_follow_the_newcomer_permissions(): void
    {
        $person = Newcomer::create(['first_visit_on' => today(), 'surname' => 'Boateng', 'first_name' => 'Ama']);
        $nobody = $this->userWith([]);
        $viewer = $this->userWith(['newcomers.view']);

        $this->actingAs($nobody)->get(route('newcomers.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('newcomers.index'))->assertOk();
        $this->actingAs($viewer)->get(route('newcomers.show', $person))->assertOk();
        $this->actingAs($viewer)->get(route('newcomers.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('newcomers.store'), $this->form())->assertForbidden();
        $this->actingAs($viewer)->get(route('newcomers.counsellors.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('newcomers.lessons.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('newcomers.lists.index'))->assertForbidden();
    }

    public function test_registering_someone_makes_a_visitor_with_a_first_visit(): void
    {
        $user = $this->userWith(['newcomers.view', 'newcomers.manage']);

        $this->actingAs($user)->post(route('newcomers.store'), $this->form(['surname' => '  Boateng ']))->assertSessionHasNoErrors();

        $person = Newcomer::firstOrFail();
        $this->assertSame(['visitor', 'active', 'Boateng'], [$person->stage, $person->status, $person->surname]);
        $this->assertSame(1, $person->visits()->count());
        $this->assertSame(['visitor'], $person->changes->pluck('to_value')->all());
        $this->actingAs($user)->get(route('newcomers.show', $person))->assertInertia(fn (Assert $page) => $page
            ->component('newcomers/show')->where('newcomer.name', 'Ama Boateng')->where('newcomer.stage', 'visitor'));
    }

    public function test_a_counsellor_makes_a_visitor_a_newcomer(): void
    {
        $user = $this->userWith(['newcomers.view', 'newcomers.manage']);
        $counsellor = $this->counsellor();

        $this->actingAs($user)->post(route('newcomers.store'), $this->form())->assertSessionHasNoErrors();
        $person = Newcomer::firstOrFail();
        $this->assertSame('visitor', $person->stage);

        $this->actingAs($user)->put(route('newcomers.update', $person), $this->form(['counsellor_id' => $counsellor->id]))->assertSessionHasNoErrors();
        $this->assertSame(['newcomer', $counsellor->id], [$person->fresh()->stage, $person->fresh()->counsellor_id]);
        $this->assertSame(['visitor', 'newcomer'], $person->changes()->reorder('id')->pluck('to_value')->all());

        // An inactive counsellor is no longer offered.
        $counsellor->update(['is_active' => false]);
        $other = Newcomer::create(['first_visit_on' => today(), 'surname' => 'Asante', 'first_name' => 'Yaw']);
        $this->actingAs($user)->put(route('newcomers.update', $other), $this->form(['counsellor_id' => $counsellor->id]))->assertSessionHasErrors('counsellor_id');
    }

    public function test_someone_under_eighteen_needs_a_guardian_and_marriage_details_only_apply_to_the_married(): void
    {
        $user = $this->userWith(['newcomers.manage']);
        $teen = $this->form(['date_of_birth' => today()->subYears(15)->toDateString()]);

        $this->actingAs($user)->post(route('newcomers.store'), $teen)->assertSessionHasErrors('guardian_name');
        $this->actingAs($user)->post(route('newcomers.store'), [...$teen, 'guardian_name' => 'Mrs Boateng', 'guardian_phone' => '0244000222'])->assertSessionHasNoErrors();
        $this->assertSame('Mrs Boateng', Newcomer::firstOrFail()->guardian_name);

        // An adult's guardian details and a single person's marriage type are dropped.
        $this->actingAs($user)->post(route('newcomers.store'), $this->form(['first_name' => 'Kwame', 'guardian_name' => 'X', 'marital_status' => 'single', 'marriage_type' => 'customary']))->assertSessionHasNoErrors();
        $adult = Newcomer::where('first_name', 'Kwame')->firstOrFail();
        $this->assertSame([null, null], [$adult->guardian_name, $adult->marriage_type]);
    }

    public function test_the_form_warns_about_someone_already_on_the_books(): void
    {
        $user = $this->userWith(['newcomers.manage']);
        $this->member('M9', ['full_name' => 'Boateng Ama', 'mobile' => '024 400 0111']);
        Newcomer::create(['first_visit_on' => today(), 'surname' => 'Asante', 'first_name' => 'Yaw', 'mobile' => '0244000111']);

        $hits = $this->actingAs($user)->getJson(route('newcomers.duplicates', ['mobile' => '0244 000 111']))->assertOk()->json();
        $this->assertEqualsCanonicalizing(['member', 'newcomer'], collect($hits)->pluck('kind')->all());
        $this->assertSame([], $this->actingAs($user)->getJson(route('newcomers.duplicates', ['mobile' => '0500000000']))->json());
    }

    public function test_returning_visits_and_status_are_recorded_and_inactive_needs_a_reason(): void
    {
        $user = $this->userWith(['newcomers.view', 'newcomers.manage']);
        $person = Newcomer::create(['first_visit_on' => today()->subWeek(), 'surname' => 'Boateng', 'first_name' => 'Ama']);

        $this->actingAs($user)->post(route('newcomers.visits.store', $person), ['visited_on' => today()->toDateString(), 'service' => 'Morning'])->assertSessionHasNoErrors();
        $this->assertSame(1, $person->visits()->count());

        $this->actingAs($user)->put(route('newcomers.status', $person), ['status' => 'inactive'])->assertSessionHasErrors('reason');
        $this->actingAs($user)->put(route('newcomers.status', $person), ['status' => 'inactive', 'reason' => 'Moved away'])->assertSessionHasNoErrors();
        $this->assertSame(['inactive', 'Moved away'], [$person->fresh()->status, $person->fresh()->inactive_reason]);

        // Inactive people are hidden from the working list, and a filter brings them back.
        $this->actingAs($user)->get(route('newcomers.index'))->assertInertia(fn (Assert $page) => $page->has('people.data', 0));
        $this->actingAs($user)->get(route('newcomers.index', ['status' => 'inactive']))->assertInertia(fn (Assert $page) => $page->has('people.data', 1));

        $this->actingAs($user)->put(route('newcomers.status', $person), ['status' => 'active'])->assertSessionHasNoErrors();
        $this->assertNull($person->fresh()->inactive_reason);
    }

    public function test_the_list_filters_by_stage_counsellor_and_search(): void
    {
        $user = $this->userWith(['newcomers.view']);
        $counsellor = $this->counsellor();
        Newcomer::create(['first_visit_on' => today(), 'surname' => 'Boateng', 'first_name' => 'Ama', 'stage' => 'newcomer', 'counsellor_id' => $counsellor->id]);
        Newcomer::create(['first_visit_on' => today(), 'surname' => 'Asante', 'first_name' => 'Yaw']);

        $this->actingAs($user)->get(route('newcomers.index'))->assertInertia(fn (Assert $page) => $page
            ->has('people.data', 2)->where('counts.visitor', 1)->where('counts.newcomer', 1));
        $this->actingAs($user)->get(route('newcomers.index', ['stage' => 'newcomer']))->assertInertia(fn (Assert $page) => $page->has('people.data', 1));
        $this->actingAs($user)->get(route('newcomers.index', ['counsellor' => 'none']))->assertInertia(fn (Assert $page) => $page->has('people.data', 1)->where('people.data.0.name', 'Yaw Asante'));
        $this->actingAs($user)->get(route('newcomers.index', ['q' => 'Boat']))->assertInertia(fn (Assert $page) => $page->has('people.data', 1));
    }

    public function test_counsellors_are_members_and_are_kept_once_they_have_people(): void
    {
        $user = $this->userWith(['settings.manage']);
        $member = $this->member();

        $this->actingAs($user)->post(route('newcomers.counsellors.store'), [])->assertSessionHasErrors('member_id');
        $this->actingAs($user)->post(route('newcomers.counsellors.store'), ['member_id' => $member->id])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('newcomers.counsellors.store'), ['member_id' => $member->id])->assertSessionHasErrors('member_id');

        $counsellor = NewcomerCounsellor::firstOrFail();
        Newcomer::create(['first_visit_on' => today(), 'surname' => 'Boateng', 'first_name' => 'Ama', 'counsellor_id' => $counsellor->id]);

        $this->actingAs($user)->get(route('newcomers.counsellors.index'))->assertInertia(fn (Assert $page) => $page
            ->component('newcomers/counsellors')->where('counsellors.0.people', 1));
        $this->actingAs($user)->delete(route('newcomers.counsellors.destroy', $counsellor));
        $this->assertNotNull($counsellor->fresh());
        $this->actingAs($user)->put(route('newcomers.counsellors.update', $counsellor), ['is_active' => false])->assertSessionHasNoErrors();
        $this->assertFalse($counsellor->fresh()->is_active);

        Newcomer::query()->delete();
        $this->actingAs($user)->delete(route('newcomers.counsellors.destroy', $counsellor));
        $this->assertNull($counsellor->fresh());
    }

    public function test_lessons_are_added_reordered_and_removed(): void
    {
        $user = $this->userWith(['settings.manage']);

        foreach (['Who is God', 'The Bible', 'Prayer'] as $title) {
            $this->actingAs($user)->post(route('newcomers.lessons.store'), ['title' => $title])->assertSessionHasNoErrors();
        }
        $this->actingAs($user)->post(route('newcomers.lessons.store'), ['title' => 'the bible'])->assertSessionHasErrors('title');

        $prayer = NewcomerLesson::where('title', 'Prayer')->firstOrFail();
        $this->actingAs($user)->put(route('newcomers.lessons.move', $prayer), ['direction' => 'up'])->assertSessionHasNoErrors();
        $this->assertSame(['Who is God', 'Prayer', 'The Bible'], NewcomerLesson::orderBy('sort_order')->pluck('title')->all());

        $this->actingAs($user)->put(route('newcomers.lessons.update', $prayer), ['title' => 'Prayer and Fasting', 'is_active' => false])->assertSessionHasNoErrors();
        $this->assertFalse($prayer->fresh()->is_active);
        $this->actingAs($user)->delete(route('newcomers.lessons.destroy', $prayer));
        $this->assertNull($prayer->fresh());
    }

    public function test_the_photo_and_gps_location_are_saved_and_served(): void
    {
        $folder = sys_get_temp_dir().'/ecm-newcomers-'.uniqid();
        config(['church.newcomer_photos' => $folder]);
        $user = $this->userWith(['newcomers.view', 'newcomers.manage']);

        $this->actingAs($user)->post(route('newcomers.store'), $this->form([
            'photo' => UploadedFile::fake()->image('camera.jpg', 600, 800),
            'latitude' => '5.5359123', 'longitude' => '-0.2400456', 'location_accuracy' => '12.4',
        ]))->assertSessionHasNoErrors();

        $person = Newcomer::firstOrFail();
        $this->assertNotNull($person->photo_path);
        $this->assertFileExists($folder.'/'.$person->photo_path);
        $this->assertEqualsWithDelta(5.5359123, $person->latitude, 0.0000001);
        $this->assertEqualsWithDelta(-0.24004560, $person->longitude, 0.0000001);
        $this->assertSame(12, $person->location_accuracy);

        $this->actingAs($user)->get(route('newcomers.photo', $person))->assertOk();
        $this->actingAs($user)->get(route('newcomers.show', $person))->assertInertia(fn (Assert $page) => $page
            ->where('newcomer.photo_url', route('newcomers.photo', $person))->where('newcomer.location_accuracy', 12));

        // A location needs both coordinates; someone without a photo has no photo page.
        $this->actingAs($user)->post(route('newcomers.store'), $this->form(['first_name' => 'Kofi', 'latitude' => '5.5']))->assertSessionHasErrors('longitude');
        $bare = Newcomer::create(['first_visit_on' => today(), 'surname' => 'Asante', 'first_name' => 'Yaw']);
        $this->actingAs($user)->get(route('newcomers.photo', $bare))->assertNotFound();

        // An edit sent without a new photo keeps the one on file.
        $this->actingAs($user)->put(route('newcomers.update', $person), $this->form())->assertSessionHasNoErrors();
        $this->assertNotNull($person->fresh()->photo_path);
    }

    public function test_sample_people_are_created_and_purged_without_touching_real_ones(): void
    {
        foreach (range(1, 5) as $i) {
            $this->member("M{$i}", ['mobile' => '0244'.str_pad((string) $i, 6, '0'), 'date_of_birth' => today()->subYears(40)]);
        }
        $real = Newcomer::create(['first_visit_on' => today(), 'surname' => 'Boateng', 'first_name' => 'Ama']);

        $this->artisan('newcomers:sample', ['--count' => 30])->assertSuccessful();

        $samples = Newcomer::where('is_sample', true)->get();
        $this->assertCount(30, $samples);
        $this->assertGreaterThan(0, NewcomerLesson::where('is_sample', true)->count());
        $this->assertGreaterThan(0, NewcomerCounsellor::where('is_sample', true)->count());
        $this->assertTrue($samples->every(fn (Newcomer $n) => $n->visits()->exists() && $n->changes()->exists()));
        // Only people who got as far as a counsellor are past the visitor stage.
        $this->assertTrue($samples->every(fn (Newcomer $n) => $n->stage === 'visitor' || $n->counsellor_id !== null));
        $this->assertTrue($samples->every(fn (Newcomer $n) => ($n->latitude === null) === ($n->longitude === null)));

        $this->artisan('newcomers:sample', ['--purge' => true])->assertSuccessful();

        $this->assertSame(0, Newcomer::where('is_sample', true)->count());
        $this->assertSame(0, NewcomerCounsellor::where('is_sample', true)->count());
        $this->assertSame(0, NewcomerLesson::where('is_sample', true)->count());
        $this->assertNotNull($real->fresh());
    }

    private function lessons(int $n = 3): void
    {
        foreach (range(1, $n) as $i) {
            NewcomerLesson::create(['title' => "Lesson {$i}", 'sort_order' => $i]);
        }
    }

    private function newcomerWithCounsellor(array $attributes = []): Newcomer
    {
        return Newcomer::create(['first_visit_on' => today()->subMonths(2), 'surname' => 'Boateng', 'first_name' => 'Ama', 'stage' => 'newcomer',
            'counsellor_id' => $this->counsellor()->id, ...$attributes]);
    }

    public function test_enrolling_makes_a_catechumen_with_every_lesson_not_started(): void
    {
        $this->lessons();
        NewcomerLesson::create(['title' => 'Retired', 'sort_order' => 9, 'is_active' => false]);
        $user = $this->userWith(['newcomers.view', 'newcomers.manage']);
        $person = $this->newcomerWithCounsellor();

        $this->actingAs($user)->post(route('newcomers.enrol', $person))->assertSessionHasNoErrors();

        $this->assertSame('catechumen', $person->fresh()->stage);
        $this->assertSame(3, $person->progress()->where('status', 'not_started')->count());
        $this->assertSame(['newcomer', 'catechumen'], [$person->changes->first()->from_value, $person->changes->first()->to_value]);

        // Only a newcomer can be enrolled, and enrolling twice changes nothing.
        $this->actingAs($user)->post(route('newcomers.enrol', $person));
        $this->assertSame(3, $person->progress()->count());
        $visitor = Newcomer::create(['first_visit_on' => today(), 'surname' => 'Asante', 'first_name' => 'Yaw']);
        $this->actingAs($user)->post(route('newcomers.enrol', $visitor));
        $this->assertSame('visitor', $visitor->fresh()->stage);
        $this->actingAs($this->userWith(['newcomers.view']))->post(route('newcomers.enrol', $this->newcomerWithCounsellor()))->assertForbidden();
    }

    public function test_lesson_progress_is_set_shown_and_counted(): void
    {
        $this->lessons();
        $user = $this->userWith(['newcomers.view', 'newcomers.manage']);
        $person = $this->newcomerWithCounsellor();
        $this->actingAs($user)->post(route('newcomers.enrol', $person));
        [$one, $two, $three] = NewcomerLesson::orderBy('sort_order')->get();

        $this->actingAs($user)->put(route('newcomers.progress.update', [$person, $one]), ['status' => 'completed', 'completed_on' => today()->subDay()->toDateString()])->assertSessionHasNoErrors();
        $this->actingAs($user)->put(route('newcomers.progress.update', [$person, $two]), ['status' => 'in_progress'])->assertSessionHasNoErrors();
        $this->actingAs($user)->put(route('newcomers.progress.update', [$person, $three]), ['status' => 'skipped', 'note' => 'Done at former church'])->assertSessionHasNoErrors();
        $this->actingAs($user)->put(route('newcomers.progress.update', [$person, $one]), ['status' => 'completed', 'completed_on' => today()->addDay()->toDateString()])->assertSessionHasErrors('completed_on');
        $this->actingAs($user)->put(route('newcomers.progress.update', [$person, $one]), ['status' => 'bogus'])->assertSessionHasErrors('status');

        $this->actingAs($user)->get(route('newcomers.show', $person))->assertInertia(fn (Assert $page) => $page
            ->where('lessons.0.status', 'completed')->where('lessons.0.completed_on', today()->subDay()->toDateString())
            ->where('lessons.1.status', 'in_progress')->where('lessons.2.note', 'Done at former church'));

        // Skipped lessons are not counted against them.
        $this->actingAs($user)->get(route('newcomers.index'))->assertInertia(fn (Assert $page) => $page
            ->where('people.data.0.lessons_done', 1)->where('people.data.0.lessons_total', 2));

        // A completed lesson forgets its date once it is reopened; a lesson added later reaches them; a lesson on a record cannot be removed.
        $this->actingAs($user)->put(route('newcomers.progress.update', [$person, $one]), ['status' => 'in_progress']);
        $this->assertNull($person->progress()->where('lesson_id', $one->id)->value('completed_on'));
        $late = NewcomerLesson::create(['title' => 'Lesson 4', 'sort_order' => 4]);
        $this->actingAs($user)->get(route('newcomers.show', $person))->assertInertia(fn (Assert $page) => $page->has('lessons', 4));
        $this->actingAs($this->userWith(['settings.manage']))->delete(route('newcomers.lessons.destroy', $late));
        $this->assertNotNull($late->fresh());
    }

    public function test_an_adult_catechumen_is_made_a_member_of_the_main_register(): void
    {
        $folder = sys_get_temp_dir().'/ecm-newcomers-'.uniqid();
        $members = sys_get_temp_dir().'/ecm-members-'.uniqid();
        config(['church.newcomer_photos' => $folder, 'church.member_photos' => $members]);
        $user = $this->userWith(['newcomers.view', 'newcomers.manage', 'newcomers.promote']);

        $this->actingAs($user)->post(route('newcomers.store'), $this->form([
            'title' => 'Mrs.', 'middle_name' => 'Efua', 'marital_status' => 'married', 'marriage_type' => 'ordinance', 'former_church' => 'Methodist, Mamprobi',
            'is_baptized' => true, 'is_confirmed' => true, 'emergency_number' => '0244999888', 'email' => 'ama@example.com',
            'photo' => UploadedFile::fake()->image('camera.jpg', 600, 800), 'latitude' => '5.5359123', 'longitude' => '-0.2400456',
        ]))->assertSessionHasNoErrors();
        $person = Newcomer::firstOrFail();
        $person->update(['stage' => 'catechumen']);

        $this->actingAs($user)->post(route('newcomers.promote', $person), ['joined_on' => today()->toDateString()])->assertRedirect();

        $person->refresh();
        $member = Member::where('id', $person->member_id)->firstOrFail();
        $this->assertSame(['member', today()->toDateString()], [$person->stage, $person->made_member_on->toDateString()]);
        $this->assertSame(['Boateng Ama Efua', 'female', 'married', 'ordinance', 'Methodist, Mamprobi', 1, '0244000111'],
            [$member->full_name, $member->sex, $member->marital_status, $member->marriage_type, $member->previous_congregation, (int) $member->is_communicant, $member->mobile]);
        $this->assertSame('active', $member->status);
        $this->assertMatchesRegularExpression('#^PCG/ECM/\d{4}/\d{6}$#', $member->member_number);
        $this->assertSame(['baptism', 'confirmation'], $member->sacraments()->orderBy('kind')->pluck('kind')->all());
        $this->assertSame('0244999888', MemberNextOfKin::where('member_id', $member->id)->value('emergency_contact_phone'));
        $this->assertEqualsWithDelta(5.5359123, $member->latitude, 0.0000001);
        $this->assertNotNull($member->photo_id);
        $this->assertFileExists($members.'/'.$member->photo?->filename);
        $this->assertFileExists($folder.'/'.$person->photo_path, 'the newcomer keeps their own photo');
        $this->assertSame(['catechumen', 'member'], [$person->changes->first()->from_value, $person->changes->first()->to_value]);

        // Promoting again does nothing more, and the newcomer page links to the member.
        $this->actingAs($user)->post(route('newcomers.promote', $person), ['joined_on' => today()->toDateString()])->assertRedirect();
        $this->actingAs($user)->get(route('newcomers.show', $person))->assertInertia(fn (Assert $page) => $page->where('newcomer.member.id', $member->id));
        $this->assertSame(1, Member::count());
    }

    public function test_a_young_catechumen_joins_junior_youth_with_their_guardian(): void
    {
        $user = $this->userWith(['newcomers.view', 'newcomers.manage', 'newcomers.promote']);
        $this->actingAs($user)->post(route('newcomers.store'), $this->form([
            'date_of_birth' => today()->subYears(15)->toDateString(), 'guardian_name' => 'Grace Boateng', 'guardian_relationship' => 'Mother', 'guardian_phone' => '0244000222',
        ]))->assertSessionHasNoErrors();
        $person = Newcomer::firstOrFail();
        $person->update(['stage' => 'catechumen']);

        $this->actingAs($user)->post(route('newcomers.promote', $person), ['joined_on' => today()->toDateString()])
            ->assertRedirect(route('members.young.edit', YoungMember::firstOrFail()));

        $young = YoungMember::firstOrFail();
        $this->assertSame(['Ama', 'Boateng', 'JY'], [$young->first_name, $young->last_name, YoungMember::departmentFor($young->date_of_birth)]);
        $guardian = $young->guardians()->firstOrFail();
        $this->assertSame(['mother', 'Grace Boateng', '0244000222', true], [$guardian->relationship, $guardian->name, $guardian->phone, (bool) $guardian->is_primary]);
        $this->assertSame([$young->id, null], [$person->fresh()->young_member_id, $person->fresh()->member_id]);
        $this->assertSame(0, Member::count());
    }

    public function test_a_person_who_cannot_be_promoted_is_told_why(): void
    {
        $user = $this->userWith(['newcomers.view', 'newcomers.manage', 'newcomers.promote']);
        $noDob = Newcomer::create(['first_visit_on' => today(), 'surname' => 'Boateng', 'first_name' => 'Ama', 'stage' => 'catechumen']);
        $noSex = Newcomer::create(['first_visit_on' => today(), 'surname' => 'Asante', 'first_name' => 'Yaw', 'stage' => 'catechumen', 'date_of_birth' => today()->subYears(30)]);
        $newcomer = Newcomer::create(['first_visit_on' => today(), 'surname' => 'Osei', 'first_name' => 'Kojo', 'stage' => 'newcomer', 'date_of_birth' => today()->subYears(30), 'sex' => 'male']);

        foreach ([$noDob, $noSex, $newcomer] as $person) {
            $this->actingAs($user)->post(route('newcomers.promote', $person), ['joined_on' => today()->toDateString()])->assertRedirect();
            $this->assertNotSame('member', $person->fresh()->stage);
        }
        $this->assertSame(0, Member::count());

        $this->actingAs($user)->get(route('newcomers.show', $noSex))->assertInertia(fn (Assert $page) => $page
            ->where('promotion.target', 'the adult members list')->where('promotion.blockers.0', 'Add their gender first: the adult register needs it.'));

        // Making a member needs its own permission.
        $this->actingAs($this->userWith(['newcomers.view', 'newcomers.manage']))->post(route('newcomers.promote', $noSex), ['joined_on' => today()->toDateString()])->assertForbidden();
    }

    public function test_the_form_lists_are_seeded_and_editable(): void
    {
        $user = $this->userWith(['settings.manage', 'newcomers.manage']);

        $this->assertContains('Temporal Membership', NewcomerOption::lists()['purpose']);

        $this->actingAs($user)->post(route('newcomers.lists.store'), ['kind' => 'source', 'name' => ' Radio  ad '])->assertSessionHasNoErrors();
        $option = NewcomerOption::where('name', 'Radio ad')->firstOrFail();
        $this->actingAs($user)->post(route('newcomers.lists.store'), ['kind' => 'source', 'name' => 'radio ad'])->assertSessionHasErrors('name');
        $this->actingAs($user)->post(route('newcomers.lists.store'), ['kind' => 'nonsense', 'name' => 'X'])->assertSessionHasErrors('kind');

        $this->actingAs($user)->get(route('newcomers.create'))->assertInertia(fn (Assert $page) => $page
            ->where('lists.source', fn ($names) => collect($names)->contains('Radio ad')));

        $this->actingAs($user)->put(route('newcomers.lists.update', $option), ['name' => 'Radio advert'])->assertSessionHasNoErrors();
        $this->assertSame('Radio advert', $option->fresh()->name);
        $this->actingAs($user)->delete(route('newcomers.lists.destroy', $option));
        $this->assertNull($option->fresh());
    }
}
