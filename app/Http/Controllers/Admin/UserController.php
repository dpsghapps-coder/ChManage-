<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->with(['role:id,name,slug', 'staff:id,staff_number,full_name'])
            ->when($request->string('q')->trim()->value(), function ($query, $term) {
                $query->where(fn ($w) => $w
                    ->where('username', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%"));
            })
            ->when($request->integer('role_id'), fn ($query, $id) => $query->where('role_id', $id))
            ->when($request->input('status') === 'active', fn ($query) => $query->where('is_active', true))
            ->when($request->input('status') === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('username')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => $this->present($user));

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only('q', 'role_id', 'status'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/users/form', [
            'user' => null,
            'roles' => $this->assignableRoles($request->user()),
            'staff' => $this->linkableStaff(),
            'presetStaffId' => $request->integer('staff_id') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->authorizeRoleAndLink($request, null, $data);

        $temporaryPassword = $this->temporaryPassword();

        $user = User::create([
            ...$data,
            'password' => $temporaryPassword,
            'must_reset_password' => true,
        ]);

        Audit::record('user.created', "Created user {$user->username}", $user, [
            'role_id' => $user->role_id, 'staff_id' => $user->staff_id,
        ]);

        Inertia::flash('temporaryPassword', $temporaryPassword);
        Inertia::flash('toast', ['type' => 'success', 'message' => "User {$user->username} created."]);

        return to_route('admin.users.edit', $user);
    }

    public function edit(Request $request, User $user): Response
    {
        return Inertia::render('admin/users/form', [
            'user' => $this->present($user->load('role:id,name,slug', 'staff:id,staff_number,full_name')) + [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'role_id' => $user->role_id,
                'staff_id' => $user->staff_id,
            ],
            'roles' => $this->assignableRoles($request->user()),
            'staff' => $this->linkableStaff($user),
            'presetStaffId' => null,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        $this->authorizeRoleAndLink($request, $user, $data);
        $this->guardAdminSafety($request, $user, $data);

        $user->fill($data);
        $changes = collect($user->getDirty())->except('updated_at');
        $old = collect($user->getOriginal())->only($changes->keys()->all());

        if ($changes->isEmpty()) {
            return to_route('admin.users.edit', $user);
        }

        $user->save();

        Audit::record('user.updated', "Updated user {$user->username}", $user, [
            'changed' => $changes->keys()->all(), 'from' => $old->all(), 'to' => $changes->all(),
        ]);

        if ($changes->has('role_id')) {
            Audit::record('user.role_changed', "Changed role of {$user->username}", $user, [
                'from' => $old['role_id'] ?? null, 'to' => $changes['role_id'],
            ]);
        }

        if ($changes->has('is_active')) {
            Audit::record($user->is_active ? 'user.activated' : 'user.deactivated',
                ($user->is_active ? 'Activated ' : 'Deactivated ')."user {$user->username}", $user);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User updated.']);

        return to_route('admin.users.edit', $user);
    }

    /** Issue a new one-time temporary password; the user must replace it at next sign-in. */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isAdmin() && ! $request->user()->isAdmin(), 403, 'Only an administrator can reset an administrator.');

        $temporaryPassword = $this->temporaryPassword();

        $user->update(['password' => $temporaryPassword, 'must_reset_password' => true]);

        Audit::record('user.password_reset', "Issued a temporary password for {$user->username}", $user);

        Inertia::flash('temporaryPassword', $temporaryPassword);

        return to_route('admin.users.edit', $user);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user?->id)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id', Rule::unique('users', 'staff_id')->ignore($user?->id)],
            'is_active' => ['required', 'boolean'],
        ], [
            'username.regex' => 'Use letters, numbers, dots, dashes and underscores only.',
            'staff_id.unique' => 'That staff member already has a user account.',
        ]);

        // Forms may send "1"/"true"; the safety checks below compare with === so normalise here.
        $data['role_id'] = (int) $data['role_id'];
        $data['staff_id'] = filled($data['staff_id'] ?? null) ? (int) $data['staff_id'] : null;
        $data['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        $data['email'] = filled($data['email'] ?? null) ? $data['email'] : null;
        $data['last_name'] = filled($data['last_name'] ?? null) ? $data['last_name'] : null;

        return $data;
    }

    /**
     * Roles are the most sensitive setting on an account, so: only administrators can hand out the admin
     * role or touch admin accounts, and linking a staff member needs its own permission.
     *
     * @param  array<string, mixed>  $data
     */
    private function authorizeRoleAndLink(Request $request, ?User $target, array $data): void
    {
        $actor = $request->user();
        $adminRoleId = Role::where('slug', Role::ADMIN)->value('id');

        if (! $actor->isAdmin() && ($data['role_id'] === $adminRoleId || $target?->isAdmin())) {
            throw ValidationException::withMessages(['role_id' => 'Only an administrator can assign the administrator role or edit an administrator.']);
        }

        $staffChanged = ($data['staff_id'] ?? null) !== $target?->staff_id;

        if ($staffChanged && ! $actor->can('staff.link_user')) {
            throw ValidationException::withMessages(['staff_id' => 'You do not have permission to link or unlink staff.']);
        }
    }

    /** @param  array<string, mixed>  $data */
    private function guardAdminSafety(Request $request, User $user, array $data): void
    {
        $actor = $request->user();
        $adminRoleId = Role::where('slug', Role::ADMIN)->value('id');

        if ($actor->id === $user->id) {
            if ((int) $data['role_id'] !== $user->role_id) {
                throw ValidationException::withMessages(['role_id' => 'You cannot change your own role. Ask another administrator.']);
            }
            if (! $data['is_active']) {
                throw ValidationException::withMessages(['is_active' => 'You cannot deactivate your own account.']);
            }
        }

        $losingAdmin = $user->role_id === $adminRoleId && ((int) $data['role_id'] !== $adminRoleId || ! $data['is_active']);

        if ($losingAdmin && ! User::where('role_id', $adminRoleId)->where('is_active', true)->where('id', '!=', $user->id)->exists()) {
            throw ValidationException::withMessages(['role_id' => 'This is the last active administrator. Make another account an administrator first.']);
        }
    }

    private function assignableRoles(User $actor)
    {
        return Role::query()
            ->when(! $actor->isAdmin(), fn ($q) => $q->where('slug', '!=', Role::ADMIN))
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    /** Staff without an account yet, plus the one already linked to $user. */
    private function linkableStaff(?User $user = null)
    {
        return Staff::query()
            ->where(fn ($q) => $q->whereDoesntHave('user')->when($user?->staff_id, fn ($w, $id) => $w->orWhere('id', $id)))
            ->where('status', '!=', 'terminated')
            ->orderBy('full_name')
            ->get(['id', 'staff_number', 'full_name']);
    }

    private function temporaryPassword(): string
    {
        return Str::password(12, symbols: false);
    }

    /** @return array<string, mixed> */
    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'must_reset_password' => $user->must_reset_password,
            'has_password' => filled($user->password),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'role' => $user->role ? ['id' => $user->role->id, 'name' => $user->role->name, 'slug' => $user->role->slug] : null,
            'staff' => $user->staff ? ['id' => $user->staff->id, 'staff_number' => $user->staff->staff_number, 'full_name' => $user->staff->full_name] : null,
        ];
    }
}
