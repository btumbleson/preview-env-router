<?php

use App\Http\Controllers\DashboardController;
use App\Http\Middleware\RequireDashboardPassword;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)
    ->middleware(RequireDashboardPassword::class)
    ->name('dashboard');
