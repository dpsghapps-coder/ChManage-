<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChurchSetting;
use App\Models\City;
use App\Models\Neighbourhood;
use App\Support\Audit;
use App\Support\Neighbourhoods;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Cities and their neighbourhoods: what the member form suggests for Residence, for the city set in Church Settings. */
class NeighbourhoodController extends Controller
{
    public function index(Request $request): Response
    {
        $churchCity = (string) (ChurchSetting::values(['city_name'])['city_name'] ?? '');
        $cities = City::withCount('neighbourhoods')->orderBy('name')->get();

        // The city asked for, else the church's own city, else the first one.
        $selected = $cities->firstWhere('id', $request->integer('city'))
            ?? $cities->first(fn (City $c) => Str::lower($c->name) === Str::lower($churchCity))
            ?? $cities->first();

        return Inertia::render('admin/neighbourhoods/index', [
            'cities' => $cities->map(fn (City $c) => [
                'id' => $c->id, 'name' => $c->name, 'region' => $c->region, 'count' => $c->neighbourhoods_count,
            ])->values(),
            'city' => $selected ? [
                'id' => $selected->id,
                'name' => $selected->name,
                'region' => $selected->region,
                'neighbourhoods' => $selected->neighbourhoods()->get(['id', 'name']),
            ] : null,
            'churchCity' => $churchCity,
            'regions' => Neighbourhoods::regions(),
        ]);
    }

    public function storeCity(Request $request): RedirectResponse
    {
        $city = City::create($this->validatedCity($request));

        Audit::record('settings.city_added', "Added the city {$city->name}", $city);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$city->name} added. Now add its neighbourhoods."]);

        return to_route('admin.neighbourhoods.index', ['city' => $city->id]);
    }

    public function updateCity(Request $request, City $city): RedirectResponse
    {
        $before = $city->only(['name', 'region']);
        $city->update($this->validatedCity($request, $city));

        Audit::record('settings.city_updated', "Updated the city {$city->name}", $city, ['before' => $before]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$city->name} saved."]);

        return to_route('admin.neighbourhoods.index', ['city' => $city->id]);
    }

    public function destroyCity(City $city): RedirectResponse
    {
        $count = $city->neighbourhoods()->count();
        $city->delete();

        Audit::record('settings.city_removed', "Removed the city {$city->name} and its {$count} neighbourhoods", null, ['city' => $city->name]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$city->name} removed."]);

        return to_route('admin.neighbourhoods.index');
    }

    /** Adds one or many neighbourhoods: one per line, or separated by commas. Names already listed are skipped. */
    public function storeNeighbourhoods(Request $request, City $city): RedirectResponse
    {
        $request->validate(['names' => ['required', 'string', 'max:20000']]);

        $names = collect(preg_split('/[\r\n,]+/', $request->string('names')->value()))
            ->map(fn ($name) => trim(preg_replace('/\s+/', ' ', $name)))
            ->filter(fn ($name) => $name !== '' && mb_strlen($name) <= 150)
            ->unique(fn ($name) => Str::lower($name));
        $existing = $city->neighbourhoods()->pluck('name')->map(fn ($name) => Str::lower($name))->all();
        $new = $names->reject(fn ($name) => in_array(Str::lower($name), $existing, true))->values();

        foreach ($new as $name) {
            $city->neighbourhoods()->create(['name' => $name]);
        }

        if ($new->isNotEmpty()) {
            Audit::record('settings.neighbourhoods_added', "Added {$new->count()} neighbourhood(s) to {$city->name}", $city, ['names' => $new->all()]);
        }

        $skipped = $names->count() - $new->count();
        Inertia::flash('toast', [
            'type' => $new->isEmpty() ? 'error' : 'success',
            'message' => ($new->isEmpty() ? 'Nothing added' : "Added {$new->count()} to {$city->name}").($skipped ? " ({$skipped} already listed)." : '.'),
        ]);

        return back();
    }

    public function updateNeighbourhood(Request $request, City $city, Neighbourhood $neighbourhood): RedirectResponse
    {
        abort_unless($neighbourhood->city_id === $city->id, 404);

        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('neighbourhoods')->where('city_id', $city->id)->ignore($neighbourhood)],
        ], ['name.unique' => "{$city->name} already has that neighbourhood."]);

        $before = $neighbourhood->name;
        $neighbourhood->update($data);

        Audit::record('settings.neighbourhood_renamed', "Renamed {$before} to {$neighbourhood->name} in {$city->name}", $city);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$neighbourhood->name} saved."]);

        return back();
    }

    public function destroyNeighbourhood(City $city, Neighbourhood $neighbourhood): RedirectResponse
    {
        abort_unless($neighbourhood->city_id === $city->id, 404);

        $neighbourhood->delete();

        Audit::record('settings.neighbourhood_removed', "Removed {$neighbourhood->name} from {$city->name}", $city);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$neighbourhood->name} removed."]);

        return back();
    }

    /** @return array{name: string, region: ?string} */
    private function validatedCity(Request $request, ?City $city = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('cities', 'name')->ignore($city)],
            'region' => ['nullable', 'string', Rule::in(Neighbourhoods::regions())],
        ]);

        return ['name' => $data['name'], 'region' => $data['region'] ?? Neighbourhoods::regionOf($data['name'])];
    }
}
