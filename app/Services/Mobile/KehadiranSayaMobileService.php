<?php

namespace App\Services\Mobile;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class KehadiranSayaMobileService
{
    public function tampilkan(Pengguna $pengguna, array $filter): array
    {
        [$siswa, $pilihanSiswa, $mode] = $this->siswaUntukPengguna(
            $pengguna,
            isset($filter['siswa_id']) ? (int) $filter['siswa_id'] : null,
        );
        $tahunPelajaran = $this->tahunPelajaran($siswa);
        $tahunDipilih = $this->pilihTahunPelajaran(
            $tahunPelajaran,
            isset($filter['tahun_pelajaran_id']) ? (int) $filter['tahun_pelajaran_id'] : null,
        );
        $bulan = filled($filter['bulan'] ?? null)
            ? Carbon::createFromFormat('Y-m-d', $filter['bulan'].'-01')->startOfMonth()
            : now()->startOfMonth();
        $anggotaKelas = $this->anggotaKelas($siswa, $tahunDipilih);
        $riwayat = $tahunDipilih
            ? AbsensiSiswa::query()
                ->where('siswa_id', $siswa->id)
                ->where('tahun_pelajaran_id', $tahunDipilih->id)
                ->whereBetween('tanggal', [
                    $bulan->copy()->startOfMonth()->toDateString(),
                    $bulan->copy()->endOfMonth()->toDateString(),
                ])
                ->latest('tanggal')
                ->latest('id')
                ->get()
            : collect();
        $hariIni = AbsensiSiswa::query()
            ->where('siswa_id', $siswa->id)
            ->whereDate('tanggal', now()->toDateString())
            ->latest('id')
            ->first();

        return [
            'mode' => $mode,
            'siswa' => $this->ringkasSiswa($siswa),
            'pilihan_siswa' => $pilihanSiswa
                ->map(fn (Siswa $item) => $this->ringkasSiswa($item))
                ->values()
                ->all(),
            'tahun_pelajaran' => $tahunPelajaran
                ->map(fn (TahunPelajaran $item) => $this->ringkasTahun($item))
                ->values()
                ->all(),
            'tahun_pelajaran_dipilih' => $tahunDipilih
                ? $this->ringkasTahun($tahunDipilih)
                : null,
            'kelas' => $anggotaKelas ? [
                'id' => (int) $anggotaKelas->kelas_id,
                'nama' => $anggotaKelas->kelas?->nama ?? '-',
                'tingkat' => (int) ($anggotaKelas->kelas?->tingkat ?? 0),
                'nomor_absen' => $anggotaKelas->nomor_absen,
                'status_keanggotaan' => $anggotaKelas->status_keanggotaan,
            ] : null,
            'filter' => [
                'siswa_id' => (int) $siswa->id,
                'tahun_pelajaran_id' => $tahunDipilih ? (int) $tahunDipilih->id : null,
                'bulan' => $bulan->format('Y-m'),
            ],
            'bulan_label' => $bulan->copy()->locale('id')->translatedFormat('F Y'),
            'hari_ini' => $this->ringkasHariIni($hariIni),
            'ringkasan' => $this->ringkasan($riwayat),
            'riwayat' => $riwayat
                ->map(fn (AbsensiSiswa $item) => $this->ringkasAbsensi($item))
                ->values()
                ->all(),
            'pesan_kosong' => $tahunDipilih
                ? 'Belum ada catatan kehadiran pada bulan ini.'
                : 'Siswa belum memiliki riwayat penempatan kelas.',
        ];
    }

    public function ringkasanBeranda(Pengguna $pengguna, Carbon $hariIni): ?array
    {
        try {
            [$siswa] = $this->siswaUntukPengguna($pengguna, null);
        } catch (HttpException) {
            return null;
        }

        $awalBulan = $hariIni->copy()->startOfMonth()->toDateString();
        $akhirBulan = $hariIni->copy()->endOfMonth()->toDateString();
        $queryBulan = AbsensiSiswa::query()
            ->where('siswa_id', $siswa->id)
            ->whereBetween('tanggal', [$awalBulan, $akhirBulan]);
        $jumlahStatus = (clone $queryBulan)
            ->selectRaw('status_kehadiran, count(*) as jumlah')
            ->groupBy('status_kehadiran')
            ->pluck('jumlah', 'status_kehadiran');
        $presensiHariIni = (clone $queryBulan)
            ->whereDate('tanggal', $hariIni->toDateString())
            ->latest('id')
            ->first();

        return [
            'hari_ini' => $this->ringkasHariIni($presensiHariIni),
            'bulan_ini' => [
                'label' => $hariIni->copy()->locale('id')->translatedFormat('F Y'),
                'total_catatan' => (int) $jumlahStatus->sum(),
                'hadir' => (int) ($jumlahStatus['hadir'] ?? 0),
                'sakit' => (int) ($jumlahStatus['sakit'] ?? 0),
                'izin' => (int) ($jumlahStatus['izin'] ?? 0),
                'dinas_luar' => 0,
                'cuti' => 0,
                'alfa' => (int) ($jumlahStatus['alfa'] ?? 0),
                'terlambat' => (clone $queryBulan)->where('menit_terlambat', '>', 0)->count(),
                'pulang_cepat' => (clone $queryBulan)->where('menit_pulang_cepat', '>', 0)->count(),
            ],
        ];
    }

    private function siswaUntukPengguna(Pengguna $pengguna, ?int $siswaId): array
    {
        if ($pengguna->akunSiswa()) {
            $siswa = $pengguna->siswa;
            abort_unless($siswa, 403);
            abort_if($siswaId !== null && $siswaId !== (int) $siswa->id, 403);

            return [$siswa, collect([$siswa]), 'siswa'];
        }

        $orangTua = $pengguna->orangTuaWali()
            ->with(['siswa' => fn ($query) => $query->orderBy('nama_lengkap')])
            ->first();
        abort_unless($orangTua, 403);
        $pilihan = $orangTua->siswa;
        abort_if($pilihan->isEmpty(), 403, 'Belum ada siswa yang terhubung dengan akun orang tua.');
        $siswa = $siswaId !== null
            ? $pilihan->firstWhere('id', $siswaId)
            : ($pilihan->firstWhere('id', $orangTua->siswa_acuan_username_id) ?: $pilihan->first());
        abort_unless($siswa, 403);

        return [$siswa, $pilihan, 'orang_tua'];
    }

    private function tahunPelajaran(Siswa $siswa): Collection
    {
        $ids = AnggotaKelas::query()
            ->where('siswa_id', $siswa->id)
            ->pluck('tahun_pelajaran_id')
            ->merge(AbsensiSiswa::query()
                ->where('siswa_id', $siswa->id)
                ->pluck('tahun_pelajaran_id'))
            ->filter()
            ->unique();

        return TahunPelajaran::query()
            ->whereIn('id', $ids)
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get(['id', 'nama', 'aktif', 'tanggal_mulai']);
    }

    private function pilihTahunPelajaran(Collection $pilihan, ?int $tahunId): ?TahunPelajaran
    {
        if ($tahunId !== null) {
            $tahun = $pilihan->firstWhere('id', $tahunId);
            abort_unless($tahun, 403);

            return $tahun;
        }

        return $pilihan->firstWhere('aktif', true) ?: $pilihan->first();
    }

    private function anggotaKelas(Siswa $siswa, ?TahunPelajaran $tahun): ?AnggotaKelas
    {
        if (! $tahun) {
            return null;
        }

        return AnggotaKelas::query()
            ->with('kelas:id,nama,tingkat')
            ->where('siswa_id', $siswa->id)
            ->where('tahun_pelajaran_id', $tahun->id)
            ->orderByRaw("CASE WHEN status_keanggotaan = 'aktif' THEN 0 ELSE 1 END")
            ->latest('id')
            ->first();
    }

    private function ringkasSiswa(Siswa $siswa): array
    {
        return [
            'id' => (int) $siswa->id,
            'nama' => $siswa->nama_lengkap,
            'nis' => $siswa->nis,
            'nisn' => $siswa->nisn,
        ];
    }

    private function ringkasTahun(TahunPelajaran $tahun): array
    {
        return [
            'id' => (int) $tahun->id,
            'nama' => $tahun->nama,
            'aktif' => (bool) $tahun->aktif,
        ];
    }

    private function ringkasHariIni(?AbsensiSiswa $absensi): array
    {
        return [
            'tercatat' => $absensi !== null,
            'status' => $absensi?->status_kehadiran,
            'label_status' => $absensi ? $this->labelStatus($absensi->status_kehadiran) : 'Belum tercatat',
            'jam_masuk' => $this->formatJam($absensi?->jam_masuk),
            'jam_pulang' => $this->formatJam($absensi?->jam_pulang),
            'menit_terlambat' => (int) ($absensi?->menit_terlambat ?? 0),
            'menit_pulang_cepat' => (int) ($absensi?->menit_pulang_cepat ?? 0),
        ];
    }

    private function ringkasan(Collection $riwayat): array
    {
        $jumlahStatus = $riwayat->countBy('status_kehadiran');
        $hadir = (int) ($jumlahStatus['hadir'] ?? 0);
        $total = $riwayat->count();

        return [
            'total_catatan' => $total,
            'hadir' => $hadir,
            'sakit' => (int) ($jumlahStatus['sakit'] ?? 0),
            'izin' => (int) ($jumlahStatus['izin'] ?? 0),
            'alfa' => (int) ($jumlahStatus['alfa'] ?? 0),
            'terlambat' => $riwayat->where('menit_terlambat', '>', 0)->count(),
            'menit_terlambat' => (int) $riwayat->sum('menit_terlambat'),
            'pulang_cepat' => $riwayat->where('menit_pulang_cepat', '>', 0)->count(),
            'menit_pulang_cepat' => (int) $riwayat->sum('menit_pulang_cepat'),
            'persentase_hadir' => $total > 0 ? round(($hadir / $total) * 100, 1) : 0,
        ];
    }

    private function ringkasAbsensi(AbsensiSiswa $absensi): array
    {
        return [
            'id' => (int) $absensi->id,
            'tanggal' => $absensi->tanggal?->toDateString(),
            'tanggal_label' => $absensi->tanggal?->copy()->locale('id')->translatedFormat('l, d F Y'),
            'status' => $absensi->status_kehadiran,
            'status_label' => $this->labelStatus($absensi->status_kehadiran),
            'jam_masuk' => $this->formatJam($absensi->jam_masuk),
            'jam_pulang' => $this->formatJam($absensi->jam_pulang),
            'status_masuk' => $absensi->status_masuk,
            'status_pulang' => $absensi->status_pulang,
            'menit_terlambat' => (int) ($absensi->menit_terlambat ?? 0),
            'menit_pulang_cepat' => (int) ($absensi->menit_pulang_cepat ?? 0),
            'sumber' => $absensi->sumber,
            'sumber_label' => $this->labelSumber($absensi->sumber),
            'catatan' => $absensi->catatan,
        ];
    }

    private function labelStatus(?string $status): string
    {
        return match ($status) {
            'hadir' => 'Hadir',
            'sakit' => 'Sakit',
            'izin' => 'Izin',
            'alfa' => 'Alfa',
            default => 'Belum tercatat',
        };
    }

    private function labelSumber(?string $sumber): string
    {
        return match ($sumber) {
            'scan_masuk' => 'Scan masuk',
            'scan_pulang' => 'Scan pulang',
            'scan' => 'Mesin scanner',
            'manual' => 'Dicatat petugas',
            'impor' => 'Impor data',
            default => filled($sumber) ? ucfirst(str_replace('_', ' ', $sumber)) : '-',
        };
    }

    private function formatJam(?string $jam): ?string
    {
        return filled($jam) ? substr($jam, 0, 5) : null;
    }
}
