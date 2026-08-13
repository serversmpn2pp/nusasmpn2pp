<?php

namespace App\Services\Ibadah;

use App\Models\GuruMataPelajaran;
use App\Models\JadwalPiketGuru;
use App\Models\Pengguna;
use App\Models\TahunPelajaran;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class AksesScanKegiatanIbadah
{
    public function dapatMemindai(
        ?Pengguna $pengguna,
        ?TahunPelajaran $tahunPelajaran = null,
        ?CarbonInterface $waktu = null,
    ): bool {
        if (! $pengguna || ! $pengguna->aktif || ! $pengguna->memilikiIzin('ibadah.scan')) {
            return false;
        }

        if ($pengguna->administrator() || $pengguna->memilikiPeran('wakil_pimpinan_kesiswaan')) {
            return true;
        }

        if (! $pengguna->pegawai_id) {
            return false;
        }

        $tahunPelajaran ??= TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();

        if (! $tahunPelajaran) {
            return false;
        }

        if ($this->guruPendidikanAgamaIslam($pengguna, $tahunPelajaran)) {
            return true;
        }

        $waktu = $waktu ? Carbon::instance($waktu) : now();

        return $this->guruPiketPada($pengguna, $tahunPelajaran, $waktu);
    }

    public function dapatMelihatRekap(
        ?Pengguna $pengguna,
        ?TahunPelajaran $tahunPelajaran = null,
        ?CarbonInterface $tanggal = null,
    ): bool {
        if (! $pengguna || ! $pengguna->aktif || ! $pengguna->memilikiIzin('ibadah.rekap')) {
            return false;
        }

        if ($pengguna->administrator() || $pengguna->memilikiPeran('wakil_pimpinan_kesiswaan')) {
            return true;
        }

        if (! $pengguna->pegawai_id) {
            return false;
        }

        $tahunPelajaran ??= TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();

        if (! $tahunPelajaran) {
            return false;
        }

        if ($this->guruPendidikanAgamaIslam($pengguna, $tahunPelajaran)) {
            return true;
        }

        $tanggal = $tanggal ? Carbon::instance($tanggal) : now();

        return $this->guruPiketPada($pengguna, $tahunPelajaran, $tanggal);
    }

    public function dapatMengoreksi(
        ?Pengguna $pengguna,
        ?TahunPelajaran $tahunPelajaran = null,
        ?CarbonInterface $tanggal = null,
    ): bool {
        if (! $pengguna || ! $pengguna->aktif || ! $pengguna->memilikiIzin('ibadah.koreksi')) {
            return false;
        }

        if ($pengguna->administrator() || $pengguna->memilikiPeran('wakil_pimpinan_kesiswaan')) {
            return true;
        }

        if (! $pengguna->pegawai_id) {
            return false;
        }

        $tahunPelajaran ??= TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();

        if (! $tahunPelajaran) {
            return false;
        }

        if ($this->guruPendidikanAgamaIslam($pengguna, $tahunPelajaran)) {
            return true;
        }

        $tanggal = $tanggal ? Carbon::instance($tanggal) : now();

        return $this->guruPiketPada($pengguna, $tahunPelajaran, $tanggal);
    }

    public function dapatMelihatRingkasanBulanan(
        ?Pengguna $pengguna,
        ?TahunPelajaran $tahunPelajaran = null,
    ): bool {
        if (! $pengguna || ! $pengguna->aktif || ! $pengguna->memilikiIzin('ibadah.rekap')) {
            return false;
        }

        if ($pengguna->administrator() || $pengguna->memilikiPeran('wakil_pimpinan_kesiswaan')) {
            return true;
        }

        if (! $pengguna->pegawai_id) {
            return false;
        }

        $tahunPelajaran ??= TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();

        if (! $tahunPelajaran) {
            return false;
        }

        if ($this->guruPendidikanAgamaIslam($pengguna, $tahunPelajaran)) {
            return true;
        }

        return JadwalPiketGuru::query()
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('pegawai_id', $pengguna->pegawai_id)
            ->where('aktif', true)
            ->exists();
    }

    private function guruPiketPada(Pengguna $pengguna, TahunPelajaran $tahunPelajaran, CarbonInterface $tanggal): bool
    {
        $hari = array_keys(JadwalPiketGuru::DAFTAR_HARI)[$tanggal->dayOfWeekIso - 1] ?? null;

        return $hari && JadwalPiketGuru::query()
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('pegawai_id', $pengguna->pegawai_id)
            ->where('hari', $hari)
            ->where('aktif', true)
            ->exists();
    }

    private function guruPendidikanAgamaIslam(Pengguna $pengguna, TahunPelajaran $tahunPelajaran): bool
    {
        return GuruMataPelajaran::query()
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('pegawai_id', $pengguna->pegawai_id)
            ->where('jenis_penugasan', 'pengampu')
            ->where('aktif', true)
            ->whereHas('mataPelajaran', function ($query) {
                $query->where('aktif', true)
                    ->where(function ($query) {
                        $query->whereRaw('LOWER(nama) LIKE ?', ['%pendidikan agama islam%'])
                            ->orWhereRaw('LOWER(nama) LIKE ?', ['%agama islam%'])
                            ->orWhereRaw('LOWER(kode) LIKE ?', ['%pai%']);
                    });
            })
            ->exists();
    }
}
