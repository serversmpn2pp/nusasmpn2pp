<?php

namespace App\Observers;

use App\Models\AnggotaKelas;
use App\Services\Kelas\UrutkanNomorAbsenKelasService;

class AnggotaKelasObserver
{
    public function __construct(
        private readonly UrutkanNomorAbsenKelasService $pengurutanNomorAbsen,
    ) {}

    public function created(AnggotaKelas $anggotaKelas): void
    {
        $this->pengurutanNomorAbsen->jalankan($anggotaKelas->kelas_id);
    }

    public function updated(AnggotaKelas $anggotaKelas): void
    {
        if (! $anggotaKelas->wasChanged(['kelas_id', 'siswa_id', 'nomor_absen', 'status_keanggotaan'])) {
            return;
        }

        $kelasSebelumnya = (int) $anggotaKelas->getOriginal('kelas_id');
        $kelasSekarang = (int) $anggotaKelas->kelas_id;

        if ($kelasSebelumnya && $kelasSebelumnya !== $kelasSekarang) {
            $this->pengurutanNomorAbsen->jalankan($kelasSebelumnya);
        }

        if ($kelasSekarang) {
            $this->pengurutanNomorAbsen->jalankan($kelasSekarang);
        }
    }

    public function deleted(AnggotaKelas $anggotaKelas): void
    {
        $this->pengurutanNomorAbsen->jalankan($anggotaKelas->kelas_id);
    }
}
