<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('outbox:dispatch')->everyMinute()->withoutOverlapping();
Schedule::command('enquiries:remind')->dailyAt('09:30');
Schedule::command('ops:status')->hourly();
Schedule::command('sessions:roll')->dailyAt('00:30');
