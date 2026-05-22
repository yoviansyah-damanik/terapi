<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Deteksi anomali API setiap 15 menit
Schedule::command('api:detect-anomalies')->everyFifteenMinutes();

// Backup Database — jadwal & status diambil dari configurations table
$terapiEnabled  = (bool) \App\Helpers\ConfigurationHelper::get('backup.terapi.enabled', false);
$terapiSchedule = \App\Helpers\ConfigurationHelper::get('backup.terapi.schedule', '0 2 * * *');
if ($terapiEnabled && $terapiSchedule) {
    Schedule::command('database:backup terapi')
        ->cron($terapiSchedule)
        ->name('backup-terapi')
        ->withoutOverlapping();
}

$simrsEnabled  = (bool) \App\Helpers\ConfigurationHelper::get('backup.simrs.enabled', false);
$simrsSchedule = \App\Helpers\ConfigurationHelper::get('backup.simrs.schedule', '0 3 * * *');
if ($simrsEnabled && $simrsSchedule) {
    Schedule::command('database:backup simrs')
        ->cron($simrsSchedule)
        ->name('backup-simrs')
        ->withoutOverlapping();
}
