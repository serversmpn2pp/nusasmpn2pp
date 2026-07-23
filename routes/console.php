<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('pembinaan:ingatkan-batas-proses')
    ->dailyAt('06:00')
    ->withoutOverlapping();

Schedule::command('pembinaan:proses-poin-keterlambatan')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('pembinaan:proses-peringatan-dini')
    ->dailyAt('05:30')
    ->withoutOverlapping();
