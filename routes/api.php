<?php

use App\Http\Controllers\Api\V1\AktivitasLoginController;
use App\Http\Controllers\Api\V1\AkunOrangTuaController;
use App\Http\Controllers\Api\V1\AkunPegawaiController;
use App\Http\Controllers\Api\V1\AkunSiswaController;
use App\Http\Controllers\Api\V1\AutentikasiController;
use App\Http\Controllers\Api\V1\BerandaController;
use App\Http\Controllers\Api\V1\GuruMataPelajaranController;
use App\Http\Controllers\Api\V1\JadwalMengajarSayaController;
use App\Http\Controllers\Api\V1\JamPelajaranController;
use App\Http\Controllers\Api\V1\InputNilaiController;
use App\Http\Controllers\Api\V1\KelasController;
use App\Http\Controllers\Api\V1\KenaikanKelasController;
use App\Http\Controllers\Api\V1\KomponenNilaiController;
use App\Http\Controllers\Api\V1\MataPelajaranController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\PegawaiController;
use App\Http\Controllers\Api\V1\PeranController;
use App\Http\Controllers\Api\V1\RekapNilaiRaporController;
use App\Http\Controllers\Api\V1\SiswaController;
use App\Http\Controllers\Api\V1\SkemaBobotNilaiController;
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

        Route::get('/akun-siswa', [AkunSiswaController::class, 'index'])
            ->middleware('izin:akun_siswa.lihat,akun_siswa.kelola,akun_siswa.cetak')
            ->name('akun-siswa.index');
        Route::post('/akun-siswa/kelas/{kelas}/buat-massal', [AkunSiswaController::class, 'storeMassal'])
            ->middleware('izin:akun_siswa.kelola')
            ->name('akun-siswa.store-massal');
        Route::get('/akun-siswa/{siswa}', [AkunSiswaController::class, 'show'])
            ->middleware('izin:akun_siswa.lihat,akun_siswa.kelola,akun_siswa.cetak')
            ->name('akun-siswa.show');
        Route::post('/akun-siswa/{siswa}', [AkunSiswaController::class, 'store'])
            ->middleware('izin:akun_siswa.kelola')
            ->name('akun-siswa.store');
        Route::patch('/akun-siswa/{siswa}/reset-kata-sandi', [AkunSiswaController::class, 'resetKataSandi'])
            ->middleware('izin:akun_siswa.kelola')
            ->name('akun-siswa.reset-kata-sandi');
        Route::patch('/akun-siswa/{siswa}/status', [AkunSiswaController::class, 'updateStatus'])
            ->middleware('izin:akun_siswa.kelola')
            ->name('akun-siswa.status');

        Route::get('/akun-orang-tua', [AkunOrangTuaController::class, 'index'])
            ->middleware('izin:akun_orang_tua.lihat,akun_orang_tua.kelola,akun_orang_tua.cetak')
            ->name('akun-orang-tua.index');
        Route::post('/akun-orang-tua/kelas/{kelas}/buat-massal', [AkunOrangTuaController::class, 'storeMassal'])
            ->middleware('izin:akun_orang_tua.kelola')
            ->name('akun-orang-tua.store-massal');
        Route::get('/akun-orang-tua/{siswa}', [AkunOrangTuaController::class, 'show'])
            ->middleware('izin:akun_orang_tua.lihat,akun_orang_tua.kelola,akun_orang_tua.cetak')
            ->name('akun-orang-tua.show');
        Route::post('/akun-orang-tua/{siswa}', [AkunOrangTuaController::class, 'store'])
            ->middleware('izin:akun_orang_tua.kelola')
            ->name('akun-orang-tua.store');
        Route::patch('/akun-orang-tua/{siswa}/reset-kata-sandi', [AkunOrangTuaController::class, 'resetKataSandi'])
            ->middleware('izin:akun_orang_tua.kelola')
            ->name('akun-orang-tua.reset-kata-sandi');
        Route::patch('/akun-orang-tua/{siswa}/status', [AkunOrangTuaController::class, 'updateStatus'])
            ->middleware('izin:akun_orang_tua.kelola')
            ->name('akun-orang-tua.status');

        Route::get('/aktivitas-login', [AktivitasLoginController::class, 'index'])
            ->middleware('izin:aktivitas_login.lihat')
            ->name('aktivitas-login.index');
        Route::get('/aktivitas-login/{riwayatLogin}', [AktivitasLoginController::class, 'show'])
            ->middleware('izin:aktivitas_login.lihat')
            ->name('aktivitas-login.show');

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

        Route::get('/kenaikan-kelas', [KenaikanKelasController::class, 'index'])
            ->middleware('izin:kenaikan_kelas.kelola')
            ->name('kenaikan-kelas.index');
        Route::post('/kenaikan-kelas/proses', [KenaikanKelasController::class, 'store'])
            ->middleware('izin:kenaikan_kelas.kelola')
            ->name('kenaikan-kelas.store');

        Route::get('/jadwal-mengajar-saya', JadwalMengajarSayaController::class)
            ->middleware('izin:jadwal.pribadi')
            ->name('jadwal-mengajar-saya');

        Route::get('/skema-bobot-nilai', [SkemaBobotNilaiController::class, 'index'])
            ->middleware('izin:nilai.skema_kelola')
            ->name('skema-bobot-nilai.index');
        Route::post('/skema-bobot-nilai', [SkemaBobotNilaiController::class, 'store'])
            ->middleware('izin:nilai.skema_kelola')
            ->name('skema-bobot-nilai.store');
        Route::patch('/skema-bobot-nilai/{skemaBobotNilai}', [SkemaBobotNilaiController::class, 'update'])
            ->middleware('izin:nilai.skema_kelola')
            ->name('skema-bobot-nilai.update');
        Route::delete('/skema-bobot-nilai/{skemaBobotNilai}', [SkemaBobotNilaiController::class, 'destroy'])
            ->middleware('izin:nilai.skema_kelola')
            ->name('skema-bobot-nilai.destroy');

        Route::get('/komponen-nilai', [KomponenNilaiController::class, 'index'])
            ->middleware('izin:nilai.komponen_kelola')
            ->name('komponen-nilai.index');
        Route::post('/komponen-nilai', [KomponenNilaiController::class, 'store'])
            ->middleware('izin:nilai.komponen_kelola')
            ->name('komponen-nilai.store');
        Route::patch('/komponen-nilai/{komponenNilai}', [KomponenNilaiController::class, 'update'])
            ->middleware('izin:nilai.komponen_kelola')
            ->name('komponen-nilai.update');
        Route::delete('/komponen-nilai/{komponenNilai}', [KomponenNilaiController::class, 'destroy'])
            ->middleware('izin:nilai.komponen_kelola')
            ->name('komponen-nilai.destroy');

        Route::get('/input-nilai', [InputNilaiController::class, 'index'])
            ->middleware('izin:nilai.input')
            ->name('input-nilai.index');
        Route::post('/input-nilai', [InputNilaiController::class, 'store'])
            ->middleware('izin:nilai.input')
            ->name('input-nilai.store');
        Route::patch('/input-nilai/publikasi/{guruMataPelajaran}/{semester}', [InputNilaiController::class, 'publikasikan'])
            ->middleware('izin:nilai.input')
            ->name('input-nilai.publikasikan');
        Route::patch('/input-nilai/publikasi/{guruMataPelajaran}/{semester}/draf', [InputNilaiController::class, 'jadikanDraf'])
            ->middleware('izin:nilai.input')
            ->name('input-nilai.jadikan-draf');

        Route::get('/rekap-nilai-rapor', RekapNilaiRaporController::class)
            ->middleware('izin:nilai.rekap')
            ->name('rekap-nilai-rapor.index');

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
