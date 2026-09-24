<?php

namespace App\Http\Controllers;

use App\Models\ChurchSetting;
use App\Models\Presbytery;
use App\Models\PresbyteryDistrict;
use App\Support\Audit;
use App\Support\Presbyteries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The PCG presbyteries and their districts: a reference page for everyone signed in, editable by people who can manage
 * settings. Records elsewhere store these names as text, so edits here change suggestions, not existing records.
 */
class PresbyteryController extends Controller
{
    public function index(): Response
    {
        $church = ChurchSetting::values(['presbytery_name', 'district_name']);

        return Inertia::render('presbyteries/index', [
            'presbyteries' => Presbyteries::all(),
            'note' => Presbyteries::note(),
            // Highlights where this congregation sits.
            'church' => [
                'presbytery' => $church['presbytery_name'] ?? null,
                'district' => $church['district_name'] ?? null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedPresbytery($request);
        $presbytery = Presbytery::create([...$data, 'sort_order' => (int) Presbytery::max('sort_order') + 1]);

        Audit::record('presbytery.created', "Added {$presbytery->title}", $presbytery);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$presbytery->title} added."]);

        return back();
    }

    public function update(Request $request, Presbytery $presbytery): RedirectResponse
    {
        $before = $presbytery->only(['name', 'title', 'short_name', 'headquarters', 'coverage']);
        $presbytery->update($this->validatedPresbytery($request, $presbytery));
        $changed = array_keys(array_diff_assoc($presbytery->only(array_keys($before)), $before));

        if ($changed) {
            Audit::record('presbytery.updated', "Updated {$presbytery->title}: ".implode(', ', $changed), $presbytery, ['before' => $before]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$presbytery->title} saved."]);

        return back();
    }

    public function storeDistrict(Request $request, Presbytery $presbytery): RedirectResponse
    {
        $district = $presbytery->districts()->create($this->validatedDistrict($request, $presbytery));

        Audit::record('presbytery.district_added', "Added {$district->name} District to {$presbytery->title}", $presbytery);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$district->name} added to {$presbytery->title}."]);

        return back();
    }

    public function updateDistrict(Request $request, Presbytery $presbytery, PresbyteryDistrict $district): RedirectResponse
    {
        abort_unless($district->presbytery_id === $presbytery->id, 404);

        $before = $district->name;
        $district->update($this->validatedDistrict($request, $presbytery, $district));

        Audit::record('presbytery.district_updated', "Updated {$before} District in {$presbytery->title}", $presbytery, ['from' => $before, 'to' => $district->name, 'note' => $district->note]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$district->name} saved."]);

        return back();
    }

    public function destroyDistrict(Presbytery $presbytery, PresbyteryDistrict $district): RedirectResponse
    {
        abort_unless($district->presbytery_id === $presbytery->id, 404);

        $district->delete();

        Audit::record('presbytery.district_removed', "Removed {$district->name} District from {$presbytery->title}", $presbytery);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$district->name} removed from {$presbytery->title}."]);

        return back();
    }

    /** @return array<string, mixed> */
    private function validatedPresbytery(Request $request, ?Presbytery $presbytery = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('presbyteries', 'name')->ignore($presbytery)],
            'short_name' => ['nullable', 'string', 'max:30'],
            'headquarters' => ['nullable', 'string', 'max:150'],
            'coverage' => ['nullable', 'string', 'max:500'],
        ]);

        // "Ga" is stored as "Ga" and printed as "Ga Presbytery".
        $data['name'] = trim(preg_replace('/\s+Presbytery$/i', '', trim($data['name'])));

        return [...array_map(fn ($v) => is_string($v) && trim($v) === '' ? null : (is_string($v) ? trim($v) : $v), $data), 'title' => Presbyteries::presbyteryTitle($data['name'])];
    }

    /** @return array{name: string, note: ?string} */
    private function validatedDistrict(Request $request, Presbytery $presbytery, ?PresbyteryDistrict $district = null): array
    {
        $request->merge(['name' => trim(preg_replace('/\s+District$/i', '', trim((string) $request->input('name'))))]);

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('presbytery_districts', 'name')->where('presbytery_id', $presbytery->id)->ignore($district),
            ],
            'note' => ['nullable', 'string', 'max:150'],
        ], ['name.unique' => "{$presbytery->title} already has a district with that name."]);

        return ['name' => $data['name'], 'note' => filled($data['note'] ?? null) ? trim($data['note']) : null];
    }
}
