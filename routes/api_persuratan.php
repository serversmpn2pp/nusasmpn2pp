<?php

use App\Http\Controllers\Api\V1\PersuratanController;
use App\Http\Controllers\Api\V1\TindakLanjutSuratController;
use Illuminate\Support\Facades\Route;

// Loaded inside the existing Sanctum mobile authentication group.
Route::middleware('throttle:120,1,persuratan-api:')
    ->where(array_fill_keys(['surat', 'berkas', 'tindakLanjut', 'bukti'], '[0-9]{1,18}'))
    ->group(function () {
        Route::prefix('persuratan')->name('persuratan.')->group(function () {
            Route::get('/', [PersuratanController::class, 'index'])->name('index');
            Route::get('/referensi', [PersuratanController::class, 'referensi'])->name('referensi');
            Route::get('/pengesahan', [PersuratanController::class, 'pengesahan'])->name('pengesahan');
            Route::get('/tindak-lanjut', [TindakLanjutSuratController::class, 'monitoring'])->name('monitoring');
            Route::post('/', [PersuratanController::class, 'store'])->middleware('throttle:15,1,persuratan-unggah:')->name('store');
            Route::get('/{surat}', [PersuratanController::class, 'show'])->name('show');
            Route::patch('/{surat}', [PersuratanController::class, 'update'])->middleware('throttle:15,1,persuratan-ubah:')->name('update');
            Route::post('/{surat}/tindakan/{aksi}', [PersuratanController::class, 'tindakan'])
                ->whereIn('aksi', ['ajukan', 'tarik', 'kembalikan', 'sahkan', 'kirim', 'batal'])
                ->middleware('throttle:30,1,persuratan-tindakan:')->name('tindakan');
            Route::post('/{surat}/disposisi', [TindakLanjutSuratController::class, 'disposisi'])
                ->middleware('throttle:30,1,persuratan-disposisi:')->name('disposisi');
            Route::get('/{surat}/berkas/{berkas}', [PersuratanController::class, 'berkas'])->name('berkas');
        });
        Route::prefix('surat-saya')->name('surat-saya.')->group(function () {
            Route::get('/', [PersuratanController::class, 'saya'])->name('index');
            Route::get('/{surat}', [PersuratanController::class, 'detailSaya'])->name('show');
            Route::post('/{surat}/baca', [PersuratanController::class, 'baca'])->middleware('throttle:30,1,surat-baca:')->name('baca');
            Route::get('/{surat}/berkas/{berkas}', [PersuratanController::class, 'berkasSaya'])->name('berkas');
        });
        Route::prefix('tindak-lanjut-surat')->name('tindak-lanjut-surat.')->controller(TindakLanjutSuratController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{tindakLanjut}', 'show')->name('show');
            Route::post('/{tindakLanjut}/laporan', 'laporan')->middleware('throttle:15,1,surat-laporan:')->name('laporan');
            Route::post('/{tindakLanjut}/tindakan/{aksi}', 'tindakan')->whereIn('aksi', ['baca', 'mulai', 'ubah', 'terima', 'kembalikan', 'batal'])
                ->middleware('throttle:30,1,surat-tindak:')->name('tindakan');
            Route::get('/{tindakLanjut}/bukti/{bukti}', 'bukti')->name('bukti');
            Route::get('/{tindakLanjut}/berkas/{berkas}', 'berkas')->name('berkas');
        });
    });
