<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('attendance:prune-location')->daily()->withoutOverlapping();
// The command owns a PostgreSQL session lock. A cache overlap lock could survive a
// killed worker for 30 minutes and prevent prompt recovery after a restart.
Schedule::command('attendance:process-closures')->everyMinute();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
