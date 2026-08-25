<?php

use App\Http\Controllers\Api\V1\AkunPegawaiController;
use App\Http\Controllers\Api\V1\AutentikasiController;
use App\Http\Controllers\Api\V1\BerandaController;
use App\Http\Controllers\Api\V1\GuruMataPelajaranController;
use App\Http\Controllers\Api\V1\JamPelajaranController;
use App\Http\Controllers\Api\V1\KelasController;
use App\Http\Controllers\Api\V1\MataPelajaranController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\PegawaiController;
use App\Http\Controllers\Api\V1\PeranController;
use App\Http\Controllers\Api\V1\SiswaController;
use App\Http\Controllers\Api\V1\TahunPelajaranController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')
    ->name('api.v1.auth.')
    ->controller(AutentikasiController::class)
    ->group(function () {
        Route::post('/login', 'login')
            ->middleware('throttle:login-api')
            ->name('login');

        Route::middleware(['auth:sanctum', 'abilities:mobile', 'akun_api_aktif'])->group(function () {
            Route::get('/saya', 'saya')->name('saya');
            Route::put('/kata-sandi', 'ubahKataSandi')->name('kata-sandi.update');
            Route::post('/logout', 'logout')->name('logout');
        });
    });

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware([
        'auth:sanctum',
        'abilities:mobile',
        'akun_api_aktif',
        'kata_sandi_api_bukan_default',
    ])
    ->group(function () {
        Route::get('/beranda', BerandaController::class)
            ->middleware('izin:beranda.akses')
            ->name('beranda');

        Route::get('/menu', MenuController::class)
            ->name('menu');

        Route::get('/pegawai', [PegawaiController::class, 'index'])
            ->middleware('izin:pegawai.lihat,pegawai.kelola')
            ->name('pegawai.index');
        Route::post('/pegawai', [PegawaiController::class, 'store'])
            ->middleware('izin:pegawai.kelola')
            ->name('pegawai.store');
        Route::get('/pegawai/{pegawai}', [PegawaiController::class, 'show'])
            ->middleware('izin:pegawai.lihat,pegawai.kelola')
            ->name('pegawai.show');
        Route::patch('/pegawai/{pegawai}', [PegawaiController::class, 'update'])
            ->middleware('izin:pegawai.kelola')
            ->name('pegawai.update');

        Route::get('/akun-pegawai', [AkunPegawaiController::class, 'index'])
            ->middleware('izin:akun.lihat,akun.kelola')
            ->name('akun-pegawai.index');
        Route::post('/akun-pegawai/buat-massal', [AkunPegawaiController::class, 'storeMassal'])
            ->middleware('izin:akun.kelola')
            ->name('akun-pegawai.store-massal');
        Route::get('/akun-pegawai/{pegawai}', [AkunPegawaiController::class, 'show'])
            ->middleware('izin:akun.lihat,akun.kelola')
            ->name('akun-pegawai.show');
        Route::post('/akun-pegawai/{pegawai}', [AkunPegawaiController::class, 'store'])
            ->middleware('izin:akun.kelola')
            ->name('akun-pegawai.store');
        Route::patch('/akun-pegawai/{pegawai}/reset-kata-sandi', [AkunPegawaiController::class, 'resetKataSandi'])
            ->middleware('izin:akun.kelola')
            ->name('akun-pegawai.reset-kata-sandi');
        Route::patch('/akun-pegawai/{pegawai}/status', [AkunPegawaiController::class, 'updateStatus'])
            ->middleware('izin:akun.kelola')
            ->name('akun-pegawai.status');
        Route::patch('/akun-pegawai/{pegawai}/peran', [AkunPegawaiController::class, 'updatePeran'])
            ->middleware('izin:akun.kelola')
            ->name('akun-pegawai.peran');

        Route::get('/peran', [PeranController::class, 'index'])
            ->middleware('izin:peran.lihat,peran.kelola')
            ->name('peran.index');
        Route::get('/peran/referensi', [PeranController::class, 'referensi'])
            ->middleware('izin:peran.kelola')
            ->name('peran.referensi');
        Route::post('/peran', [PeranController::class, 'store'])
            ->middleware('izin:peran.kelola')
            ->name('peran.store');
        Route::get('/peran/{peran}', [PeranController::class, 'show'])
            ->middleware('izin:peran.lihat,peran.kelola')
            ->name('peran.show');
        Route::patch('/peran/{peran}', [PeranController::class, 'update'])
            ->middleware('izin:peran.kelola')
            ->name('peran.update');
        Route::delete('/peran/{peran}', [PeranController::class, 'destroy'])
            ->middleware('izin:peran.kelola')
            ->name('peran.destroy');

        Route::get('/tahun-pelajaran', [TahunPelajaranController::class, 'index'])
            ->middleware('izin:tahun_pelajaran.lihat,tahun_pelajaran.kelola')
            ->name('tahun-pelajaran.index');
        Route::post('/tahun-pelajaran', [TahunPelajaranController::class, 'store'])
            ->middleware('izin:tahun_pelajaran.kelola')
            ->name('tahun-pelajaran.store');
        Route::patch('/tahun-pelajaran/{tahunPelajaran}', [TahunPelajaranController::class, 'update'])
            ->middleware('izin:tahun_pelajaran.kelola')
            ->name('tahun-pelajaran.update');

        Route::get('/siswa', [SiswaController::class, 'index'])
            ->middleware('izin:siswa.lihat,siswa.kelola')
            ->name('siswa.index');

        Route::get('/siswa/{siswa}', [SiswaController::class, 'show'])
            ->middleware('izin:siswa.lihat,siswa.kelola')
            ->name('siswa.show');

        Route::get('/kelas', [KelasController::class, 'index'])
            ->middleware('izin:kelas.lihat,kelas.kelola')
            ->name('kelas.index');

        Route::get('/kelas/{kelas}', [KelasController::class, 'show'])
            ->middleware('izin:kelas.lihat,kelas.kelola')
            ->name('kelas.show');

        Route::get('/kelas/{kelas}/calon-anggota', [KelasController::class, 'calonAnggota'])
            ->middleware('izin:kelas.kelola')
            ->name('kelas.calon-anggota');

        Route::post('/kelas/{kelas}/anggota', [KelasController::class, 'tambahAnggota'])
            ->middleware('izin:kelas.kelola')
            ->name('kelas.anggota.store');

        Route::patch('/kelas/{kelas}/anggota/{anggotaKelas}', [KelasController::class, 'ubahAnggota'])
            ->middleware('izin:kelas.kelola')
            ->name('kelas.anggota.update');

        Route::delete('/kelas/{kelas}/anggota/{anggotaKelas}', [KelasController::class, 'hapusAnggota'])
            ->middleware('izin:kelas.kelola')
            ->name('kelas.anggota.destroy');

        Route::get('/kelas/{kelas}/jadwal/pilihan', [KelasController::class, 'pilihanJadwal'])
            ->middleware('izin:jadwal.kelola')
            ->name('kelas.jadwal.pilihan');

        Route::put('/kelas/{kelas}/jadwal/{jamPelajaran}', [KelasController::class, 'ubahSlotJadwal'])
            ->middleware('izin:jadwal.kelola')
            ->name('kelas.jadwal.update');

        Route::get('/jam-pelajaran', [JamPelajaranController::class, 'index'])
            ->middleware('admin')
            ->name('jam-pelajaran.index');
        Route::post('/jam-pelajaran', [JamPelajaranController::class, 'store'])
            ->middleware('admin')
            ->name('jam-pelajaran.store');
        Route::patch('/jam-pelajaran/{jamPelajaran}', [JamPelajaranController::class, 'update'])
            ->middleware('admin')
            ->name('jam-pelajaran.update');

        Route::get('/mata-pelajaran', [MataPelajaranController::class, 'index'])
            ->middleware('izin:mata_pelajaran.lihat,mata_pelajaran.kelola')
            ->name('mata-pelajaran.index');
        Route::get('/mata-pelajaran/referensi', [MataPelajaranController::class, 'referensi'])
            ->middleware('izin:mata_pelajaran.kelola')
            ->name('mata-pelajaran.referensi');
        Route::post('/mata-pelajaran', [MataPelajaranController::class, 'store'])
            ->middleware('izin:mata_pelajaran.kelola')
            ->name('mata-pelajaran.store');
        Route::patch('/mata-pelajaran/{mataPelajaran}', [MataPelajaranController::class, 'update'])
            ->middleware('izin:mata_pelajaran.kelola')
            ->name('mata-pelajaran.update');

        Route::get('/guru-mata-pelajaran', [GuruMataPelajaranController::class, 'index'])
            ->middleware('izin:guru_mapel.lihat,guru_mapel.kelola')
            ->name('guru-mata-pelajaran.index');
        Route::get('/guru-mata-pelajaran/referensi', [GuruMataPelajaranController::class, 'referensi'])
            ->middleware('izin:guru_mapel.kelola')
            ->name('guru-mata-pelajaran.referensi');
        Route::post('/guru-mata-pelajaran', [GuruMataPelajaranController::class, 'store'])
            ->middleware('izin:guru_mapel.kelola')
            ->name('guru-mata-pelajaran.store');
        Route::patch('/guru-mata-pelajaran/{guruMataPelajaran}', [GuruMataPelajaranController::class, 'update'])
            ->middleware('izin:guru_mapel.kelola')
            ->name('guru-mata-pelajaran.update');
    });
