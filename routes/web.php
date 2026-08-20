<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| Phase 2 Placeholder Routes
|--------------------------------------------------------------------------
| These routes make the sidebar fully navigable.
| The actual modules will be implemented in later phases.
|--------------------------------------------------------------------------
*/

Route::view('/listing', 'placeholder', [
    'pageTitle' => 'Listing',
    'pageDescription' => 'View and manage imported Request for Assistance records.',
    'phase' => 'Phase 4',
])->name('listing');

Route::view('/reports', 'placeholder', [
    'pageTitle' => 'Reports',
    'pageDescription' => 'Generate RFA monitoring, disposition, processing, and management reports.',
    'phase' => 'Phase 6',
])->name('reports');

Route::view('/pct-process', 'placeholder', [
    'pageTitle' => 'PCT Process',
    'pageDescription' => 'Monitor RFA prescribed processing time and identify nearing, on PCT, and beyond PCT cases.',
    'phase' => 'Phase 5',
])->name('pct-process');

Route::view('/users', 'placeholder', [
    'pageTitle' => 'Users',
    'pageDescription' => 'Manage system users, roles, permissions, and account access.',
    'phase' => 'Later Phase',
])->name('users');

Route::view('/administration', 'placeholder', [
    'pageTitle' => 'Administration',
    'pageDescription' => 'Manage system configuration, settings, logs, backup, and administrative functions.',
    'phase' => 'Later Phase',
])->name('administration');