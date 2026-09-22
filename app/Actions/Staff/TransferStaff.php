<?php

namespace App\Actions\Staff;

use App\Models\Staff;
use App\Models\StaffTransfer;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves a staff member to a new department, position and/or station and records the history.
 *
 * Deliberately touches ONLY the staff record. The linked user account, its role and its permissions are
 * left exactly as they are - a transfer must never change what someone is allowed to do.
 */
class TransferStaff
{
    /**
     * @param  array{to_department_id?: ?int, to_position_id?: ?int, to_location?: ?string, effective_on: string, reason?: ?string}  $data
     */
    public function handle(Staff $staff, array $data, ?User $recordedBy = null): StaffTransfer
    {
        // An omitted/blank destination means "no change" for that part of the post.
        $toDepartment = ($data['to_department_id'] ?? null) ?: $staff->department_id;
        $toPosition = ($data['to_position_id'] ?? null) ?: $staff->position_id;
        $toLocation = filled($data['to_location'] ?? null) ? trim($data['to_location']) : $staff->location;

        $changed = $toDepartment !== $staff->department_id
            || $toPosition !== $staff->position_id
            || $toLocation !== $staff->location;

        if (! $changed) {
            throw ValidationException::withMessages([
                'to_department_id' => 'Choose at least one thing that changes: department, position or station.',
            ]);
        }

        return DB::transaction(function () use ($staff, $data, $recordedBy, $toDepartment, $toPosition, $toLocation) {
            $transfer = StaffTransfer::create([
                'staff_id' => $staff->id,
                'from_department_id' => $staff->department_id,
                'to_department_id' => $toDepartment,
                'from_position_id' => $staff->position_id,
                'to_position_id' => $toPosition,
                'from_location' => $staff->location,
                'to_location' => $toLocation,
                'effective_on' => $data['effective_on'],
                'reason' => $data['reason'] ?? null,
                'recorded_by' => $recordedBy?->id,
            ]);

            $staff->update([
                'department_id' => $toDepartment,
                'position_id' => $toPosition,
                'location' => $toLocation,
            ]);

            Audit::record('staff.transferred', "{$staff->full_name} was transferred", $staff, [
                'from' => ['department_id' => $transfer->from_department_id, 'position_id' => $transfer->from_position_id, 'location' => $transfer->from_location],
                'to' => ['department_id' => $toDepartment, 'position_id' => $toPosition, 'location' => $toLocation],
                'effective_on' => $data['effective_on'],
            ]);

            return $transfer;
        });
    }
}
