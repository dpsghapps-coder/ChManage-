<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChurchSetting;
use App\Models\City;
use App\Models\Role;
use App\Support\Audit;
use App\Support\Presbyteries;
use App\Support\RequestAccess;
use App\Support\RequestTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Who this church is: name, presbytery, district and congregation. */
class ChurchSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/church/edit', [
            'settings' => [...$this->current(), 'followup_days' => ChurchSetting::followUpDays(), 'term_warning_days' => ChurchSetting::termWarningDays(), 'portal_phone_field' => ChurchSetting::portalPhoneField()],
            'portalPhoneFields' => ChurchSetting::PORTAL_PHONE_FIELDS,
            // Which role receives each kind of member request; empty means administrators only.
            'requestTypes' => collect(RequestTypes::all())->map(fn ($t, $key) => ['key' => $key, 'label' => $t['label']])->values(),
            'requestHandlers' => collect(RequestAccess::handlers())->map(fn ($id) => $id ? (string) $id : '')->all(),
            'roles' => Role::where('slug', '!=', Role::ADMIN)->orderBy('name')->get(['id', 'name']),
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
            'term_warning_days' => ['sometimes', 'integer', 'between:7,365'],
            'portal_phone_field' => ['sometimes', Rule::in(array_keys(ChurchSetting::PORTAL_PHONE_FIELDS))],
            'request_handlers' => ['sometimes', 'array'],
            'request_handlers.*' => ['nullable', 'integer', Rule::exists('roles', 'id')],
        ], ['followup_days.between' => 'Choose between 7 and 365 days.', 'term_warning_days.between' => 'Choose between 7 and 365 days.']);

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

        if (isset($data['term_warning_days']) && (int) $data['term_warning_days'] !== ChurchSetting::termWarningDays()) {
            $changed['term_warning_days'] = ['from' => ChurchSetting::termWarningDays(), 'to' => (int) $data['term_warning_days']];
            ChurchSetting::put(ChurchSetting::TERM_WARNING_DAYS, (string) (int) $data['term_warning_days']);
        }

        if (isset($data['portal_phone_field']) && $data['portal_phone_field'] !== ChurchSetting::portalPhoneField()) {
            $changed['portal_phone_field'] = ['from' => ChurchSetting::portalPhoneField(), 'to' => $data['portal_phone_field']];
            ChurchSetting::put(ChurchSetting::PORTAL_PHONE, $data['portal_phone_field']);
        }

        if (isset($data['request_handlers'])) {
            $before = RequestAccess::handlers();
            $after = [...$before, ...collect($data['request_handlers'])->only(RequestTypes::keys())->map(fn ($v) => filled($v) ? (int) $v : null)->all()];

            if ($after !== $before) {
                $changed['request_handlers'] = ['from' => $before, 'to' => $after];
                RequestAccess::save($after);
            }
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
