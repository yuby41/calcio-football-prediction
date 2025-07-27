<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule tasks
Schedule::command('matches:auto-finish')
    ->everyTenMinutes()
    ->withoutOverlapping();

Schedule::command('predictions:update-accuracy --force')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

Schedule::command('football:sync-today')
    ->everyTwoHours()
    ->withoutOverlapping();

Schedule::command('football:update-live')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

Schedule::command('ml:predict')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('team:generate-statistics')
    ->dailyAt('02:00')
    ->withoutOverlapping();
