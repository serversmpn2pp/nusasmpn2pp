<?php

namespace App\Services\Cbt;

use App\Models\GuruMataPelajaran;
use App\Models\Pengguna;
use App\Models\UjianCbt;
use Illuminate\Support\Collection;

class CakupanHasilUjianCbtService
{
    public function kelasDapatDilihat(Pengguna $pengguna, UjianCbt $ujianCbt): Collection
    {
        $semuaKelas = $this->semuaKelas($ujianCbt);

        if ($pengguna->memilikiIzin(['cbt.kelola', 'cbt.panitia', 'cbt.terpusat_lihat'])) {
            return $semuaKelas;
        }

        if ($ujianCbt->asesmenKelas()
            && $pengguna->memilikiIzin('cbt.asesmen_kelola')
            && (int) $ujianCbt->dibuat_oleh_pengguna_id === (int) $pengguna->id) {
            return $semuaKelas;
        }

        return $this->kelasGuruMapel($pengguna, $ujianCbt, $semuaKelas);
    }

    public function kelasDapatDiekspor(Pengguna $pengguna, UjianCbt $ujianCbt): Collection
    {
        $semuaKelas = $this->semuaKelas($ujianCbt);

        if ($pengguna->memilikiIzin('cbt.kelola')) {
            return $semuaKelas;
        }

        if ($ujianCbt->asesmenKelas()
            && $pengguna->memilikiIzin('cbt.asesmen_kelola')
            && (int) $ujianCbt->dibuat_oleh_pengguna_id === (int) $pengguna->id) {
            return $semuaKelas;
        }

        return $this->kelasGuruMapel($pengguna, $ujianCbt, $semuaKelas);
    }

    private function semuaKelas(UjianCbt $ujianCbt): Collection
    {
        $ujianCbt->loadMissing('kelasUjianCbt.kelas');

        return $ujianCbt->kelasUjianCbt
            ->filter(fn ($kelasUjian) => $kelasUjian->kelas)
            ->sortBy(fn ($kelasUjian) => $kelasUjian->kelas->nama)
            ->values();
    }

    private function kelasGuruMapel(Pengguna $pengguna, UjianCbt $ujianCbt, Collection $semuaKelas): Collection
    {
        if ($ujianCbt->asesmenKelas()
            || ! $pengguna->pegawai_id
            || ! $pengguna->memilikiIzin('cbt.soal_kelola')) {
            return collect();
        }

        $kelasIds = GuruMataPelajaran::query()
            ->where('pegawai_id', $pengguna->pegawai_id)
            ->where('tahun_pelajaran_id', $ujianCbt->tahun_pelajaran_id)
            ->where('mata_pelajaran_id', $ujianCbt->mata_pelajaran_id)
            ->where('aktif', true)
            ->pluck('kelas_id')
            ->map(fn ($id) => (int) $id);

        return $semuaKelas
            ->filter(fn ($kelasUjian) => $kelasIds->contains((int) $kelasUjian->kelas_id))
            ->values();
    }
}
