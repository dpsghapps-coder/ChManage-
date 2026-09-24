<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemberGroup;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** The service groups (choir, brigade, ...) a member can belong to, as offered on the member form's Church step. */
class ServiceGroupController extends Controller
{
    public function index(): Response
    {
        $counts = DB::table('member_group_memberships')->selectRaw('member_group_id, count(*) as n')->groupBy('member_group_id')->pluck('n', 'member_group_id');

        return Inertia::render('admin/service-groups/index', [
            'groups' => MemberGroup::orderBy('name')->get(['id', 'name', 'short_name'])->map(fn (MemberGroup $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'short_name' => $g->short_name,
                'members' => (int) ($counts[$g->id] ?? 0),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $group = MemberGroup::create($this->validated($request));

        Audit::record('settings.group_added', "Added the service group {$group->name}", $group);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$group->name} added."]);

        return back();
    }

    public function update(Request $request, MemberGroup $group): RedirectResponse
    {
        $before = $group->only(['name', 'short_name']);
        $group->update($this->validated($request, $group));

        Audit::record('settings.group_updated', "Updated the service group {$group->name}", $group, ['before' => $before]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$group->name} saved."]);

        return back();
    }

    /** Only an empty group can go: removing one would silently take it off its members' records. */
    public function destroy(MemberGroup $group): RedirectResponse
    {
        $members = DB::table('member_group_memberships')->where('member_group_id', $group->id)->count();

        if ($members > 0) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "{$group->name} still has {$members} member(s). Move them to another group first."]);

            return back();
        }

        $group->delete();

        Audit::record('settings.group_removed', "Removed the service group {$group->name}", null, ['group' => $group->name]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$group->name} removed."]);

        return back();
    }

    /** @return array{name: string, short_name: ?string} */
    private function validated(Request $request, ?MemberGroup $group = null): array
    {
        $request->merge([
            'name' => trim(preg_replace('/\s+/', ' ', (string) $request->input('name'))),
            'short_name' => trim((string) $request->input('short_name')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('member_groups', 'name')->ignore($group)],
            'short_name' => ['nullable', 'string', 'max:50'],
        ], ['name.unique' => 'There is already a group with that name.']);

        return ['name' => $data['name'], 'short_name' => filled($data['short_name'] ?? null) ? $data['short_name'] : null];
    }
}
