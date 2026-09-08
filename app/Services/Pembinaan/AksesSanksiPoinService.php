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

        $pegawaiId = (int) ($pengguna->pegawai_id ?? 0);

        if ($pegawaiId <= 0) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('petugas_pegawai_id', $pegawaiId);
    }

    public function bolehLihat(?Pengguna $pengguna, SanksiPoinSiswa $sanksi): bool
    {
        if (! $pengguna) {
            return false;
        }

        if ($this->aksesLuas($pengguna)) {
            return true;
        }

        return (int) $pengguna->pegawai_id > 0
            && (int) $sanksi->petugas_pegawai_id === (int) $pengguna->pegawai_id;
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

    public function dapatMembuka(Pengguna $pengguna): bool
    {
        if ($this->aksesLuas($pengguna)) {
            return true;
        }

        return (int) $pengguna->pegawai_id > 0
            && SanksiPoinSiswa::query()
                ->where('petugas_pegawai_id', $pengguna->pegawai_id)
                ->exists();
    }

    public function pastikanDapatMembuka(Pengguna $pengguna): void
    {
        abort_unless($this->dapatMembuka($pengguna), 403);
    }
}
