<?php

use App\Http\Controllers\PertemuanTahfidzController;
use App\Http\Controllers\RaporTahfidzController;
use App\Http\Controllers\RekapTahfidzController;
use App\Http\Controllers\TahfidzAnakController;
use App\Http\Controllers\TahfidzController;
use App\Http\Controllers\WisudaTahfidzController;
use Illuminate\Support\Facades\Route;

Route::prefix('tahfidz')->name('tahfidz.')->middleware('akun_pegawai')->controller(TahfidzController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/surah', 'surah')->name('surah');
    Route::get('/tambah', 'form')->name('create');
    Route::post('/', 'simpan')->middleware('throttle:30,1,tahfidz:')->name('store');
    Route::get('/{kelompok}', 'show')->whereNumber('kelompok')->name('show');
    Route::get('/{kelompok}/edit', 'form')->whereNumber('kelompok')->name('edit');
    Route::put('/{kelompok}', 'simpan')->whereNumber('kelompok')->middleware('throttle:30,1,tahfidz:')->name('update');
    Route::get('/{kelompok}/anggota', 'anggota')->whereNumber('kelompok')->name('anggota.edit');
    Route::put('/{kelompok}/anggota', 'simpanAnggota')->whereNumber('kelompok')->middleware('throttle:30,1,tahfidz:')->name('anggota.update');
});

Route::prefix('tahfidz/{kelompok}')->name('tahfidz.')->middleware('akun_pegawai')->where(['kelompok' => '[0-9]+'])
    ->controller(PertemuanTahfidzController::class)->group(function () {
        Route::post('/pertemuan', 'buka')->middleware('throttle:30,1,tahfidz-pertemuan:')->name('pertemuan.store');
        Route::get('/pertemuan/{pertemuan}', 'show')->whereNumber('pertemuan')->name('pertemuan.show');
        Route::post('/pertemuan/{pertemuan}/selesaikan', 'selesaikan')->whereNumber('pertemuan')
            ->middleware('throttle:30,1,tahfidz-selesai:')->name('pertemuan.selesaikan');
        Route::post('/pertemuan/{pertemuan}/setoran', 'simpan')->whereNumber('pertemuan')
            ->middleware('throttle:120,1,tahfidz-setoran:')->name('setoran.store');
        Route::get('/anggota/{anggota}/riwayat', 'riwayat')->whereNumber('anggota')->name('anggota.riwayat');
    });

Route::prefix('tahfidz')->name('tahfidz.')->middleware('akun_pegawai')->group(function () {
    Route::get('/rapor', [RaporTahfidzController::class, 'index'])->name('rapor.index');
    Route::get('/rapor/kelompok/{kelompok}/cetak', [RaporTahfidzController::class, 'cetakKelompok'])->whereNumber('kelompok')->name('rapor.kelompok.cetak');
    Route::get('/target-rapor', [RaporTahfidzController::class, 'pengaturan'])->name('rapor.pengaturan');
    Route::post('/target-rapor', [RaporTahfidzController::class, 'simpan'])->middleware('throttle:30,1,tahfidz-rapor:')->name('rapor.simpan');
    Route::get('/wisuda', [WisudaTahfidzController::class, 'index'])->name('wisuda.index');
    Route::post('/wisuda', [WisudaTahfidzController::class, 'simpanAcara'])->middleware('throttle:30,1,tahfidz-wisuda:')->name('wisuda.store');
    Route::put('/wisuda/{wisuda}', [WisudaTahfidzController::class, 'simpanAcara'])->whereNumber('wisuda')->middleware('throttle:30,1,tahfidz-wisuda:')->name('wisuda.update');
    Route::post('/wisuda/kelulusan/{kelulusan}/terbitkan', [WisudaTahfidzController::class, 'terbitkan'])->whereNumber('kelulusan')->middleware('throttle:60,1,tahfidz-sertifikat:')->name('wisuda.terbitkan');
    Route::get('/wisuda/kelulusan/{kelulusan}/sertifikat', [WisudaTahfidzController::class, 'cetak'])->whereNumber('kelulusan')->name('wisuda.sertifikat');
    Route::post('/wisuda/kelulusan/{kelulusan}/publikasi', [WisudaTahfidzController::class, 'publikasi'])->whereNumber('kelulusan')->middleware('throttle:60,1,tahfidz-publikasi:')->name('wisuda.publikasi');

    Route::prefix('/{kelompok}/anggota/{anggota}')->where(['kelompok' => '[0-9]+', 'anggota' => '[0-9]+'])->group(function () {
        Route::get('/rapor', [RaporTahfidzController::class, 'cetak'])->name('rapor.cetak');
        Route::post('/rapor/publikasi', [RaporTahfidzController::class, 'publikasi'])->middleware('throttle:60,1,tahfidz-publikasi:')->name('rapor.publikasi');
        Route::post('/verifikasi', [WisudaTahfidzController::class, 'verifikasi'])->middleware('throttle:60,1,tahfidz-verifikasi:')->name('wisuda.verifikasi');
        Route::get('/sertifikat', [WisudaTahfidzController::class, 'cetakSiswa'])->name('anggota.sertifikat');
    });
});

Route::prefix('tahfidz-anak')->name('tahfidz-anak.')->controller(TahfidzAnakController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/rapor/{id}', 'rapor')->whereNumber('id')->name('rapor');
    Route::get('/sertifikat/{id}', 'sertifikat')->whereNumber('id')->name('sertifikat');
});

Route::prefix('tahfidz/{kelompok}')->name('tahfidz.')->middleware('akun_pegawai')->where(['kelompok' => '[0-9]+'])
    ->controller(RekapTahfidzController::class)->group(function () {
        Route::get('/rekap', 'kelompok')->name('rekap.kelompok');
        Route::get('/anggota/{anggota}/rekap', 'siswa')->whereNumber('anggota')->name('rekap.siswa');
    });
