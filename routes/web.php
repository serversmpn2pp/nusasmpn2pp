<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AkunPegawaiController;
use App\Http\Controllers\AnggotaKelasController;
use App\Http\Controllers\AutentikasiController;
use App\Http\Controllers\BerandaController;
use App\Http\Controllers\GuruMataPelajaranController;
use App\Http\Controllers\InputNilaiController;
use App\Http\Controllers\KartuPelajarController;
use App\Http\Controllers\KartuPegawaiController;
use App\Http\Controllers\KenaikanKelasController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\KomponenNilaiController;
use App\Http\Controllers\LaporanAbsensiController;
use App\Http\Controllers\MataPelajaranController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\PengaturanAbsensiController;
use App\Http\Controllers\PengaturanAbsensiPegawaiController;
use App\Http\Controllers\PeranController;
use App\Http\Controllers\RekapAbsensiHarianController;
use App\Http\Controllers\RekapNilaiRaporController;
use App\Http\Controllers\ScanAbsensiController;
use App\Http\Controllers\ScanAbsensiPegawaiController;
use App\Http\Controllers\SkemaBobotNilaiController;
use App\Http\Controllers\SiswaController;
use App\Http\Controllers\TahunPelajaranController;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('beranda');
    }

    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AutentikasiController::class, 'createLogin'])->name('login');
    Route::post('login', [AutentikasiController::class, 'storeLogin'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AutentikasiController::class, 'logout'])->name('logout');
    Route::get('ganti-kata-sandi', [AutentikasiController::class, 'editKataSandi'])->name('kata-sandi.edit');
    Route::put('ganti-kata-sandi', [AutentikasiController::class, 'updateKataSandi'])->name('kata-sandi.update');

    Route::middleware('kata_sandi_bukan_default')->group(function () {
        Route::get('beranda', [BerandaController::class, 'index'])->name('beranda');

        Route::middleware('admin')->group(function () {
            Route::get('akun-pegawai', [AkunPegawaiController::class, 'index'])->name('akun-pegawai.index');
            Route::post('akun-pegawai/buat-massal', [AkunPegawaiController::class, 'storeMassal'])->name('akun-pegawai.buat-massal');
            Route::post('akun-pegawai/{pegawai}', [AkunPegawaiController::class, 'store'])->name('akun-pegawai.store');
            Route::patch('akun-pegawai/{pengguna}/reset-password', [AkunPegawaiController::class, 'resetPassword'])->name('akun-pegawai.reset-password');
            Route::patch('akun-pegawai/{pengguna}/status', [AkunPegawaiController::class, 'ubahStatus'])->name('akun-pegawai.status');
            Route::patch('akun-pegawai/{pengguna}/peran', [AkunPegawaiController::class, 'updatePeran'])->name('akun-pegawai.peran.update');

            Route::resource('peran', PeranController::class)->except(['show']);
            Route::get('pegawai/import', [PegawaiController::class, 'createImport'])->name('pegawai.import.create');
            Route::post('pegawai/import', [PegawaiController::class, 'storeImport'])->name('pegawai.import.store');
            Route::resource('pegawai', PegawaiController::class);

            Route::get('siswa/import', [SiswaController::class, 'createImport'])->name('siswa.import.create');
            Route::post('siswa/import', [SiswaController::class, 'storeImport'])->name('siswa.import.store');
            Route::resource('siswa', SiswaController::class);
            Route::get('kartu-pelajar', [KartuPelajarController::class, 'index'])->name('kartu-pelajar.index');
            Route::get('kartu-pegawai', [KartuPegawaiController::class, 'index'])->name('kartu-pegawai.index');

            Route::resource('tahun-pelajaran', TahunPelajaranController::class);
            Route::resource('mata-pelajaran', MataPelajaranController::class);
            Route::resource('guru-mata-pelajaran', GuruMataPelajaranController::class);
            Route::resource('skema-bobot-nilai', SkemaBobotNilaiController::class);
            Route::resource('komponen-nilai', KomponenNilaiController::class);
            Route::get('input-nilai', [InputNilaiController::class, 'index'])->name('input-nilai.index');
            Route::post('input-nilai', [InputNilaiController::class, 'store'])->name('input-nilai.store');
            Route::get('rekap-nilai-rapor', [RekapNilaiRaporController::class, 'index'])->name('rekap-nilai-rapor.index');
            Route::resource('pengaturan-absensi', PengaturanAbsensiController::class);
            Route::resource('pengaturan-absensi-pegawai', PengaturanAbsensiPegawaiController::class);
            Route::get('scan-absensi', [ScanAbsensiController::class, 'index'])->name('scan-absensi.index');
            Route::post('scan-absensi', [ScanAbsensiController::class, 'store'])->name('scan-absensi.store');
            Route::get('scan-absensi-pegawai', [ScanAbsensiPegawaiController::class, 'index'])->name('scan-absensi-pegawai.index');
            Route::post('scan-absensi-pegawai', [ScanAbsensiPegawaiController::class, 'store'])->name('scan-absensi-pegawai.store');
            Route::get('rekap-absensi-harian', [RekapAbsensiHarianController::class, 'index'])->name('rekap-absensi-harian.index');
            Route::get('rekap-absensi-harian/{anggotaKelas}/koreksi', [RekapAbsensiHarianController::class, 'editKoreksi'])->name('rekap-absensi-harian.koreksi.edit');
            Route::put('rekap-absensi-harian/{anggotaKelas}/koreksi', [RekapAbsensiHarianController::class, 'updateKoreksi'])->name('rekap-absensi-harian.koreksi.update');
            Route::get('laporan-absensi', [LaporanAbsensiController::class, 'index'])->name('laporan-absensi.index');
            Route::get('laporan-absensi/export', [LaporanAbsensiController::class, 'exportExcel'])->name('laporan-absensi.export');
            Route::get('kenaikan-kelas', [KenaikanKelasController::class, 'index'])->name('kenaikan-kelas.index');
            Route::post('kenaikan-kelas', [KenaikanKelasController::class, 'store'])->name('kenaikan-kelas.store');
            Route::post('kelas/{kelas}/anggota-kelas', [AnggotaKelasController::class, 'store'])->name('anggota-kelas.store');
            Route::resource('kelas', KelasController::class)->parameters([
                'kelas' => 'kelas',
            ]);
            Route::patch('anggota-kelas/{anggotaKelas}', [AnggotaKelasController::class, 'update'])->name('anggota-kelas.update');
            Route::delete('anggota-kelas/{anggotaKelas}', [AnggotaKelasController::class, 'destroy'])->name('anggota-kelas.destroy');
        });
    });
});
