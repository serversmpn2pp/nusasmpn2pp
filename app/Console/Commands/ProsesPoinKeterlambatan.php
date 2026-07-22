<?php

namespace App\Console\Commands;

use App\Models\PengaturanAbsensi;
use App\Services\Pembinaan\ProsesPoinKeterlambatanService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ProsesPoinKeterlambatan extends Command
{
    protected $signature = 'pembinaan:proses-poin-keterlambatan
        {--tanggal= : Tanggal absensi format YYYY-MM-DD}
        {--tahun= : ID tahun pelajaran}
        {--kelas= : ID kelas}
        {--paksa : Proses meskipun waktu scan masuk belum ditutup}';

    protected $description = 'Membuat laporan pelanggaran keterlambatan dari rekap absensi siswa';

    public function handle(ProsesPoinKeterlambatanService $proses): int
    {
        $tanggal = CarbonImmutable::parse($this->option('tanggal') ?: now());

        if (! $this->option('tanggal') && ! $this->option('paksa') && ! $this->waktuProsesSudahTiba($tanggal)) {
            $this->components->info('Waktu scan masuk belum ditutup atau pengaturan hari ini tidak aktif.');

            return self::SUCCESS;
        }

        $hasil = $proses->prosesTanggal(
            $tanggal,
            $this->idDariOpsi('tahun'),
            $this->idDariOpsi('kelas'),
            null,
            (bool) $this->option('paksa') || filled($this->option('tanggal')),
        );

        $this->components->info(sprintf(
            'Selesai: %d data, %d laporan baru, %d diperbarui, %d dibatalkan, %d tanpa perubahan.',
            $hasil['total'],
            $hasil['dibuat'],
            $hasil['diperbarui'],
            $hasil['dibatalkan'],
            $hasil['diabaikan'],
        ));

        return self::SUCCESS;
    }

    private function waktuProsesSudahTiba(CarbonImmutable $tanggal): bool
    {
        $hari = [1 => 'senin', 2 => 'selasa', 3 => 'rabu', 4 => 'kamis', 5 => 'jumat', 6 => 'sabtu', 7 => 'minggu'][$tanggal->isoWeekday()];
        $pengaturan = PengaturanAbsensi::query()->where('hari', $hari)->where('aktif', true)->first();

        if (! $pengaturan?->jam_scan_masuk_selesai) {
            return false;
        }

        $batas = CarbonImmutable::parse($tanggal->toDateString().' '.substr($pengaturan->jam_scan_masuk_selesai, 0, 5));

        return now()->greaterThanOrEqualTo($batas);
    }

    private function idDariOpsi(string $nama): ?int
    {
        $nilai = $this->option($nama);

        return is_numeric($nilai) && (int) $nilai > 0 ? (int) $nilai : null;
    }
}
