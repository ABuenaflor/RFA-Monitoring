<?php

use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\GovernanceController;
use App\Http\Controllers\Admin\ReadinessController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\NotificationFeedController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\PctCycleTimeController;
use App\Http\Controllers\PctProcessController;
use App\Http\Controllers\IntakeReminderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RfaCaseController;
use App\Http\Controllers\RfaController;
use App\Http\Controllers\RfaImportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SeadoAssignmentController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {

    Route::get(
        '/login',
        [AuthenticatedSessionController::class, 'create']
    )->name('login');

    Route::post(
        '/login',
        [AuthenticatedSessionController::class, 'store']
    )->middleware('throttle:login');

});

Route::post(
    '/logout',
    [AuthenticatedSessionController::class, 'destroy']
)
    ->middleware('auth')
    ->name('logout');


/*
|--------------------------------------------------------------------------
| Authenticated Application
|--------------------------------------------------------------------------
|
| Every route below requires a signed-in, active account. Permission gates
| are applied per route with the framework can: middleware — UI visibility
| is never treated as authorization.
|
| auth.session signs out every other session of an account once its
| password changes, so a stolen session dies with the old password.
|
*/

Route::middleware(['auth', 'auth.session'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Own Account
    |--------------------------------------------------------------------------
    |
    | Always reachable, so a user forced to change their password is never
    | locked out of the screen that lets them do it.
    |
    */

    Route::get(
        '/account',
        [ProfileController::class, 'edit']
    )->name('account.edit');

    Route::put(
        '/account',
        [ProfileController::class, 'update']
    )->name('account.update');

    Route::put(
        '/account/password',
        [ProfileController::class, 'updatePassword']
    )->name('account.password');


    /*
    |--------------------------------------------------------------------------
    | Notification Bell
    |--------------------------------------------------------------------------
    |
    | The signed-in user's own alerts only, so no permission is needed.
    |
    */

    Route::get(
        '/notifications/feed',
        [NotificationFeedController::class, 'feed']
    )->name('notifications.feed');

    Route::get(
        '/notifications/{notification}/open',
        [NotificationFeedController::class, 'open']
    )->name('notifications.open');


    /*
    |--------------------------------------------------------------------------
    | My Conference Schedule
    |--------------------------------------------------------------------------
    |
    | Always the signed-in user's own conferences; there is no parameter
    | for viewing anyone else's.
    |
    */

    Route::get(
        '/schedule',
        [ScheduleController::class, 'index']
    )->name('schedule.index');


    /*
    |--------------------------------------------------------------------------
    | Holidays & Events (skipped by the PCT working-day clock)
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/holidays',
        [HolidayController::class, 'index']
    )
        ->middleware('can:pct.view')
        ->name('holidays.index');

    Route::middleware('can:holidays.manage')->group(function () {

        Route::post(
            '/holidays',
            [HolidayController::class, 'store']
        )->name('holidays.store');

        Route::put(
            '/holidays/{holiday}',
            [HolidayController::class, 'update']
        )->name('holidays.update');

        Route::delete(
            '/holidays/{holiday}',
            [HolidayController::class, 'destroy']
        )->name('holidays.destroy');

    });


    /*
    |--------------------------------------------------------------------------
    | SEADO Assignment
    |--------------------------------------------------------------------------
    */

    Route::middleware('can:rfa.assign')->group(function () {

        Route::get(
            '/seado-assignment',
            [SeadoAssignmentController::class, 'index']
        )->name('seado-assignment.index');

        Route::post(
            '/seado-assignment/exclusions',
            [SeadoAssignmentController::class, 'exclude']
        )->name('seado-assignment.exclusions.store');

        Route::delete(
            '/seado-assignment/exclusions/{exclusion}',
            [SeadoAssignmentController::class, 'include']
        )->name('seado-assignment.exclusions.destroy');

        Route::post(
            '/seado-assignment/{rfa}',
            [SeadoAssignmentController::class, 'assign']
        )->name('seado-assignment.assign');

    });


    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/dashboard',
        [DashboardController::class, 'index']
    )
        ->middleware('can:dashboard.view')
        ->name('dashboard');


    /*
    |--------------------------------------------------------------------------
    | CSV Import
    |--------------------------------------------------------------------------
    */

    Route::middleware('can:import.manage')->group(function () {

        Route::get(
            '/imports',
            [RfaImportController::class, 'index']
        )->name('imports.index');

        Route::post(
            '/imports',
            [RfaImportController::class, 'store']
        )->name('imports.store');

    });


    /*
    |--------------------------------------------------------------------------
    | RFA Master Listing
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/listing',
        [RfaController::class, 'index']
    )
        ->middleware('can:rfa.view')
        ->name('listing');


    /*
    |--------------------------------------------------------------------------
    | Missing intake dates reminder
    |--------------------------------------------------------------------------
    |
    | Shown after sign-in until dismissed; dismissing lasts for the session.
    |
    */

    Route::post(
        '/intake-reminder/dismiss',
        [IntakeReminderController::class, 'dismiss']
    )
        ->name('intake-reminder.dismiss');


    /*
    |--------------------------------------------------------------------------
    | Case Management
    |--------------------------------------------------------------------------
    |
    | Each section of a case saves independently, so a permission can be
    | granted for assignment or disposition without opening the whole record.
    |
    */

    Route::prefix('rfas/{rfa}')
        ->name('rfas.')
        ->group(function () {

            Route::get(
                '/',
                [RfaCaseController::class, 'show']
            )
                ->middleware('can:rfa.view')
                ->name('show');

            Route::put(
                '/details',
                [RfaCaseController::class, 'updateDetails']
            )
                ->middleware('can:rfa.manage')
                ->name('details');

            Route::put(
                '/assignment',
                [RfaCaseController::class, 'updateAssignment']
            )
                ->middleware('can:rfa.assign')
                ->name('assignment');

            Route::put(
                '/workflow',
                [RfaCaseController::class, 'updateWorkflow']
            )
                ->middleware('can:rfa.manage')
                ->name('workflow');

            /*
            | The case's SEADO may save these even without
            | rfa.manage; the controller checks.
            */

            Route::put(
                '/intake-dates',
                [RfaCaseController::class, 'updateIntakeDates']
            )
                ->middleware('can:rfa.view')
                ->name('intake-dates');

            Route::put(
                '/conference',
                [RfaCaseController::class, 'updateConference']
            )
                ->middleware('can:rfa.manage')
                ->name('conference');

            Route::put(
                '/disposition',
                [RfaCaseController::class, 'updateDisposition']
            )
                ->middleware('can:rfa.dispose')
                ->name('disposition');

            Route::put(
                '/reopen',
                [RfaCaseController::class, 'reopen']
            )
                ->middleware('can:rfa.dispose')
                ->name('reopen');

            Route::post(
                '/notes',
                [RfaCaseController::class, 'storeNote']
            )
                ->middleware('can:rfa.manage')
                ->name('notes');

        });


    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/reports',
        [ReportController::class, 'index']
    )
        ->middleware('can:reports.view')
        ->name('reports');

    Route::get(
        '/reports/export',
        [ReportController::class, 'exportCsv']
    )
        ->middleware('can:reports.export')
        ->name('reports.export');

    Route::get(
        '/reports/print',
        [ReportController::class, 'print']
    )
        ->middleware('can:reports.export')
        ->name('reports.print');


    /*
    |--------------------------------------------------------------------------
    | PCT Process Monitoring
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/pct-process',
        [PctProcessController::class, 'index']
    )
        ->middleware('can:pct.view')
        ->name('pct-process');

    Route::get(
        '/pct-process/cycle-time',
        [PctCycleTimeController::class, 'index']
    )
        ->middleware('can:pct.view')
        ->name('pct-cycle-time');


    /*
    |--------------------------------------------------------------------------
    | Users & Access Control
    |--------------------------------------------------------------------------
    */

    Route::prefix('users')
        ->name('admin.access.')
        ->group(function () {

            Route::get(
                '/',
                [UserController::class, 'index']
            )
                ->middleware('can:users.view')
                ->name('index');

            Route::middleware('can:users.manage')->group(function () {

                Route::get(
                    '/create',
                    [UserController::class, 'create']
                )->name('create');

                Route::post(
                    '/',
                    [UserController::class, 'store']
                )->name('store');

                Route::get(
                    '/{user}/edit',
                    [UserController::class, 'edit']
                )->name('edit');

                Route::put(
                    '/{user}',
                    [UserController::class, 'update']
                )->name('update');

                Route::patch(
                    '/{user}/status',
                    [UserController::class, 'toggleStatus']
                )->name('toggle-status');

            });

            Route::delete(
                '/{user}',
                [UserController::class, 'destroy']
            )
                ->middleware('can:users.delete')
                ->name('destroy');

        });


    /*
    |--------------------------------------------------------------------------
    | Roles & Permissions
    |--------------------------------------------------------------------------
    */

    Route::prefix('roles')
        ->name('admin.roles.')
        ->middleware('can:roles.manage')
        ->group(function () {

            Route::get(
                '/',
                [RoleController::class, 'index']
            )->name('index');

            Route::get(
                '/create',
                [RoleController::class, 'create']
            )->name('create');

            Route::post(
                '/',
                [RoleController::class, 'store']
            )->name('store');

            Route::get(
                '/{role}/edit',
                [RoleController::class, 'edit']
            )->name('edit');

            Route::put(
                '/{role}',
                [RoleController::class, 'update']
            )->name('update');

            Route::delete(
                '/{role}',
                [RoleController::class, 'destroy']
            )->name('destroy');

        });


    /*
    |--------------------------------------------------------------------------
    | Notifications & Audit
    |--------------------------------------------------------------------------
    |
    | The page itself needs notifications.view. The audit trail section inside
    | it is additionally gated on audit.view by the controller.
    |
    */

    Route::prefix('operations')
        ->name('operations.')
        ->middleware('can:notifications.view')
        ->group(function () {

            Route::get(
                '/',
                [OperationsController::class, 'index']
            )->name('index');

            Route::post(
                '/notifications/read-all',
                [OperationsController::class, 'markAllRead']
            )->name('notifications.read-all');

            Route::post(
                '/notifications/{notification}/read',
                [OperationsController::class, 'markRead']
            )->name('notifications.read');

            Route::delete(
                '/notifications/{notification}',
                [OperationsController::class, 'destroy']
            )->name('notifications.destroy');

            Route::post(
                '/scan',
                [OperationsController::class, 'scan']
            )->name('scan');

        });


    /*
    |--------------------------------------------------------------------------
    | Data Governance
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin/governance')
        ->name('admin.governance.')
        ->middleware('can:governance.view')
        ->group(function () {

            Route::get(
                '/',
                [GovernanceController::class, 'index']
            )->name('index');

            Route::get(
                '/export',
                [GovernanceController::class, 'export']
            )->name('export');

        });


    /*
    |--------------------------------------------------------------------------
    | Database Backups
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin/backups')
        ->name('admin.backups.')
        ->group(function () {

            Route::get(
                '/',
                [BackupController::class, 'index']
            )
                ->middleware('can:backup.view')
                ->name('index');

            Route::middleware('can:backup.manage')->group(function () {

                Route::post(
                    '/',
                    [BackupController::class, 'store']
                )->name('store');

                Route::get(
                    '/{backup}/download',
                    [BackupController::class, 'download']
                )->name('download');

                Route::delete(
                    '/{backup}',
                    [BackupController::class, 'destroy']
                )->name('destroy');

            });

        });


    /*
    |--------------------------------------------------------------------------
    | System Settings
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin/settings')
        ->name('admin.settings.')
        ->middleware('can:settings.manage')
        ->group(function () {

            Route::get(
                '/',
                [SettingsController::class, 'index']
            )->name('index');

            Route::put(
                '/',
                [SettingsController::class, 'update']
            )->name('update');

        });


    /*
    |--------------------------------------------------------------------------
    | Release Readiness
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin/readiness')
        ->name('admin.readiness.')
        ->group(function () {

            Route::get(
                '/',
                [ReadinessController::class, 'index']
            )
                ->middleware('can:readiness.view')
                ->name('index');

            Route::middleware('can:readiness.manage')->group(function () {

                Route::post(
                    '/results',
                    [ReadinessController::class, 'recordResult']
                )->name('results');

                Route::post(
                    '/signoff',
                    [ReadinessController::class, 'signOff']
                )->name('signoff');

            });

        });


    /*
    |--------------------------------------------------------------------------
    | Legacy Administration Link
    |--------------------------------------------------------------------------
    */

    Route::redirect('/administration', '/admin/settings')
        ->name('administration');

});
