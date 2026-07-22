<?php

namespace App\Http\Controllers;

use App\Models\AbsensiSiswa;
use App\Models\AbsensiPegawai;
use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\Kelas;
use App\Models\KomponenNilai;
use App\Models\LogScanAbsensi;
use App\Models\LogScanAbsensiPegawai;
use App\Models\LaporanPembinaanSiswa;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\SanksiPoinSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;

class BerandaController extends Controller
{
    public function index()
    {
        $pengguna = auth()->user();

        $hariIni = now();
        $tanggalHariIni = $hariIni->toDateString();
        $tahunPelajaranAktif = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();
        $awalBulan = $hariIni->copy()->startOfMonth();
        $akhirBulan = $hariIni->copy()->endOfMonth();

        if (! $pengguna?->administrator()) {
            return view('beranda.index', $this->dataDashboardPegawai(
                pengguna: $pengguna,
                hariIni: $hariIni,
                tahunPelajaranAktif: $tahunPelajaranAktif,
                awalBulan: $awalBulan,
                akhirBulan: $akhirBulan,
            ));
        }

        $jumlahSiswaAktif = Siswa::query()->where('aktif', true)->count();
        $jumlahPegawaiAktif = Pegawai::query()->where('aktif', true)->count();
        $jumlahSiswaDipantau = $tahunPelajaranAktif
            ? AnggotaKelas::query()
                ->where('tahun_pelajaran_id', $tahunPelajaranAktif->id)
                ->where('status_keanggotaan', 'aktif')
                ->distinct('siswa_id')
                ->count('siswa_id')
            : $jumlahSiswaAktif;

        if ($jumlahSiswaDipantau === 0) {
            $jumlahSiswaDipantau = $jumlahSiswaAktif;
        }

        $ringkasanUtama = [
            'siswa_aktif' => $jumlahSiswaAktif,
            'pegawai_aktif' => $jumlahPegawaiAktif,
            'kelas_aktif' => Kelas::query()->where('aktif', true)->count(),
            'mata_pelajaran_aktif' => MataPelajaran::query()->where('aktif', true)->count(),
        ];

        $absensiHariIni = AbsensiSiswa::query()->whereDate('tanggal', $tanggalHariIni);
        $jumlahAbsensiSiswaTercatat = (clone $absensiHariIni)->distinct('siswa_id')->count('siswa_id');
        $ringkasanAbsensi = [
            'siswa_dipantau' => $jumlahSiswaDipantau,
            'hadir' => (clone $absensiHariIni)->where('status_kehadiran', 'hadir')->count(),
            'izin' => (clone $absensiHariIni)->where('status_kehadiran', 'izin')->count(),
            'sakit' => (clone $absensiHariIni)->where('status_kehadiran', 'sakit')->count(),
            'alfa' => (clone $absensiHariIni)->where('status_kehadiran', 'alfa')->count(),
            'terlambat' => (clone $absensiHariIni)->where('menit_terlambat', '>', 0)->count(),
            'pulang_cepat' => (clone $absensiHariIni)->where('menit_pulang_cepat', '>', 0)->count(),
            'belum_scan' => max($jumlahSiswaDipantau - $jumlahAbsensiSiswaTercatat, 0),
            'scan_berhasil' => LogScanAbsensi::query()
                ->whereDate('tanggal', $tanggalHariIni)
                ->where('berhasil', true)
                ->count(),
        ];

        $absensiPegawaiHariIni = AbsensiPegawai::query()->whereDate('tanggal', $tanggalHariIni);
        $jumlahAbsensiPegawaiTercatat = (clone $absensiPegawaiHariIni)->distinct('pegawai_id')->count('pegawai_id');
        $ringkasanAbsensiPegawai = [
            'pegawai_dipantau' => $jumlahPegawaiAktif,
            'hadir' => (clone $absensiPegawaiHariIni)->where('status_kehadiran', 'hadir')->count(),
            'izin_sakit_dinas' => (clone $absensiPegawaiHariIni)
                ->whereIn('status_kehadiran', ['izin', 'sakit', 'dinas_luar', 'cuti'])
                ->count(),
            'alfa' => (clone $absensiPegawaiHariIni)->where('status_kehadiran', 'alfa')->count(),
            'terlambat' => (clone $absensiPegawaiHariIni)->where('menit_terlambat', '>', 0)->count(),
            'pulang_cepat' => (clone $absensiPegawaiHariIni)->where('menit_pulang_cepat', '>', 0)->count(),
            'belum_scan' => max($jumlahPegawaiAktif - $jumlahAbsensiPegawaiTercatat, 0),
            'scan_berhasil' => LogScanAbsensiPegawai::query()
                ->whereDate('tanggal', $tanggalHariIni)
                ->where('berhasil', true)
                ->count(),
        ];

        $guruMapelAktif = GuruMataPelajaran::query()
            ->where('aktif', true)
            ->when($tahunPelajaranAktif, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id));
        $komponenNilaiAktif = KomponenNilai::query()
            ->where('aktif', true)
            ->when($tahunPelajaranAktif, function ($query) use ($tahunPelajaranAktif) {
                $query->whereHas('guruMataPelajaran', fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id));
            });
        $nilaiSiswa = NilaiSiswa::query()
            ->when($tahunPelajaranAktif, function ($query) use ($tahunPelajaranAktif) {
                $query->whereHas('komponenNilai.guruMataPelajaran', fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id));
            });
        $ringkasanAkademik = [
            'guru_mapel_aktif' => (clone $guruMapelAktif)->count(),
            'komponen_nilai_aktif' => (clone $komponenNilaiAktif)->count(),
            'komponen_belum_terisi' => (clone $komponenNilaiAktif)->whereDoesntHave('nilaiSiswa')->count(),
            'nilai_masuk' => (clone $nilaiSiswa)->count(),
            'kelas_terisi' => Kelas::query()->whereHas('anggotaKelas')->count(),
        ];

        $ringkasanPembinaan = [
            'baru' => LaporanPembinaanSiswa::query()->where('status', 'baru')->count(),
            'diproses' => LaporanPembinaanSiswa::query()->where('status', 'diproses')->count(),
            'perlu_tindak_lanjut' => LaporanPembinaanSiswa::query()->where('status', 'perlu_tindak_lanjut')->count(),
            'selesai_bulan_ini' => LaporanPembinaanSiswa::query()
                ->where('status', 'selesai')
                ->whereBetween('updated_at', [$awalBulan, $akhirBulan])
                ->count(),
            'menunggu_bk' => LaporanPembinaanSiswa::query()
                ->whereIn('status_verifikasi', ['diajukan', 'pemeriksaan_bk', 'perlu_klarifikasi'])
                ->count(),
            'menunggu_persetujuan' => LaporanPembinaanSiswa::query()
                ->whereIn('status_verifikasi', ['menunggu_persetujuan', 'disetujui_sebagian', 'perlu_musyawarah'])
                ->count(),
            'poin_aktif' => (int) TransaksiPoinSiswa::query()
                ->when($tahunPelajaranAktif, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id))
                ->sum('poin'),
            'sanksi_menunggu' => SanksiPoinSiswa::query()
                ->when($tahunPelajaranAktif, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id))
                ->whereIn('status', ['menunggu', 'diproses'])
                ->count(),
        ];

        $logScanTerakhir = LogScanAbsensi::query()
            ->with('siswa:id,nama_lengkap,nisn')
            ->latest('waktu_scan')
            ->limit(5)
            ->get();

        $logScanPegawaiTerakhir = LogScanAbsensiPegawai::query()
            ->with('pegawai:id,nama_lengkap,nip')
            ->latest('waktu_scan')
            ->limit(5)
            ->get();

        $siswaTerlambatHariIni = AbsensiSiswa::query()
            ->with(['siswa:id,nama_lengkap,nisn', 'kelas:id,nama'])
            ->whereDate('tanggal', $tanggalHariIni)
            ->where('menit_terlambat', '>', 0)
            ->orderByDesc('menit_terlambat')
            ->limit(5)
            ->get();

        $siswaAlfaHariIni = AbsensiSiswa::query()
            ->with(['siswa:id,nama_lengkap,nisn', 'kelas:id,nama'])
            ->whereDate('tanggal', $tanggalHariIni)
            ->where('status_kehadiran', 'alfa')
            ->latest('id')
            ->limit(5)
            ->get();

        $siswaBelumScanHariIni = AnggotaKelas::query()
            ->with(['siswa:id,nama_lengkap,nisn', 'kelas:id,nama'])
            ->where('status_keanggotaan', 'aktif')
            ->when($tahunPelajaranAktif, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id))
            ->whereHas('siswa', fn ($query) => $query->where('aktif', true))
            ->whereDoesntHave('siswa.absensiSiswa', fn ($query) => $query->whereDate('tanggal', $tanggalHariIni))
            ->orderBy('kelas_id')
            ->orderBy('nomor_absen')
            ->limit(5)
            ->get();

        $pegawaiTerlambatHariIni = AbsensiPegawai::query()
            ->with('pegawai:id,nama_lengkap,nip')
            ->whereDate('tanggal', $tanggalHariIni)
            ->where('menit_terlambat', '>', 0)
            ->orderByDesc('menit_terlambat')
            ->limit(5)
            ->get();

        $pegawaiBelumScanHariIni = Pegawai::query()
            ->where('aktif', true)
            ->whereDoesntHave('absensiPegawai', fn ($query) => $query->whereDate('tanggal', $tanggalHariIni))
            ->orderBy('nama_lengkap')
            ->limit(5)
            ->get(['id', 'nama_lengkap', 'nip']);

        $laporanPembinaanPerluPerhatian = LaporanPembinaanSiswa::query()
            ->with(['siswa:id,nama_lengkap,nisn', 'kategoriPembinaanSiswa:id,nama'])
            ->where(function ($query) {
                $query->whereIn('status', ['baru', 'perlu_tindak_lanjut'])
                    ->orWhereIn('status_verifikasi', ['diajukan', 'pemeriksaan_bk', 'perlu_klarifikasi', 'menunggu_persetujuan', 'disetujui_sebagian', 'perlu_musyawarah']);
            })
            ->orderByRaw("case when status_verifikasi = 'perlu_musyawarah' then 0 when status = 'perlu_tindak_lanjut' then 1 else 2 end")
            ->orderByDesc('tanggal_kejadian')
            ->limit(5)
            ->get();

        return view('beranda.index', compact(
            'hariIni',
            'tahunPelajaranAktif',
            'ringkasanUtama',
            'ringkasanAbsensi',
            'ringkasanAbsensiPegawai',
            'ringkasanAkademik',
            'ringkasanPembinaan',
            'logScanTerakhir',
            'logScanPegawaiTerakhir',
            'siswaTerlambatHariIni',
            'siswaAlfaHariIni',
            'siswaBelumScanHariIni',
            'pegawaiTerlambatHariIni',
            'pegawaiBelumScanHariIni',
            'laporanPembinaanPerluPerhatian',
        ));
    }

    private function dataDashboardPegawai(
        Pengguna $pengguna,
        $hariIni,
        ?TahunPelajaran $tahunPelajaranAktif,
        $awalBulan,
        $akhirBulan,
    ): array {
        $pegawaiLogin = $pengguna->pegawai()->first();
        $labelBulan = $hariIni->copy()->locale('id')->translatedFormat('F Y');
        $awalBulanTanggal = $awalBulan->toDateString();
        $akhirBulanTanggal = $akhirBulan->toDateString();

        $absensiPegawaiBulan = AbsensiPegawai::query()
            ->where('pegawai_id', $pengguna->pegawai_id ?: 0)
            ->whereBetween('tanggal', [$awalBulanTanggal, $akhirBulanTanggal]);
        $jumlahStatusPegawaiBulan = (clone $absensiPegawaiBulan)
            ->selectRaw('status_kehadiran, count(*) as jumlah')
            ->groupBy('status_kehadiran')
            ->pluck('jumlah', 'status_kehadiran');

        $rekapAbsensiPegawaiBulan = collect(AbsensiPegawai::DAFTAR_STATUS_KEHADIRAN)
            ->map(fn (string $label, string $status) => [
                'kode' => $status,
                'label' => $label,
                'jumlah' => (int) ($jumlahStatusPegawaiBulan[$status] ?? 0),
                'warna' => $this->warnaStatusAbsensiPegawai($status),
            ])
            ->values();

        $ringkasanAbsensiPegawaiPribadi = [
            'total_catatan' => (int) $jumlahStatusPegawaiBulan->sum(),
            'hadir' => (int) ($jumlahStatusPegawaiBulan['hadir'] ?? 0),
            'sakit' => (int) ($jumlahStatusPegawaiBulan['sakit'] ?? 0),
            'izin' => (int) ($jumlahStatusPegawaiBulan['izin'] ?? 0),
            'dinas_luar' => (int) ($jumlahStatusPegawaiBulan['dinas_luar'] ?? 0),
            'cuti' => (int) ($jumlahStatusPegawaiBulan['cuti'] ?? 0),
            'alfa' => (int) ($jumlahStatusPegawaiBulan['alfa'] ?? 0),
            'terlambat' => (clone $absensiPegawaiBulan)->where('menit_terlambat', '>', 0)->count(),
            'pulang_cepat' => (clone $absensiPegawaiBulan)->where('menit_pulang_cepat', '>', 0)->count(),
            'belum_pulang' => (clone $absensiPegawaiBulan)
                ->where('status_kehadiran', 'hadir')
                ->whereNotNull('jam_masuk')
                ->whereNull('jam_pulang')
                ->count(),
        ];

        $kelasWali = Kelas::query()
            ->withCount([
                'anggotaKelas as jumlah_siswa_aktif' => function ($query) use ($tahunPelajaranAktif) {
                    $query->where('status_keanggotaan', 'aktif')
                        ->when($tahunPelajaranAktif, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id));
                },
            ])
            ->where('wali_kelas_id', $pengguna->pegawai_id ?: 0)
            ->where('aktif', true)
            ->when($tahunPelajaranAktif, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id))
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get(['id', 'tahun_pelajaran_id', 'nama', 'tingkat']);

        $kelasWaliIds = $kelasWali->pluck('id')->map(fn ($id) => (int) $id)->all();
        $siswaGuruWaliIds = $pengguna->siswaWaliIds();
        $jumlahSiswaGuruWali = count($siswaGuruWaliIds);
        $memilikiPerwalian = $kelasWaliIds !== [] || $siswaGuruWaliIds !== [];

        $absensiSiswaWaliBulan = AbsensiSiswa::query()
            ->whereIn('kelas_id', $kelasWaliIds)
            ->whereBetween('tanggal', [$awalBulanTanggal, $akhirBulanTanggal])
            ->when($tahunPelajaranAktif, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id));
        $jumlahStatusSiswaWaliBulan = (clone $absensiSiswaWaliBulan)
            ->selectRaw('status_kehadiran, count(*) as jumlah')
            ->groupBy('status_kehadiran')
            ->pluck('jumlah', 'status_kehadiran');

        $labelStatusSiswa = [
            'hadir' => 'Hadir',
            'sakit' => 'Sakit',
            'izin' => 'Izin',
            'alfa' => 'Alfa',
        ];

        $rekapAbsensiSiswaWaliBulan = collect($labelStatusSiswa)
            ->map(fn (string $label, string $status) => [
                'kode' => $status,
                'label' => $label,
                'jumlah' => (int) ($jumlahStatusSiswaWaliBulan[$status] ?? 0),
                'warna' => $this->warnaStatusAbsensiSiswa($status),
            ])
            ->values();

        $jumlahSiswaWali = AnggotaKelas::query()
            ->whereIn('kelas_id', $kelasWaliIds)
            ->where('status_keanggotaan', 'aktif')
            ->when($tahunPelajaranAktif, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id))
            ->distinct('siswa_id')
            ->count('siswa_id');

        $ringkasanAbsensiSiswaWali = [
            'jumlah_siswa' => $jumlahSiswaWali,
            'total_catatan' => (int) $jumlahStatusSiswaWaliBulan->sum(),
            'hadir' => (int) ($jumlahStatusSiswaWaliBulan['hadir'] ?? 0),
            'sakit' => (int) ($jumlahStatusSiswaWaliBulan['sakit'] ?? 0),
            'izin' => (int) ($jumlahStatusSiswaWaliBulan['izin'] ?? 0),
            'alfa' => (int) ($jumlahStatusSiswaWaliBulan['alfa'] ?? 0),
            'terlambat' => (clone $absensiSiswaWaliBulan)->where('menit_terlambat', '>', 0)->count(),
            'pulang_cepat' => (clone $absensiSiswaWaliBulan)->where('menit_pulang_cepat', '>', 0)->count(),
        ];

        $laporanPembinaanWaliBulan = LaporanPembinaanSiswa::query()
            ->where(function ($query) use ($kelasWaliIds, $siswaGuruWaliIds) {
                if ($kelasWaliIds === [] && $siswaGuruWaliIds === []) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->when($kelasWaliIds !== [], fn ($query) => $query->whereIn('kelas_id', $kelasWaliIds))
                    ->when($siswaGuruWaliIds !== [], fn ($query) => $query->orWhereIn('siswa_id', $siswaGuruWaliIds));
            })
            ->whereBetween('tanggal_kejadian', [$awalBulanTanggal, $akhirBulanTanggal])
            ->when($tahunPelajaranAktif, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id));
        $jumlahStatusPembinaanWaliBulan = (clone $laporanPembinaanWaliBulan)
            ->selectRaw('status, count(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        $rekapPembinaanWaliBulan = collect(LaporanPembinaanSiswa::DAFTAR_STATUS)
            ->except('dibatalkan')
            ->map(fn (string $label, string $status) => [
                'kode' => $status,
                'label' => $label,
                'jumlah' => (int) ($jumlahStatusPembinaanWaliBulan[$status] ?? 0),
                'warna' => $this->warnaStatusPembinaan($status),
            ])
            ->values();

        $ringkasanPembinaanWali = [
            'total_laporan' => (int) $jumlahStatusPembinaanWaliBulan->sum(),
            'siswa_terlapor' => (clone $laporanPembinaanWaliBulan)->distinct('siswa_id')->count('siswa_id'),
            'baru' => (int) ($jumlahStatusPembinaanWaliBulan['baru'] ?? 0),
            'diproses' => (int) ($jumlahStatusPembinaanWaliBulan['diproses'] ?? 0),
            'perlu_tindak_lanjut' => (int) ($jumlahStatusPembinaanWaliBulan['perlu_tindak_lanjut'] ?? 0),
            'selesai' => (int) ($jumlahStatusPembinaanWaliBulan['selesai'] ?? 0),
            'menunggu_persetujuan' => (clone $laporanPembinaanWaliBulan)
                ->whereIn('status_verifikasi', ['menunggu_persetujuan', 'disetujui_sebagian', 'perlu_musyawarah'])
                ->count(),
            'poin_aktif' => (int) TransaksiPoinSiswa::query()
                ->when($tahunPelajaranAktif, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id))
                ->where(function ($query) use ($kelasWaliIds, $siswaGuruWaliIds) {
                    if ($kelasWaliIds === [] && $siswaGuruWaliIds === []) {
                        $query->whereRaw('1 = 0');

                        return;
                    }

                    $query->whereHas('siswa', function ($query) use ($kelasWaliIds, $siswaGuruWaliIds) {
                        $query->where(function ($query) use ($kelasWaliIds, $siswaGuruWaliIds) {
                            $query->when($kelasWaliIds !== [], fn ($query) => $query->whereHas('anggotaKelas', fn ($query) => $query->whereIn('kelas_id', $kelasWaliIds)))
                                ->when($siswaGuruWaliIds !== [], fn ($query) => $query->orWhereIn('id', $siswaGuruWaliIds));
                        });
                    });
                })
                ->sum('poin'),
        ];

        $laporanPembinaanWali = (clone $laporanPembinaanWaliBulan)
            ->with(['siswa:id,nama_lengkap,nisn', 'kelas:id,nama', 'kategoriPembinaanSiswa:id,nama'])
            ->orderByDesc('tanggal_kejadian')
            ->latest('id')
            ->limit(5)
            ->get();

        return [
            'hariIni' => $hariIni,
            'tahunPelajaranAktif' => $tahunPelajaranAktif,
            'labelBulan' => $labelBulan,
            'pegawaiLogin' => $pegawaiLogin,
            'rekapAbsensiPegawaiBulan' => $rekapAbsensiPegawaiBulan,
            'ringkasanAbsensiPegawaiPribadi' => $ringkasanAbsensiPegawaiPribadi,
            'maksGrafikPegawai' => max((int) $rekapAbsensiPegawaiBulan->max('jumlah'), 1),
            'kelasWali' => $kelasWali,
            'jumlahSiswaGuruWali' => $jumlahSiswaGuruWali,
            'memilikiPerwalian' => $memilikiPerwalian,
            'rekapAbsensiSiswaWaliBulan' => $rekapAbsensiSiswaWaliBulan,
            'ringkasanAbsensiSiswaWali' => $ringkasanAbsensiSiswaWali,
            'maksGrafikSiswaWali' => max((int) $rekapAbsensiSiswaWaliBulan->max('jumlah'), 1),
            'rekapPembinaanWaliBulan' => $rekapPembinaanWaliBulan,
            'ringkasanPembinaanWali' => $ringkasanPembinaanWali,
            'laporanPembinaanWali' => $laporanPembinaanWali,
            'maksGrafikPembinaanWali' => max((int) $rekapPembinaanWaliBulan->max('jumlah'), 1),
        ];
    }

    private function warnaStatusAbsensiPegawai(string $status): string
    {
        return match ($status) {
            'hadir' => '#15477A',
            'sakit' => '#F1C40F',
            'izin' => '#2B83C6',
            'dinas_luar' => '#16A34A',
            'cuti' => '#7C3AED',
            'alfa' => '#DC2626',
            default => '#64748B',
        };
    }

    private function warnaStatusAbsensiSiswa(string $status): string
    {
        return match ($status) {
            'hadir' => '#15477A',
            'sakit' => '#F1C40F',
            'izin' => '#2B83C6',
            'alfa' => '#DC2626',
            default => '#64748B',
        };
    }

    private function warnaStatusPembinaan(string $status): string
    {
        return match ($status) {
            'baru' => '#F1C40F',
            'diproses' => '#2B83C6',
            'perlu_tindak_lanjut' => '#DC2626',
            'selesai' => '#16A34A',
            default => '#64748B',
        };
    }
}
