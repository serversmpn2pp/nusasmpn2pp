<?php

use App\Services\Cbt\SelesaikanPengerjaanKedaluwarsaCbtService;
use App\Services\Humas\IngatkanAgendaHumasService;
use App\Services\Humas\IngatkanDokumenHumasService;
use App\Services\Humas\IngatkanKerjaSamaHumasService;
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

Artisan::command('humas:ingatkan-masa-berlaku-dokumen', function (IngatkanDokumenHumasService $service) {
    $jumlah = $service->kirimPengingat();
    $this->info("{$jumlah} pengingat masa berlaku dokumen Humas dikirim.");

    return Command::SUCCESS;
})->purpose('Mengirim pengingat masa berlaku dokumen Humas');

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

Schedule::command('pembinaan:proses-poin-presensi')
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

Schedule::command('humas:ingatkan-masa-berlaku-dokumen')
    ->dailyAt('07:00')
    ->withoutOverlapping();

Artisan::command('humas:ingatkan-agenda', function (IngatkanAgendaHumasService $service) {
    $jumlah = $service->kirimPengingat();
    $this->info("{$jumlah} pengingat agenda dan tindak lanjut Humas baru dikirim.");

    return Command::SUCCESS;
})->purpose('Mengirim pengingat agenda dan batas waktu tindak lanjut Humas');

Schedule::command('humas:ingatkan-agenda')->hourly()->withoutOverlapping();

Artisan::command('humas:ingatkan-mou', function (IngatkanKerjaSamaHumasService $service) {
    $jumlah = $service->kirimPengingat();
    $this->info("{$jumlah} pengingat masa berlaku MoU baru dikirim.");

    return Command::SUCCESS;
})->purpose('Mengirim pengingat MoU mendekati akhir, berakhir hari ini, dan kedaluwarsa');

Schedule::command('humas:ingatkan-mou')->dailyAt('07:10')->withoutOverlapping();
