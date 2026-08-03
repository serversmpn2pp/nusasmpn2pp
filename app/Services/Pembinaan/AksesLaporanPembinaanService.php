<?php

namespace App\Services\Pembinaan;

use App\Models\LaporanPembinaanSiswa;
use App\Models\Pengguna;

class AksesLaporanPembinaanService
{
    public const STATUS_FINAL = ['disahkan', 'ditetapkan_pembinaan', 'tidak_terbukti', 'dibatalkan'];

    public function bolehLihat(?Pengguna $pengguna, LaporanPembinaanSiswa $laporan): bool
    {
        if (! $pengguna) {
            return false;
        }

        if ($this->aksesLuas($pengguna)) {
            return true;
        }

        return (int) $laporan->dibuat_oleh_pengguna_id === (int) $pengguna->id
            || (filled($pengguna->pegawai_id) && (int) $laporan->pelapor_pegawai_id === (int) $pengguna->pegawai_id)
            || in_array((int) $laporan->kelas_id, $pengguna->kelasWaliIds(), true)
            || in_array((int) $laporan->siswa_id, $pengguna->siswaWaliIds(), true);
    }

    public function pastikanBolehLihat(?Pengguna $pengguna, LaporanPembinaanSiswa $laporan): void
    {
        abort_unless($this->bolehLihat($pengguna, $laporan), 403);
    }

    public function bolehKelolaFakta(?Pengguna $pengguna, LaporanPembinaanSiswa $laporan): bool
    {
        return $this->bolehLihat($pengguna, $laporan)
            && ! $this->statusFinal($laporan)
            && ($pengguna?->memilikiIzin(['bk.kelola', 'poin_siswa.lapor', 'poin_siswa.verifikasi_bk']) ?? false);
    }

    public function bolehMencatatKlarifikasi(?Pengguna $pengguna, LaporanPembinaanSiswa $laporan): bool
    {
        return $this->bolehLihat($pengguna, $laporan)
            && ! $this->statusFinal($laporan)
            && ($pengguna?->memilikiIzin('poin_siswa.verifikasi_bk') ?? false);
    }

    public function bolehMenghapusCatatan(?Pengguna $pengguna, ?int $pembuatId): bool
    {
        return (bool) ($pengguna?->administrator()
            || $pengguna?->memilikiIzin(['bk.kelola', 'poin_siswa.verifikasi_bk'])
            || ((int) $pembuatId > 0 && (int) $pembuatId === (int) $pengguna?->id));
    }

    public function statusFinal(LaporanPembinaanSiswa $laporan): bool
    {
        return in_array($laporan->status_verifikasi, self::STATUS_FINAL, true);
    }

    public function aksesLuas(?Pengguna $pengguna): bool
    {
        return (bool) ($pengguna?->administrator()
            || $pengguna?->memilikiPeran(['pimpinan', 'wakil_pimpinan_kesiswaan', 'bk'])
            || $pengguna?->memilikiIzin('poin_siswa.verifikasi_bk'));
    }
}
