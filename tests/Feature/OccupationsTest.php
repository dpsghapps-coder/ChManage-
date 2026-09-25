<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Profession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OccupationsTest extends TestCase
{
    use RefreshDatabase;

    private function member(array $attributes = []): Member
    {
        return Member::create(['member_number' => 'M1', 'full_name' => 'Mensah Efua', 'status' => 'active', 'sex' => 'female', ...$attributes]);
    }

    public function test_the_occupations_file_is_loaded_by_category(): void
    {
        $this->assertSame('Education & Academia', Profession::where('name', 'Senior High School (SHS) Teacher')->value('category'));
        $this->assertCount(14, Profession::grouped());

        // The older occupations (only in the real database) are filed into those categories, so none is left
        // uncategorised; the member keeps theirs.
        $student = Profession::create(['name' => 'Student']);
        $member = $this->member(['profession_id' => $student->id]);
        (require database_path('migrations/2026_09_25_160000_file_older_occupations_into_categories.php'))->up();
        $this->assertSame('Demographics & Non-Working Status', $student->fresh()->category);
        $this->assertSame(0, Profession::whereNull('category')->count());
        $this->assertSame($student->id, $member->fresh()->profession_id);

        $this->actingAs($this->userWith(['members.create']))->get(route('members.adult.create'))->assertInertia(fn (Assert $page) => $page
            ->where('occupations', fn ($groups) => collect($groups)->pluck('category')->contains('Healthcare & Medical Services')));
    }

    public function test_occupation_and_previous_congregation_are_saved_and_shown(): void
    {
        $user = $this->userWith(['members.create', 'members.view']);
        $nurse = Profession::where('name', 'like', '%Nurse%')->firstOrFail();

        $this->actingAs($user)->post(route('members.adult.store'), [
            'first_name' => 'Efua', 'last_name' => 'Mensah', 'sex' => 'female', 'date_of_birth' => today()->subYears(30)->toDateString(),
            'has_related' => true, 'profession_id' => $nurse->id, 'previous_congregation' => ' Ebenezer, Osu ',
        ])->assertSessionHasNoErrors();

        $member = Member::where('first_name', 'Efua')->firstOrFail();
        $this->assertSame([$nurse->id, 'Ebenezer, Osu'], [$member->profession_id, $member->previous_congregation]);

        $this->actingAs($user)->get(route('members.show', $member))->assertInertia(fn (Assert $page) => $page
            ->where('member.occupation', $nurse->name)->where('member.previous_congregation', 'Ebenezer, Osu'));

        $this->actingAs($user)->get(route('members.adult.create'))->assertInertia(fn (Assert $page) => $page
            ->where('previousCongregations', ['Ebenezer, Osu']));
    }

    public function test_occupations_are_managed_by_settings_managers(): void
    {
        $viewer = $this->userWith(['members.view']);
        $this->actingAs($viewer)->get(route('admin.occupations.index'))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.occupations.store'), ['name' => 'X'])->assertForbidden();

        $user = $this->userWith(['settings.manage']);
        $this->actingAs($user)->get(route('admin.occupations.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/occupations/index')->where('uncategorised', 'Uncategorised'));

        $this->actingAs($user)->post(route('admin.occupations.store'), ['name' => ' Drone  Pilot ', 'category' => 'Information Technology & Engineering'])
            ->assertSessionHasNoErrors();
        $drone = Profession::where('name', 'Drone Pilot')->firstOrFail();
        $this->assertSame('Information Technology & Engineering', $drone->category);
        $this->actingAs($user)->post(route('admin.occupations.store'), ['name' => 'drone pilot'])->assertSessionHasErrors('name');

        // Every occupation needs a real category.
        $this->actingAs($user)->put(route('admin.occupations.update', $drone), ['name' => 'Drone Operator', 'category' => ''])->assertSessionHasErrors('category');
        $this->actingAs($user)->put(route('admin.occupations.update', $drone), ['name' => 'Drone Operator', 'category' => 'Uncategorised'])->assertSessionHasErrors('category');
        $this->actingAs($user)->put(route('admin.occupations.update', $drone), ['name' => 'Drone Operator', 'category' => 'Aviation'])->assertSessionHasNoErrors();
        $this->assertSame(['Drone Operator', 'Aviation'], [$drone->fresh()->name, $drone->fresh()->category]);

        // An occupation members have cannot be removed; an unused one can.
        $this->member(['profession_id' => $drone->id]);
        $this->actingAs($user)->delete(route('admin.occupations.destroy', $drone))->assertRedirect();
        $this->assertNotNull($drone->fresh());
        Member::where('profession_id', $drone->id)->update(['profession_id' => null]);
        $this->actingAs($user)->delete(route('admin.occupations.destroy', $drone))->assertRedirect();
        $this->assertNull($drone->fresh());

        // Renaming a category moves every occupation in it.
        $count = Profession::where('category', 'Media, Arts & Entertainment')->count();
        $this->actingAs($user)->put(route('admin.occupations.categories.update'), ['from' => 'Media, Arts & Entertainment', 'to' => 'Media & Arts'])
            ->assertSessionHasNoErrors();
        $this->assertSame($count, Profession::where('category', 'Media & Arts')->count());
        $this->assertSame(0, Profession::where('category', 'Media, Arts & Entertainment')->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'settings.occupation_category_renamed']);
    }
}
