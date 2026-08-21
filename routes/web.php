<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RfaImportController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RfaController;

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

Route::view('/reports', 'placeholder', [
    'pageTitle' => 'Reports',
    'pageDescription' =>
        'Generate RFA monitoring, disposition, processing, and management reports.',
    'phase' => 'Phase 6',
])->name('reports');

Route::view('/pct-process', 'placeholder', [
    'pageTitle' => 'PCT Process',
    'pageDescription' =>
        'Monitor RFA prescribed processing time and identify nearing, on PCT, and beyond PCT cases.',
    'phase' => 'Phase 5',
])->name('pct-process');

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
