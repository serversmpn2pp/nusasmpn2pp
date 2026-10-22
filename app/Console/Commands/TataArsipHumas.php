<?php

namespace App\Console\Commands;

use App\Services\Humas\KlasifikasiDokumenHumasService;
use Illuminate\Console\Command;

class TataArsipHumas extends Command
{
    protected $signature = 'humas:tata-arsip {--terapkan : Simpan pemetaan yang pasti tanpa mengonfirmasi klasifikasi}';

    protected $description = 'Pratinjau atau terapkan klasifikasi arsip Humas lama tanpa memindahkan berkas';

    public function handle(KlasifikasiDokumenHumasService $service): int
    {
        $terapkan = (bool) $this->option('terapkan');
        $hasil = $service->tataArsipLama($terapkan);
        $this->info($terapkan ? 'Penataan diterapkan. Berkas dan kategori lama tidak diubah.' : 'Pratinjau saja. Tidak ada data yang diubah.');
        $this->table(array_keys($hasil), [array_values($hasil)]);
        $this->warn('Klasifikasi otomatis tetap memerlukan pemeriksaan petugas.');

        return self::SUCCESS;
    }
}
