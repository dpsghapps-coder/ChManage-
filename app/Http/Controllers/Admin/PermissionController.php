<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionRegistry;
use Inertia\Inertia;
use Inertia\Response;

/** Read-only: permissions are defined in code (config/permissions.php) and synced to the database. */
class PermissionController extends Controller
{
    public function index(): Response
    {
        $labels = PermissionRegistry::moduleLabels();
        $roles = Role::orderBy('name')->get(['id', 'name', 'slug']);
        $holders = Permission::with('roles:id')->get()->mapWithKeys(fn ($p) => [$p->id => $p->roles->pluck('id')->all()]);
        $adminId = $roles->firstWhere('slug', Role::ADMIN)?->id;

        $groups = Permission::all()->sortBy(fn ($p) => array_search($p->name, PermissionRegistry::names()))
            ->groupBy('module')
            ->map(fn ($items, $module) => [
                'module' => $module,
                'label' => $labels[$module] ?? $module,
                'permissions' => $items->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'description' => $p->description,
                    // The administrator role holds everything by definition.
                    'role_ids' => array_values(array_unique([...$holders[$p->id], ...($adminId ? [$adminId] : [])])),
                ])->values()->all(),
            ])
            ->sortBy(fn ($g) => array_search($g['module'], array_keys($labels), true))
            ->values()
            ->all();

        return Inertia::render('admin/permissions/index', [
            'groups' => $groups,
            'roles' => $roles,
        ]);
    }
}
