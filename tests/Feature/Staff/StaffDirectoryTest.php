<?php

namespace Tests\Feature\Staff;

use App\Models\Department;
use App\Models\Position;
use App\Models\Staff;
use App\Models\StaffTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function staffPayload(array $overrides = []): array
    {
        return ['full_name' => 'Ama Mensah', 'status' => 'active', ...$overrides];
    }

    public function test_staff_are_added_with_an_automatic_staff_number(): void
    {
        $user = $this->userWith(['staff.create']);

        $this->actingAs($user)->post('/staff', $this->staffPayload())->assertRedirect();
        $this->actingAs($user)->post('/staff', $this->staffPayload(['full_name' => 'Kofi Boateng']))->assertRedirect();

        $this->assertSame(['STF-0001', 'STF-0002'], Staff::orderBy('id')->pluck('staff_number')->all());
    }

    public function test_a_full_name_and_valid_status_are_required(): void
    {
        $user = $this->userWith(['staff.create']);

        $this->actingAs($user)->post('/staff', ['status' => 'active'])->assertSessionHasErrors('full_name');
        $this->actingAs($user)->post('/staff', $this->staffPayload(['status' => 'retired']))->assertSessionHasErrors('status');
    }

    public function test_a_transfer_moves_the_staff_member_and_keeps_the_history(): void
    {
        $finance = Department::create(['name' => 'Finance']);
        $admin = Department::create(['name' => 'Administration']);
        $clerk = Position::create(['name' => 'Clerk']);
        $secretary = Position::create(['name' => 'Secretary']);
        $staff = Staff::create($this->staffPayload(['department_id' => $finance->id, 'position_id' => $clerk->id, 'location' => 'Ebenezer']));

        $mover = $this->userWith(['staff.transfer']);

        $this->actingAs($mover)->post("/staff/{$staff->id}/transfers", [
            'to_department_id' => $admin->id,
            'to_position_id' => $secretary->id,
            'to_location' => 'Mamprobi',
            'effective_on' => '2026-10-01',
            'reason' => 'Posted by the session',
        ])->assertRedirect("/staff/{$staff->id}");

        $staff->refresh();
        $this->assertSame($admin->id, $staff->department_id);
        $this->assertSame($secretary->id, $staff->position_id);
        $this->assertSame('Mamprobi', $staff->location);

        $transfer = StaffTransfer::firstOrFail();
        $this->assertSame($finance->id, $transfer->from_department_id);
        $this->assertSame($admin->id, $transfer->to_department_id);
        $this->assertSame('Ebenezer', $transfer->from_location);
        $this->assertSame($mover->id, $transfer->recorded_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'staff.transferred', 'subject_id' => $staff->id]);
    }

    public function test_a_transfer_never_changes_the_linked_user_or_their_role(): void
    {
        $finance = Department::create(['name' => 'Finance']);
        $admin = Department::create(['name' => 'Administration']);
        $staff = Staff::create($this->staffPayload(['department_id' => $finance->id]));

        $role = $this->roleWith(['contributions.create', 'members.view'], 'Clerk-like');
        $account = User::factory()->create(['role_id' => $role->id, 'staff_id' => $staff->id, 'username' => 'ama']);
        $before = $account->only(['id', 'username', 'role_id', 'staff_id', 'is_active', 'password']);

        $this->actingAs($this->userWith(['staff.transfer']))
            ->post("/staff/{$staff->id}/transfers", ['to_department_id' => $admin->id, 'effective_on' => '2026-10-01'])
            ->assertRedirect();

        $this->assertSame($admin->id, $staff->fresh()->department_id);

        $after = $account->fresh();
        $this->assertSame($before, $after->only(['id', 'username', 'role_id', 'staff_id', 'is_active', 'password']));
        $this->assertEqualsCanonicalizing(['contributions.create', 'members.view'], $after->permissionNames());
    }

    public function test_a_transfer_that_changes_nothing_is_rejected(): void
    {
        $finance = Department::create(['name' => 'Finance']);
        $staff = Staff::create($this->staffPayload(['department_id' => $finance->id]));

        $this->actingAs($this->userWith(['staff.transfer']))
            ->post("/staff/{$staff->id}/transfers", ['to_department_id' => $finance->id, 'effective_on' => '2026-10-01'])
            ->assertSessionHasErrors('to_department_id');

        $this->assertSame(0, StaffTransfer::count());
    }

    public function test_transferring_needs_the_transfer_permission(): void
    {
        $dept = Department::create(['name' => 'Finance']);
        $staff = Staff::create($this->staffPayload());

        $this->actingAs($this->userWith(['staff.view', 'staff.edit']))
            ->post("/staff/{$staff->id}/transfers", ['to_department_id' => $dept->id, 'effective_on' => '2026-10-01'])
            ->assertForbidden();

        $this->assertSame(0, StaffTransfer::count());
    }

    public function test_terminating_a_staff_member_deactivates_their_account(): void
    {
        $staff = Staff::create($this->staffPayload());
        $account = User::factory()->create(['staff_id' => $staff->id, 'role_id' => $this->roleWith([])->id]);

        $this->actingAs($this->userWith(['staff.edit']))
            ->put("/staff/{$staff->id}", $this->staffPayload(['status' => 'terminated']))
            ->assertRedirect();

        $this->assertFalse($account->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.deactivated', 'subject_id' => $account->id]);
    }

    public function test_a_staff_member_with_an_account_cannot_be_deleted(): void
    {
        $staff = Staff::create($this->staffPayload());
        User::factory()->create(['staff_id' => $staff->id]);

        $this->actingAs($this->userWith(['staff.delete']))->delete("/staff/{$staff->id}")->assertSessionHasErrors('staff');

        $this->assertDatabaseHas('staff', ['id' => $staff->id]);
    }

    public function test_an_unlinked_staff_member_can_be_deleted(): void
    {
        $staff = Staff::create($this->staffPayload());

        $this->actingAs($this->userWith(['staff.delete']))->delete("/staff/{$staff->id}")->assertRedirect('/staff');

        $this->assertDatabaseMissing('staff', ['id' => $staff->id]);
    }
}
