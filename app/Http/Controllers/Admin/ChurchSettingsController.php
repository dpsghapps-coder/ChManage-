<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChurchSetting;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Who this church is: name, presbytery, district and congregation. */
class ChurchSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/church/edit', ['settings' => $this->current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'church_name' => ['required', 'string', 'max:150'],
            'presbytery' => ['required', 'string', 'max:150'],
            'district' => ['required', 'string', 'max:150'],
            'congregation' => ['required', 'string', 'max:150'],
        ]);

        $before = $this->current();
        $changed = [];

        foreach (ChurchSetting::IDENTITY as $field => $key) {
            $value = trim($data[$field]);

            if ($value !== ($before[$field] ?? '')) {
                $changed[$field] = ['from' => $before[$field] ?? '', 'to' => $value];
            }

            ChurchSetting::put($key, $value);
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
