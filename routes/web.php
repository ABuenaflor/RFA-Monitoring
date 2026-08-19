<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
