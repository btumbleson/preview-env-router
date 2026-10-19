<?php

use App\Http\Controllers\RegisterPreviewController;
use App\Http\Middleware\RequireRouterToken;
use Illuminate\Support\Facades\Route;

Route::post('/previews', RegisterPreviewController::class)
    ->middleware([RequireRouterToken::class, 'throttle:30,1'])
    ->name('previews.register');
