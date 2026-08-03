<?php

namespace App\Services\Pembinaan;

use App\Models\LaporanPembinaanSiswa;
use App\Models\PengaturanBatasProsesPelanggaran;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class PengaturanBatasProsesPelanggaranService
{
    public const TAHAP_PEMERIKSAAN_BK = 'pemeriksaan_bk';

    public function nilaiUntukTahun(?int $tahunPelajaranId): PengaturanBatasProsesPelanggaran
    {
        $pengaturan = $tahunPelajaranId
            ? PengaturanBatasProsesPelanggaran::where('tahun_pelajaran_id', $tahunPelajaranId)->first()
            : null;

        return $pengaturan ?? new PengaturanBatasProsesPelanggaran([
            'tahun_pelajaran_id' => $tahunPelajaranId,
            'batas_hari_pemeriksaan_bk' => (int) config('pembinaan.batas_hari.pemeriksaan_bk', 2),
            'batas_hari_persetujuan' => (int) config('pembinaan.batas_hari.persetujuan', 2),
            'batas_hari_musyawarah' => (int) config('pembinaan.batas_hari.musyawarah', 3),
            'pengingat_hari_sebelum_batas' => 1,
            'notifikasi_pengingat_aktif' => true,
            'notifikasi_terlambat_aktif' => true,
        ]);
    }

    public function tetapkanBatas(
        LaporanPembinaanSiswa $laporan,
        ?string $status = null,
        CarbonInterface|string|null $acuan = null,
    ): LaporanPembinaanSiswa {
        [$tahap, $jumlahHari] = $this->tahapDanJumlahHari(
            $status ?? $laporan->status_verifikasi,
            $laporan->tahun_pelajaran_id,
        );

        if (! $tahap) {
            return $laporan;
        }

        $mulai = $acuan instanceof CarbonInterface
            ? CarbonImmutable::instance($acuan)
            : CarbonImmutable::parse($acuan ?? now());

        $laporan->update([
            'tahap_batas_proses' => $tahap,
            'batas_proses_pada' => $mulai->addDays($jumlahHari),
        ]);

        return $laporan->refresh();
    }

    /** @return array{0: string|null, 1: int} */
    public function tahapDanJumlahHari(string $status, ?int $tahunPelajaranId): array
    {
        $pengaturan = $this->nilaiUntukTahun($tahunPelajaranId);

        return match (true) {
            in_array($status, AntreanVerifikasiPelanggaranService::STATUS_BK, true) => [self::TAHAP_PEMERIKSAAN_BK, max(1, $pengaturan->batas_hari_pemeriksaan_bk)],
            default => [null, 0],
        };
    }
}
