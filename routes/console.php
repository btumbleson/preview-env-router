<?php

use Illuminate\Support\Facades\Schedule;

// Requires the scheduler to be enabled on the router's Cloud environment.
Schedule::command('previews:sync')
    ->everyTwoMinutes()
    ->withoutOverlapping()
    ->when(fn () => filled(config('router.cloud.token')));
