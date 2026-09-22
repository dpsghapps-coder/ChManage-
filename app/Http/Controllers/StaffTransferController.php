<?php

namespace App\Http\Controllers;

use App\Actions\Staff\TransferStaff;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StaffTransferController extends Controller
{
    public function store(Request $request, Staff $staff, TransferStaff $transfer): RedirectResponse
    {
        $data = $request->validate([
            'to_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'to_position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'to_location' => ['nullable', 'string', 'max:150'],
            'effective_on' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $transfer->handle($staff, $data, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$staff->full_name} transferred. Their account and role are unchanged."]);

        return to_route('staff.show', $staff);
    }
}
