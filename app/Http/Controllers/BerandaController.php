<?php

namespace App\Http\Controllers;

use App\Models\AbsensiSiswa;
use App\Models\GuruMataPelajaran;
use App\Models\Kelas;
use App\Models\KomponenNilai;
use App\Models\LogScanAbsensi;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\Pegawai;
use App\Models\Siswa;
use App\Models\TahunPelajaran;

class BerandaController extends Controller
{
    public function index()
    {
        $pengguna = auth()->user();

        if (! $pengguna?->administrator()) {
            return view('beranda.index');
        }

        $hariIni = now();
        $tanggalHariIni = $hariIni->toDateString();
        $tahunPelajaranAktif = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();

        $ringkasanUtama = [
            'siswa_aktif' => Siswa::query()->where('aktif', true)->count(),
            'pegawai_aktif' => Pegawai::query()->where('aktif', true)->count(),
            'kelas_aktif' => Kelas::query()->where('aktif', true)->count(),
            'mata_pelajaran_aktif' => MataPelajaran::query()->where('aktif', true)->count(),
            'guru_mapel_aktif' => GuruMataPelajaran::query()->where('aktif', true)->count(),
            'nilai_masuk' => NilaiSiswa::query()->count(),
        ];

        $absensiHariIni = AbsensiSiswa::query()->whereDate('tanggal', $tanggalHariIni);
        $ringkasanAbsensi = [
            'hadir' => (clone $absensiHariIni)->where('status_kehadiran', 'hadir')->count(),
            'izin' => (clone $absensiHariIni)->where('status_kehadiran', 'izin')->count(),
            'sakit' => (clone $absensiHariIni)->where('status_kehadiran', 'sakit')->count(),
            'alfa' => (clone $absensiHariIni)->where('status_kehadiran', 'alfa')->count(),
            'terlambat' => (clone $absensiHariIni)->where('menit_terlambat', '>', 0)->count(),
            'pulang_cepat' => (clone $absensiHariIni)->where('menit_pulang_cepat', '>', 0)->count(),
        ];

        $ringkasanAkademik = [
            'komponen_nilai_aktif' => KomponenNilai::query()->where('aktif', true)->count(),
            'kelas_terisi' => Kelas::query()->whereHas('anggotaKelas')->count(),
            'scan_berhasil_hari_ini' => LogScanAbsensi::query()
                ->whereDate('tanggal', $tanggalHariIni)
                ->where('berhasil', true)
                ->count(),
        ];

        $logScanTerakhir = LogScanAbsensi::query()
            ->with('siswa:id,nama_lengkap,nisn')
            ->latest('waktu_scan')
            ->limit(5)
            ->get();

        $siswaTerbaru = Siswa::query()
            ->latest('id')
            ->limit(5)
            ->get(['id', 'nama_lengkap', 'nisn', 'aktif']);

        $pegawaiTerbaru = Pegawai::query()
            ->latest('id')
            ->limit(5)
            ->get(['id', 'nama_lengkap', 'nip', 'aktif']);

        return view('beranda.index', compact(
            'hariIni',
            'tahunPelajaranAktif',
            'ringkasanUtama',
            'ringkasanAbsensi',
            'ringkasanAkademik',
            'logScanTerakhir',
            'siswaTerbaru',
            'pegawaiTerbaru',
        ));
    }
}
