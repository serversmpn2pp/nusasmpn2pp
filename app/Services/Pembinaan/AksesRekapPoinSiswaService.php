<?php

namespace App\Services\Pembinaan;

use App\Models\Pengguna;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Builder;

class AksesRekapPoinSiswaService
{
    public function terapkanCakupan(
        Builder $query,
        Pengguna $pengguna,
        ?int $tahunPelajaranId = null,
        ?string $cakupan = null,
    ): Builder
    {
        if ($cakupan === 'guru_wali') {
            $siswaWaliIds = $pengguna->siswaWaliIds();

            return $siswaWaliIds === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('id', $siswaWaliIds);
        }

        if ($this->aksesLuas($pengguna)) {
            return $query;
        }

        $kelasWaliIds = $pengguna->kelasWaliIds();
        $siswaWaliIds = $pengguna->siswaWaliIds();

        if ($kelasWaliIds === [] && $siswaWaliIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($kelasWaliIds, $siswaWaliIds, $tahunPelajaranId) {
            if ($siswaWaliIds !== []) {
                $query->whereIn('id', $siswaWaliIds);
            }

            if ($kelasWaliIds !== []) {
                $metode = $siswaWaliIds !== [] ? 'orWhereHas' : 'whereHas';
                $query->{$metode}('anggotaKelas', fn (Builder $query) => $query
                    ->whereIn('kelas_id', $kelasWaliIds)
                    ->where('status_keanggotaan', 'aktif')
                    ->when($tahunPelajaranId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId)));
            }
        });
    }

    public function bolehLihat(
        ?Pengguna $pengguna,
        Siswa $siswa,
        ?int $tahunPelajaranId = null,
        ?string $cakupan = null,
    ): bool
    {
        if (! $pengguna) {
            return false;
        }

        if ($cakupan === 'guru_wali') {
            return in_array((int) $siswa->id, $pengguna->siswaWaliIds(), true);
        }

        if ($this->aksesLuas($pengguna)) {
            return true;
        }

        if (in_array((int) $siswa->id, $pengguna->siswaWaliIds(), true)) {
            return true;
        }

        $kelasWaliIds = $pengguna->kelasWaliIds();
        if ($kelasWaliIds === []) {
            return false;
        }

        return $siswa->anggotaKelas()
            ->whereIn('kelas_id', $kelasWaliIds)
            ->where('status_keanggotaan', 'aktif')
            ->when($tahunPelajaranId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->exists();
    }

    public function aksesLuas(Pengguna $pengguna): bool
    {
        return $pengguna->administrator()
            || $pengguna->memilikiPeran(['pimpinan', 'wakil_pimpinan_kesiswaan', 'bk'])
            || $pengguna->memilikiIzin(['poin_siswa.verifikasi_bk', 'poin_siswa.sanksi_kelola']);
    }
}
