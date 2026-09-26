<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ChurchSettingsController;
use App\Http\Controllers\Admin\CommitteeController;
use App\Http\Controllers\Admin\EventVenueController;
use App\Http\Controllers\Admin\NeighbourhoodController;
use App\Http\Controllers\Admin\NewcomerCounsellorController;
use App\Http\Controllers\Admin\NewcomerLessonController;
use App\Http\Controllers\Admin\NewcomerOptionController;
use App\Http\Controllers\Admin\OccupationController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ServiceGroupController;
use App\Http\Controllers\Admin\ServicePositionController;
use App\Http\Controllers\Admin\TownController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\CommitteeMemberController;
use App\Http\Controllers\CommunionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventParticipantController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MeetingActionController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\MeetingDecisionController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberReportController;
use App\Http\Controllers\MemberRequestController;
use App\Http\Controllers\NewcomerController;
use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Portal\PortalRequestController;
use App\Http\Controllers\PresbyteryController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SpeakingController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffLookupController;
use App\Http\Controllers\StaffTransferController;
use App\Http\Controllers\YoungMemberController;
use Illuminate\Support\Facades\Route;

// Internal system: no public landing page. Guests are sent to sign in by the `auth` middleware.
Route::redirect('/', '/dashboard')->name('home');

// First-time setup: open only while there are no user accounts.
Route::get('setup', [SetupController::class, 'show'])->name('setup.show');
Route::post('setup', [SetupController::class, 'store'])->name('setup.store');

// The member portal: members sign in with their phone number and date of birth (the `member` guard, a session of its own).
Route::prefix('portal')->name('portal.')->group(function () {
    Route::middleware('guest:member')->group(function () {
        Route::get('login', [PortalAuthController::class, 'create'])->name('login');
        Route::post('login', [PortalAuthController::class, 'store'])->name('login.store');
    });

    Route::middleware(['auth:member', 'portal'])->group(function () {
        Route::get('/', [PortalController::class, 'home'])->name('home');
        Route::get('requests/{type}/new', [PortalRequestController::class, 'create'])->name('requests.create');
        Route::post('requests/{type}', [PortalRequestController::class, 'store'])->name('requests.store');
        Route::post('requests/{memberRequest}/cancel', [PortalRequestController::class, 'cancel'])->name('requests.cancel');
        Route::post('logout', [PortalAuthController::class, 'destroy'])->name('logout');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

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

    // Visitors, newcomers and catechumens. The settings tabs (counsellors, lessons, lists) come before {newcomer}.
    Route::prefix('people/newcomers')->name('newcomers.')->group(function () {
        Route::get('/', [NewcomerController::class, 'index'])->middleware('permission:newcomers.view')->name('index');
        Route::get('overview', [NewcomerController::class, 'overview'])->middleware('permission:newcomers.view')->name('overview');
        Route::get('dashboard', [NewcomerController::class, 'dashboard'])->middleware('permission:newcomers.view')->name('dashboard');
        Route::get('create', [NewcomerController::class, 'create'])->middleware('permission:newcomers.manage')->name('create');
        Route::post('/', [NewcomerController::class, 'store'])->middleware('permission:newcomers.manage')->name('store');
        Route::get('duplicates', [NewcomerController::class, 'duplicates'])->middleware('permission:newcomers.manage')->name('duplicates');

        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('counsellors', [NewcomerCounsellorController::class, 'index'])->name('counsellors.index');
            Route::post('counsellors', [NewcomerCounsellorController::class, 'store'])->name('counsellors.store');
            Route::put('counsellors/{counsellor}', [NewcomerCounsellorController::class, 'update'])->name('counsellors.update');
            Route::delete('counsellors/{counsellor}', [NewcomerCounsellorController::class, 'destroy'])->name('counsellors.destroy');

            Route::get('lessons', [NewcomerLessonController::class, 'index'])->name('lessons.index');
            Route::post('lessons', [NewcomerLessonController::class, 'store'])->name('lessons.store');
            Route::put('lessons/{lesson}', [NewcomerLessonController::class, 'update'])->name('lessons.update');
            Route::put('lessons/{lesson}/move', [NewcomerLessonController::class, 'move'])->name('lessons.move');
            Route::delete('lessons/{lesson}', [NewcomerLessonController::class, 'destroy'])->name('lessons.destroy');

            Route::get('lists', [NewcomerOptionController::class, 'index'])->name('lists.index');
            Route::post('lists', [NewcomerOptionController::class, 'store'])->name('lists.store');
            Route::put('lists/{option}', [NewcomerOptionController::class, 'update'])->name('lists.update');
            Route::delete('lists/{option}', [NewcomerOptionController::class, 'destroy'])->name('lists.destroy');
        });

        Route::get('{newcomer}', [NewcomerController::class, 'show'])->middleware('permission:newcomers.view')->name('show');
        Route::get('{newcomer}/photo', [NewcomerController::class, 'photo'])->middleware('permission:newcomers.view')->name('photo');
        Route::get('{newcomer}/edit', [NewcomerController::class, 'edit'])->middleware('permission:newcomers.manage')->name('edit');
        Route::put('{newcomer}', [NewcomerController::class, 'update'])->middleware('permission:newcomers.manage')->name('update');
        Route::post('{newcomer}/visits', [NewcomerController::class, 'addVisit'])->middleware('permission:newcomers.manage')->name('visits.store');
        Route::post('{newcomer}/enrol', [NewcomerController::class, 'enrol'])->middleware('permission:newcomers.manage')->name('enrol');
        Route::put('{newcomer}/lessons/{lesson}', [NewcomerController::class, 'updateProgress'])->middleware('permission:newcomers.manage')->name('progress.update');
        Route::post('{newcomer}/promote', [NewcomerController::class, 'promote'])->middleware('permission:newcomers.promote')->name('promote');
        Route::put('{newcomer}/status', [NewcomerController::class, 'setStatus'])->middleware('permission:newcomers.manage')->name('status');
    });

    // Events and the calendar. The venues list (settings) comes before {event}.
    Route::get('operations/calendar', [EventController::class, 'calendar'])->middleware('permission:events.view')->name('events.calendar');
    Route::prefix('operations/events')->name('events.')->group(function () {
        Route::get('/', [EventController::class, 'index'])->middleware('permission:events.view')->name('index');
        Route::get('create', [EventController::class, 'create'])->middleware('permission:events.manage')->name('create');
        Route::post('/', [EventController::class, 'store'])->middleware('permission:events.manage')->name('store');

        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('venues', [EventVenueController::class, 'index'])->name('venues.index');
            Route::post('venues', [EventVenueController::class, 'store'])->name('venues.store');
            Route::put('venues/{venue}', [EventVenueController::class, 'update'])->name('venues.update');
            Route::delete('venues/{venue}', [EventVenueController::class, 'destroy'])->name('venues.destroy');
        });

        Route::get('{event}', [EventController::class, 'show'])->middleware('permission:events.view')->name('show');
        Route::get('{event}/edit', [EventController::class, 'edit'])->middleware('permission:events.manage')->name('edit');
        Route::put('{event}', [EventController::class, 'update'])->middleware('permission:events.manage')->name('update');
        Route::put('{event}/status', [EventController::class, 'setStatus'])->middleware('permission:events.manage')->name('status');
        Route::delete('{event}', [EventController::class, 'destroy'])->middleware('permission:events.manage')->name('destroy');

        Route::post('{event}/participants', [EventParticipantController::class, 'store'])->middleware('permission:events.manage')->name('participants.store');
        Route::delete('{event}/participants', [EventParticipantController::class, 'clear'])->middleware('permission:events.manage')->name('participants.clear');
        Route::delete('{event}/participants/{participant}', [EventParticipantController::class, 'destroy'])->middleware('permission:events.manage')->name('participants.destroy');
    });

    // Committee meetings, their decisions and the actions that follow. Fixed paths come before {meeting}.
    Route::prefix('operations/meetings')->name('meetings.')->group(function () {
        Route::get('/', [MeetingController::class, 'index'])->middleware('permission:meetings.view')->name('index');
        Route::get('create', [MeetingController::class, 'create'])->middleware('permission:meetings.manage')->name('create');
        Route::post('/', [MeetingController::class, 'store'])->middleware('permission:meetings.manage')->name('store');

        Route::get('{meeting}', [MeetingController::class, 'show'])->middleware('permission:meetings.view')->name('show');
        Route::get('{meeting}/edit', [MeetingController::class, 'edit'])->middleware('permission:meetings.manage')->name('edit');
        Route::put('{meeting}', [MeetingController::class, 'update'])->middleware('permission:meetings.manage')->name('update');
        Route::put('{meeting}/status', [MeetingController::class, 'setStatus'])->middleware('permission:meetings.manage')->name('status');
        Route::put('{meeting}/minutes', [MeetingController::class, 'updateMinutes'])->middleware('permission:meetings.manage')->name('minutes');
        Route::delete('{meeting}', [MeetingController::class, 'destroy'])->middleware('permission:meetings.manage')->name('destroy');

        Route::post('{meeting}/attendees/committee', [MeetingController::class, 'addCommitteeAttendees'])->middleware('permission:meetings.manage')->name('attendees.committee');
        Route::post('{meeting}/attendees', [MeetingController::class, 'storeAttendee'])->middleware('permission:meetings.manage')->name('attendees.store');
        Route::put('{meeting}/attendees/{attendee}', [MeetingController::class, 'updateAttendee'])->middleware('permission:meetings.manage')->name('attendees.update');
        Route::delete('{meeting}/attendees/{attendee}', [MeetingController::class, 'destroyAttendee'])->middleware('permission:meetings.manage')->name('attendees.destroy');

        Route::post('{meeting}/decisions', [MeetingDecisionController::class, 'store'])->middleware('permission:meetings.manage')->name('decisions.store');
    });

    Route::get('operations/decisions', [MeetingDecisionController::class, 'index'])->middleware('permission:meetings.view')->name('decisions.index');
    Route::put('operations/decisions/{decision}', [MeetingDecisionController::class, 'update'])->middleware('permission:meetings.manage')->name('decisions.update');
    Route::delete('operations/decisions/{decision}', [MeetingDecisionController::class, 'destroy'])->middleware('permission:meetings.manage')->name('decisions.destroy');
    Route::post('operations/decisions/{decision}/actions', [MeetingActionController::class, 'store'])->middleware('permission:meetings.manage')->name('actions.store');

    Route::get('operations/actions', [MeetingActionController::class, 'index'])->middleware('permission:meetings.view')->name('actions.index');
    Route::put('operations/actions/{action}', [MeetingActionController::class, 'update'])->middleware('permission:meetings.manage')->name('actions.update');
    Route::delete('operations/actions/{action}', [MeetingActionController::class, 'destroy'])->middleware('permission:meetings.manage')->name('actions.destroy');

    // Who serves on each committee, term by term.
    Route::prefix('ministry/committees')->name('committees.')->group(function () {
        Route::get('/', [CommitteeMemberController::class, 'overview'])->middleware('permission:committees.view')->name('overview');
        Route::get('{committee}', [CommitteeMemberController::class, 'show'])->middleware('permission:committees.view')->name('show');
        Route::put('{committee}/rules', [CommitteeMemberController::class, 'updateRules'])->middleware('permission:committees.manage')->name('rules');
        Route::post('{committee}/members', [CommitteeMemberController::class, 'store'])->middleware('permission:committees.manage')->name('members.store');
    });
    Route::prefix('ministry/committee-terms/{membership}')->name('committees.terms.')->middleware('permission:committees.manage')->group(function () {
        Route::put('/', [CommitteeMemberController::class, 'update'])->name('update');
        Route::post('renew', [CommitteeMemberController::class, 'renew'])->name('renew');
        Route::put('end', [CommitteeMemberController::class, 'end'])->name('end');
        Route::delete('/', [CommitteeMemberController::class, 'destroy'])->name('destroy');
    });

    // Modules still to be built: each is a "coming soon" page with its menu link, named e.g. finance.giving.
    foreach (config('modules') as $section => $module) {
        foreach ($module['pages'] as $slug => [$title, $description]) {
            Route::inertia("{$section}/{$slug}", 'coming-soon', [
                'section' => $module['title'], 'title' => $title, 'description' => $description, 'path' => "/{$section}/{$slug}",
            ])->middleware('permission:members.view')->name("{$section}.{$slug}");
        }
    }

    // PCG presbyteries and districts: a reference open to everyone signed in, edited by those who manage settings.
    Route::get('presbyteries', [PresbyteryController::class, 'index'])->name('presbyteries.index');
    Route::middleware('permission:settings.manage')->prefix('presbyteries')->name('presbyteries.')->group(function () {
        Route::post('/', [PresbyteryController::class, 'store'])->name('store');
        Route::put('{presbytery}', [PresbyteryController::class, 'update'])->name('update');
        Route::post('{presbytery}/districts', [PresbyteryController::class, 'storeDistrict'])->name('districts.store');
        Route::put('{presbytery}/districts/{district}', [PresbyteryController::class, 'updateDistrict'])->name('districts.update');
        Route::delete('{presbytery}/districts/{district}', [PresbyteryController::class, 'destroyDistrict'])->name('districts.destroy');
    });
    Route::get('locations/towns', [LocationController::class, 'towns'])->name('locations.towns');

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

    // Communion: the notes of speaking to members before it (own permission), and who received it at each service.
    // The speaking routes come before {service}.
    Route::prefix('communion/speaking')->name('speaking.')->group(function () {
        Route::get('/', [SpeakingController::class, 'index'])->middleware('permission:speaking.view')->name('index');
        Route::get('create', [SpeakingController::class, 'create'])->middleware('permission:speaking.manage')->name('create');
        Route::post('/', [SpeakingController::class, 'store'])->middleware('permission:speaking.manage')->name('store');
        Route::get('{note}', [SpeakingController::class, 'show'])->middleware('permission:speaking.view')->name('show');
        Route::get('{note}/edit', [SpeakingController::class, 'edit'])->middleware('permission:speaking.manage')->name('edit');
        Route::put('{note}', [SpeakingController::class, 'update'])->middleware('permission:speaking.manage')->name('update');
        Route::delete('{note}', [SpeakingController::class, 'destroy'])->middleware('permission:speaking.manage')->name('destroy');
    });

    Route::prefix('communion')->name('communion.')->group(function () {
        Route::get('/', [CommunionController::class, 'index'])->middleware('permission:communion.view')->name('index');
        Route::get('create', [CommunionController::class, 'create'])->middleware('permission:communion.manage')->name('create');
        Route::post('/', [CommunionController::class, 'store'])->middleware('permission:communion.manage')->name('store');
        Route::get('{service}', [CommunionController::class, 'show'])->middleware('permission:communion.view')->name('show');
        Route::get('{service}/edit', [CommunionController::class, 'edit'])->middleware('permission:communion.manage')->name('edit');
        Route::put('{service}', [CommunionController::class, 'update'])->middleware('permission:communion.manage')->name('update');
        Route::delete('{service}', [CommunionController::class, 'destroy'])->middleware('permission:communion.manage')->name('destroy');
        Route::post('{service}/communicants', [CommunionController::class, 'communicants'])->middleware('permission:communion.manage')->name('communicants');
        Route::post('{service}/attendees', [CommunionController::class, 'addAttendee'])->middleware('permission:communion.manage')->name('attendees.store');
        Route::put('{service}/attendance', [CommunionController::class, 'attendance'])->middleware('permission:communion.manage')->name('attendance');
        Route::delete('{service}/attendees/{attendee}', [CommunionController::class, 'removeAttendee'])->middleware('permission:communion.manage')->name('attendees.destroy');
    });

    // Requests members make from the portal. Who sees which type is decided in the controller (Church Settings routing).
    Route::prefix('people/requests')->name('requests.')->group(function () {
        Route::get('/', [MemberRequestController::class, 'index'])->name('index');
        Route::get('{memberRequest}', [MemberRequestController::class, 'show'])->name('show');
        Route::post('{memberRequest}/review', [MemberRequestController::class, 'review'])->name('review');
        Route::post('{memberRequest}/decide', [MemberRequestController::class, 'decide'])->name('decide');
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

        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('neighbourhoods', [NeighbourhoodController::class, 'index'])->name('neighbourhoods.index');

            Route::get('service-groups', [ServiceGroupController::class, 'index'])->name('service-groups.index');
            Route::post('service-groups', [ServiceGroupController::class, 'store'])->name('service-groups.store');
            Route::put('service-groups/{group}', [ServiceGroupController::class, 'update'])->name('service-groups.update');
            Route::delete('service-groups/{group}', [ServiceGroupController::class, 'destroy'])->name('service-groups.destroy');

            Route::get('occupations', [OccupationController::class, 'index'])->name('occupations.index');
            Route::post('occupations', [OccupationController::class, 'store'])->name('occupations.store');
            Route::put('occupations/categories', [OccupationController::class, 'renameCategory'])->name('occupations.categories.update');
            Route::put('occupations/{occupation}', [OccupationController::class, 'update'])->name('occupations.update');
            Route::delete('occupations/{occupation}', [OccupationController::class, 'destroy'])->name('occupations.destroy');

            Route::get('committees', [CommitteeController::class, 'index'])->name('committees.index');
            Route::post('committees', [CommitteeController::class, 'store'])->name('committees.store');
            Route::put('committees/{committee}', [CommitteeController::class, 'update'])->name('committees.update');
            Route::delete('committees/{committee}', [CommitteeController::class, 'destroy'])->name('committees.destroy');

            Route::get('service-positions', [ServicePositionController::class, 'index'])->name('service-positions.index');
            Route::post('service-positions', [ServicePositionController::class, 'store'])->name('service-positions.store');
            Route::put('service-positions/{position}', [ServicePositionController::class, 'update'])->name('service-positions.update');
            Route::delete('service-positions/{position}', [ServicePositionController::class, 'destroy'])->name('service-positions.destroy');

            Route::post('cities', [NeighbourhoodController::class, 'storeCity'])->name('cities.store');
            Route::put('cities/{city}', [NeighbourhoodController::class, 'updateCity'])->name('cities.update');
            Route::delete('cities/{city}', [NeighbourhoodController::class, 'destroyCity'])->name('cities.destroy');
            Route::post('cities/{city}/neighbourhoods', [NeighbourhoodController::class, 'storeNeighbourhoods'])->name('cities.neighbourhoods.store');
            Route::put('cities/{city}/neighbourhoods/{neighbourhood}', [NeighbourhoodController::class, 'updateNeighbourhood'])->name('cities.neighbourhoods.update');
            Route::delete('cities/{city}/neighbourhoods/{neighbourhood}', [NeighbourhoodController::class, 'destroyNeighbourhood'])->name('cities.neighbourhoods.destroy');
        });

        Route::get('towns', [TownController::class, 'index'])->middleware('permission:settings.manage')->name('towns.index');
        Route::post('towns/refresh', [TownController::class, 'refresh'])->middleware('permission:settings.manage')->name('towns.refresh');
    });
});

require __DIR__.'/settings.php';
