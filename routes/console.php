<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('atlas:ledger-check')
    ->dailyAt('03:30')
    ->withoutOverlapping();

Schedule::command('atlas:project-recurrences')
    ->dailyAt('04:00')
    ->withoutOverlapping();

Schedule::command('atlas:fetch-quotes')
    ->weekdays()
    ->at('23:15')
    ->withoutOverlapping();
