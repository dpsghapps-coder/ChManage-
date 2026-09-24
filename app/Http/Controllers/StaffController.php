<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Position;
use App\Models\Staff;
use App\Rules\PhoneNumber;
use App\Support\Audit;
use App\Support\Presbyteries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function index(Request $request): Response
    {
        $staff = Staff::query()
            ->with(['department:id,name', 'position:id,name', 'user:id,staff_id,username,is_active'])
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('full_name', 'like', "%{$term}%")
                ->orWhere('staff_number', 'like', "%{$term}%")
                ->orWhere('telephone', 'like', "%{$term}%")))
            ->when($request->integer('department_id'), fn ($q, $id) => $q->where('department_id', $id))
            ->when($request->integer('position_id'), fn ($q, $id) => $q->where('position_id', $id))
            ->when($request->string('status')->value(), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('full_name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Staff $s) => [
                'id' => $s->id,
                'staff_number' => $s->staff_number,
                'title' => $s->title,
                'full_name' => $s->full_name,
                'telephone' => $s->telephone,
                'location' => $s->location,
                'status' => $s->status,
                'department' => $s->department?->name,
                'position' => $s->position?->name,
                'user' => $s->user ? ['username' => $s->user->username, 'is_active' => $s->user->is_active] : null,
            ]);

        return Inertia::render('staff/index', [
            'staff' => $staff,
            'filters' => $request->only('q', 'department_id', 'position_id', 'status'),
            ...$this->lookups(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('staff/form', [
            'staff' => null,
            'nextStaffNumber' => Staff::nextStaffNumber(),
            ...$this->lookups(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['staff_number'] = $data['staff_number'] ?? Staff::nextStaffNumber();

        $staff = Staff::create($data);

        Audit::record('staff.created', "Added staff member {$staff->full_name}", $staff);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$staff->full_name} added to the directory."]);

        return to_route('staff.show', $staff);
    }

    public function show(Staff $staff): Response
    {
        $staff->load([
            'department:id,name', 'position:id,name',
            'user' => fn ($q) => $q->with('role:id,name')->select('id', 'staff_id', 'username', 'is_active', 'role_id', 'last_login_at'),
            'transfers.fromDepartment:id,name', 'transfers.toDepartment:id,name',
            'transfers.fromPosition:id,name', 'transfers.toPosition:id,name', 'transfers.recorder:id,username',
        ]);

        return Inertia::render('staff/show', [
            'staff' => [
                ...Arr::only($staff->toArray(), [
                    'id', 'staff_number', 'title', 'full_name', 'sex', 'date_of_birth', 'telephone', 'email', 'address',
                    'ssnit_number', 'location', 'status', 'joined_on', 'left_on', 'emergency_contact_name',
                    'emergency_contact_phone', 'notes', 'department_id', 'position_id',
                ]),
                'department' => $staff->department?->name,
                'position' => $staff->position?->name,
                'user' => $staff->user ? [
                    'id' => $staff->user->id,
                    'username' => $staff->user->username,
                    'is_active' => $staff->user->is_active,
                    'role' => $staff->user->role?->name,
                    'last_login_at' => $staff->user->last_login_at?->toIso8601String(),
                ] : null,
                'transfers' => $staff->transfers->map(fn ($t) => [
                    'id' => $t->id,
                    'effective_on' => $t->effective_on->toDateString(),
                    'from_department' => $t->fromDepartment?->name,
                    'to_department' => $t->toDepartment?->name,
                    'from_position' => $t->fromPosition?->name,
                    'to_position' => $t->toPosition?->name,
                    'from_location' => $t->from_location,
                    'to_location' => $t->to_location,
                    'reason' => $t->reason,
                    'recorded_by' => $t->recorder?->username,
                ])->values()->all(),
            ],
            ...$this->lookups(),
        ]);
    }

    public function edit(Staff $staff): Response
    {
        return Inertia::render('staff/form', [
            'staff' => Arr::only($staff->toArray(), [
                'id', 'staff_number', 'title', 'full_name', 'sex', 'date_of_birth', 'telephone', 'email', 'address',
                'ssnit_number', 'department_id', 'position_id', 'location', 'status', 'joined_on', 'left_on',
                'emergency_contact_name', 'emergency_contact_phone', 'notes',
            ]),
            'nextStaffNumber' => null,
            ...$this->lookups(),
        ]);
    }

    public function update(Request $request, Staff $staff): RedirectResponse
    {
        $data = $this->validated($request, $staff);
        $data['staff_number'] = $data['staff_number'] ?? $staff->staff_number;

        $staff->fill($data);
        $changed = array_keys($staff->getDirty());
        $staff->save();

        Audit::record('staff.updated', "Updated staff member {$staff->full_name}", $staff, ['changed' => $changed]);

        // A terminated staff member must not keep access: deactivate the linked account.
        $user = $staff->user;
        if ($staff->wasChanged('status') && $staff->status === 'terminated' && $user?->is_active) {
            $user->update(['is_active' => false]);
            Audit::record('user.deactivated', "Deactivated {$user->username} because {$staff->full_name} was terminated", $user);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Staff details updated.']);

        return to_route('staff.show', $staff);
    }

    public function destroy(Staff $staff): RedirectResponse
    {
        if ($staff->user()->exists()) {
            return back()->withErrors(['staff' => 'This staff member has a user account. Unlink it first, or set the status to Terminated instead.']);
        }

        if (DB::table('payroll_payments')->where('staff_id', $staff->id)->exists()) {
            return back()->withErrors(['staff' => 'This staff member has payroll records. Set the status to Terminated instead of deleting.']);
        }

        Audit::record('staff.deleted', "Deleted staff member {$staff->full_name}", $staff);
        $staff->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Staff record deleted.']);

        return to_route('staff.index');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Staff $staff = null): array
    {
        return $request->validate([
            'staff_number' => ['nullable', 'string', 'max:30', Rule::unique('staff', 'staff_number')->ignore($staff?->id)],
            'title' => ['nullable', 'string', 'max:20'],
            'full_name' => ['required', 'string', 'max:200'],
            'sex' => ['nullable', Rule::in(['male', 'female'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'telephone' => ['nullable', new PhoneNumber],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:1000'],
            'ssnit_number' => ['nullable', 'string', 'max:40'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'location' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in(Staff::STATUSES)],
            'joined_on' => ['nullable', 'date'],
            'left_on' => ['nullable', 'date', 'after_or_equal:joined_on'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', new PhoneNumber],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /** @return array<string, mixed> */
    private function lookups(): array
    {
        return [
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'positions' => Position::orderBy('name')->get(['id', 'name']),
            'statuses' => Staff::STATUSES,
            // Suggestions for Station / congregation: stations already recorded, then every PCG district.
            'stations' => Presbyteries::placeSuggestions(Staff::distinct()->pluck('location')),
        ];
    }
}
