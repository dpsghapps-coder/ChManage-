<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Committee;
use App\Models\MemberServiceRecord;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The church's committees, offered as the name of a committee service record on the member form. Service records
 * store the name as text: a rename carries over to them, and removing a committee leaves them as they are.
 */
class CommitteeController extends Controller
{
    public function index(): Response
    {
        $records = MemberServiceRecord::where('type', 'committee')->selectRaw('name, count(*) as n')->groupBy('name')->pluck('n', 'name')
            ->mapWithKeys(fn ($n, $name) => [mb_strtolower($name) => $n]);

        return Inertia::render('admin/committees/index', [
            'committees' => Committee::orderBy('sort_order')->orderBy('name')->get()->map(fn (Committee $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'records' => (int) ($records[mb_strtolower($c->name)] ?? 0),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $committee = Committee::create([...$this->validated($request), 'sort_order' => (int) Committee::max('sort_order') + 1]);

        Audit::record('settings.committee_added', "Added the committee {$committee->name}", null, ['committee' => $committee->name]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$committee->name} added."]);

        return back();
    }

    public function update(Request $request, Committee $committee): RedirectResponse
    {
        $before = $committee->name;
        $name = $this->validated($request, $committee)['name'];

        $moved = DB::transaction(function () use ($committee, $before, $name) {
            $committee->update(['name' => $name]);

            return MemberServiceRecord::where('type', 'committee')->where('name', $before)->update(['name' => $name]);
        });

        Audit::record('settings.committee_renamed', "Renamed the committee {$before} to {$name}", null, ['from' => $before, 'to' => $name, 'service_records' => $moved]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$name} saved".($moved ? " ({$moved} service record(s) updated)." : '.')]);

        return back();
    }

    public function destroy(Committee $committee): RedirectResponse
    {
        $committee->delete();

        Audit::record('settings.committee_removed', "Removed the committee {$committee->name}", null, ['committee' => $committee->name]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$committee->name} removed."]);

        return back();
    }

    /** @return array{name: string} */
    private function validated(Request $request, ?Committee $committee = null): array
    {
        $request->merge(['name' => trim(preg_replace('/\s+/', ' ', (string) $request->input('name')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('committees', 'name')->ignore($committee)],
        ], ['name.unique' => 'That committee is already on the list.']);
    }
}
