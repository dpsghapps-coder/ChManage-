<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ChurchSetting;
use App\Models\Presbytery;
use App\Support\Presbyteries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PresbyteriesTest extends TestCase
{
    use RefreshDatabase;

    private const SAMPLE = <<<'MD'
        # Heading that is not a presbytery

        ## 1. Ga Presbytery
        * **Headquarters:** Osu Kuku Hill, Accra
        * **Geographical Coverage:** Eastern Greater Accra
        * **Number of Districts:** 2 Pastoral Districts

        ### Districts:
        * Osu
        * La (Labadi)

        ---

        ## 2. Presbytery of North America and Australia (PNAA)
        * **Headquarters:** New Jersey / New York, USA

        ### Districts & Sub-Districts Include:
        * New York District

        > **Administrative Note:**
        > New districts are created by the *General Assembly*.
        MD;

    protected function tearDown(): void
    {
        Presbyteries::flush();

        parent::tearDown();
    }

    public function test_the_markdown_is_read_into_presbyteries_and_districts(): void
    {
        $parsed = Presbyteries::parse(self::SAMPLE);
        [$ga, $pnaa] = $parsed['presbyteries'];

        $this->assertCount(2, $parsed['presbyteries']);
        $this->assertSame(['Ga', 'Ga Presbytery', null, 'Osu Kuku Hill, Accra', 'Eastern Greater Accra'], [$ga['name'], $ga['title'], $ga['short_name'], $ga['headquarters'], $ga['coverage']]);
        $this->assertSame([['label' => 'Number of Districts', 'value' => '2 Pastoral Districts']], $ga['facts']);
        $this->assertSame([['name' => 'Osu', 'note' => null], ['name' => 'La', 'note' => 'Labadi']], $ga['districts']);

        $this->assertSame(['Presbytery of North America and Australia', 'PNAA'], [$pnaa['name'], $pnaa['short_name']]);
        $this->assertSame([['name' => 'New York District', 'note' => null]], $pnaa['districts']);
        $this->assertSame('New districts are created by the General Assembly.', $parsed['note']);
    }

    public function test_the_shipped_file_lists_all_21_presbyteries(): void
    {
        $all = Presbyteries::all();

        $this->assertCount(21, $all);
        $this->assertSame('Ga', $all[0]['name']);
        $this->assertCount(28, $all[0]['districts']);

        foreach ($all as $presbytery) {
            $this->assertNotEmpty($presbytery['districts'], "{$presbytery['title']} has no districts");
        }
    }

    public function test_titles_are_not_doubled(): void
    {
        $this->assertSame('Ga Presbytery', Presbyteries::presbyteryTitle('Ga'));
        $this->assertSame('Ga Presbytery', Presbyteries::presbyteryTitle('Ga Presbytery'));
        $this->assertSame('Presbytery of North America and Australia', Presbyteries::presbyteryTitle('Presbytery of North America and Australia'));
        $this->assertSame('Osu District', Presbyteries::districtTitle('Osu'));
        $this->assertSame('New York District', Presbyteries::districtTitle('New York District'));
    }

    public function test_place_suggestions_start_with_this_congregation_then_recorded_places(): void
    {
        ChurchSetting::put('congregation_name', 'Ebenezer Congregation, Kaneshie');

        $places = Presbyteries::placeSuggestions(['Osu Ebenezer', null, '', 'ebenezer congregation, kaneshie', 'Adabraka']);

        $this->assertSame(['Ebenezer Congregation, Kaneshie', 'Adabraka', 'Osu Ebenezer'], array_slice($places, 0, 3));
        $this->assertContains('Osu District, Ga Presbytery', $places);
        $this->assertSame(count($places), count(array_unique(array_map('strtolower', $places))));
    }

    public function test_the_list_comes_from_the_database_seeded_from_the_file(): void
    {
        $this->assertSame(21, Presbytery::count(), 'the migration seeds every presbytery in the file');

        Presbytery::where('name', 'Ga')->firstOrFail()->districts()->create(['name' => 'Kotobabi']);
        $this->assertContains('Kotobabi', Presbyteries::options()[0]['districts']);

        // Without the file only the closing note is lost; the list itself lives in the database.
        config(['church.presbyteries' => base_path('does-not-exist.md')]);
        $this->assertNull(Presbyteries::note());
        $this->assertCount(21, Presbyteries::all());
    }

    public function test_only_people_who_manage_settings_can_edit_the_list(): void
    {
        $ga = Presbytery::where('name', 'Ga')->firstOrFail();
        $district = $ga->districts()->firstOrFail();
        $viewer = $this->userWith([]);

        $this->actingAs($viewer)->post(route('presbyteries.store'), ['name' => 'Bono East'])->assertForbidden();
        $this->actingAs($viewer)->put(route('presbyteries.update', $ga), ['name' => 'Ga'])->assertForbidden();
        $this->actingAs($viewer)->post(route('presbyteries.districts.store', $ga), ['name' => 'Kotobabi'])->assertForbidden();
        $this->actingAs($viewer)->put(route('presbyteries.districts.update', [$ga, $district]), ['name' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->delete(route('presbyteries.districts.destroy', [$ga, $district]))->assertForbidden();
    }

    public function test_districts_can_be_added_renamed_and_removed(): void
    {
        $user = $this->userWith(['settings.manage']);
        $ga = Presbytery::where('name', 'Ga')->firstOrFail();

        // "District" at the end is dropped: the name is stored as it is on the list.
        $this->actingAs($user)->post(route('presbyteries.districts.store', $ga), ['name' => '  Kotobabi District ', 'note' => ''])
            ->assertSessionHasNoErrors();
        $district = $ga->districts()->where('name', 'Kotobabi')->firstOrFail();
        $this->assertNull($district->note);

        $this->actingAs($user)->post(route('presbyteries.districts.store', $ga), ['name' => 'kotobabi'])
            ->assertSessionHasErrors(['name' => 'Ga Presbytery already has a district with that name.']);

        // The same name is fine under another presbytery.
        $gaWest = Presbytery::where('name', 'Ga West')->firstOrFail();
        $this->actingAs($user)->post(route('presbyteries.districts.store', $gaWest), ['name' => 'Kotobabi'])->assertSessionHasNoErrors();

        $this->actingAs($user)->put(route('presbyteries.districts.update', [$ga, $district]), ['name' => 'Kotobabi North', 'note' => 'Mission field'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Kotobabi North', 'Mission field'], [$district->fresh()->name, $district->fresh()->note]);

        // A district is only reachable through its own presbytery.
        $this->actingAs($user)->delete(route('presbyteries.districts.destroy', [$gaWest, $district]))->assertNotFound();

        $this->actingAs($user)->delete(route('presbyteries.districts.destroy', [$ga, $district]))->assertRedirect();
        $this->assertNull($district->fresh());

        $this->assertSame(
            ['presbytery.district_added', 'presbytery.district_added', 'presbytery.district_updated', 'presbytery.district_removed'],
            AuditLog::where('event', 'like', 'presbytery.%')->orderBy('id')->pluck('event')->all(),
        );
    }

    public function test_a_presbytery_can_be_added_and_edited(): void
    {
        $user = $this->userWith(['settings.manage']);

        $this->actingAs($user)->post(route('presbyteries.store'), ['name' => 'Bono East Presbytery', 'headquarters' => 'Techiman', 'short_name' => ''])
            ->assertSessionHasNoErrors();
        $new = Presbytery::where('name', 'Bono East')->firstOrFail();
        $this->assertSame(['Bono East Presbytery', 'Techiman', null, 22], [$new->title, $new->headquarters, $new->short_name, $new->sort_order]);

        $this->actingAs($user)->post(route('presbyteries.store'), ['name' => 'Ga'])->assertSessionHasErrors('name');

        $this->actingAs($user)->put(route('presbyteries.update', $new), ['name' => 'Bono East', 'headquarters' => 'Kintampo', 'coverage' => 'Bono East Region'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Kintampo', 'Bono East Region'], [$new->fresh()->headquarters, $new->fresh()->coverage]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'presbytery.updated']);
    }

    public function test_anyone_signed_in_can_open_the_reference_page(): void
    {
        ChurchSetting::put('presbytery_name', 'Ga');

        $this->get(route('presbyteries.index'))->assertRedirect(route('login'));

        $this->actingAs($this->userWith([]))->get(route('presbyteries.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('presbyteries/index')->has('presbyteries', 21)->where('church.presbytery', 'Ga')->whereType('note', 'string'));
    }

    public function test_church_settings_suggests_presbyteries_and_their_districts(): void
    {
        $this->actingAs($this->userWith(['settings.manage']))->get(route('admin.church.edit'))->assertInertia(fn (Assert $page) => $page
            ->has('presbyteries', 21)->where('presbyteries.0.name', 'Ga')->where('presbyteries.0.headquarters', 'Osu Kuku Hill, Accra')
            ->where('presbyteries.0.districts', fn ($districts) => collect($districts)->contains('Osu')));
    }

    public function test_the_staff_form_suggests_stations(): void
    {
        $this->actingAs($this->userWith(['staff.create']))->get(route('staff.create'))->assertInertia(fn (Assert $page) => $page
            ->where('stations', fn ($places) => collect($places)->contains('Kaneshie District, Ga West Presbytery')));
    }
}
