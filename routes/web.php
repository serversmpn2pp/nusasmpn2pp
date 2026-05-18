<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnggotaKelasController;
use App\Http\Controllers\AutentikasiController;
use App\Http\Controllers\KenaikanKelasController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\SiswaController;
use App\Http\Controllers\TahunPelajaranController;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('pegawai.index');
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

    Route::get('pegawai/import', [PegawaiController::class, 'createImport'])->name('pegawai.import.create');
    Route::post('pegawai/import', [PegawaiController::class, 'storeImport'])->name('pegawai.import.store');
    Route::resource('pegawai', PegawaiController::class);

    Route::get('siswa/import', [SiswaController::class, 'createImport'])->name('siswa.import.create');
    Route::post('siswa/import', [SiswaController::class, 'storeImport'])->name('siswa.import.store');
    Route::resource('siswa', SiswaController::class);

    Route::resource('tahun-pelajaran', TahunPelajaranController::class);
    Route::get('kenaikan-kelas', [KenaikanKelasController::class, 'index'])->name('kenaikan-kelas.index');
    Route::post('kenaikan-kelas', [KenaikanKelasController::class, 'store'])->name('kenaikan-kelas.store');
    Route::post('kelas/{kelas}/anggota-kelas', [AnggotaKelasController::class, 'store'])->name('anggota-kelas.store');
    Route::resource('kelas', KelasController::class)->parameters([
        'kelas' => 'kelas',
    ]);
    Route::patch('anggota-kelas/{anggotaKelas}', [AnggotaKelasController::class, 'update'])->name('anggota-kelas.update');
    Route::delete('anggota-kelas/{anggotaKelas}', [AnggotaKelasController::class, 'destroy'])->name('anggota-kelas.destroy');
});
