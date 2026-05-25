<?php

use App\Jobs\ActualizarMetricasDiariasJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new ActualizarMetricasDiariasJob)
    ->dailyAt('01:00')
    ->name('metricas-diarias')
    ->onOneServer();
