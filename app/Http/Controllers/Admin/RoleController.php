<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Audit;
use App\Support\PermissionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Roles are built by combining permissions. */
class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()
            ->withCount('users')
            ->with('permissions:id,name')
            ->orderByRaw("slug = 'admin' desc")
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'users_count' => $role->users_count,
                'permissions' => $role->isAdmin() ? PermissionRegistry::names() : $role->permissions->pluck('name')->sort()->values()->all(),
            ]);

        return Inertia::render('admin/roles/index', [
            'roles' => $roles,
            'modules' => PermissionRegistry::moduleLabels(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/roles/form', [
            'role' => null,
            'groups' => $this->groups(),
            'held' => $this->heldBy(request()->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $actor = $request->user();

        // Nobody can hand out a permission they do not hold themselves.
        $granted = $this->clamp($actor, $data['permissions'] ?? [], []);

        $role = Role::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'description' => $data['description'] ?? null,
        ]);
        $role->permissions()->sync($granted);

        Audit::record('role.created', "Created role {$role->name}", $role, [
            'permissions' => Permission::whereIn('id', $granted)->pluck('name')->sort()->values()->all(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Role {$role->name} created."]);

        return to_route('admin.roles.index');
    }

    public function edit(Request $request, Role $role): Response
    {
        return Inertia::render('admin/roles/form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'is_admin' => $role->isAdmin(),
                'users_count' => $role->users()->count(),
                'permission_ids' => $role->permissions()->pluck('permissions.id')->all(),
            ],
            'groups' => $this->groups(),
            'held' => $this->heldBy($request->user()),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $this->validated($request, $role);
        $actor = $request->user();

        if ($role->isAdmin()) {
            // The administrator role bypasses permission checks and must keep its name; only the description is editable.
            $role->update(['description' => $data['description'] ?? null]);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Administrator role updated (name and access are fixed).']);

            return to_route('admin.roles.index');
        }

        $before = $role->permissions()->pluck('permissions.id')->all();
        $after = $this->clamp($actor, $data['permissions'] ?? [], $before);

        $role->update(['name' => $data['name'], 'description' => $data['description'] ?? null]);
        $role->permissions()->sync($after);

        $names = fn (array $ids) => Permission::whereIn('id', $ids)->pluck('name')->sort()->values()->all();
        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));

        if ($added || $removed) {
            Audit::record('role.permissions_changed', "Changed permissions of role {$role->name}", $role, [
                'added' => $names($added), 'removed' => $names($removed),
            ]);
        }

        // If the actor edited their own role, drop the cached permission list for the rest of this request.
        $actor->flushPermissionCache();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Role {$role->name} updated."]);

        return to_route('admin.roles.index');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->withErrors(['role' => 'System roles cannot be deleted.']);
        }

        if ($role->users()->exists()) {
            return back()->withErrors(['role' => "Reassign the {$role->users()->count()} user(s) with this role first."]);
        }

        Audit::record('role.deleted', "Deleted role {$role->name}", $role);
        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Role deleted.']);

        return to_route('admin.roles.index');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);
    }

    /**
     * A non-administrator can only add or remove permissions that they hold themselves; permissions they do
     * not hold stay exactly as they were. Administrators set the role exactly as submitted.
     *
     * @param  list<int>  $requested
     * @param  list<int>  $current
     * @return list<int>
     */
    private function clamp($actor, array $requested, array $current): array
    {
        if ($actor->isAdmin()) {
            return array_values(array_unique(array_map('intval', $requested)));
        }

        $heldIds = Permission::whereIn('name', $actor->permissionNames())->pluck('id')->all();
        $kept = array_diff($current, $heldIds);
        $applied = array_intersect(array_map('intval', $requested), $heldIds);

        return array_values(array_unique([...$kept, ...$applied]));
    }

    /** Permission ids the actor may grant (all of them for an administrator). */
    private function heldBy($actor): array
    {
        return $actor->isAdmin() ? Permission::pluck('id')->all() : Permission::whereIn('name', $actor->permissionNames())->pluck('id')->all();
    }

    /** @return list<array{module: string, label: string, permissions: list<array<string, mixed>>}> */
    private function groups(): array
    {
        $labels = PermissionRegistry::moduleLabels();

        return Permission::all()->sortBy(fn ($p) => array_search($p->name, PermissionRegistry::names()))
            ->groupBy('module')
            ->map(fn ($items, $module) => [
                'module' => $module,
                'label' => $labels[$module] ?? Str::headline($module),
                'permissions' => $items->map(fn (Permission $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'action' => Str::after($p->name, '.'),
                    'description' => $p->description,
                ])->values()->all(),
            ])
            ->sortBy(fn ($group) => array_search($group['module'], array_keys($labels), true))
            ->values()
            ->all();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'role';
        $slug = $base;

        for ($i = 2; Role::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}_{$i}";
        }

        return $slug;
    }
}
