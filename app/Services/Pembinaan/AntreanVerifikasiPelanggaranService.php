<?php

namespace App\Services\Pembinaan;

use App\Models\LaporanPembinaanSiswa;
use App\Models\Pengguna;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class AntreanVerifikasiPelanggaranService
{
    // Status persetujuan lama tetap dikenali agar laporan lama kembali ke antrean BK.
    public const STATUS_BK = [
        'diajukan',
        'pemeriksaan_bk',
        'perlu_klarifikasi',
        'dikembalikan_bk',
        'menunggu_persetujuan',
        'disetujui_sebagian',
        'perlu_musyawarah',
    ];

    public const STATUS_WAKIL = ['menunggu_pengesahan_wakil'];

    public const STATUS_FINAL = ['disahkan', 'ditetapkan_pembinaan', 'tidak_terbukti', 'dibatalkan'];

    public function queryUntuk(Pengguna $pengguna): Builder
    {
        $query = LaporanPembinaanSiswa::query()->where('status_verifikasi', '!=', 'tidak_perlu');

        if ($this->dapatMemantauSemua($pengguna)
            || $pengguna->memilikiIzin(['poin_siswa.verifikasi_bk', 'poin_siswa.sahkan_wakil'])) {
            return $query;
        }

        return $query->whereRaw('1 = 0');
    }

    public function terapkanJenisAntrean(Builder $query, string $antrean): Builder
    {
        return match ($antrean) {
            'bk' => $query->whereIn('status_verifikasi', self::STATUS_BK),
            'wakil' => $query->whereIn('status_verifikasi', self::STATUS_WAKIL),
            'terlambat' => $query->where(fn (Builder $query) => $this->batasiTerlambat($query)),
            'selesai' => $query->whereIn('status_verifikasi', self::STATUS_FINAL),
            default => $query->whereNotIn('status_verifikasi', self::STATUS_FINAL),
        };
    }

    public function ringkasan(Pengguna $pengguna): array
    {
        $dasar = $this->queryUntuk($pengguna);

        return [
            'aktif' => (clone $dasar)->whereNotIn('status_verifikasi', self::STATUS_FINAL)->count(),
            'bk' => (clone $dasar)->whereIn('status_verifikasi', self::STATUS_BK)->count(),
            'wakil' => (clone $dasar)->whereIn('status_verifikasi', self::STATUS_WAKIL)->count(),
            'terlambat' => (clone $dasar)->where(fn (Builder $query) => $this->batasiTerlambat($query))->count(),
            'selesai' => (clone $dasar)->whereIn('status_verifikasi', self::STATUS_FINAL)->count(),
        ];
    }

    public function lengkapiUntukTampilan(LaporanPembinaanSiswa $laporan, Pengguna $pengguna): LaporanPembinaanSiswa
    {
        [$tahap, $batasHari, $acuan] = $this->tahapBatasDanAcuan($laporan);
        $hariMenunggu = $acuan ? max(0, (int) floor($acuan->diffInHours(now()) / 24)) : 0;
        $batasProses = $laporan->batas_proses_pada;
        $sisaHari = $batasProses
            ? (int) ceil(($batasProses->getTimestamp() - now()->getTimestamp()) / 86400)
            : max(0, $batasHari - $hariMenunggu);
        $terlambat = $batasProses
            ? now()->greaterThanOrEqualTo($batasProses)
            : $batasHari > 0 && $hariMenunggu >= $batasHari;

        $laporan->setAttribute('tahap_aktif', $tahap);
        $laporan->setAttribute('tugas_pengguna', match (true) {
            in_array($laporan->status_verifikasi, self::STATUS_FINAL, true) => 'Proses keputusan selesai',
            in_array($laporan->status_verifikasi, self::STATUS_WAKIL, true) => 'Menunggu pengesahan Wakil Kesiswaan',
            $laporan->status_verifikasi === 'dikembalikan_bk' => 'Dikembalikan oleh Wakil Kesiswaan',
            default => 'Menunggu keputusan BK',
        });
        $laporan->setAttribute('batas_hari', $batasHari);
        $laporan->setAttribute('hari_menunggu', $hariMenunggu);
        $laporan->setAttribute('sisa_hari', $sisaHari);
        $laporan->setAttribute('terlambat_diproses', $terlambat);
        $laporan->setAttribute('kelengkapan_fakta', [
            'kronologi' => filled($laporan->kronologi),
            'lokasi' => filled($laporan->tempat_kejadian),
            'butir' => $laporan->butir_pelanggaran_laporan_count > 0,
            'bukti' => $laporan->bukti_laporan_pembinaan_siswa_count > 0,
            'saksi' => $laporan->saksi_laporan_pembinaan_siswa_count > 0,
            'klarifikasi' => $laporan->klarifikasi_siswa_pembinaan_count > 0,
        ]);

        return $laporan;
    }

    public function dapatMemantauSemua(Pengguna $pengguna): bool
    {
        return $pengguna->administrator()
            || $pengguna->memilikiPeran(['pimpinan', 'wakil_pimpinan_kesiswaan']);
    }

    private function batasiTerlambat(Builder $query): Builder
    {
        $batasBk = max(1, (int) config('pembinaan.batas_hari.pemeriksaan_bk', 2));
        $batasWakil = max(1, (int) config('pembinaan.batas_hari.persetujuan', 2));

        return $query->where(function (Builder $query) use ($batasBk, $batasWakil) {
            $query->where(fn (Builder $query) => $query
                ->whereIn('status_verifikasi', array_merge(self::STATUS_BK, self::STATUS_WAKIL))
                ->whereNotNull('batas_proses_pada')
                ->where('batas_proses_pada', '<=', now()))
                ->orWhere(fn (Builder $query) => $query
                    ->whereIn('status_verifikasi', self::STATUS_BK)
                    ->whereNull('batas_proses_pada')
                    ->where('updated_at', '<=', now()->subDays($batasBk)))
                ->orWhere(fn (Builder $query) => $query
                    ->whereIn('status_verifikasi', self::STATUS_WAKIL)
                    ->whereNull('batas_proses_pada')
                    ->where('updated_at', '<=', now()->subDays($batasWakil)));
        });
    }

    /** @return array{0: int, 1: int, 2: CarbonInterface|null} */
    private function tahapBatasDanAcuan(LaporanPembinaanSiswa $laporan): array
    {
        if (in_array($laporan->status_verifikasi, self::STATUS_BK, true)) {
            return [1, $this->hitungBatasHari($laporan, $laporan->created_at, 2), $laporan->created_at];
        }

        if (in_array($laporan->status_verifikasi, self::STATUS_WAKIL, true)) {
            return [2, $this->hitungBatasHari($laporan, $laporan->updated_at, 2, 'persetujuan'), $laporan->updated_at];
        }

        return [3, 0, $laporan->updated_at];
    }

    private function hitungBatasHari(
        LaporanPembinaanSiswa $laporan,
        ?CarbonInterface $acuan,
        int $nilaiAwal,
        string $kunciKonfigurasi = 'pemeriksaan_bk',
    ): int {
        if ($laporan->batas_proses_pada && $acuan) {
            return max(1, (int) ceil(
                ($laporan->batas_proses_pada->getTimestamp() - $acuan->getTimestamp()) / 86400,
            ));
        }

        return max(1, (int) config('pembinaan.batas_hari.'.$kunciKonfigurasi, $nilaiAwal));
    }
}
