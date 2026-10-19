<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::post('/sync', [DashboardController::class, 'sync'])->name('sync');

Route::put('/applications/{application}/sections', [DashboardController::class, 'updateSections'])
    ->name('sections.update');
