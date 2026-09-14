<?php

use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PctProcessController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RfaController;
use App\Http\Controllers\RfaImportController;
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
*/

Route::middleware('auth')->group(function () {

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

                Route::delete(
                    '/{user}',
                    [UserController::class, 'destroy']
                )->name('destroy');

            });

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
    | Administration
    |--------------------------------------------------------------------------
    */

    Route::view('/administration', 'placeholder', [
        'pageTitle' => 'Administration',
        'pageDescription' =>
            'Manage system configuration, settings, logs, backup, and administrative functions.',
        'phase' => 'Later Phase',
    ])
        ->middleware('can:governance.view')
        ->name('administration');

});
