<?php

use App\Http\Controllers\LaporanSupervisiController;
use App\Http\Controllers\PascaobservasiSupervisiController;
use App\Http\Controllers\PengisianSupervisiController;
use App\Http\Controllers\SupervisiController;
use Illuminate\Support\Facades\Route;

Route::prefix('supervisi')->name('supervisi.')->middleware('akun_pegawai')->controller(SupervisiController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::controller(LaporanSupervisiController::class)->group(function () {
        Route::get('/rekap', 'index')->name('rekap.index');
        Route::get('/rekap/cetak', 'cetakRekap')->middleware('throttle:10,1')->name('rekap.cetak');
        Route::get('/rekap/excel', 'export')->middleware('throttle:10,1')->name('rekap.export');
        Route::post('/laporan/cetak', 'cetakKolektif')->middleware('throttle:10,1')->name('laporan.kolektif');
        Route::get('/sesi/{sesi}/laporan', 'show')->whereNumber('sesi')->name('laporan.show');
        Route::get('/sesi/{sesi}/laporan/cetak', 'cetakSatu')->whereNumber('sesi')->middleware('throttle:10,1')->name('laporan.cetak');
    });
    Route::get('/pilihan', 'pilihan')->middleware('izin:supervisi.kelola')->name('pilihan');
    Route::get('/program', 'programIndex')->name('program.index');
    Route::get('/program/tambah', 'programForm')->middleware('izin:supervisi.kelola')->name('program.create');
    Route::post('/program', 'programSimpan')->middleware(['izin:supervisi.kelola', 'throttle:30,1'])->name('program.store');
    Route::get('/program/{program}/edit', 'programForm')->whereNumber('program')->middleware('izin:supervisi.kelola')->name('program.edit');
    Route::put('/program/{program}', 'programSimpan')->whereNumber('program')->middleware(['izin:supervisi.kelola', 'throttle:30,1'])->name('program.update');
    Route::get('/program/{program}', 'programShow')->whereNumber('program')->name('program.show');
    Route::get('/program/{program}/jadwal/tambah', 'tambahForm')->whereNumber('program')->middleware('izin:supervisi.kelola')->name('jadwal.create');
    Route::post('/program/{program}/jadwal', 'tambah')->whereNumber('program')->middleware(['izin:supervisi.kelola', 'throttle:30,1'])->name('jadwal.store');
    Route::post('/program/{program}/jadwal/{aksi}', 'sesiTindakan')->whereNumber('program')->whereIn('aksi', ['terbitkan', 'batalkan'])->middleware(['izin:supervisi.kelola', 'throttle:15,1'])->name('jadwal.tindakan');
    Route::get('/sesi/{sesi}', 'sesiShow')->whereNumber('sesi')->name('sesi.show');
    Route::get('/sesi/{sesi}/edit', 'sesiEdit')->whereNumber('sesi')->middleware('izin:supervisi.kelola')->name('sesi.edit');
    Route::put('/sesi/{sesi}', 'sesiUpdate')->whereNumber('sesi')->middleware(['izin:supervisi.kelola', 'throttle:30,1'])->name('sesi.update');
    Route::prefix('sesi/{sesi}')->whereNumber('sesi')->controller(PengisianSupervisiController::class)->group(function () {
        Route::get('/praobservasi', 'praShow')->name('praobservasi.show');
        Route::put('/praobservasi', 'praSimpan')->middleware('throttle:30,1')->name('praobservasi.update');
        Route::post('/praobservasi/{aksiPra}', 'praTindakan')->whereIn('aksiPra', ['kirim', 'kembalikan'])->middleware('throttle:15,1')->name('praobservasi.tindakan');
        Route::get('/penilaian', 'nilaiShow')->name('penilaian.show');
        Route::put('/penilaian', 'nilaiSimpan')->middleware('throttle:30,1')->name('penilaian.update');
        Route::post('/penilaian/{aksiNilai}', 'nilaiTindakan')->whereIn('aksiNilai', ['finalisasi', 'buka-ulang'])->middleware('throttle:15,1')->name('penilaian.tindakan');
        Route::post('/lampiran', 'lampiranSimpan')->middleware('throttle:15,1')->name('lampiran.store');
        Route::get('/lampiran/{lampiran}', 'lampiranUnduh')->whereNumber('lampiran')->name('lampiran.download');
        Route::delete('/lampiran/{lampiran}', 'lampiranHapus')->whereNumber('lampiran')->middleware('throttle:15,1')->name('lampiran.destroy');
    });
    Route::middleware('izin:supervisi.instrumen_kelola')->group(function () {
        Route::get('/instrumen', 'instrumenIndex')->name('instrumen.index');
        Route::get('/instrumen/tambah', 'instrumenForm')->name('instrumen.create');
        Route::post('/instrumen', 'instrumenSimpan')->middleware('throttle:30,1')->name('instrumen.store');
        Route::get('/instrumen/{instrumen}', 'instrumenShow')->whereNumber('instrumen')->name('instrumen.show');
        Route::get('/instrumen/{instrumen}/edit', 'instrumenForm')->whereNumber('instrumen')->name('instrumen.edit');
        Route::put('/instrumen/{instrumen}', 'instrumenSimpan')->whereNumber('instrumen')->middleware('throttle:30,1')->name('instrumen.update');
        Route::post('/instrumen/{instrumen}/{aksi}', 'instrumenTindakan')->whereNumber('instrumen')->whereIn('aksi', ['revisi', 'terbitkan', 'aktif'])->middleware('throttle:15,1')->name('instrumen.tindakan');
    });
    Route::prefix('sesi/{sesi}')->whereNumber('sesi')->controller(PascaobservasiSupervisiController::class)->group(function () {
        Route::get('/pascaobservasi', 'show')->name('pascaobservasi.show');
        Route::put('/refleksi', 'refleksiSimpan')->middleware('throttle:30,1')->name('refleksi.update');
        Route::post('/refleksi/{aksiRefleksi}', 'refleksiTindakan')->whereIn('aksiRefleksi', ['kirim', 'terima', 'kembalikan', 'sesuaikan-hasil'])->middleware('throttle:15,1')->name('refleksi.tindakan');
        Route::post('/pascaobservasi/{aksiPasca}', 'tutup')->whereIn('aksiPasca', ['selesaikan', 'buka-ulang'])->middleware('throttle:15,1')->name('pascaobservasi.tindakan');
        Route::post('/tindak-lanjut', 'rencanaSimpan')->middleware('throttle:30,1')->name('tindak-lanjut.store');
        Route::post('/tindak-lanjut/terbitkan', 'terbitkan')->middleware('throttle:15,1')->name('tindak-lanjut.terbitkan');
        Route::get('/tindak-lanjut/{tindak}', 'tugasShow')->whereNumber('tindak')->name('tindak-lanjut.show');
        Route::put('/tindak-lanjut/{tindak}', 'rencanaSimpan')->whereNumber('tindak')->middleware('throttle:30,1')->name('tindak-lanjut.update');
        Route::put('/tindak-lanjut/{tindak}/pelaksanaan', 'pelaksanaanSimpan')->whereNumber('tindak')->middleware('throttle:30,1')->name('tindak-lanjut.pelaksanaan');
        Route::post('/tindak-lanjut/{tindak}/{aksiTugas}', 'tugasTindakan')->whereNumber('tindak')->whereIn('aksiTugas', ['ajukan', 'verifikasi', 'kembalikan', 'buka-ulang', 'batalkan'])->middleware('throttle:15,1')->name('tindak-lanjut.tindakan');
        Route::post('/tindak-lanjut/{tindak}/lampiran', 'unggah')->whereNumber('tindak')->middleware('throttle:15,1')->name('tindak-lanjut.lampiran.store');
        Route::get('/tindak-lanjut/{tindak}/lampiran/{bukti}', 'unduh')->whereNumber(['tindak', 'bukti'])->name('tindak-lanjut.lampiran.download');
        Route::delete('/tindak-lanjut/{tindak}/lampiran/{bukti}', 'hapus')->whereNumber(['tindak', 'bukti'])->middleware('throttle:15,1')->name('tindak-lanjut.lampiran.destroy');
    });
});
