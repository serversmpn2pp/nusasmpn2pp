<?php

use App\Http\Controllers\Api\V1\Humas\AgendaController;
use App\Http\Controllers\Api\V1\Humas\AgendaDokumenController;
use App\Http\Controllers\Api\V1\Humas\BundelController;
use App\Http\Controllers\Api\V1\Humas\DashboardController;
use App\Http\Controllers\Api\V1\Humas\DokumenController;
use App\Http\Controllers\Api\V1\Humas\PengaduanController;
use App\Http\Controllers\Api\V1\Humas\PengaduanSayaController;
use App\Http\Controllers\Api\V1\Humas\PertemuanSayaController;
use App\Http\Controllers\Api\V1\Humas\UmpanBalikController;
use App\Http\Controllers\Api\V1\Humas\UmpanBalikSayaController;
use Illuminate\Support\Facades\Route;

// Loaded inside the existing Sanctum mobile authentication group.
Route::prefix('humas')->name('humas.')->middleware('throttle:120,1,humas-akses:')
    ->where(array_fill_keys(['agendaHumas', 'pesertaPertemuanHumas', 'tindakLanjutAgendaHumas', 'tiket', 'lampiran', 'formulir', 'pertanyaan', 'tindak', 'dokumenHumas', 'riwayatDokumenHumas'], '[0-9]{1,18}') + ['token' => '[A-Za-z0-9]{64}'])
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
        Route::get('/dashboard/referensi', [DashboardController::class, 'referensi'])->name('dashboard.referensi');
        Route::prefix('dokumen')->name('dokumen.')->controller(DokumenController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/referensi', 'referensi')->name('referensi');
            Route::post('/', 'store')->middleware('throttle:15,1,humas-dokumen:')->name('store');
            Route::get('/{dokumenHumas}', 'show')->name('show');
            Route::patch('/{dokumenHumas}', 'update')->name('update');
            Route::post('/{dokumenHumas}/revisi', 'revisi')->middleware('throttle:15,1,humas-revisi-dokumen:')->name('revisi');
            Route::patch('/{dokumenHumas}/status', 'status')->name('status');
            Route::get('/{dokumenHumas}/riwayat', 'riwayat')->name('riwayat.index');
            Route::get('/{dokumenHumas}/unduh', 'unduh')->name('unduh');
            Route::get('/{dokumenHumas}/riwayat/{riwayatDokumenHumas}/unduh', 'unduhRiwayat')->name('riwayat.unduh');
        });
        Route::prefix('agenda/{agendaHumas}/dokumen')->name('agenda.dokumen.')->controller(AgendaDokumenController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::delete('/{dokumenHumas}', 'destroy')->name('destroy');
        });
        Route::prefix('agenda/{agendaHumas}/bundel')->name('agenda.bundel.')->controller(BundelController::class)->group(function () {
            Route::get('/', 'show')->name('show');
            Route::get('/dokumen', 'dokumen')->name('dokumen');
            Route::get('/formulir', 'formulir')->name('formulir');
            Route::get('/riwayat', 'riwayat')->name('riwayat');
            Route::post('/unduh', 'unduh')->middleware('throttle:6,1,humas-bundel:')->name('unduh');
        });
        Route::prefix('agenda')->name('agenda.')->controller(AgendaController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->middleware('throttle:30,1,humas-agenda:')->name('store');
            Route::get('/{agendaHumas}', 'show')->name('show');
            Route::patch('/{agendaHumas}', 'update')->name('update');
            Route::put('/{agendaHumas}/notulen', 'notulen')->name('notulen');
            Route::patch('/{agendaHumas}/status', 'status')->name('status');
            Route::get('/{agendaHumas}/peserta', 'peserta')->name('peserta.index');
            Route::post('/{agendaHumas}/peserta', 'tambahPeserta')->name('peserta.store');
            Route::delete('/{agendaHumas}/peserta/{pesertaPertemuanHumas}', 'hapusPeserta')->name('peserta.destroy');
            Route::put('/{agendaHumas}/presensi', 'presensi')->name('presensi');
            Route::post('/{agendaHumas}/undangan', 'undangan')->name('undangan');
            Route::patch('/{agendaHumas}/akses-presensi', 'aksesPresensi')->name('akses-presensi');
            Route::get('/{agendaHumas}/tindak-lanjut', 'tindakLanjut')->name('tindak.index');
            Route::post('/{agendaHumas}/tindak-lanjut', 'tambahTindak')->name('tindak.store');
            Route::patch('/{agendaHumas}/tindak-lanjut/{tindakLanjutAgendaHumas}', 'ubahTindak')->name('tindak.update');
        });
        Route::prefix('pertemuan-saya')->name('pertemuan-saya.')->controller(PertemuanSayaController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/scan/{token}', 'scan')->name('scan');
            Route::post('/scan/{token}/hadir', 'hadir')->middleware('throttle:20,1,humas-presensi:')->name('hadir');
            Route::get('/{agendaHumas}', 'show')->name('show');
        });
        Route::prefix('pengaduan')->name('pengaduan.')->controller(PengaduanController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/referensi', 'referensi')->name('referensi');
            Route::post('/', 'store')->middleware('throttle:15,1,humas-tiket:')->name('store');
            Route::get('/{tiket}', 'show')->name('show');
            Route::patch('/{tiket}', 'update')->name('update');
            Route::get('/{tiket}/riwayat', 'riwayat')->name('riwayat');
            Route::get('/{tiket}/pesan', 'pesan')->name('pesan');
            Route::post('/{tiket}/balasan', 'balasan')->middleware('throttle:15,1,humas-balasan:')->name('balasan');
            Route::post('/{tiket}/tindakan/{aksi}', 'tindakan')->whereIn('aksi', ['disposisi', 'tarik', 'proses', 'menunggu', 'usulkan-selesai', 'selesaikan', 'tutup', 'buka-kembali'])->name('tindakan');
            Route::post('/{tiket}/lampiran', 'tambahLampiran')->name('lampiran.store');
            Route::get('/{tiket}/lampiran/{lampiran}', 'lampiran')->name('lampiran');
        });
        Route::prefix('pengaduan-saya')->name('pengaduan-saya.')->controller(PengaduanSayaController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/referensi', 'referensi')->name('referensi');
            Route::post('/', 'store')->middleware('throttle:15,1,humas-laporan-wali:')->name('store');
            Route::get('/{tiket}', 'show')->name('show');
            Route::get('/{tiket}/pesan', 'pesan')->name('pesan');
            Route::post('/{tiket}/informasi', 'informasi')->middleware('throttle:15,1,humas-informasi-wali:')->name('informasi');
            Route::get('/{tiket}/lampiran/{lampiran}', 'lampiran')->name('lampiran');
        });
        Route::prefix('umpan-balik')->name('umpan-balik.')->controller(UmpanBalikController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/referensi', 'referensi')->name('referensi');
            Route::post('/', 'store')->name('store');
            Route::get('/{formulir}', 'show')->name('show');
            Route::patch('/{formulir}', 'update')->name('update');
            Route::patch('/{formulir}/status', 'status')->name('status');
            Route::get('/{formulir}/jawaban-teks/{pertanyaan}', 'jawabanTeks')->name('jawaban-teks');
            Route::get('/{formulir}/tindak-lanjut', 'tindakLanjut')->name('tindak.index');
            Route::post('/{formulir}/tindak-lanjut', 'tambahTindak')->name('tindak.store');
            Route::patch('/{formulir}/tindak-lanjut/{tindak}', 'ubahTindak')->name('tindak.update');
        });
        Route::prefix('umpan-balik-saya')->name('umpan-balik-saya.')->controller(UmpanBalikSayaController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{formulir}', 'show')->name('show');
            Route::post('/{formulir}', 'store')->middleware('throttle:15,1,humas-jawaban-wali:')->name('store');
        });
    });
