<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RfaImportController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RfaController;
use App\Http\Controllers\PctProcessController;
use App\Http\Controllers\ReportController;

Route::redirect('/', '/dashboard');


/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

Route::get(
    '/dashboard',
    [DashboardController::class, 'index']
)->name('dashboard');


/*
|--------------------------------------------------------------------------
| CSV Import
|--------------------------------------------------------------------------
*/

Route::get(
    '/imports',
    [RfaImportController::class, 'index']
)->name('imports.index');

Route::post(
    '/imports',
    [RfaImportController::class, 'store']
)->name('imports.store');


/*
|--------------------------------------------------------------------------
| Phase Placeholder Pages
|--------------------------------------------------------------------------
*/

Route::get(
    '/listing',
    [RfaController::class, 'index']
)->name('listing');

Route::get(
    '/reports',
    [ReportController::class, 'index']
)->name('reports');

Route::get(
    '/reports/export',
    [ReportController::class, 'exportCsv']
)->name('reports.export');

Route::get(
    '/pct-process',
    [PctProcessController::class, 'index']
)->name('pct-process');

Route::view('/users', 'placeholder', [
    'pageTitle' => 'Users',
    'pageDescription' =>
        'Manage system users, roles, permissions, and account access.',
    'phase' => 'Later Phase',
])->name('users');

Route::view('/administration', 'placeholder', [
    'pageTitle' => 'Administration',
    'pageDescription' =>
        'Manage system configuration, settings, logs, backup, and administrative functions.',
    'phase' => 'Later Phase',
])->name('administration');
