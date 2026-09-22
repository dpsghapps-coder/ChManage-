<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ChurchSettingsController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberReportController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffLookupController;
use App\Http\Controllers\StaffTransferController;
use App\Http\Controllers\YoungMemberController;
use Illuminate\Support\Facades\Route;

// Internal system: no public landing page. Guests are sent to sign in by the `auth` middleware.
Route::redirect('/', '/dashboard')->name('home');

Route::middleware('auth')->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Shown after signing in with a temporary password (see EnsurePasswordIsChanged).
    Route::get('account/password', [ForcePasswordChangeController::class, 'edit'])->name('password.change');
    Route::put('account/password', [ForcePasswordChangeController::class, 'update'])->name('password.change.update');

    // Members: adults, Junior Youth and Children Service ---------------------------------------------
    // Specific paths first: members/{member} would otherwise swallow "create", "search" and "adult".
    Route::get('members', [MemberController::class, 'index'])->middleware('permission:members.view')->name('members.index');
    Route::get('members/create', [YoungMemberController::class, 'create'])->middleware('permission:members.create')->name('members.create');
    Route::post('members', [YoungMemberController::class, 'store'])->middleware('permission:members.create')->name('members.store');
    Route::get('members/suggest', [MemberController::class, 'suggest'])->middleware('permission:members.view')->name('members.suggest');
    Route::get('members/search', [MemberController::class, 'search'])->middleware('permission:members.create,members.edit')->name('members.search');

    Route::get('members/adult/create', [MemberController::class, 'createAdult'])->middleware('permission:members.create')->name('members.adult.create');
    Route::post('members/adult', [MemberController::class, 'storeAdult'])->middleware('permission:members.create')->name('members.adult.store');

    Route::prefix('members/young/{youngMember}')->name('members.young.')->group(function () {
        Route::get('/', [YoungMemberController::class, 'show'])->middleware('permission:members.view')->name('show');
        Route::get('edit', [YoungMemberController::class, 'edit'])->middleware('permission:members.edit')->name('edit');
        Route::put('/', [YoungMemberController::class, 'update'])->middleware('permission:members.edit')->name('update');
        Route::delete('/', [YoungMemberController::class, 'destroy'])->middleware('permission:members.delete')->name('destroy');
        Route::post('restore', [YoungMemberController::class, 'restore'])->middleware('permission:members.delete')->name('restore');
        Route::get('photo', [YoungMemberController::class, 'photo'])->middleware('permission:members.view')->name('photo');
        Route::get('report', [MemberReportController::class, 'young'])->middleware('permission:members.export')->name('report');
    });

    Route::get('members/{member}/report', [MemberReportController::class, 'adult'])->middleware('permission:members.export')->name('members.report');
    Route::get('members/{member}/related', [MemberController::class, 'related'])->middleware('permission:members.view')->name('members.related');
    Route::get('members/{member}', [MemberController::class, 'showAdult'])->middleware('permission:members.view')->name('members.show');
    Route::get('members/{member}/edit', [MemberController::class, 'editAdult'])->middleware('permission:members.edit')->name('members.edit');
    Route::put('members/{member}', [MemberController::class, 'updateAdult'])->middleware('permission:members.edit')->name('members.update');
    Route::delete('members/{member}', [MemberController::class, 'destroy'])->middleware('permission:members.delete')->name('members.destroy');
    Route::post('members/{member}/restore', [MemberController::class, 'restore'])->middleware('permission:members.delete')->name('members.restore');
    Route::get('members/{member}/photo', [MemberController::class, 'photo'])->middleware('permission:members.view,members.create,members.edit')->name('members.photo');

    Route::prefix('staff')->name('staff.')->group(function () {
        Route::get('/', [StaffController::class, 'index'])->middleware('permission:staff.view')->name('index');
        Route::get('create', [StaffController::class, 'create'])->middleware('permission:staff.create')->name('create');
        Route::post('/', [StaffController::class, 'store'])->middleware('permission:staff.create')->name('store');

        Route::post('lookups/departments', [StaffLookupController::class, 'storeDepartment'])->middleware('permission:staff.create,staff.edit')->name('departments.store');
        Route::post('lookups/positions', [StaffLookupController::class, 'storePosition'])->middleware('permission:staff.create,staff.edit')->name('positions.store');

        Route::get('{staff}', [StaffController::class, 'show'])->middleware('permission:staff.view')->name('show');
        Route::get('{staff}/edit', [StaffController::class, 'edit'])->middleware('permission:staff.edit')->name('edit');
        Route::put('{staff}', [StaffController::class, 'update'])->middleware('permission:staff.edit')->name('update');
        Route::delete('{staff}', [StaffController::class, 'destroy'])->middleware('permission:staff.delete')->name('destroy');
        Route::post('{staff}/transfers', [StaffTransferController::class, 'store'])->middleware('permission:staff.transfer')->name('transfers.store');
    });

    // Administration: users, roles, permissions, audit log ----------------------------------------
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->middleware('permission:users.create')->name('users.create');
        Route::post('users', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.edit')->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->middleware('permission:users.edit')->name('users.update');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('permission:users.reset_password')->name('users.reset-password');

        Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
        Route::get('roles/create', [RoleController::class, 'create'])->middleware('permission:roles.manage')->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.manage')->name('roles.store');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.manage')->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.manage')->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.manage')->name('roles.destroy');

        Route::get('permissions', [PermissionController::class, 'index'])->middleware('permission:permissions.view')->name('permissions.index');
        Route::get('audit', [AuditLogController::class, 'index'])->middleware('permission:audit.view')->name('audit.index');

        Route::get('church', [ChurchSettingsController::class, 'edit'])->middleware('permission:settings.manage')->name('church.edit');
        Route::put('church', [ChurchSettingsController::class, 'update'])->middleware('permission:settings.manage')->name('church.update');
    });
});

require __DIR__.'/settings.php';
