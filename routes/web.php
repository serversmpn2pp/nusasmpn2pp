<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\SiswaController;
use App\Http\Controllers\TahunPelajaranController;

Route::get('/', function () {
    return redirect()->route('pegawai.index');
});

Route::get('pegawai/import', [PegawaiController::class, 'createImport'])->name('pegawai.import.create');
Route::post('pegawai/import', [PegawaiController::class, 'storeImport'])->name('pegawai.import.store');
Route::resource('pegawai', PegawaiController::class);

Route::get('siswa/import', [SiswaController::class, 'createImport'])->name('siswa.import.create');
Route::post('siswa/import', [SiswaController::class, 'storeImport'])->name('siswa.import.store');
Route::resource('siswa', SiswaController::class);

Route::resource('tahun-pelajaran', TahunPelajaranController::class);
Route::resource('kelas', KelasController::class)->parameters([
    'kelas' => 'kelas',
]);
