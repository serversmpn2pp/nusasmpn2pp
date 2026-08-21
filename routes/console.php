<?php

use App\Services\Sistem\CadanganDatabaseService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('nusa:cadangkan-database {--otomatis}', function (CadanganDatabaseService $service) {
    try {
        $cadangan = $service->buatCadangan($this->option('otomatis') ? 'otomatis' : 'manual');
        $this->info('Cadangan berhasil dibuat: '.$cadangan['nama_file'].' ('.$cadangan['ukuran_label'].').');

        return Command::SUCCESS;
    } catch (Throwable $exception) {
        $this->error($exception->getMessage());

        return Command::FAILURE;
    }
})->purpose('Membuat cadangan database PostgreSQL NUSA');

if (config('cadangan_database.otomatis_aktif', true)) {
    Schedule::command('nusa:cadangkan-database --otomatis')
        ->dailyAt(config('cadangan_database.jadwal_otomatis', '01:00'))
        ->withoutOverlapping();
}

Schedule::command('pembinaan:ingatkan-batas-proses')
    ->dailyAt('06:00')
    ->withoutOverlapping();

Schedule::command('pembinaan:proses-poin-keterlambatan')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('pembinaan:proses-peringatan-dini')
    ->dailyAt('05:30')
    ->withoutOverlapping();
