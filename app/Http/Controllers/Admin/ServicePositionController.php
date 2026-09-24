<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemberServiceRecord;
use App\Models\ServicePosition;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The positions offered on the member form's Service step, for each type of service. Service records store the
 * position as text, so renaming or removing one here changes what is offered, not what members' records say.
 */
class ServicePositionController extends Controller
{
    public function index(): Response
    {
        $positions = ServicePosition::orderBy('sort_order')->orderBy('name')->get()->groupBy('type');

        return Inertia::render('admin/service-positions/index', [
            'types' => collect(MemberServiceRecord::TYPES)->map(fn ($label, $type) => [
                'type' => $type,
                'label' => $label,
                'positions' => $positions->get($type, collect())->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $position = ServicePosition::create([...$data, 'sort_order' => (int) ServicePosition::where('type', $data['type'])->max('sort_order') + 1]);

        Audit::record('settings.position_added', "Added the {$this->label($position)} position {$position->name}", null, $position->only(['type', 'name']));
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$position->name} added."]);

        return back();
    }

    public function update(Request $request, ServicePosition $position): RedirectResponse
    {
        $before = $position->name;
        $position->update(['name' => $this->validated($request, $position)['name']]);

        Audit::record('settings.position_renamed', "Renamed the {$this->label($position)} position {$before} to {$position->name}", null, ['type' => $position->type]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$position->name} saved."]);

        return back();
    }

    public function destroy(ServicePosition $position): RedirectResponse
    {
        $position->delete();

        Audit::record('settings.position_removed', "Removed the {$this->label($position)} position {$position->name}", null, $position->only(['type', 'name']));
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$position->name} removed."]);

        return back();
    }

    /** @return array{type: string, name: string} */
    private function validated(Request $request, ?ServicePosition $position = null): array
    {
        $request->merge(['name' => trim(preg_replace('/\s+/', ' ', (string) $request->input('name')))]);
        $type = $position?->type ?? $request->input('type');

        $data = $request->validate([
            'type' => [$position ? 'nullable' : 'required', Rule::in(array_keys(MemberServiceRecord::TYPES))],
            'name' => ['required', 'string', 'max:100', Rule::unique('service_positions', 'name')->where('type', $type)->ignore($position)],
        ], ['name.unique' => 'That position is already on this list.']);

        return ['type' => $type, 'name' => $data['name']];
    }

    private function label(ServicePosition $position): string
    {
        return MemberServiceRecord::TYPES[$position->type] ?? $position->type;
    }
}
