<?php

namespace App\Services\Pembinaan;

use App\Models\LaporanPembinaanSiswa;
use App\Models\Pengguna;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class AntreanVerifikasiPelanggaranService
{
    public const STATUS_BK = ['diajukan', 'pemeriksaan_bk', 'perlu_klarifikasi'];

    public const STATUS_PERSETUJUAN = ['menunggu_persetujuan', 'disetujui_sebagian'];

    public const STATUS_FINAL = ['disahkan', 'tidak_terbukti', 'dibatalkan'];

    public function queryUntuk(Pengguna $pengguna): Builder
    {
        $query = LaporanPembinaanSiswa::query()->where('jenis_laporan', 'pelanggaran');

        if ($this->dapatMemantauSemua($pengguna)) {
            return $query;
        }

        $pegawaiId = (int) ($pengguna->pegawai_id ?? 0);
        $bisaBk = $pengguna->memilikiIzin('poin_siswa.verifikasi_bk');
        $bisaMenyetujui = $pegawaiId > 0 && $pengguna->memilikiIzin('poin_siswa.menyetujui');
        $bisaMusyawarah = $pengguna->memilikiIzin('poin_siswa.putus_konflik');

        return $query->where(function (Builder $query) use ($pegawaiId, $bisaBk, $bisaMenyetujui, $bisaMusyawarah) {
            $punyaCakupan = false;

            if ($bisaBk) {
                $query->whereIn('status_verifikasi', self::STATUS_BK);
                $punyaCakupan = true;
            }

            if ($bisaMenyetujui) {
                $metode = $punyaCakupan ? 'orWhere' : 'where';
                $query->{$metode}(function (Builder $query) use ($pegawaiId) {
                    $query->whereIn('status_verifikasi', self::STATUS_PERSETUJUAN)
                        ->where(function (Builder $query) use ($pegawaiId) {
                            $query->where('wali_kelas_pegawai_id', $pegawaiId)
                                ->orWhere('guru_wali_pegawai_id', $pegawaiId);
                        });
                });
                $punyaCakupan = true;
            }

            if ($bisaMusyawarah) {
                $metode = $punyaCakupan ? 'orWhere' : 'where';
                $query->{$metode}(fn (Builder $query) => $this->batasiMusyawarah($query));
                $punyaCakupan = true;
            }

            if (! $punyaCakupan) {
                $query->whereRaw('1 = 0');
            }
        });
    }

    public function terapkanJenisAntrean(Builder $query, string $antrean): Builder
    {
        return match ($antrean) {
            'bk' => $query->whereIn('status_verifikasi', self::STATUS_BK),
            'persetujuan' => $query->whereIn('status_verifikasi', self::STATUS_PERSETUJUAN),
            'musyawarah' => $query->where(fn (Builder $query) => $this->batasiMusyawarah($query)),
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
            'persetujuan' => (clone $dasar)->whereIn('status_verifikasi', self::STATUS_PERSETUJUAN)->count(),
            'musyawarah' => (clone $dasar)->where(fn (Builder $query) => $this->batasiMusyawarah($query))->count(),
            'terlambat' => (clone $dasar)->where(fn (Builder $query) => $this->batasiTerlambat($query))->count(),
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
        $persetujuan = $laporan->persetujuanPelanggaran->keyBy('jenis_persetujuan');
        $pegawaiId = (int) ($pengguna->pegawai_id ?? 0);

        $tugas = match (true) {
            in_array($laporan->status_verifikasi, self::STATUS_BK, true) => 'Pemeriksaan fakta BK',
            $laporan->status_verifikasi === 'perlu_musyawarah' => 'Musyawarah Wakil Kesiswaan',
            $this->memerlukanPengganti($laporan) && $pengguna->memilikiIzin('poin_siswa.putus_konflik') => 'Persetujuan pengganti',
            $pegawaiId > 0 && $pegawaiId === (int) $laporan->wali_kelas_pegawai_id && ! $persetujuan->has('wali_kelas') => 'Persetujuan Wali Kelas',
            $pegawaiId > 0 && $pegawaiId === (int) $laporan->guru_wali_pegawai_id && ! $persetujuan->has('guru_wali') => 'Persetujuan Guru Wali',
            in_array($laporan->status_verifikasi, self::STATUS_PERSETUJUAN, true) => 'Menunggu persetujuan',
            in_array($laporan->status_verifikasi, self::STATUS_FINAL, true) => 'Proses selesai',
            default => 'Monitoring proses',
        };

        $laporan->setAttribute('tahap_aktif', $tahap);
        $laporan->setAttribute('tugas_pengguna', $tugas);
        $laporan->setAttribute('batas_hari', $batasHari);
        $laporan->setAttribute('hari_menunggu', $hariMenunggu);
        $laporan->setAttribute('sisa_hari', $sisaHari);
        $laporan->setAttribute('terlambat_diproses', $terlambat);
        $laporan->setAttribute('memerlukan_pengganti', $this->memerlukanPengganti($laporan));
        $laporan->setAttribute('jumlah_persetujuan_sah', $persetujuan->where('keputusan', 'setuju')->pluck('pegawai_id')->filter()->unique()->count());
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

    public function memerlukanPengganti(LaporanPembinaanSiswa $laporan): bool
    {
        return ! $laporan->wali_kelas_pegawai_id
            || ! $laporan->guru_wali_pegawai_id
            || (int) $laporan->wali_kelas_pegawai_id === (int) $laporan->guru_wali_pegawai_id;
    }

    public function dapatMemantauSemua(Pengguna $pengguna): bool
    {
        return $pengguna->administrator()
            || $pengguna->memilikiPeran(['pimpinan', 'wakil_pimpinan_kesiswaan']);
    }

    private function batasiMusyawarah(Builder $query): Builder
    {
        return $query->where('status_verifikasi', 'perlu_musyawarah')
            ->orWhere(function (Builder $query) {
                $query->whereIn('status_verifikasi', self::STATUS_PERSETUJUAN)
                    ->where(function (Builder $query) {
                        $query->whereNull('wali_kelas_pegawai_id')
                            ->orWhereNull('guru_wali_pegawai_id')
                            ->orWhereColumn('wali_kelas_pegawai_id', 'guru_wali_pegawai_id');
                    });
            });
    }

    private function batasiTerlambat(Builder $query): Builder
    {
        $batasBk = max(1, (int) config('pembinaan.batas_hari.pemeriksaan_bk', 2));
        $batasPersetujuan = max(1, (int) config('pembinaan.batas_hari.persetujuan', 2));
        $batasMusyawarah = max(1, (int) config('pembinaan.batas_hari.musyawarah', 3));

        return $query->where(function (Builder $query) use ($batasBk, $batasPersetujuan, $batasMusyawarah) {
            $query->where(fn (Builder $query) => $query
                ->whereNotNull('batas_proses_pada')
                ->where('batas_proses_pada', '<=', now())
                ->whereNotIn('status_verifikasi', self::STATUS_FINAL))
                ->orWhere(function (Builder $query) use ($batasBk, $batasPersetujuan, $batasMusyawarah) {
                    $query->whereNull('batas_proses_pada')
                        ->where(function (Builder $query) use ($batasBk, $batasPersetujuan, $batasMusyawarah) {
                            $query->where(fn (Builder $query) => $query
                                ->whereIn('status_verifikasi', self::STATUS_BK)
                                ->where('updated_at', '<=', now()->subDays($batasBk)))
                                ->orWhere(fn (Builder $query) => $query
                                    ->whereIn('status_verifikasi', self::STATUS_PERSETUJUAN)
                                    ->where('updated_at', '<=', now()->subDays($batasPersetujuan)))
                                ->orWhere(fn (Builder $query) => $query
                                    ->where('status_verifikasi', 'perlu_musyawarah')
                                    ->where('updated_at', '<=', now()->subDays($batasMusyawarah)));
                        });
                });
        });
    }

    /** @return array{0: int, 1: int, 2: CarbonInterface|null} */
    private function tahapBatasDanAcuan(LaporanPembinaanSiswa $laporan): array
    {
        if (in_array($laporan->status_verifikasi, self::STATUS_BK, true)) {
            return [1, $this->hitungBatasHari($laporan, $laporan->created_at, 'pemeriksaan_bk', 2), $laporan->created_at];
        }

        if (in_array($laporan->status_verifikasi, self::STATUS_PERSETUJUAN, true)) {
            $acuan = $laporan->verifikasiBkPelanggaran->sortByDesc('diverifikasi_pada')->first()?->diverifikasi_pada
                ?? $laporan->updated_at;

            return [2, $this->hitungBatasHari($laporan, $acuan, 'persetujuan', 2), $acuan];
        }

        if ($laporan->status_verifikasi === 'perlu_musyawarah') {
            $acuan = $laporan->persetujuanPelanggaran->sortByDesc('diputuskan_pada')->first()?->diputuskan_pada
                ?? $laporan->updated_at;

            return [2, $this->hitungBatasHari($laporan, $acuan, 'musyawarah', 3), $acuan];
        }

        return [3, 0, $laporan->updated_at];
    }

    private function hitungBatasHari(
        LaporanPembinaanSiswa $laporan,
        ?CarbonInterface $acuan,
        string $kunciKonfigurasi,
        int $nilaiAwal,
    ): int {
        if ($laporan->batas_proses_pada && $acuan) {
            return max(1, (int) ceil(
                ($laporan->batas_proses_pada->getTimestamp() - $acuan->getTimestamp()) / 86400,
            ));
        }

        return max(1, (int) config("pembinaan.batas_hari.{$kunciKonfigurasi}", $nilaiAwal));
    }
}
