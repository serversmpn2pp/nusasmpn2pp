<?php

use App\Http\Controllers\Api\V1\AktivitasLoginController;
use App\Http\Controllers\Api\V1\AkunOrangTuaController;
use App\Http\Controllers\Api\V1\AkunPegawaiController;
use App\Http\Controllers\Api\V1\AkunSiswaController;
use App\Http\Controllers\Api\V1\AutentikasiController;
use App\Http\Controllers\Api\V1\BerandaController;
use App\Http\Controllers\Api\V1\FotoIdentitasController;
use App\Http\Controllers\Api\V1\GuruMataPelajaranController;
use App\Http\Controllers\Api\V1\InputNilaiController;
use App\Http\Controllers\Api\V1\JadwalGuruPiketController;
use App\Http\Controllers\Api\V1\JadwalKegiatanIbadahController;
use App\Http\Controllers\Api\V1\JadwalMengajarSayaController;
use App\Http\Controllers\Api\V1\JamPelajaranController;
use App\Http\Controllers\Api\V1\JenisPelanggaranSiswaController;
use App\Http\Controllers\Api\V1\JenisPerangkatAjarController;
use App\Http\Controllers\Api\V1\KartuPegawaiController;
use App\Http\Controllers\Api\V1\KartuPelajarController;
use App\Http\Controllers\Api\V1\KategoriPembinaanSiswaController;
use App\Http\Controllers\Api\V1\KegiatanIbadahController;
use App\Http\Controllers\Api\V1\KelasController;
use App\Http\Controllers\Api\V1\KenaikanKelasController;
use App\Http\Controllers\Api\V1\KomponenNilaiController;
use App\Http\Controllers\Api\V1\KonfirmasiBerhalanganIbadahController;
use App\Http\Controllers\Api\V1\LaporanPresensiPegawaiController;
use App\Http\Controllers\Api\V1\LaporanPresensiSiswaController;
use App\Http\Controllers\Api\V1\MataPelajaranController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\MonitoringSurveiController;
use App\Http\Controllers\Api\V1\NilaiSayaController;
use App\Http\Controllers\Api\V1\PegawaiController;
use App\Http\Controllers\Api\V1\PemeriksaanPerangkatAjarController;
use App\Http\Controllers\Api\V1\PenempatanSiswaController;
use App\Http\Controllers\Api\V1\PengaturanBerhalanganIbadahController;
use App\Http\Controllers\Api\V1\PengaturanPresensiPegawaiController;
use App\Http\Controllers\Api\V1\PengaturanPresensiSiswaController;
use App\Http\Controllers\Api\V1\PeranController;
use App\Http\Controllers\Api\V1\PerangkatAjarSayaController;
use App\Http\Controllers\Api\V1\PernyataanSurveiController;
use App\Http\Controllers\Api\V1\PiketSayaController;
use App\Http\Controllers\Api\V1\RekapKegiatanIbadahController;
use App\Http\Controllers\Api\V1\RekapNilaiRaporController;
use App\Http\Controllers\Api\V1\RekapPresensiPegawaiController;
use App\Http\Controllers\Api\V1\RekapPresensiSiswaController;
use App\Http\Controllers\Api\V1\RingkasanKegiatanIbadahBulananController;
use App\Http\Controllers\Api\V1\ScanBerhalanganIbadahController;
use App\Http\Controllers\Api\V1\ScanKegiatanIbadahController;
use App\Http\Controllers\Api\V1\SiswaController;
use App\Http\Controllers\Api\V1\SkemaBobotNilaiController;
use App\Http\Controllers\Api\V1\StatusScanPresensiPegawaiController;
use App\Http\Controllers\Api\V1\StatusScanPresensiSiswaController;
use App\Http\Controllers\Api\V1\SurveiPembelajaranController;
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

        Route::get('/jadwal-guru-piket', [JadwalGuruPiketController::class, 'index'])
            ->middleware('izin:piket_guru.kelola')
            ->name('jadwal-guru-piket.index');
        Route::get('/jadwal-guru-piket/referensi', [JadwalGuruPiketController::class, 'referensi'])
            ->middleware('izin:piket_guru.kelola')
            ->name('jadwal-guru-piket.referensi');
        Route::post('/jadwal-guru-piket', [JadwalGuruPiketController::class, 'store'])
            ->middleware('izin:piket_guru.kelola')
            ->name('jadwal-guru-piket.store');
        Route::patch('/jadwal-guru-piket/{jadwalPiketGuru}', [JadwalGuruPiketController::class, 'update'])
            ->middleware('izin:piket_guru.kelola')
            ->name('jadwal-guru-piket.update');
        Route::delete('/jadwal-guru-piket/{jadwalPiketGuru}', [JadwalGuruPiketController::class, 'destroy'])
            ->middleware('izin:piket_guru.kelola')
            ->name('jadwal-guru-piket.destroy');

        Route::get('/piket-saya', [PiketSayaController::class, 'index'])
            ->middleware('izin:piket_guru.lihat_pribadi,piket_guru.catat_kehadiran')
            ->name('piket-saya.index');
        Route::patch('/piket-saya/kehadiran/{anggotaKelas}', [PiketSayaController::class, 'update'])
            ->middleware('izin:piket_guru.catat_kehadiran')
            ->name('piket-saya.kehadiran.update');

        Route::get('/foto-identitas', [FotoIdentitasController::class, 'index'])
            ->middleware('izin:siswa.kelola,pegawai.kelola')
            ->name('foto-identitas.index');
        Route::post('/foto-identitas/siswa/{siswa}', [FotoIdentitasController::class, 'updateSiswa'])
            ->middleware('izin:siswa.kelola')
            ->name('foto-identitas.siswa.update');
        Route::post('/foto-identitas/pegawai/{pegawai}', [FotoIdentitasController::class, 'updatePegawai'])
            ->middleware('izin:pegawai.kelola')
            ->name('foto-identitas.pegawai.update');

        Route::get('/kartu-pegawai', KartuPegawaiController::class)
            ->middleware('izin:pegawai.lihat,pegawai.kelola')
            ->name('kartu-pegawai.index');

        Route::get('/kartu-pelajar', KartuPelajarController::class)
            ->middleware('izin:kartu_pelajar.lihat,kartu_pelajar.cetak')
            ->name('kartu-pelajar.index');

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

        Route::get('/penempatan-siswa', [PenempatanSiswaController::class, 'index'])
            ->middleware('izin:kelas.lihat,kelas.kelola')
            ->name('penempatan-siswa.index');
        Route::post('/penempatan-siswa/masukkan', [PenempatanSiswaController::class, 'store'])
            ->middleware('izin:kelas.kelola')
            ->name('penempatan-siswa.store');

        Route::get('/pengaturan-presensi-siswa', [PengaturanPresensiSiswaController::class, 'index'])
            ->middleware('izin:absensi.pengaturan_kelola')
            ->name('pengaturan-presensi-siswa.index');
        Route::post('/pengaturan-presensi-siswa', [PengaturanPresensiSiswaController::class, 'store'])
            ->middleware('izin:absensi.pengaturan_kelola')
            ->name('pengaturan-presensi-siswa.store');
        Route::patch('/pengaturan-presensi-siswa/{pengaturanAbsensi}', [PengaturanPresensiSiswaController::class, 'update'])
            ->middleware('izin:absensi.pengaturan_kelola')
            ->name('pengaturan-presensi-siswa.update');

        Route::get('/pengaturan-presensi-pegawai', [PengaturanPresensiPegawaiController::class, 'index'])
            ->middleware('izin:absensi.pengaturan_kelola')
            ->name('pengaturan-presensi-pegawai.index');
        Route::post('/pengaturan-presensi-pegawai', [PengaturanPresensiPegawaiController::class, 'store'])
            ->middleware('izin:absensi.pengaturan_kelola')
            ->name('pengaturan-presensi-pegawai.store');
        Route::patch('/pengaturan-presensi-pegawai/{pengaturanAbsensiPegawai}', [PengaturanPresensiPegawaiController::class, 'update'])
            ->middleware('izin:absensi.pengaturan_kelola')
            ->name('pengaturan-presensi-pegawai.update');

        Route::get('/status-scan-presensi-siswa', StatusScanPresensiSiswaController::class)
            ->middleware('izin:absensi.scan,absensi.lihat,absensi.koreksi,absensi.koreksi_hari_ini,absensi.laporan')
            ->name('status-scan-presensi-siswa.index');

        Route::get('/status-scan-presensi-pegawai', StatusScanPresensiPegawaiController::class)
            ->middleware('izin:absensi.scan,absensi.lihat,absensi.koreksi,absensi.laporan')
            ->name('status-scan-presensi-pegawai.index');

        Route::get('/rekap-presensi-siswa', [RekapPresensiSiswaController::class, 'index'])
            ->middleware('izin:absensi.lihat,absensi.koreksi,absensi.koreksi_hari_ini,absensi.laporan')
            ->name('rekap-presensi-siswa.index');
        Route::get('/rekap-presensi-siswa/pesan-whatsapp', [RekapPresensiSiswaController::class, 'pesanWhatsapp'])
            ->middleware('izin:absensi.lihat,absensi.koreksi,absensi.koreksi_hari_ini,absensi.laporan')
            ->name('rekap-presensi-siswa.pesan-whatsapp');
        Route::get('/rekap-presensi-siswa/{anggotaKelas}', [RekapPresensiSiswaController::class, 'show'])
            ->middleware('izin:absensi.lihat,absensi.koreksi,absensi.koreksi_hari_ini,absensi.laporan')
            ->name('rekap-presensi-siswa.show');
        Route::patch('/rekap-presensi-siswa/{anggotaKelas}/koreksi', [RekapPresensiSiswaController::class, 'update'])
            ->middleware('izin:absensi.koreksi,absensi.koreksi_hari_ini')
            ->name('rekap-presensi-siswa.update');

        Route::get('/rekap-presensi-pegawai', [RekapPresensiPegawaiController::class, 'index'])
            ->middleware('izin:absensi.lihat,absensi.koreksi,absensi.laporan,absensi_pegawai.pribadi')
            ->name('rekap-presensi-pegawai.index');
        Route::get('/rekap-presensi-pegawai/{pegawai}', [RekapPresensiPegawaiController::class, 'show'])
            ->middleware('izin:absensi.lihat,absensi.koreksi,absensi.laporan,absensi_pegawai.pribadi')
            ->name('rekap-presensi-pegawai.show');
        Route::patch('/rekap-presensi-pegawai/{pegawai}/koreksi', [RekapPresensiPegawaiController::class, 'update'])
            ->middleware('izin:absensi.koreksi')
            ->name('rekap-presensi-pegawai.update');

        Route::get('/laporan-presensi-siswa/export', [LaporanPresensiSiswaController::class, 'export'])
            ->middleware('izin:laporan.export')
            ->name('laporan-presensi-siswa.export');
        Route::get('/laporan-presensi-siswa', [LaporanPresensiSiswaController::class, 'index'])
            ->middleware('izin:absensi.laporan')
            ->name('laporan-presensi-siswa.index');
        Route::get('/laporan-presensi-siswa/{anggotaKelas}', [LaporanPresensiSiswaController::class, 'show'])
            ->middleware('izin:absensi.laporan')
            ->name('laporan-presensi-siswa.show');

        Route::get('/laporan-presensi-pegawai', [LaporanPresensiPegawaiController::class, 'index'])
            ->middleware('izin:absensi.laporan,absensi_pegawai.pribadi')
            ->name('laporan-presensi-pegawai.index');
        Route::get('/laporan-presensi-pegawai/{pegawai}', [LaporanPresensiPegawaiController::class, 'show'])
            ->middleware('izin:absensi.laporan,absensi_pegawai.pribadi')
            ->name('laporan-presensi-pegawai.show');

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

        Route::get('/pernyataan-survei', [PernyataanSurveiController::class, 'index'])
            ->middleware('izin:survei.pertanyaan_kelola')
            ->name('pernyataan-survei.index');
        Route::post('/pernyataan-survei', [PernyataanSurveiController::class, 'store'])
            ->middleware('izin:survei.pertanyaan_kelola')
            ->name('pernyataan-survei.store');
        Route::patch('/pernyataan-survei/{pertanyaanSurveiPembelajaran}', [PernyataanSurveiController::class, 'update'])
            ->middleware('izin:survei.pertanyaan_kelola')
            ->name('pernyataan-survei.update');
        Route::patch('/pernyataan-survei/{pertanyaanSurveiPembelajaran}/status', [PernyataanSurveiController::class, 'updateStatus'])
            ->middleware('izin:survei.pertanyaan_kelola')
            ->name('pernyataan-survei.status');

        Route::get('/monitoring-survei', [MonitoringSurveiController::class, 'index'])
            ->middleware('izin:survei.monitor')
            ->name('monitoring-survei.index');
        Route::get('/monitoring-survei/{guruMataPelajaran}', [MonitoringSurveiController::class, 'show'])
            ->middleware('izin:survei.monitor')
            ->name('monitoring-survei.show');

        Route::get('/perangkat-ajar-saya', [PerangkatAjarSayaController::class, 'index'])
            ->middleware('izin:perangkat_ajar.upload')
            ->name('perangkat-ajar-saya.index');
        Route::post('/perangkat-ajar-saya', [PerangkatAjarSayaController::class, 'store'])
            ->middleware('izin:perangkat_ajar.upload')
            ->name('perangkat-ajar-saya.store');
        Route::get('/perangkat-ajar-saya/{perangkatAjar}', [PerangkatAjarSayaController::class, 'show'])
            ->middleware('izin:perangkat_ajar.upload')
            ->name('perangkat-ajar-saya.show');
        Route::post('/perangkat-ajar-saya/{perangkatAjar}', [PerangkatAjarSayaController::class, 'update'])
            ->middleware('izin:perangkat_ajar.upload')
            ->name('perangkat-ajar-saya.update');

        Route::get('/pemeriksaan-perangkat-ajar', [PemeriksaanPerangkatAjarController::class, 'index'])
            ->middleware('izin:perangkat_ajar.lihat,perangkat_ajar.periksa')
            ->name('pemeriksaan-perangkat-ajar.index');
        Route::get('/pemeriksaan-perangkat-ajar/guru/{pegawai}', [PemeriksaanPerangkatAjarController::class, 'showTeacher'])
            ->middleware('izin:perangkat_ajar.lihat,perangkat_ajar.periksa')
            ->name('pemeriksaan-perangkat-ajar.guru');
        Route::get('/pemeriksaan-perangkat-ajar/dokumen/{perangkatAjar}', [PemeriksaanPerangkatAjarController::class, 'showDocument'])
            ->middleware('izin:perangkat_ajar.lihat,perangkat_ajar.periksa')
            ->name('pemeriksaan-perangkat-ajar.dokumen');
        Route::get('/pemeriksaan-perangkat-ajar/dokumen/{perangkatAjar}/file', [PemeriksaanPerangkatAjarController::class, 'file'])
            ->middleware('izin:perangkat_ajar.lihat,perangkat_ajar.periksa')
            ->name('pemeriksaan-perangkat-ajar.file');
        Route::patch('/pemeriksaan-perangkat-ajar/dokumen/{perangkatAjar}', [PemeriksaanPerangkatAjarController::class, 'update'])
            ->middleware('izin:perangkat_ajar.periksa')
            ->name('pemeriksaan-perangkat-ajar.update');

        Route::get('/jenis-perangkat-ajar', [JenisPerangkatAjarController::class, 'index'])
            ->middleware('izin:perangkat_ajar.jenis_kelola')
            ->name('jenis-perangkat-ajar.index');
        Route::post('/jenis-perangkat-ajar', [JenisPerangkatAjarController::class, 'store'])
            ->middleware('izin:perangkat_ajar.jenis_kelola')
            ->name('jenis-perangkat-ajar.store');
        Route::patch('/jenis-perangkat-ajar/{jenisPerangkatAjar}', [JenisPerangkatAjarController::class, 'update'])
            ->middleware('izin:perangkat_ajar.jenis_kelola')
            ->name('jenis-perangkat-ajar.update');
        Route::delete('/jenis-perangkat-ajar/{jenisPerangkatAjar}', [JenisPerangkatAjarController::class, 'destroy'])
            ->middleware('izin:perangkat_ajar.jenis_kelola')
            ->name('jenis-perangkat-ajar.destroy');

        Route::get('/kegiatan-ibadah', [KegiatanIbadahController::class, 'index'])
            ->middleware('izin:ibadah.pengaturan_kelola')
            ->name('kegiatan-ibadah.index');
        Route::post('/kegiatan-ibadah', [KegiatanIbadahController::class, 'store'])
            ->middleware('izin:ibadah.pengaturan_kelola')
            ->name('kegiatan-ibadah.store');
        Route::patch('/kegiatan-ibadah/{kegiatanIbadah}', [KegiatanIbadahController::class, 'update'])
            ->middleware('izin:ibadah.pengaturan_kelola')
            ->name('kegiatan-ibadah.update');
        Route::delete('/kegiatan-ibadah/{kegiatanIbadah}', [KegiatanIbadahController::class, 'destroy'])
            ->middleware('izin:ibadah.pengaturan_kelola')
            ->name('kegiatan-ibadah.destroy');

        Route::get('/kategori-pembinaan-siswa', [KategoriPembinaanSiswaController::class, 'index'])
            ->middleware('izin:bk.lihat,bk.kelola')
            ->name('kategori-pembinaan-siswa.index');
        Route::post('/kategori-pembinaan-siswa', [KategoriPembinaanSiswaController::class, 'store'])
            ->middleware('izin:bk.kelola')
            ->name('kategori-pembinaan-siswa.store');
        Route::patch('/kategori-pembinaan-siswa/{kategoriPembinaanSiswa}', [KategoriPembinaanSiswaController::class, 'update'])
            ->middleware('izin:bk.kelola')
            ->name('kategori-pembinaan-siswa.update');
        Route::delete('/kategori-pembinaan-siswa/{kategoriPembinaanSiswa}', [KategoriPembinaanSiswaController::class, 'destroy'])
            ->middleware('izin:bk.kelola')
            ->name('kategori-pembinaan-siswa.destroy');

        Route::get('/jenis-pelanggaran-siswa', [JenisPelanggaranSiswaController::class, 'index'])
            ->middleware('izin:poin_siswa.pengaturan')
            ->name('jenis-pelanggaran-siswa.index');
        Route::post('/jenis-pelanggaran-siswa', [JenisPelanggaranSiswaController::class, 'store'])
            ->middleware('izin:poin_siswa.pengaturan')
            ->name('jenis-pelanggaran-siswa.store');
        Route::patch('/jenis-pelanggaran-siswa/{jenisPelanggaranSiswa}', [JenisPelanggaranSiswaController::class, 'update'])
            ->middleware('izin:poin_siswa.pengaturan')
            ->name('jenis-pelanggaran-siswa.update');
        Route::delete('/jenis-pelanggaran-siswa/{jenisPelanggaranSiswa}', [JenisPelanggaranSiswaController::class, 'destroy'])
            ->middleware('izin:poin_siswa.pengaturan')
            ->name('jenis-pelanggaran-siswa.destroy');

        Route::get('/jadwal-kegiatan-ibadah', [JadwalKegiatanIbadahController::class, 'index'])
            ->middleware('izin:ibadah.pengaturan_kelola')
            ->name('jadwal-kegiatan-ibadah.index');
        Route::post('/jadwal-kegiatan-ibadah', [JadwalKegiatanIbadahController::class, 'store'])
            ->middleware('izin:ibadah.pengaturan_kelola')
            ->name('jadwal-kegiatan-ibadah.store');
        Route::patch('/jadwal-kegiatan-ibadah/{jadwalKegiatanIbadah}', [JadwalKegiatanIbadahController::class, 'update'])
            ->middleware('izin:ibadah.pengaturan_kelola')
            ->name('jadwal-kegiatan-ibadah.update');
        Route::delete('/jadwal-kegiatan-ibadah/{jadwalKegiatanIbadah}', [JadwalKegiatanIbadahController::class, 'destroy'])
            ->middleware('izin:ibadah.pengaturan_kelola')
            ->name('jadwal-kegiatan-ibadah.destroy');

        Route::get('/pengaturan-berhalangan-ibadah', [PengaturanBerhalanganIbadahController::class, 'index'])
            ->middleware('izin:ibadah.pengaturan_kelola')
            ->name('pengaturan-berhalangan-ibadah.index');
        Route::put('/pengaturan-berhalangan-ibadah', [PengaturanBerhalanganIbadahController::class, 'update'])
            ->middleware('izin:ibadah.pengaturan_kelola')
            ->name('pengaturan-berhalangan-ibadah.update');
        Route::post('/pengaturan-berhalangan-ibadah/pendamping', [PengaturanBerhalanganIbadahController::class, 'storePendamping'])
            ->middleware('izin:ibadah.pengaturan_kelola')
            ->name('pengaturan-berhalangan-ibadah.pendamping.store');
        Route::delete('/pengaturan-berhalangan-ibadah/pendamping/{penugasanPendampingIbadahSiswi}', [PengaturanBerhalanganIbadahController::class, 'destroyPendamping'])
            ->middleware('izin:ibadah.pengaturan_kelola')
            ->name('pengaturan-berhalangan-ibadah.pendamping.destroy');

        Route::get('/scan-kegiatan-ibadah', [ScanKegiatanIbadahController::class, 'index'])
            ->middleware('izin:ibadah.scan')
            ->name('scan-kegiatan-ibadah.index');
        Route::post('/scan-kegiatan-ibadah', [ScanKegiatanIbadahController::class, 'store'])
            ->middleware('izin:ibadah.scan')
            ->name('scan-kegiatan-ibadah.store');

        Route::get('/scan-berhalangan-ibadah', [ScanBerhalanganIbadahController::class, 'index'])
            ->name('scan-berhalangan-ibadah.index');
        Route::post('/scan-berhalangan-ibadah', [ScanBerhalanganIbadahController::class, 'store'])
            ->name('scan-berhalangan-ibadah.store');

        Route::get('/konfirmasi-berhalangan-ibadah', [KonfirmasiBerhalanganIbadahController::class, 'index'])
            ->name('konfirmasi-berhalangan-ibadah.index');
        Route::get('/konfirmasi-berhalangan-ibadah/{periodeBerhalanganIbadah}', [KonfirmasiBerhalanganIbadahController::class, 'show'])
            ->name('konfirmasi-berhalangan-ibadah.show');
        Route::put('/konfirmasi-berhalangan-ibadah/{periodeBerhalanganIbadah}', [KonfirmasiBerhalanganIbadahController::class, 'update'])
            ->name('konfirmasi-berhalangan-ibadah.update');

        Route::get('/rekap-kegiatan-ibadah', [RekapKegiatanIbadahController::class, 'index'])
            ->middleware('izin:ibadah.rekap')
            ->name('rekap-kegiatan-ibadah.index');
        Route::get('/rekap-kegiatan-ibadah/koreksi/{anggotaKelas}', [RekapKegiatanIbadahController::class, 'showCorrection'])
            ->middleware('izin:ibadah.koreksi')
            ->name('rekap-kegiatan-ibadah.koreksi.show');
        Route::put('/rekap-kegiatan-ibadah/koreksi/{anggotaKelas}', [RekapKegiatanIbadahController::class, 'updateCorrection'])
            ->middleware('izin:ibadah.koreksi')
            ->name('rekap-kegiatan-ibadah.koreksi.update');

        Route::get('/ringkasan-kegiatan-ibadah-bulanan', RingkasanKegiatanIbadahBulananController::class)
            ->middleware('izin:ibadah.rekap')
            ->name('ringkasan-kegiatan-ibadah-bulanan');

        Route::get('/nilai-saya', NilaiSayaController::class)
            ->name('nilai-saya.index');
        Route::get('/survei-pembelajaran/{guruMataPelajaran}/{semester}', [SurveiPembelajaranController::class, 'show'])
            ->name('survei-pembelajaran.show');
        Route::post('/survei-pembelajaran/{guruMataPelajaran}/{semester}', [SurveiPembelajaranController::class, 'store'])
            ->name('survei-pembelajaran.store');

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
