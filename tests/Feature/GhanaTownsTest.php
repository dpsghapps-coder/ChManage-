<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ChurchSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class GhanaTownsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_town_list_is_served_to_signed_in_users_and_cached(): void
    {
        $this->get(route('locations.towns'))->assertRedirect(route('login'));

        $response = $this->actingAs($this->userWith([]))->get(route('locations.towns'))->assertOk();
        $towns = json_decode($response->streamedContent() ?: file_get_contents(config('church.ghana_towns')), true);

        $this->assertGreaterThan(4000, count($towns));
        $this->assertContains(['name' => 'Accra', 'district' => 'Accra', 'region' => 'Greater Accra', 'capital' => true], $towns);
        $this->assertStringContainsString('max-age=86400', $response->headers->get('Cache-Control'));

        // A browser that already has this version gets "not modified".
        $this->actingAs($this->userWith([]))->get(route('locations.towns'), ['If-None-Match' => $response->headers->get('ETag')])
            ->assertStatus(304);
    }

    public function test_the_church_city_chooses_the_residence_neighbourhoods(): void
    {
        $admin = $this->userWith(['settings.manage', 'members.create']);
        $settings = ['church_name' => 'Presbyterian Church of Ghana', 'presbytery' => 'Ga West', 'district' => 'Kaneshie', 'congregation' => 'Ebenezer'];

        // With no city, Residence is not narrowed.
        $this->actingAs($admin)->get(route('members.adult.create'))->assertInertia(fn (Assert $page) => $page
            ->where('residenceArea', ['city' => null, 'region' => null, 'neighbourhoods' => []]));

        $this->actingAs($admin)->put(route('admin.church.update'), [...$settings, 'city' => ' Accra '])->assertSessionHasNoErrors();
        $this->assertSame('Accra', ChurchSetting::find('city_name')->setting_value);

        $this->actingAs($admin)->get(route('members.adult.create'))->assertInertia(fn (Assert $page) => $page
            ->where('residenceArea.city', 'Accra')->where('residenceArea.region', 'Greater Accra')
            ->where('residenceArea.neighbourhoods', fn ($names) => collect($names)->contains('Victoriaborg') && collect($names)->contains('Osu-Alata')));

        // A city without a neighbourhood list still narrows Residence to its region's towns.
        $this->actingAs($admin)->put(route('admin.church.update'), [...$settings, 'city' => 'Dodowa'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->get(route('members.adult.create'))->assertInertia(fn (Assert $page) => $page
            ->where('residenceArea', ['city' => 'Dodowa', 'region' => 'Greater Accra', 'neighbourhoods' => []]));

        // City is optional.
        $this->actingAs($admin)->put(route('admin.church.update'), [...$settings, 'city' => ''])->assertSessionHasNoErrors();
        $this->assertSame('', ChurchSetting::find('city_name')->setting_value);
    }

    public function test_the_towns_page_is_for_people_who_manage_settings(): void
    {
        $this->actingAs($this->userWith(['members.view']))->get(route('admin.towns.index'))->assertForbidden();
        $this->actingAs($this->userWith(['members.view']))->post(route('admin.towns.refresh'))->assertForbidden();

        $this->actingAs($this->userWith(['settings.manage']))->get(route('admin.towns.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/towns/index')->has('sources', 3)->whereType('updatedAt', 'string'));
    }

    public function test_refreshing_runs_the_import_and_reports_failure(): void
    {
        $user = $this->userWith(['settings.manage']);

        Artisan::shouldReceive('call')->once()->with('locations:import')->andReturn(0);
        Artisan::shouldReceive('output')->once()->andReturn('Wrote 4440 towns to /somewhere/ghana-towns.json.');
        $this->actingAs($user)->from(route('admin.towns.index'))->post(route('admin.towns.refresh'))->assertRedirect(route('admin.towns.index'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'settings.towns_refreshed', 'user_id' => $user->id]);

        Artisan::shouldReceive('call')->once()->with('locations:import')->andThrow(new RuntimeException('cURL error 28'));
        $this->actingAs($user)->from(route('admin.towns.index'))->post(route('admin.towns.refresh'))->assertRedirect(route('admin.towns.index'));
        $this->assertSame(1, AuditLog::where('event', 'settings.towns_refreshed')->count(), 'a failed refresh is not recorded as done');
    }

    public function test_the_import_merges_all_three_sources_by_town_and_region(): void
    {
        $from = sys_get_temp_dir().'/ghana-towns-'.uniqid();
        $out = resource_path('data/ghana-towns.json');
        $original = file_get_contents($out);
        File::ensureDirectoryExists($from);

        file_put_contents("{$from}/regions.json", json_encode([['name' => 'Greater Accra Region', 'slug' => 'greater-accra-region', 'capital' => 'Accra']]));
        file_put_contents("{$from}/districts.json", json_encode([['name' => 'La Nkwantanang Madina', 'slug' => 'la-nkwantanang', 'capital' => 'Madina', 'region_slug' => 'greater-accra-region']]));
        file_put_contents("{$from}/cities.json", json_encode([['name' => 'Madina', 'district_slug' => 'la-nkwantanang'], ['name' => 'Oyarifa', 'district_slug' => 'la-nkwantanang']]));
        file_put_contents("{$from}/ghana-cities.json", json_encode(['Greater Accra' => ['Madina', 'Kaneshie'], 'Nort East' => ['Nalerigu']]));
        file_put_contents("{$from}/maaddae-greater-accra.csv", "id,name,country,region\n1,Mamprobi,322,55\n2,Valco Estades,322,55\n3,Kaneshie,322,55\n");

        try {
            $this->artisan('locations:import', ['--from' => $from])->assertSuccessful();
            $towns = json_decode(file_get_contents($out), true);

            $this->assertSame([
                ['name' => 'Accra', 'district' => null, 'region' => 'Greater Accra', 'capital' => true],
                ['name' => 'Kaneshie', 'district' => null, 'region' => 'Greater Accra'],
                ['name' => 'Madina', 'district' => 'La Nkwantanang Madina', 'region' => 'Greater Accra', 'capital' => true],
                ['name' => 'Mamprobi', 'district' => null, 'region' => 'Greater Accra'],
                ['name' => 'Nalerigu', 'district' => null, 'region' => 'North East'],
                ['name' => 'Oyarifa', 'district' => 'La Nkwantanang Madina', 'region' => 'Greater Accra'],
                ['name' => 'Valco Estates', 'district' => null, 'region' => 'Greater Accra'],
            ], $towns, 'one Madina and one Kaneshie, capitals marked, "Nort East" and "Valco Estades" corrected');
        } finally {
            file_put_contents($out, $original);
            File::deleteDirectory($from);
        }
    }
}
