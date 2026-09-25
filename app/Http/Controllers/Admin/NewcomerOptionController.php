<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewcomerOption;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The editable lists on the newcomer form: title, current status, purpose, service, how they heard of us and
 * former church. The form stores the wording as text, so removing or renaming a choice never changes a record.
 */
class NewcomerOptionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('newcomers/lists', [
            'kinds' => NewcomerOption::KINDS,
            'options' => NewcomerOption::orderBy('sort_order')->orderBy('name')->get(['id', 'kind', 'name'])->groupBy('kind')->map->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $option = NewcomerOption::create([...$data, 'sort_order' => (int) NewcomerOption::where('kind', $data['kind'])->max('sort_order') + 1]);

        Audit::record('settings.newcomer_option_added', "Added {$option->name} to the newcomer list ".NewcomerOption::KINDS[$option->kind], $option);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$option->name} added."]);

        return back();
    }

    public function update(Request $request, NewcomerOption $option): RedirectResponse
    {
        $option->update(['name' => $this->validated($request, $option)['name']]);

        Audit::record('settings.newcomer_option_updated', 'Renamed a choice on the newcomer list '.NewcomerOption::KINDS[$option->kind], $option);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$option->name} saved."]);

        return back();
    }

    public function destroy(NewcomerOption $option): RedirectResponse
    {
        $option->delete();

        Audit::record('settings.newcomer_option_removed', "Removed {$option->name} from the newcomer list ".NewcomerOption::KINDS[$option->kind], null, ['name' => $option->name]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$option->name} removed."]);

        return back();
    }

    /** @return array{kind: string, name: string} */
    private function validated(Request $request, ?NewcomerOption $option = null): array
    {
        $kind = $option?->kind ?? (string) $request->input('kind');
        $request->merge(['name' => trim(preg_replace('/\s+/', ' ', (string) $request->input('name')))]);

        $data = $request->validate([
            'kind' => [$option ? 'nullable' : 'required', Rule::in(array_keys(NewcomerOption::KINDS))],
            'name' => ['required', 'string', 'max:150', Rule::unique('newcomer_options', 'name')->where('kind', $kind)->ignore($option)],
        ], ['name.unique' => 'That is already on the list.']);

        return ['kind' => $kind, 'name' => $data['name']];
    }
}
