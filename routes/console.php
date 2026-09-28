<?php

use App\Services\Cbt\SelesaikanPengerjaanKedaluwarsaCbtService;
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

Artisan::command('cbt:selesaikan-kedaluwarsa', function (SelesaikanPengerjaanKedaluwarsaCbtService $service) {
    $jumlah = $service->selesaikanSemua();
    $this->info("{$jumlah} pengerjaan CBT kedaluwarsa ditutup dan dikoreksi otomatis.");

    return Command::SUCCESS;
})->purpose('Menutup pengerjaan CBT yang waktunya habis dan mengoreksi jawaban objektif');

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

Schedule::command('sanctum:prune-expired --hours=24')
    ->daily()
    ->withoutOverlapping();

Schedule::command('cbt:selesaikan-kedaluwarsa')
    ->everyMinute()
    ->withoutOverlapping();
