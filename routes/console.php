<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler institucional: evaluación diaria de alertas preventivas y vencimientos de mantenimiento (CP-05)
Schedule::command('panol:evaluar-mantenimientos')->dailyAt('07:00');

// Respaldo integral institucional diario de base de datos y almacenamiento (SLA-08, SLA-09)
Schedule::command('app:backup')->dailyAt('02:00')->appendOutputTo(storage_path('logs/backup.log'));
