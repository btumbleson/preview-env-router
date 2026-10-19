<?php

use Illuminate\Support\Facades\Schedule;

// How the router finds new previews: there's no hook from Cloud, so poll.
// Requires the scheduler to be enabled on the router's Cloud environment.
Schedule::command('previews:sync')
    ->everyMinute()
    ->withoutOverlapping()
    ->when(fn () => filled(config('router.cloud.token')));
