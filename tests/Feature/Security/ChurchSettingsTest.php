<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\ChurchSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChurchSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function details(array $overrides = []): array
    {
        return [
            'church_name' => 'Presbyterian Church of Ghana', 'presbytery' => 'Accra West',
            'district' => 'Mamprobi', 'congregation' => 'Ebenezer Congregation, Mamprobi', ...$overrides,
        ];
    }

    public function test_only_people_who_can_manage_settings_reach_the_page(): void
    {
        $this->actingAs($this->userWith(['members.view']))->get(route('admin.church.edit'))->assertForbidden();
        $this->actingAs($this->userWith(['members.view']))->put(route('admin.church.update'), $this->details())->assertForbidden();
        $this->actingAs($this->userWith(['settings.manage']))->get(route('admin.church.edit'))->assertOk();
    }

    public function test_it_shows_what_is_stored_and_blanks_for_what_is_not(): void
    {
        ChurchSetting::put('church_name', 'Presbyterian Church of Ghana');
        ChurchSetting::put('congregation_name', 'Ebenezer Congregation, Mamprobi');

        $this->actingAs($this->userWith(['settings.manage']))->get(route('admin.church.edit'))->assertInertia(fn (Assert $page) => $page
            ->component('admin/church/edit')->where('settings.church_name', 'Presbyterian Church of Ghana')
            ->where('settings.congregation', 'Ebenezer Congregation, Mamprobi')->where('settings.presbytery', '')->where('settings.district', ''));
    }

    public function test_presbytery_district_and_congregation_are_saved_and_audited(): void
    {
        $user = $this->userWith(['settings.manage']);

        $this->actingAs($user)->put(route('admin.church.update'), $this->details())->assertRedirect(route('admin.church.edit'));

        $this->assertSame('Accra West', ChurchSetting::find('presbytery_name')->setting_value);
        $this->assertSame('Mamprobi', ChurchSetting::find('district_name')->setting_value);
        $this->assertSame('Ebenezer Congregation, Mamprobi', ChurchSetting::find('congregation_name')->setting_value);
        $this->assertDatabaseHas('audit_logs', ['event' => 'settings.updated', 'user_id' => $user->id]);

        // Saving the same values again changes nothing and is not audited a second time.
        $this->actingAs($user)->put(route('admin.church.update'), $this->details());
        $this->assertSame(1, AuditLog::where('event', 'settings.updated')->count());

        $this->actingAs($user)->put(route('admin.church.update'), $this->details(['district' => 'Korle Klottey']));
        $this->assertSame('Korle Klottey', ChurchSetting::find('district_name')->setting_value);
        $this->assertSame(2, AuditLog::where('event', 'settings.updated')->count());
    }

    public function test_every_field_is_required(): void
    {
        $this->actingAs($this->userWith(['settings.manage']))->put(route('admin.church.update'), $this->details(['presbytery' => '', 'district' => '', 'congregation' => '']))
            ->assertSessionHasErrors(['presbytery', 'district', 'congregation']);

        $this->assertNull(ChurchSetting::find('presbytery_name'));
    }
}
