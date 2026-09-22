<?php

use App\Jobs\FetchSportsDataJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks — Parlay Tracker
|--------------------------------------------------------------------------
|
| FetchSportsDataJob se ejecuta cada minuto para consultar la API deportiva,
| actualizar los eventos en vivo y disparar la evaluación de picks con
| broadcast WebSocket hacia Angular.
|
| Para activar el scheduler en desarrollo, ejecuta:
|   php artisan schedule:work
|
| En producción (Hetzner) añadir al crontab del servidor:
|   * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
|
*/

Schedule::job(new FetchSportsDataJob, 'sports-data')
    ->everyMinute()
    ->name('fetch-sports-data')
    ->withoutOverlapping(5)  // No solapar si tarda más de 5 minutos
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Scheduler: FetchSportsDataJob falló');
    });
