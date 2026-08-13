<?php

namespace App\Observers;

use App\Models\Siswa;
use App\Services\Kelas\UrutkanNomorAbsenKelasService;

class SiswaObserver
{
    public function __construct(
        private readonly UrutkanNomorAbsenKelasService $pengurutanNomorAbsen,
    ) {}

    public function updated(Siswa $siswa): void
    {
        if (! $siswa->wasChanged('nama_lengkap')) {
            return;
        }

        $siswa->anggotaKelas()
            ->distinct()
            ->pluck('kelas_id')
            ->each(fn ($kelasId) => $this->pengurutanNomorAbsen->jalankan((int) $kelasId));
    }
}
