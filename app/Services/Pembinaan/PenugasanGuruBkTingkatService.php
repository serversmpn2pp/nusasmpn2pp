<?php

namespace App\Services\Pembinaan;

use App\Models\LaporanPembinaanSiswa;
use App\Models\Pengguna;
use App\Models\PenugasanGuruBkTingkat;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Illuminate\Support\Collection;

class PenugasanGuruBkTingkatService
{
    public const DAFTAR_TINGKAT = [
        7 => 'Tingkat 7',
        8 => 'Tingkat 8',
        9 => 'Tingkat 9',
    ];

    public function __construct(private NotifikasiPenggunaService $notifikasi) {}

    public function bolehMemproses(?Pengguna $pengguna, LaporanPembinaanSiswa $laporan): bool
    {
        if (! $pengguna) {
            return false;
        }

        if ($this->bolehMemprosesSemuaTingkat($pengguna)) {
            return true;
        }

        if (! $this->petugasBk($pengguna) || ! $pengguna->pegawai_id) {
            return false;
        }

        $tahunPelajaranId = $laporan->tahun_pelajaran_id;
        if (! $tahunPelajaranId) {
            return false;
        }

        // Saat pembagian belum pernah diatur, alur lama tetap berjalan agar laporan tidak terhenti.
        if (! $this->pembagianAktif((int) $tahunPelajaranId)) {
            return true;
        }

        $tingkat = $this->tingkatLaporan($laporan);
        if (! $tingkat) {
            return false;
        }

        return PenugasanGuruBkTingkat::query()
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('pegawai_id', $pengguna->pegawai_id)
            ->where('tingkat', $tingkat)
            ->where('aktif', true)
            ->exists();
    }

    public function modeBaca(?Pengguna $pengguna, LaporanPembinaanSiswa $laporan): bool
    {
        return (bool) ($pengguna
            && $this->petugasBk($pengguna)
            && ! $this->bolehMemprosesSemuaTingkat($pengguna)
            && ! $this->bolehMemproses($pengguna, $laporan));
    }

    public function bolehMemprosesSemuaTingkat(Pengguna $pengguna): bool
    {
        return $pengguna->administrator()
            || $pengguna->memilikiPeran('wakil_pimpinan_kesiswaan')
            || $pengguna->memilikiIzin('poin_siswa.sahkan_wakil');
    }

    public function petugasBk(Pengguna $pengguna): bool
    {
        return $pengguna->peran === 'bk'
            || $pengguna->memilikiPeran('bk')
            || $pengguna->memilikiIzin('poin_siswa.verifikasi_bk');
    }

    public function pembagianAktif(int $tahunPelajaranId): bool
    {
        return PenugasanGuruBkTingkat::query()
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('aktif', true)
            ->exists();
    }

    public function tingkatLaporan(LaporanPembinaanSiswa $laporan): ?int
    {
        $tingkat = $laporan->relationLoaded('kelas')
            ? $laporan->kelas?->tingkat
            : $laporan->kelas()->value('tingkat');

        if (! is_numeric($tingkat)) {
            $tingkat = $laporan->kelas()->value('tingkat');
        }

        $tingkat = is_numeric($tingkat) ? (int) $tingkat : null;

        return array_key_exists($tingkat, self::DAFTAR_TINGKAT) ? $tingkat : null;
    }

    public function tingkatDitugaskan(Pengguna $pengguna, int $tahunPelajaranId): Collection
    {
        if (! $pengguna->pegawai_id) {
            return collect();
        }

        return PenugasanGuruBkTingkat::query()
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('pegawai_id', $pengguna->pegawai_id)
            ->where('aktif', true)
            ->orderBy('tingkat')
            ->pluck('tingkat')
            ->map(fn ($tingkat) => (int) $tingkat)
            ->values();
    }

    public function penerimaNotifikasi(
        LaporanPembinaanSiswa $laporan,
        ?int $kecualiPenggunaId = null,
        bool $sertakanAdministrator = true,
    ): Collection {
        return $this->penerimaNotifikasiTingkat(
            $laporan->tahun_pelajaran_id ? (int) $laporan->tahun_pelajaran_id : null,
            $this->tingkatLaporan($laporan),
            $kecualiPenggunaId,
            $sertakanAdministrator,
        );
    }

    public function penerimaNotifikasiTingkat(
        ?int $tahunPelajaranId,
        ?int $tingkat,
        ?int $kecualiPenggunaId = null,
        bool $sertakanAdministrator = true,
    ): Collection {
        $tingkatValid = $tingkat && array_key_exists($tingkat, self::DAFTAR_TINGKAT);

        if ($tahunPelajaranId && $this->pembagianAktif((int) $tahunPelajaranId)) {
            if ($tingkatValid) {
                $pegawaiIds = PenugasanGuruBkTingkat::query()
                    ->where('tahun_pelajaran_id', $tahunPelajaranId)
                    ->where('tingkat', $tingkat)
                    ->where('aktif', true)
                    ->pluck('pegawai_id');
                $penerima = $this->notifikasi->penggunaUntukDaftarPegawai($pegawaiIds, $kecualiPenggunaId)
                    ->filter(fn (Pengguna $pengguna) => $this->petugasBk($pengguna));
            } else {
                $penerima = collect();
            }
        } else {
            $penerima = $this->notifikasi->penggunaDenganPeran('bk', $kecualiPenggunaId);
        }

        if ($sertakanAdministrator) {
            $penerima = $penerima->merge(
                $this->notifikasi->penggunaDenganPeran('administrator', $kecualiPenggunaId),
            );
        }

        return $penerima->unique('id')->values();
    }
}
