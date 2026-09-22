<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff directory. The old `employees` table (2 rows, referenced by payroll) becomes `staff`, keeping its ids.
 * A user links to at most one staff member; a transfer changes the staff member's post, never the user or role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::rename('employees', 'staff');

        Schema::table('payroll_payments', function (Blueprint $table) {
            $table->renameColumn('employee_id', 'staff_id');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->string('staff_number', 30)->nullable()->unique()->after('id');
            $table->string('title', 20)->nullable()->after('staff_number');
            $table->enum('sex', ['male', 'female'])->nullable()->after('full_name');
            $table->date('date_of_birth')->nullable()->after('sex');
            $table->string('email', 150)->nullable()->after('telephone');
            $table->text('address')->nullable()->after('email');
            $table->unsignedInteger('department_id')->nullable()->after('ssnit_number');
            $table->unsignedInteger('position_id')->nullable()->after('department_id');
            $table->string('location', 150)->nullable()->after('position_id')->comment('Station / congregation currently served');
            $table->unsignedInteger('member_id')->nullable()->after('location')->comment('Set when the staff member is also on the members register');
            $table->enum('status', ['active', 'on_leave', 'suspended', 'terminated'])->default('active')->after('member_id');
            $table->date('joined_on')->nullable()->after('status');
            $table->date('left_on')->nullable()->after('joined_on');
            $table->string('emergency_contact_name', 150)->nullable()->after('left_on');
            $table->string('emergency_contact_phone', 30)->nullable()->after('emergency_contact_name');
            $table->unsignedInteger('photo_id')->nullable()->after('emergency_contact_phone');
            $table->text('notes')->nullable()->after('photo_id');
            $table->timestamps();

            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            $table->foreign('position_id')->references('id')->on('positions')->nullOnDelete();
            $table->foreign('member_id')->references('id')->on('members')->nullOnDelete();
            $table->foreign('photo_id')->references('id')->on('media_files')->nullOnDelete();
            $table->index('full_name');
            $table->index('status');
        });

        // Give the existing rows a staff number: STF-0001, STF-0002 ...
        foreach (DB::table('staff')->orderBy('id')->get() as $i => $row) {
            DB::table('staff')->where('id', $row->id)->update([
                'staff_number' => sprintf('STF-%04d', $i + 1),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('staff_id')->nullable()->unique()->after('role_id');
            $table->foreign('staff_id')->references('id')->on('staff')->nullOnDelete();
        });

        Schema::create('staff_transfers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('staff_id');
            $table->unsignedInteger('from_department_id')->nullable();
            $table->unsignedInteger('to_department_id')->nullable();
            $table->unsignedInteger('from_position_id')->nullable();
            $table->unsignedInteger('to_position_id')->nullable();
            $table->string('from_location', 150)->nullable();
            $table->string('to_location', 150)->nullable();
            $table->date('effective_on');
            $table->text('reason')->nullable();
            $table->unsignedInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->index(['staff_id', 'effective_on']);
            $table->foreign('staff_id')->references('id')->on('staff')->cascadeOnDelete();
            $table->foreign('from_department_id')->references('id')->on('departments')->nullOnDelete();
            $table->foreign('to_department_id')->references('id')->on('departments')->nullOnDelete();
            $table->foreign('from_position_id')->references('id')->on('positions')->nullOnDelete();
            $table->foreign('to_position_id')->references('id')->on('positions')->nullOnDelete();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('event', 60)->index();          // e.g. role.permissions_changed
            $table->string('subject_type', 60)->nullable();
            $table->unsignedInteger('subject_id')->nullable();
            $table->string('description');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['subject_type', 'subject_id']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('staff_transfers');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
            $table->dropColumn('staff_id');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['position_id']);
            $table->dropForeign(['member_id']);
            $table->dropForeign(['photo_id']);
            $table->dropIndex(['full_name']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'staff_number', 'title', 'sex', 'date_of_birth', 'email', 'address', 'department_id', 'position_id',
                'location', 'member_id', 'status', 'joined_on', 'left_on', 'emergency_contact_name',
                'emergency_contact_phone', 'photo_id', 'notes', 'created_at', 'updated_at',
            ]);
        });

        Schema::table('payroll_payments', function (Blueprint $table) {
            $table->renameColumn('staff_id', 'employee_id');
        });

        Schema::rename('staff', 'employees');
        Schema::dropIfExists('positions');
    }
};
