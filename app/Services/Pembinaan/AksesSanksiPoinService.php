<?php

namespace App\Services\Pembinaan;

use App\Models\Pengguna;
use App\Models\SanksiPoinSiswa;
use Illuminate\Database\Eloquent\Builder;

class AksesSanksiPoinService
{
    public function terapkanCakupan(Builder $query, Pengguna $pengguna): Builder
    {
        if ($this->aksesLuas($pengguna)) {
            return $query;
        }

        $kelasIds = $pengguna->kelasWaliIds();
        $siswaIds = $pengguna->siswaWaliIds();
        $pegawaiId = (int) ($pengguna->pegawai_id ?? 0);

        if ($kelasIds === [] && $siswaIds === [] && $pegawaiId <= 0) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($kelasIds, $siswaIds, $pegawaiId) {
            if ($pegawaiId > 0) {
                $query->where('petugas_pegawai_id', $pegawaiId);
            }

            if ($siswaIds !== []) {
                $metode = $pegawaiId > 0 ? 'orWhereIn' : 'whereIn';
                $query->{$metode}('siswa_id', $siswaIds);
            }

            if ($kelasIds !== []) {
                $metode = $pegawaiId > 0 || $siswaIds !== [] ? 'orWhereHas' : 'whereHas';
                $query->{$metode}('siswa.anggotaKelas', fn (Builder $query) => $query
                    ->whereIn('kelas_id', $kelasIds)
                    ->whereColumn('anggota_kelas.tahun_pelajaran_id', 'sanksi_poin_siswa.tahun_pelajaran_id'));
            }
        });
    }

    public function bolehLihat(?Pengguna $pengguna, SanksiPoinSiswa $sanksi): bool
    {
        if (! $pengguna) {
            return false;
        }

        if ($this->aksesLuas($pengguna)) {
            return true;
        }

        return (int) $sanksi->petugas_pegawai_id === (int) $pengguna->pegawai_id
            || in_array((int) $sanksi->siswa_id, $pengguna->siswaWaliIds(), true)
            || $sanksi->siswa()->whereHas('anggotaKelas', fn (Builder $query) => $query
                ->where('tahun_pelajaran_id', $sanksi->tahun_pelajaran_id)
                ->whereIn('kelas_id', $pengguna->kelasWaliIds()))->exists();
    }

    public function bolehKelola(?Pengguna $pengguna, SanksiPoinSiswa $sanksi): bool
    {
        return (bool) ($pengguna
            && ! $sanksi->sudahFinal()
            && ($pengguna->memilikiIzin('poin_siswa.sanksi_kelola')
                || ((int) $pengguna->pegawai_id > 0 && (int) $pengguna->pegawai_id === (int) $sanksi->petugas_pegawai_id)));
    }

    public function aksesLuas(Pengguna $pengguna): bool
    {
        return $pengguna->administrator()
            || $pengguna->memilikiPeran(['pimpinan', 'wakil_pimpinan_kesiswaan', 'bk'])
            || $pengguna->memilikiIzin('poin_siswa.sanksi_kelola');
    }
}
