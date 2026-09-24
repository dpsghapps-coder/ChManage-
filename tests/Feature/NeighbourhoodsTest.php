<?php

namespace Tests\Feature;

use App\Models\ChurchSetting;
use App\Models\City;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NeighbourhoodsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_cities_are_seeded_and_the_page_is_for_people_who_manage_settings(): void
    {
        $accra = City::where('name', 'Accra')->firstOrFail();
        $this->assertSame('Greater Accra', $accra->region);
        $this->assertSame(128, $accra->neighbourhoods()->count());

        // Accra plus the 58 cities of ghana-cities-neighbourhoods.json, across all 16 regions.
        $this->assertSame(59, City::count());
        $this->assertSame(16, City::distinct()->count('region'));
        $this->assertSame(['Ashanti', 47], [City::where('name', 'Kumasi')->value('region'), City::where('name', 'Kumasi')->first()->neighbourhoods()->count()]);

        $viewer = $this->userWith(['members.view']);
        $this->actingAs($viewer)->get(route('admin.neighbourhoods.index'))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.cities.store'), ['name' => 'Dodowa'])->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.cities.neighbourhoods.store', $accra), ['names' => 'X'])->assertForbidden();

        ChurchSetting::put('city_name', 'accra');
        $this->actingAs($this->userWith(['settings.manage']))->get(route('admin.neighbourhoods.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/neighbourhoods/index')
                ->where('city.name', 'Accra')->has('city.neighbourhoods', 128)->where('churchCity', 'accra')->has('regions', 16));
    }

    public function test_a_city_is_added_with_its_region_and_a_pasted_list_of_neighbourhoods(): void
    {
        $user = $this->userWith(['settings.manage']);

        // Left blank, the region is looked up on the Ghana town list: Dodowa the district capital (Greater Accra), not
        // the village of the same name in Ahafo.
        $this->actingAs($user)->post(route('admin.cities.store'), ['name' => ' Dodowa ', 'region' => ''])->assertSessionHasNoErrors();
        $city = City::where('name', 'Dodowa')->firstOrFail();
        $this->assertSame('Greater Accra', $city->region);
        $this->actingAs($user)->post(route('admin.cities.store'), ['name' => 'dodowa'])->assertSessionHasErrors('name');
        $this->actingAs($user)->post(route('admin.cities.store'), ['name' => 'Wa', 'region' => 'Atlantis'])->assertSessionHasErrors('region');

        // One per line or separated by commas; blanks, repeats and names already listed are skipped.
        $this->actingAs($user)->post(route('admin.cities.neighbourhoods.store', $city), ['names' => "Adum\r\nAsafo, Bantama\n\n  adum \nSuame"])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Adum', 'Asafo', 'Bantama', 'Suame'], $city->neighbourhoods()->pluck('name')->all());

        $this->actingAs($user)->post(route('admin.cities.neighbourhoods.store', $city), ['names' => 'Asafo, Tafo']);
        $this->assertSame(5, $city->neighbourhoods()->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'settings.neighbourhoods_added']);
    }

    public function test_neighbourhoods_can_be_renamed_and_removed_and_a_city_removed(): void
    {
        $user = $this->userWith(['settings.manage']);
        $accra = City::where('name', 'Accra')->firstOrFail();
        $osu = $accra->neighbourhoods()->where('name', 'Osu')->firstOrFail();

        $this->actingAs($user)->put(route('admin.cities.neighbourhoods.update', [$accra, $osu]), ['name' => 'Adabraka'])
            ->assertSessionHasErrors(['name' => 'Accra already has that neighbourhood.']);
        $this->actingAs($user)->put(route('admin.cities.neighbourhoods.update', [$accra, $osu]), ['name' => 'Osu Christiansborg'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Osu Christiansborg', $osu->fresh()->name);

        // A neighbourhood is only reachable through its own city.
        $other = City::create(['name' => 'Somewhere Else', 'region' => 'Greater Accra']);
        $this->actingAs($user)->delete(route('admin.cities.neighbourhoods.destroy', [$other, $osu]))->assertNotFound();

        $this->actingAs($user)->delete(route('admin.cities.neighbourhoods.destroy', [$accra, $osu]))->assertRedirect();
        $this->assertNull($osu->fresh());

        $this->actingAs($user)->delete(route('admin.cities.destroy', $accra))->assertRedirect(route('admin.neighbourhoods.index'));
        $this->assertNull($accra->fresh());
        $this->assertSame(0, $accra->neighbourhoods()->count(), 'its neighbourhoods go with it');
    }

    public function test_residence_follows_the_church_city_and_its_edited_list(): void
    {
        $user = $this->userWith(['settings.manage', 'members.create']);
        $city = City::create(['name' => 'Dodowa', 'region' => 'Greater Accra']);
        $city->neighbourhoods()->create(['name' => 'Bantama']);

        ChurchSetting::put('city_name', 'Accra');
        $this->actingAs($user)->get(route('members.adult.create'))->assertInertia(fn (Assert $page) => $page
            ->where('residenceArea.region', 'Greater Accra')->where('residenceArea.neighbourhoods', fn ($n) => collect($n)->contains('Victoriaborg')));

        // Changing the church's city changes the list straight away, as does adding to it.
        ChurchSetting::put('city_name', 'Dodowa');
        $city->neighbourhoods()->create(['name' => 'Adum']);
        $this->actingAs($user)->get(route('members.adult.create'))->assertInertia(fn (Assert $page) => $page
            ->where('residenceArea', ['city' => 'Dodowa', 'region' => 'Greater Accra', 'neighbourhoods' => ['Adum', 'Bantama']]));

        $this->actingAs($user)->get(route('admin.church.edit'))->assertInertia(fn (Assert $page) => $page
            ->has('cities', 60)->where('cities', fn ($names) => collect($names)->contains('Dodowa') && collect($names)->contains('Tamale')));
    }

    public function test_a_json_list_is_merged_without_removing_anything(): void
    {
        $tamale = City::where('name', 'Tamale')->firstOrFail();
        $tamale->neighbourhoods()->create(['name' => 'Added In The App']);
        $before = $tamale->neighbourhoods()->count();
        $file = tempnam(sys_get_temp_dir(), 'nbh');
        file_put_contents($file, json_encode([
            ['region' => 'Northern Region', 'cities' => [
                ['city' => 'Tamale', 'neighbourhoods' => ['Lamashegu', 'Vittin Estates', 'Kalpohin']],
                ['city' => 'Tolon', 'neighbourhoods' => ['Tolon Central', ' tolon central ']],
            ]],
        ]));

        try {
            $this->artisan('neighbourhoods:import', ['file' => $file])->expectsOutputToContain('Added 1 cities')->assertSuccessful();
            $this->assertSame(['Northern', ['Tolon Central']], [City::where('name', 'Tolon')->value('region'), City::where('name', 'Tolon')->first()->neighbourhoods()->pluck('name')->all()]);
            $this->assertContains('Kalpohin', $tamale->neighbourhoods()->pluck('name')->all());
            $this->assertGreaterThanOrEqual($before, $tamale->neighbourhoods()->count());
            $this->assertTrue($tamale->neighbourhoods()->where('name', 'Added In The App')->exists(), 'edits made in the app are kept');

            // Running it again adds nothing.
            $this->artisan('neighbourhoods:import', ['file' => $file])->expectsOutput('Added 0 cities and 0 neighbourhoods.');
        } finally {
            unlink($file);
        }

        $this->artisan('neighbourhoods:import', ['file' => 'missing.json'])->assertFailed();
    }
}
