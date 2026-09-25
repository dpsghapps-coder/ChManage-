<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChurchSetting;
use App\Models\City;
use App\Support\Audit;
use App\Support\Presbyteries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Who this church is: name, presbytery, district and congregation. */
class ChurchSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/church/edit', [
            'settings' => [...$this->current(), 'followup_days' => ChurchSetting::followUpDays()],
            // Suggestions only: a presbytery or district missing from the list can still be typed in.
            'presbyteries' => Presbyteries::options(),
            // Cities with a neighbourhood list come first among the City suggestions.
            'cities' => City::orderBy('name')->pluck('name'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'church_name' => ['required', 'string', 'max:150'],
            'presbytery' => ['required', 'string', 'max:150'],
            'district' => ['required', 'string', 'max:150'],
            'congregation' => ['required', 'string', 'max:150'],
            // Optional: without it, Residence suggests towns from anywhere in Ghana.
            'city' => ['nullable', 'string', 'max:150'],
            'followup_days' => ['sometimes', 'integer', 'between:7,365'],
        ], ['followup_days.between' => 'Choose between 7 and 365 days.']);

        $before = $this->current();
        $changed = [];

        foreach (ChurchSetting::IDENTITY as $field => $key) {
            $value = trim((string) ($data[$field] ?? ''));

            if ($value !== ($before[$field] ?? '')) {
                $changed[$field] = ['from' => $before[$field] ?? '', 'to' => $value];
            }

            ChurchSetting::put($key, $value);
        }

        if (isset($data['followup_days']) && (int) $data['followup_days'] !== ChurchSetting::followUpDays()) {
            $changed['followup_days'] = ['from' => ChurchSetting::followUpDays(), 'to' => (int) $data['followup_days']];
            ChurchSetting::put(ChurchSetting::FOLLOWUP_DAYS, (string) (int) $data['followup_days']);
        }

        if ($changed) {
            Audit::record('settings.updated', 'Updated church settings: '.implode(', ', array_keys($changed)), null, $changed);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Church settings saved.']);

        return to_route('admin.church.edit');
    }

    /** @return array<string, string> */
    private function current(): array
    {
        $stored = ChurchSetting::values(array_values(ChurchSetting::IDENTITY));

        return collect(ChurchSetting::IDENTITY)->map(fn ($key) => (string) ($stored[$key] ?? ''))->all();
    }
}
