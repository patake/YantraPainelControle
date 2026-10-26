<?php

use Illuminate\Support\Facades\Schedule;

// Trial vencido vira expirado (R-LIC-3).
Schedule::command('devices:expire-trials')->everyFiveMinutes()->withoutOverlapping();
