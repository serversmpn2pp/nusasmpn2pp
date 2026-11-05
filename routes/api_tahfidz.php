<?php

use App\Http\Controllers\Api\V1\TahfidzMobileController;
use App\Http\Controllers\PertemuanTahfidzController;
use App\Http\Controllers\RaporTahfidzController;
use App\Http\Controllers\TahfidzController;
use App\Http\Controllers\WisudaTahfidzController;
use Illuminate\Support\Facades\Route;

Route::prefix('tahfidz')->name('tahfidz.')->group(function () {
    Route::get('/', [TahfidzMobileController::class, 'index'])->name('index');
    Route::get('/referensi', [TahfidzMobileController::class, 'referensi'])->name('referensi');
    Route::get('/target-rapor', [TahfidzMobileController::class, 'target'])->name('target');
    Route::post('/target-rapor', [RaporTahfidzController::class, 'simpan'])->middleware('throttle:30,1,tahfidz-rapor:')->name('target.store');
    Route::get('/wisuda', [TahfidzMobileController::class, 'wisuda'])->name('wisuda');
    Route::post('/wisuda', [WisudaTahfidzController::class, 'simpanAcara'])->middleware('throttle:30,1,tahfidz-wisuda:')->name('wisuda.store');
    Route::put('/wisuda/{wisuda}', [WisudaTahfidzController::class, 'simpanAcara'])->whereNumber('wisuda')->middleware('throttle:30,1,tahfidz-wisuda:')->name('wisuda.update');
    Route::post('/wisuda/kelulusan/{kelulusan}/terbitkan', [WisudaTahfidzController::class, 'terbitkan'])->whereNumber('kelulusan')->middleware('throttle:60,1,tahfidz-sertifikat:')->name('wisuda.terbitkan');
    Route::post('/wisuda/kelulusan/{kelulusan}/publikasi', [WisudaTahfidzController::class, 'publikasi'])->whereNumber('kelulusan')->middleware('throttle:60,1,tahfidz-publikasi:')->name('wisuda.publikasi');
    Route::post('/', [TahfidzController::class, 'simpan'])->middleware('throttle:30,1,tahfidz:')->name('store');
    Route::prefix('{kelompok}')->where(['kelompok' => '[0-9]+'])->group(function () {
        Route::get('/', [TahfidzMobileController::class, 'show'])->name('show');
        Route::put('/', [TahfidzController::class, 'simpan'])->middleware('throttle:30,1,tahfidz:')->name('update');
        Route::get('/anggota', [TahfidzMobileController::class, 'anggota'])->name('anggota');
        Route::get('/rapor', [TahfidzMobileController::class, 'raporKelompok'])->name('rapor');
        Route::put('/anggota', [TahfidzController::class, 'simpanAnggota'])->middleware('throttle:30,1,tahfidz:')->name('anggota.update');
        Route::post('/pertemuan', [PertemuanTahfidzController::class, 'buka'])->middleware('throttle:30,1,tahfidz-pertemuan:')->name('pertemuan.store');
        Route::get('/pertemuan/{pertemuan}', [TahfidzMobileController::class, 'pertemuan'])->whereNumber('pertemuan')->name('pertemuan');
        Route::post('/pertemuan/{pertemuan}/setoran', [PertemuanTahfidzController::class, 'simpan'])->whereNumber('pertemuan')->middleware('throttle:120,1,tahfidz-setoran:')->name('setoran.store');
        Route::post('/pertemuan/{pertemuan}/selesaikan', [PertemuanTahfidzController::class, 'selesaikan'])->whereNumber('pertemuan')->middleware('throttle:30,1,tahfidz-selesai:')->name('pertemuan.selesaikan');
        Route::get('/anggota/{anggota}', [TahfidzMobileController::class, 'siswa'])->whereNumber('anggota')->name('siswa');
        Route::post('/anggota/{anggota}/rapor/publikasi', [RaporTahfidzController::class, 'publikasi'])->whereNumber('anggota')->middleware('throttle:60,1,tahfidz-publikasi:')->name('rapor.publikasi');
        Route::post('/anggota/{anggota}/verifikasi', [WisudaTahfidzController::class, 'verifikasi'])->whereNumber('anggota')->middleware('throttle:60,1,tahfidz-verifikasi:')->name('verifikasi');
    });
});
Route::get('/tahfidz-anak', [TahfidzMobileController::class, 'anak'])->name('tahfidz-anak.index');
Route::get('/tahfidz-saya', [TahfidzMobileController::class, 'saya'])->name('tahfidz-saya.index');
Route::get('/tahfidz-anak/rapor/{id}', [TahfidzMobileController::class, 'raporAnak'])->whereNumber('id')->name('tahfidz-anak.rapor');
Route::get('/tahfidz-anak/sertifikat/{id}', [TahfidzMobileController::class, 'sertifikatAnak'])->whereNumber('id')->name('tahfidz-anak.sertifikat');
