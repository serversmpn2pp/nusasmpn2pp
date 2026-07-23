<?php

namespace App\Console\Commands;

use App\Services\Pembinaan\ProsesPeringatanDiniSiswaService;
use Illuminate\Console\Command;

class ProsesPeringatanDiniSiswa extends Command
{
    protected $signature = 'pembinaan:proses-peringatan-dini {--tahun-pelajaran= : ID tahun pelajaran tertentu}';

    protected $description = 'Mendeteksi dan memperbarui peringatan dini siswa serta notifikasi petugas terkait';

    public function handle(ProsesPeringatanDiniSiswaService $service): int
    {
        $tahunPelajaranId = is_numeric($this->option('tahun-pelajaran'))
            ? (int) $this->option('tahun-pelajaran')
            : null;
        $hasil = $service->proses($tahunPelajaranId);

        $this->info(sprintf(
            'Peringatan dini selesai: %d tahun, %d siswa, %d peringatan baru, %d diperbarui, %d selesai, %d notifikasi.',
            $hasil['tahun_diproses'],
            $hasil['siswa_diproses'],
            $hasil['peringatan_baru'],
            $hasil['peringatan_diperbarui'],
            $hasil['peringatan_diselesaikan'],
            $hasil['notifikasi_terkirim'],
        ));

        return self::SUCCESS;
    }
}
