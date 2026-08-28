<?php

namespace App\Services\Piket;

use App\Models\GuruMataPelajaran;
use App\Models\JadwalPiketGuru;
use App\Models\Pengguna;
use App\Models\TahunPelajaran;

class GuruPiketHariIniService
{
    public function tahunPelajaranAktif(): TahunPelajaran
    {
        $tahunPelajaran = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id')
            ->first();

        abort_unless($tahunPelajaran, 422, 'Belum ada tahun pelajaran aktif.');

        return $tahunPelajaran;
    }

    public function kodeHariIni(): ?string
    {
        return array_keys(JadwalPiketGuru::DAFTAR_HARI)[now()->dayOfWeekIso - 1] ?? null;
    }

    public function guruMapelAktif(?Pengguna $pengguna, TahunPelajaran $tahunPelajaran): bool
    {
        return (bool) ($pengguna?->pegawai_id && GuruMataPelajaran::query()
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('pegawai_id', $pengguna->pegawai_id)
            ->where('jenis_penugasan', 'pengampu')
            ->where('aktif', true)
            ->exists());
    }

    public function jadwalHariIni(?Pengguna $pengguna, TahunPelajaran $tahunPelajaran): ?JadwalPiketGuru
    {
        $kodeHari = $this->kodeHariIni();
        if (! $pengguna?->pegawai_id || ! $kodeHari || ! $this->guruMapelAktif($pengguna, $tahunPelajaran)) {
            return null;
        }

        return JadwalPiketGuru::query()
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('pegawai_id', $pengguna->pegawai_id)
            ->where('hari', $kodeHari)
            ->where('aktif', true)
            ->first();
    }

    public function pastikanSedangPiket(?Pengguna $pengguna, TahunPelajaran $tahunPelajaran): JadwalPiketGuru
    {
        $jadwal = $this->jadwalHariIni($pengguna, $tahunPelajaran);

        abort_unless($jadwal, 403, 'Pencatatan hanya dapat dilakukan oleh guru yang sedang bertugas piket hari ini.');

        return $jadwal;
    }
}
