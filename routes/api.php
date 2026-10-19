<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Triggered by a curl in the preview automation's initial deploy commands.
Route::post('/sync', [DashboardController::class, 'syncApi'])
    ->middleware('throttle:10,1')
    ->name('api.sync');
