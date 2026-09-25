<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Profession;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The occupations offered on the member form, grouped by category. Members link to an occupation, so a rename shows on
 * every member who has it, and an occupation that members have cannot be removed.
 */
class OccupationController extends Controller
{
    public function index(): Response
    {
        $counts = Member::whereNotNull('profession_id')->selectRaw('profession_id, count(*) as n')->groupBy('profession_id')->pluck('n', 'profession_id');

        return Inertia::render('admin/occupations/index', [
            'groups' => collect(Profession::grouped())->map(fn ($group) => [
                ...$group,
                'options' => array_map(fn ($o) => [...$o, 'members' => (int) ($counts[$o['id']] ?? 0)], $group['options']),
            ])->values(),
            'uncategorised' => Profession::UNCATEGORISED,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $occupation = Profession::create([...$data, 'sort_order' => (int) Profession::where('category', $data['category'])->max('sort_order') + 1]);

        Audit::record('settings.occupation_added', "Added the occupation {$occupation->name}", null, $data);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$occupation->name} added."]);

        return back();
    }

    public function update(Request $request, Profession $occupation): RedirectResponse
    {
        $before = $occupation->only(['name', 'category']);
        $occupation->update($this->validated($request, $occupation));

        Audit::record('settings.occupation_updated', "Updated the occupation {$occupation->name}", null, ['before' => $before, 'after' => $occupation->only(['name', 'category'])]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$occupation->name} saved."]);

        return back();
    }

    public function destroy(Profession $occupation): RedirectResponse
    {
        $members = Member::where('profession_id', $occupation->id)->count();

        if ($members > 0) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "{$members} member(s) have {$occupation->name}. Give them another occupation first, or rename it instead."]);

            return back();
        }

        $occupation->delete();

        Audit::record('settings.occupation_removed', "Removed the occupation {$occupation->name}", null, $occupation->only(['name', 'category']));
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$occupation->name} removed."]);

        return back();
    }

    /** Renames a category: every occupation in it moves to the new name. */
    public function renameCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'string', 'max:100', Rule::exists('professions', 'category')],
            'to' => ['required', 'string', 'max:100'],
        ]);
        $to = trim(preg_replace('/\s+/', ' ', $data['to']));

        $moved = Profession::where('category', $data['from'])->update(['category' => $to]);

        Audit::record('settings.occupation_category_renamed', "Renamed the occupation category {$data['from']} to {$to}", null, ['occupations' => $moved]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$data['from']} renamed to {$to}."]);

        return back();
    }

    /** Every occupation belongs to a category. @return array{name: string, category: string} */
    private function validated(Request $request, ?Profession $occupation = null): array
    {
        $clean = fn ($value) => trim(preg_replace('/\s+/', ' ', (string) $value));
        $request->merge(['name' => $clean($request->input('name')), 'category' => $clean($request->input('category'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('professions', 'name')->ignore($occupation)],
            // "Uncategorised" is how an occupation without a category is shown, not a category to file one under.
            'category' => ['required', 'string', 'max:100', Rule::notIn([Profession::UNCATEGORISED])],
        ], [
            'name.unique' => 'That occupation is already on the list.',
            'category.required' => 'Choose a category, or type a new one.',
            'category.not_in' => 'Choose a category, or type a new one.',
        ]);

        return ['name' => $data['name'], 'category' => $data['category']];
    }
}
