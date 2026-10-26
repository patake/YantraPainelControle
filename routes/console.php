<?php

use Illuminate\Support\Facades\Schedule;

// Trial vencido vira expirado (R-LIC-3).
Schedule::command('devices:expire-trials')->everyFiveMinutes()->withoutOverlapping();

// Comandos sem resposta expiram e perdem o payload, que pode ter a senha do Tasy (R-EXA-5).
Schedule::command('commands:expire')->everyMinute()->withoutOverlapping();

// Previews de exames terminados ou com mais de 24 horas (R-EXA-7).
Schedule::command('exams:purge-previews')->hourly()->withoutOverlapping();
