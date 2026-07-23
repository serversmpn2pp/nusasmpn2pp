<?php

namespace App\Services\Pembinaan;

use App\Models\AnggotaKelas;
use App\Models\AturanSanksiPoin;
use App\Models\LaporanPembinaanSiswa;
use App\Models\SanksiPoinSiswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MonitoringPoinSiswaService
{
    public function aturanAktif(): Collection
    {
        return AturanSanksiPoin::query()
            ->where('aktif', true)
            ->orderBy('batas_poin')
            ->get();
    }

    public function indikator(int $totalPoin, int $laporanMenunggu, int $sanksiAktif, Collection $aturan): array
    {
        $totalPoin = max(0, $totalPoin);
        $aturanBerikutnya = $aturan->first(fn (AturanSanksiPoin $item) => $item->batas_poin > $totalPoin);
        $jarak = $aturanBerikutnya ? max(0, $aturanBerikutnya->batas_poin - $totalPoin) : null;
        $mendekati = $aturanBerikutnya
            && $totalPoin > 0
            && $jarak <= max(10, (int) ceil($aturanBerikutnya->batas_poin * 0.2));

        if ($sanksiAktif > 0) {
            $kode = 'sanksi_aktif';
            $label = 'Sanksi perlu ditindaklanjuti';
            $kelas = 'badge badge-danger';
        } elseif (! $aturanBerikutnya && $totalPoin > 0) {
            $kode = 'ambang_tertinggi';
            $label = 'Ambang tertinggi tercapai';
            $kelas = 'badge badge-danger';
        } elseif ($mendekati) {
            $kode = 'mendekati_sanksi';
            $label = 'Mendekati ambang sanksi';
            $kelas = 'badge badge-warning';
        } elseif ($laporanMenunggu > 0) {
            $kode = 'menunggu_verifikasi';
            $label = 'Menunggu verifikasi';
            $kelas = 'badge badge-warning';
        } elseif ($totalPoin > 0) {
            $kode = 'terpantau';
            $label = 'Dalam pemantauan';
            $kelas = 'badge badge-info';
        } else {
            $kode = 'terkendali';
            $label = 'Belum memiliki poin';
            $kelas = 'badge badge-active';
        }

        $persentase = $aturanBerikutnya
            ? min(100, (int) round(($totalPoin / max(1, $aturanBerikutnya->batas_poin)) * 100))
            : ($totalPoin > 0 ? 100 : 0);

        return compact(
            'kode',
            'label',
            'kelas',
            'aturanBerikutnya',
            'jarak',
            'persentase',
        );
    }

    public function ringkasan(Collection $siswaIds, ?int $tahunPelajaranId, Collection $aturan): array
    {
        $siswaIds = $siswaIds->map(fn ($id) => (int) $id)->unique()->values();
        if ($siswaIds->isEmpty()) {
            return [
                'total_siswa' => 0,
                'siswa_berpoin' => 0,
                'mendekati_sanksi' => 0,
                'laporan_menunggu' => 0,
                'sanksi_aktif' => 0,
            ];
        }

        $saldo = $this->saldoPoinPerSiswa($siswaIds, $tahunPelajaranId);
        $laporanMenunggu = $this->laporanMenungguPerSiswa($siswaIds, $tahunPelajaranId);
        $sanksiAktif = $this->sanksiAktifPerSiswa($siswaIds, $tahunPelajaranId);

        $mendekati = $siswaIds->filter(function (int $siswaId) use ($saldo, $laporanMenunggu, $sanksiAktif, $aturan) {
            $indikator = $this->indikator(
                (int) $saldo->get($siswaId, 0),
                (int) $laporanMenunggu->get($siswaId, 0),
                (int) $sanksiAktif->get($siswaId, 0),
                $aturan,
            );

            return $indikator['kode'] === 'mendekati_sanksi';
        })->count();

        return [
            'total_siswa' => $siswaIds->count(),
            'siswa_berpoin' => $saldo->filter(fn ($poin) => (int) $poin > 0)->count(),
            'mendekati_sanksi' => $mendekati,
            'laporan_menunggu' => $laporanMenunggu->filter(fn ($jumlah) => (int) $jumlah > 0)->count(),
            'sanksi_aktif' => $sanksiAktif->filter(fn ($jumlah) => (int) $jumlah > 0)->count(),
        ];
    }

    public function ringkasanKelas(Collection $siswaIds, ?int $tahunPelajaranId): Collection
    {
        $siswaIds = $siswaIds->map(fn ($id) => (int) $id)->unique()->values();
        if ($siswaIds->isEmpty() || ! $tahunPelajaranId) {
            return collect();
        }

        $saldo = $this->saldoPoinPerSiswa($siswaIds, $tahunPelajaranId);
        $laporanMenunggu = $this->laporanMenungguPerSiswa($siswaIds, $tahunPelajaranId);
        $sanksiAktif = $this->sanksiAktifPerSiswa($siswaIds, $tahunPelajaranId);

        return AnggotaKelas::query()
            ->with('kelas:id,nama,tingkat')
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('status_keanggotaan', 'aktif')
            ->whereIn('siswa_id', $siswaIds)
            ->get()
            ->groupBy('kelas_id')
            ->map(function (Collection $anggota) use ($saldo, $laporanMenunggu, $sanksiAktif) {
                $anggota = $anggota->unique('siswa_id');
                $ids = $anggota->pluck('siswa_id')->map(fn ($id) => (int) $id);

                return [
                    'kelas' => $anggota->first()?->kelas,
                    'jumlah_siswa' => $ids->count(),
                    'siswa_berpoin' => $ids->filter(fn ($id) => (int) $saldo->get($id, 0) > 0)->count(),
                    'total_poin' => $ids->sum(fn ($id) => (int) $saldo->get($id, 0)),
                    'menunggu' => $ids->filter(fn ($id) => (int) $laporanMenunggu->get($id, 0) > 0)->count(),
                    'sanksi_aktif' => $ids->filter(fn ($id) => (int) $sanksiAktif->get($id, 0) > 0)->count(),
                ];
            })
            ->filter(fn (array $item) => $item['kelas'])
            ->sortBy([
                fn (array $a, array $b) => ($a['kelas']->tingkat ?? 0) <=> ($b['kelas']->tingkat ?? 0),
                fn (array $a, array $b) => strnatcasecmp($a['kelas']->nama, $b['kelas']->nama),
            ])
            ->values();
    }

    public function perkembanganBulanan(int $siswaId, ?TahunPelajaran $tahunPelajaran): Collection
    {
        $perubahan = TransaksiPoinSiswa::query()
            ->where('siswa_id', $siswaId)
            ->when($tahunPelajaran, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaran->id))
            ->orderBy('tercatat_pada')
            ->get(['poin', 'tercatat_pada'])
            ->groupBy(fn (TransaksiPoinSiswa $item) => $item->tercatat_pada->format('Y-m'))
            ->map(fn (Collection $items) => (int) $items->sum('poin'));

        $awal = $tahunPelajaran?->tanggal_mulai?->copy()->startOfMonth()
            ?? Carbon::now()->startOfYear();
        $akhir = $tahunPelajaran?->tanggal_selesai?->copy()->startOfMonth()
            ?? $awal->copy()->addMonths(11);
        $saldo = 0;

        return collect(CarbonPeriod::create($awal, '1 month', $akhir))
            ->take(18)
            ->map(function (Carbon $bulan) use (&$saldo, $perubahan) {
                $nilaiPerubahan = (int) $perubahan->get($bulan->format('Y-m'), 0);
                $saldo = max(0, $saldo + $nilaiPerubahan);

                return [
                    'kunci' => $bulan->format('Y-m'),
                    'label' => $bulan->translatedFormat('M Y'),
                    'perubahan' => $nilaiPerubahan,
                    'saldo' => $saldo,
                ];
            });
    }

    public function saldoPoinPerSiswa(Collection $siswaIds, ?int $tahunPelajaranId): Collection
    {
        if ($siswaIds->isEmpty()) {
            return collect();
        }

        return TransaksiPoinSiswa::query()
            ->select('siswa_id', DB::raw('SUM(poin) AS total_poin'))
            ->whereIn('siswa_id', $siswaIds)
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->groupBy('siswa_id')
            ->pluck('total_poin', 'siswa_id')
            ->map(fn ($poin) => max(0, (int) $poin));
    }

    private function laporanMenungguPerSiswa(Collection $siswaIds, ?int $tahunPelajaranId): Collection
    {
        return LaporanPembinaanSiswa::query()
            ->select('siswa_id', DB::raw('COUNT(*) AS jumlah'))
            ->whereIn('siswa_id', $siswaIds)
            ->where('jenis_laporan', 'pelanggaran')
            ->whereNotIn('status_verifikasi', AksesLaporanPembinaanService::STATUS_FINAL)
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->groupBy('siswa_id')
            ->pluck('jumlah', 'siswa_id')
            ->map(fn ($jumlah) => (int) $jumlah);
    }

    private function sanksiAktifPerSiswa(Collection $siswaIds, ?int $tahunPelajaranId): Collection
    {
        return SanksiPoinSiswa::query()
            ->select('siswa_id', DB::raw('COUNT(*) AS jumlah'))
            ->whereIn('siswa_id', $siswaIds)
            ->whereNotIn('status', SanksiPoinSiswa::STATUS_FINAL)
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->groupBy('siswa_id')
            ->pluck('jumlah', 'siswa_id')
            ->map(fn ($jumlah) => (int) $jumlah);
    }
}
