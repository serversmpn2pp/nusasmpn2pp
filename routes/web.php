<?php

use App\Http\Controllers\AkademikAnakController;
use App\Http\Controllers\AksesUjianCbtController;
use App\Http\Controllers\AktivitasLoginController;
use App\Http\Controllers\AkunOrangTuaController;
use App\Http\Controllers\AkunPegawaiController;
use App\Http\Controllers\AkunSiswaController;
use App\Http\Controllers\AnggotaKelasController;
use App\Http\Controllers\AsesmenKelasCbtController;
use App\Http\Controllers\AturanSanksiPoinController;
use App\Http\Controllers\AutentikasiController;
use App\Http\Controllers\BarangController;
use App\Http\Controllers\BerandaController;
use App\Http\Controllers\BuktiLaporanPembinaanController;
use App\Http\Controllers\BuktiPelaksanaanSanksiController;
use App\Http\Controllers\CadanganDatabaseController;
use App\Http\Controllers\DashboardSaranaPrasaranaController;
use App\Http\Controllers\DokumenPoinSiswaController;
use App\Http\Controllers\FotoIdentitasController;
use App\Http\Controllers\GuruMataPelajaranController;
use App\Http\Controllers\HasilSurveiSayaController;
use App\Http\Controllers\ImportPenerimaanBarangController;
use App\Http\Controllers\InputNilaiController;
use App\Http\Controllers\JadwalKegiatanIbadahController;
use App\Http\Controllers\JadwalKelasSayaController;
use App\Http\Controllers\JadwalPelajaranController;
use App\Http\Controllers\JadwalPiketGuruController;
use App\Http\Controllers\JadwalPiketSayaController;
use App\Http\Controllers\JadwalSayaController;
use App\Http\Controllers\JadwalUjianTerpusatController;
use App\Http\Controllers\JamPelajaranController;
use App\Http\Controllers\JenisPelanggaranSiswaController;
use App\Http\Controllers\JenisPerangkatAjarController;
use App\Http\Controllers\KartuPegawaiController;
use App\Http\Controllers\KartuPelajarController;
use App\Http\Controllers\KatalogBarangController;
use App\Http\Controllers\KategoriBarangController;
use App\Http\Controllers\KategoriPembinaanSiswaController;
use App\Http\Controllers\KegiatanIbadahController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\KelasWaliController;
use App\Http\Controllers\KenaikanKelasController;
use App\Http\Controllers\KlarifikasiSiswaPembinaanController;
use App\Http\Controllers\KomponenNilaiController;
use App\Http\Controllers\KonfirmasiBerhalanganIbadahController;
use App\Http\Controllers\KoreksiKegiatanIbadahController;
use App\Http\Controllers\KoreksiManualUjianCbtController;
use App\Http\Controllers\KoreksiOtomatisUjianCbtController;
use App\Http\Controllers\LabelBarcodeInventarisController;
use App\Http\Controllers\LaporanAbsensiController;
use App\Http\Controllers\LaporanAbsensiPegawaiBulananController;
use App\Http\Controllers\LaporanInventarisBulananController;
use App\Http\Controllers\LaporanPembinaanSiswaController;
use App\Http\Controllers\LokasiBarangController;
use App\Http\Controllers\MataPelajaranController;
use App\Http\Controllers\MonitoringSurveiController;
use App\Http\Controllers\MonitoringUjianCbtController;
use App\Http\Controllers\MutasiStokBarangController;
use App\Http\Controllers\NilaiSayaController;
use App\Http\Controllers\NotifikasiAbsensiSiswaController;
use App\Http\Controllers\NotifikasiPenggunaController;
use App\Http\Controllers\PaketSoalUjianTerpusatController;
use App\Http\Controllers\PanitiaUjianTerpusatController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\PelaksanaanNilaiUjianTerpusatController;
use App\Http\Controllers\PelaksanaanUjianTerpusatController;
use App\Http\Controllers\PembagianPesertaUjianTerpusatController;
use App\Http\Controllers\PembinaanPoinAnakController;
use App\Http\Controllers\PemeriksaanPerangkatAjarController;
use App\Http\Controllers\PeminjamanBarangController;
use App\Http\Controllers\PendampinganSiswaController;
use App\Http\Controllers\PenempatanSiswaController;
use App\Http\Controllers\PenerimaanBarangController;
use App\Http\Controllers\PengajuanBarangController;
use App\Http\Controllers\PengajuanBarangSayaController;
use App\Http\Controllers\PengaturanAbsensiController;
use App\Http\Controllers\PengaturanAbsensiPegawaiController;
use App\Http\Controllers\PengaturanBatasProsesPelanggaranController;
use App\Http\Controllers\PengaturanBerhalanganIbadahController;
use App\Http\Controllers\PengaturanInventarisController;
use App\Http\Controllers\PengaturanPeringatanDiniPoinController;
use App\Http\Controllers\PengaturanPoinKeterlambatanController;
use App\Http\Controllers\PengembalianBarangController;
use App\Http\Controllers\PenguranganPoinSiswaController;
use App\Http\Controllers\PenugasanGuruWaliController;
use App\Http\Controllers\PeranController;
use App\Http\Controllers\PerangkatAjarSayaController;
use App\Http\Controllers\PergantianGuruMataPelajaranController;
use App\Http\Controllers\PeringatanDiniSiswaController;
use App\Http\Controllers\PertanyaanSurveiPembelajaranController;
use App\Http\Controllers\PesertaUjianCbtController;
use App\Http\Controllers\PiketKehadiranSiswaController;
use App\Http\Controllers\PresensiAnakController;
use App\Http\Controllers\PresensiUjianCbtController;
use App\Http\Controllers\ProfilOrangTuaController;
use App\Http\Controllers\ProfilPegawaiController;
use App\Http\Controllers\ProfilSiswaController;
use App\Http\Controllers\ProgressKasusSiswaController;
use App\Http\Controllers\PublikasiNilaiController;
use App\Http\Controllers\PusatCbtController;
use App\Http\Controllers\PusatVerifikasiPelanggaranController;
use App\Http\Controllers\RekapAbsensiHarianController;
use App\Http\Controllers\RekapAbsensiPegawaiHarianController;
use App\Http\Controllers\RekapBerhalanganIbadahController;
use App\Http\Controllers\RekapHasilUjianCbtController;
use App\Http\Controllers\RekapKegiatanIbadahController;
use App\Http\Controllers\RekapNilaiRaporController;
use App\Http\Controllers\RekapPeminjamanBarangController;
use App\Http\Controllers\RekapPoinSiswaController;
use App\Http\Controllers\RingkasanKegiatanIbadahBulananController;
use App\Http\Controllers\RuangKegiatanUjianCbtController;
use App\Http\Controllers\RuangUjianCbtController;
use App\Http\Controllers\SaksiLaporanPembinaanController;
use App\Http\Controllers\SaldoStokBarangController;
use App\Http\Controllers\SanksiPoinSiswaController;
use App\Http\Controllers\SatuanBarangController;
use App\Http\Controllers\ScanAbsensiController;
use App\Http\Controllers\ScanAbsensiPegawaiController;
use App\Http\Controllers\ScanBerhalanganIbadahController;
use App\Http\Controllers\ScanKegiatanIbadahController;
use App\Http\Controllers\SesiKegiatanUjianCbtController;
use App\Http\Controllers\SesiUjianCbtController;
use App\Http\Controllers\SiswaController;
use App\Http\Controllers\SiswaWaliSayaController;
use App\Http\Controllers\SkemaBobotNilaiController;
use App\Http\Controllers\SoalCbtController;
use App\Http\Controllers\SoalUjianCbtController;
use App\Http\Controllers\SumberPerolehanBarangController;
use App\Http\Controllers\SurveiPembelajaranController;
use App\Http\Controllers\TahunPelajaranController;
use App\Http\Controllers\TerapkanNilaiCbtController;
use App\Http\Controllers\TindakLanjutPembinaanSiswaController;
use App\Http\Controllers\TugasPengawasUjianController;
use App\Http\Controllers\UjianCbtController;
use App\Http\Controllers\UjianSayaController;
use App\Http\Controllers\UjianTerpusatController;
use App\Http\Controllers\UnitBarangController;
use App\Http\Controllers\VerifikasiPelanggaranSiswaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('beranda');
    }

    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AutentikasiController::class, 'createLogin'])->name('login');
    Route::post('login', [AutentikasiController::class, 'storeLogin'])->name('login.store');
});

Route::middleware(['auth', 'identitas_sesi', 'kata_sandi_bukan_default'])
    ->prefix('cbt')
    ->name('cbt.')
    ->group(function () {
        Route::post('keluar', [AksesUjianCbtController::class, 'logout'])->name('logout');
        Route::get('ujian', [AksesUjianCbtController::class, 'show'])->name('ujian.show');
        Route::post('ujian/mulai', [AksesUjianCbtController::class, 'mulai'])->name('ujian.mulai');
        Route::get('ujian/kerjakan', [AksesUjianCbtController::class, 'kerjakan'])->name('ujian.kerjakan');
        Route::post('ujian/jawaban', [AksesUjianCbtController::class, 'simpanJawaban'])->name('ujian.jawaban');
        Route::post('ujian/simpan', [AksesUjianCbtController::class, 'simpan'])->name('ujian.simpan');
        Route::get('ujian/selesai', [AksesUjianCbtController::class, 'selesai'])->name('ujian.selesai');
    });

Route::middleware(['auth', 'identitas_sesi'])->group(function () {
    Route::post('logout', [AutentikasiController::class, 'logout'])->name('logout');
    Route::get('ganti-kata-sandi', [AutentikasiController::class, 'editKataSandi'])->name('kata-sandi.edit');
    Route::put('ganti-kata-sandi', [AutentikasiController::class, 'updateKataSandi'])->name('kata-sandi.update');

    Route::middleware('kata_sandi_bukan_default')->group(function () {
        Route::get('notifikasi', [NotifikasiPenggunaController::class, 'index'])->name('notifikasi.index');
        Route::get('notifikasi/ringkasan', [NotifikasiPenggunaController::class, 'ringkasan'])->name('notifikasi.ringkasan');
        Route::patch('notifikasi/baca-semua', [NotifikasiPenggunaController::class, 'tandaiSemuaDibaca'])->name('notifikasi.baca-semua');
        Route::post('notifikasi/{notifikasiPengguna}/buka', [NotifikasiPenggunaController::class, 'buka'])->name('notifikasi.buka');
        Route::patch('notifikasi/{notifikasiPengguna}/baca', [NotifikasiPenggunaController::class, 'tandaiDibaca'])->name('notifikasi.baca');

        Route::get('beranda', [BerandaController::class, 'index'])
            ->middleware('izin:beranda.akses')
            ->name('beranda');

        Route::get('presensi-anak', [PresensiAnakController::class, 'index'])
            ->name('presensi-anak.index');
        Route::get('akademik-anak', [AkademikAnakController::class, 'index'])
            ->name('akademik-anak.index');
        Route::get('pembinaan-poin-anak', [PembinaanPoinAnakController::class, 'index'])
            ->name('pembinaan-poin-anak.index');
        Route::get('pembinaan-poin-anak/{laporanPembinaanSiswa}', [PembinaanPoinAnakController::class, 'show'])
            ->name('pembinaan-poin-anak.show');
        Route::get('profil-orang-tua', [ProfilOrangTuaController::class, 'edit'])
            ->name('profil-orang-tua.edit');
        Route::put('profil-orang-tua', [ProfilOrangTuaController::class, 'update'])
            ->name('profil-orang-tua.update');
        Route::get('profil-siswa', [ProfilSiswaController::class, 'show'])
            ->name('profil-siswa.show');

        Route::get('progress-kasus-saya', [ProgressKasusSiswaController::class, 'index'])
            ->name('progress-kasus-siswa.index');
        Route::get('progress-kasus-saya/{laporanPembinaanSiswa}', [ProgressKasusSiswaController::class, 'show'])
            ->name('progress-kasus-siswa.show');
        Route::get('nilai-saya', [NilaiSayaController::class, 'index'])
            ->name('nilai-saya.index');
        Route::get('ujian-saya', [UjianSayaController::class, 'index'])
            ->name('ujian-saya.index');
        Route::post('ujian-saya/{pesertaUjianCbt}/masuk', [AksesUjianCbtController::class, 'masukDariAkunSiswa'])
            ->middleware('throttle:10,1')
            ->name('ujian-saya.masuk');
        Route::get('survei-pembelajaran/{guruMataPelajaran}/{semester}', [SurveiPembelajaranController::class, 'create'])
            ->name('survei-pembelajaran.create');
        Route::post('survei-pembelajaran/{guruMataPelajaran}/{semester}', [SurveiPembelajaranController::class, 'store'])
            ->name('survei-pembelajaran.store');
        Route::get('hasil-survei-saya', [HasilSurveiSayaController::class, 'index'])
            ->middleware('izin:survei.hasil_pribadi')
            ->name('hasil-survei-saya.index');
        Route::get('monitoring-survei', [MonitoringSurveiController::class, 'index'])
            ->middleware('izin:survei.monitor')
            ->name('monitoring-survei.index');

        Route::middleware('izin:akun.lihat,akun.kelola')->group(function () {
            Route::get('akun-pegawai', [AkunPegawaiController::class, 'index'])->name('akun-pegawai.index');
        });

        Route::middleware('izin:akun_siswa.lihat,akun_siswa.kelola,akun_siswa.cetak')->group(function () {
            Route::get('akun-siswa', [AkunSiswaController::class, 'index'])->name('akun-siswa.index');
        });

        Route::middleware('izin:akun_siswa.cetak,akun_siswa.kelola')->group(function () {
            Route::get('akun-siswa/kelas/{kelas}/cetak', [AkunSiswaController::class, 'cetak'])->name('akun-siswa.cetak');
        });

        Route::middleware('izin:akun.kelola')->group(function () {
            Route::post('akun-pegawai/buat-massal', [AkunPegawaiController::class, 'storeMassal'])->name('akun-pegawai.buat-massal');
            Route::post('akun-pegawai/{pegawai}', [AkunPegawaiController::class, 'store'])->name('akun-pegawai.store');
            Route::patch('akun-pegawai/{pengguna}/reset-password', [AkunPegawaiController::class, 'resetPassword'])->name('akun-pegawai.reset-password');
            Route::patch('akun-pegawai/{pengguna}/status', [AkunPegawaiController::class, 'ubahStatus'])->name('akun-pegawai.status');
            Route::patch('akun-pegawai/{pengguna}/peran', [AkunPegawaiController::class, 'updatePeran'])->name('akun-pegawai.peran.update');
        });

        Route::middleware('izin:akun_siswa.kelola')->group(function () {
            Route::post('akun-siswa/kelas/{kelas}/buat-massal', [AkunSiswaController::class, 'storeMassal'])->name('akun-siswa.buat-massal');
            Route::post('akun-siswa/{siswa}', [AkunSiswaController::class, 'store'])->name('akun-siswa.store');
            Route::patch('akun-siswa/{pengguna}/reset-password', [AkunSiswaController::class, 'resetPassword'])->name('akun-siswa.reset-password');
            Route::patch('akun-siswa/{pengguna}/status', [AkunSiswaController::class, 'ubahStatus'])->name('akun-siswa.status');
        });

        Route::middleware('izin:akun_orang_tua.lihat,akun_orang_tua.kelola,akun_orang_tua.cetak')->group(function () {
            Route::get('akun-orang-tua', [AkunOrangTuaController::class, 'index'])->name('akun-orang-tua.index');
        });

        Route::middleware('izin:akun_orang_tua.cetak,akun_orang_tua.kelola')->group(function () {
            Route::get('akun-orang-tua/kelas/{kelas}/cetak', [AkunOrangTuaController::class, 'cetak'])->name('akun-orang-tua.cetak');
        });

        Route::middleware('izin:akun_orang_tua.kelola')->group(function () {
            Route::post('akun-orang-tua/kelas/{kelas}/buat-massal', [AkunOrangTuaController::class, 'storeMassal'])->name('akun-orang-tua.buat-massal');
            Route::post('akun-orang-tua/{siswa}', [AkunOrangTuaController::class, 'store'])->name('akun-orang-tua.store');
            Route::patch('akun-orang-tua/{pengguna}/reset-password', [AkunOrangTuaController::class, 'resetPassword'])->name('akun-orang-tua.reset-password');
            Route::patch('akun-orang-tua/{pengguna}/status', [AkunOrangTuaController::class, 'ubahStatus'])->name('akun-orang-tua.status');
        });

        Route::resource('peran', PeranController::class)
            ->only(['index'])
            ->middleware('izin:peran.lihat,peran.kelola');
        Route::resource('peran', PeranController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:peran.kelola');

        Route::get('aktivitas-login', [AktivitasLoginController::class, 'index'])
            ->middleware('izin:aktivitas_login.lihat')
            ->name('aktivitas-login.index');

        Route::middleware('izin:cadangan_database.kelola')->group(function () {
            Route::get('cadangan-database', [CadanganDatabaseController::class, 'index'])->name('cadangan-database.index');
            Route::post('cadangan-database', [CadanganDatabaseController::class, 'store'])->name('cadangan-database.store');
            Route::post('cadangan-database/pulihkan-unggahan', [CadanganDatabaseController::class, 'restoreUpload'])->name('cadangan-database.restore-upload');
            Route::get('cadangan-database/{namaFile}/unduh', [CadanganDatabaseController::class, 'download'])->name('cadangan-database.download');
            Route::post('cadangan-database/{namaFile}/pulihkan', [CadanganDatabaseController::class, 'restore'])->name('cadangan-database.restore');
            Route::delete('cadangan-database/{namaFile}', [CadanganDatabaseController::class, 'destroy'])->name('cadangan-database.destroy');
        });

        Route::middleware('izin:pegawai.kelola')->group(function () {
            Route::get('pegawai/import', [PegawaiController::class, 'createImport'])->name('pegawai.import.create');
            Route::post('pegawai/import', [PegawaiController::class, 'storeImport'])->name('pegawai.import.store');
        });
        Route::middleware('izin:pegawai.profil')->group(function () {
            Route::get('profil-saya', [ProfilPegawaiController::class, 'edit'])->name('profil-pegawai.edit');
            Route::put('profil-saya', [ProfilPegawaiController::class, 'update'])->name('profil-pegawai.update');
            Route::post('profil-saya/foto', [ProfilPegawaiController::class, 'updateFoto'])->name('profil-pegawai.foto.update');
        });
        Route::post('pegawai/{pegawai}/foto', [PegawaiController::class, 'updateFoto'])
            ->middleware('izin:pegawai.kelola')
            ->name('pegawai.foto.update');
        Route::get('foto-identitas', [FotoIdentitasController::class, 'index'])
            ->middleware('izin:siswa.kelola,pegawai.kelola')
            ->name('foto-identitas.index');
        Route::resource('pegawai', PegawaiController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:pegawai.kelola');
        Route::resource('pegawai', PegawaiController::class)
            ->only(['index', 'show'])
            ->middleware('izin:pegawai.lihat,pegawai.kelola');

        Route::middleware('izin:siswa.kelola')->group(function () {
            Route::get('siswa/import', [SiswaController::class, 'createImport'])->name('siswa.import.create');
            Route::post('siswa/import', [SiswaController::class, 'storeImport'])->name('siswa.import.store');
            Route::post('siswa/{siswa}/foto', [SiswaController::class, 'updateFoto'])->name('siswa.foto.update');
        });
        Route::resource('siswa', SiswaController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:siswa.kelola');
        Route::resource('siswa', SiswaController::class)
            ->only(['index', 'show'])
            ->middleware('izin:siswa.lihat,siswa.kelola');

        Route::get('kartu-pelajar', [KartuPelajarController::class, 'index'])
            ->middleware('izin:kartu_pelajar.lihat,kartu_pelajar.cetak')
            ->name('kartu-pelajar.index');
        Route::get('kartu-pegawai', [KartuPegawaiController::class, 'index'])
            ->middleware('izin:pegawai.lihat,pegawai.kelola')
            ->name('kartu-pegawai.index');

        Route::resource('tahun-pelajaran', TahunPelajaranController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:tahun_pelajaran.kelola');
        Route::resource('tahun-pelajaran', TahunPelajaranController::class)
            ->only(['index', 'show'])
            ->middleware('izin:tahun_pelajaran.lihat,tahun_pelajaran.kelola');

        Route::resource('mata-pelajaran', MataPelajaranController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:mata_pelajaran.kelola');
        Route::resource('mata-pelajaran', MataPelajaranController::class)
            ->only(['index', 'show'])
            ->middleware('izin:mata_pelajaran.lihat,mata_pelajaran.kelola');

        Route::resource('guru-mata-pelajaran', GuruMataPelajaranController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:guru_mapel.kelola');
        Route::get(
            'guru-mata-pelajaran/{guruMataPelajaran}/ganti-guru',
            [PergantianGuruMataPelajaranController::class, 'edit'],
        )
            ->middleware('izin:guru_mapel.kelola')
            ->name('guru-mata-pelajaran.ganti-guru');
        Route::put(
            'guru-mata-pelajaran/{guruMataPelajaran}/ganti-guru',
            [PergantianGuruMataPelajaranController::class, 'update'],
        )
            ->middleware('izin:guru_mapel.kelola')
            ->name('guru-mata-pelajaran.simpan-pergantian');
        Route::resource('guru-mata-pelajaran', GuruMataPelajaranController::class)
            ->only(['index', 'show'])
            ->middleware('izin:guru_mapel.lihat,guru_mapel.kelola');
        Route::resource('jam-pelajaran', JamPelajaranController::class)
            ->middleware('admin');
        Route::get('jadwal-pelajaran/susun', [JadwalPelajaranController::class, 'susun'])
            ->middleware('izin:jadwal.kelola')
            ->name('jadwal-pelajaran.susun');
        Route::post('jadwal-pelajaran/susun', [JadwalPelajaranController::class, 'simpanMassal'])
            ->middleware('izin:jadwal.kelola')
            ->name('jadwal-pelajaran.simpan-massal');
        Route::resource('jadwal-pelajaran', JadwalPelajaranController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:jadwal.kelola');
        Route::resource('jadwal-pelajaran', JadwalPelajaranController::class)
            ->only(['index', 'show'])
            ->middleware('izin:jadwal.lihat,jadwal.kelola');
        Route::get('jadwal-saya', [JadwalSayaController::class, 'index'])
            ->middleware('izin:jadwal.pribadi')
            ->name('jadwal-saya.index');
        Route::get('jadwal-kelas-saya', [JadwalKelasSayaController::class, 'index'])
            ->middleware('izin:jadwal.lihat')
            ->name('jadwal-kelas-saya.index');
        Route::middleware('akun_pegawai')->group(function () {
            Route::get('tugas-pengawas-ujian', [TugasPengawasUjianController::class, 'index'])->name('tugas-pengawas-ujian.index');
            Route::get('tugas-pengawas-ujian/{ruangUjianCbt}', [TugasPengawasUjianController::class, 'show'])->name('tugas-pengawas-ujian.show');
            Route::post('tugas-pengawas-ujian/{ruangUjianCbt}/bukti', [TugasPengawasUjianController::class, 'storeBukti'])->name('tugas-pengawas-ujian.bukti.store');
            Route::get('tugas-pengawas-ujian/{ruangUjianCbt}/bukti/{buktiRuangUjianCbt}', [TugasPengawasUjianController::class, 'lihatBukti'])->name('tugas-pengawas-ujian.bukti.show');
            Route::delete('tugas-pengawas-ujian/{ruangUjianCbt}/bukti/{buktiRuangUjianCbt}', [TugasPengawasUjianController::class, 'destroyBukti'])->name('tugas-pengawas-ujian.bukti.destroy');
            Route::patch('tugas-pengawas-ujian/{ruangUjianCbt}/kirim', [TugasPengawasUjianController::class, 'kirim'])->name('tugas-pengawas-ujian.kirim');
        });
        Route::patch('tugas-pengawas-ujian/{ruangUjianCbt}/periksa', [TugasPengawasUjianController::class, 'periksa'])
            ->middleware('izin:cbt.panitia,cbt.kelola')
            ->name('tugas-pengawas-ujian.periksa');
        Route::get('kelas-wali', [KelasWaliController::class, 'index'])
            ->middleware('izin:kelas.lihat')
            ->name('kelas-wali.index');
        Route::resource('jenis-perangkat-ajar', JenisPerangkatAjarController::class)
            ->middleware('izin:perangkat_ajar.jenis_kelola');
        Route::resource('pertanyaan-survei-pembelajaran', PertanyaanSurveiPembelajaranController::class)
            ->only(['index', 'create', 'store', 'edit', 'update'])
            ->middleware('izin:survei.pertanyaan_kelola');
        Route::patch(
            'pertanyaan-survei-pembelajaran/{pertanyaanSurveiPembelajaran}/status',
            [PertanyaanSurveiPembelajaranController::class, 'ubahStatus'],
        )
            ->middleware('izin:survei.pertanyaan_kelola')
            ->name('pertanyaan-survei-pembelajaran.status');
        Route::get('pusat-cbt', [PusatCbtController::class, 'index'])
            ->middleware('izin:cbt.lihat,cbt.kelola,cbt.soal_kelola,cbt.presensi,cbt.asesmen_kelola,cbt.panitia,cbt.terpusat_lihat')
            ->name('pusat-cbt.index');
        Route::middleware('izin:cbt.soal_kelola,cbt.kelola,cbt.panitia,cbt.terpusat_lihat')->group(function () {
            Route::get('paket-soal-ujian-terpusat', [PaketSoalUjianTerpusatController::class, 'index'])->name('paket-soal-terpusat.index');
            Route::get('paket-soal-ujian-terpusat/{jadwalUjianCbt}', [PaketSoalUjianTerpusatController::class, 'show'])->name('paket-soal-terpusat.show');
        });
        Route::put('paket-soal-ujian-terpusat/{jadwalUjianCbt}', [PaketSoalUjianTerpusatController::class, 'update'])
            ->middleware('izin:cbt.soal_kelola,cbt.kelola')
            ->name('paket-soal-terpusat.update');
        Route::resource('asesmen-kelas-cbt', AsesmenKelasCbtController::class)
            ->parameters(['asesmen-kelas-cbt' => 'ujianCbt'])
            ->middleware('izin:cbt.asesmen_kelola,cbt.kelola');
        Route::middleware('izin:cbt.panitia,cbt.terpusat_lihat,cbt.kelola')->group(function () {
            Route::get('ujian-terpusat', [UjianTerpusatController::class, 'index'])->name('ujian-terpusat.index');
            Route::get('ujian-terpusat/{kegiatanUjianCbt}', [UjianTerpusatController::class, 'show'])->name('ujian-terpusat.show');
            Route::get('ujian-terpusat/{kegiatanUjianCbt}/jadwal-peserta', [PelaksanaanUjianTerpusatController::class, 'index'])->name('ujian-terpusat.pelaksanaan.index');
            Route::get('ujian-terpusat/{kegiatanUjianCbt}/pembagian-peserta/{kelompokPeserta}', [PembagianPesertaUjianTerpusatController::class, 'show'])->name('ujian-terpusat.peserta.show');
            Route::get('ujian-terpusat/{kegiatanUjianCbt}/pembagian-peserta/{kelompokPeserta}/ruang/{ruangKegiatanUjianCbt}/label-meja', [PembagianPesertaUjianTerpusatController::class, 'cetakLabelMeja'])->name('ujian-terpusat.peserta.label-meja');
            Route::get('ujian-terpusat/{kegiatanUjianCbt}/jadwal/{jadwalUjianCbt}/ruang/{ruangKegiatanUjianCbt}/dokumen', [PelaksanaanNilaiUjianTerpusatController::class, 'cetakDokumenRuang'])->name('ujian-terpusat.dokumen-ruang.cetak');
        });
        Route::get('ujian-terpusat/{kegiatanUjianCbt}/pelaksanaan-nilai', [PelaksanaanNilaiUjianTerpusatController::class, 'index'])
            ->middleware('izin:cbt.soal_kelola,cbt.panitia,cbt.terpusat_lihat,cbt.kelola')
            ->name('ujian-terpusat.pelaksanaan-nilai.index');
        Route::get('ujian-terpusat/{kegiatanUjianCbt}/nilai-hasil', [PelaksanaanNilaiUjianTerpusatController::class, 'hasil'])
            ->middleware('izin:cbt.soal_kelola,cbt.panitia,cbt.terpusat_lihat,cbt.kelola')
            ->name('ujian-terpusat.nilai-hasil.index');
        Route::middleware('izin:cbt.kelola')->group(function () {
            Route::get('ujian-terpusat-baru', [UjianTerpusatController::class, 'create'])->name('ujian-terpusat.create');
            Route::post('ujian-terpusat', [UjianTerpusatController::class, 'store'])->name('ujian-terpusat.store');
            Route::get('ujian-terpusat/{kegiatanUjianCbt}/edit', [UjianTerpusatController::class, 'edit'])->name('ujian-terpusat.edit');
            Route::put('ujian-terpusat/{kegiatanUjianCbt}', [UjianTerpusatController::class, 'update'])->name('ujian-terpusat.update');
            Route::delete('ujian-terpusat/{kegiatanUjianCbt}', [UjianTerpusatController::class, 'destroy'])->name('ujian-terpusat.destroy');
            Route::post('ujian-terpusat/{kegiatanUjianCbt}/panitia', [PanitiaUjianTerpusatController::class, 'store'])->name('ujian-terpusat.panitia.store');
            Route::delete('ujian-terpusat/{kegiatanUjianCbt}/panitia/{panitiaUjianCbt}', [PanitiaUjianTerpusatController::class, 'destroy'])->name('ujian-terpusat.panitia.destroy');
        });
        Route::middleware('izin:cbt.panitia,cbt.kelola')->group(function () {
            Route::post('ujian-terpusat/{kegiatanUjianCbt}/sesi', [SesiKegiatanUjianCbtController::class, 'store'])->name('ujian-terpusat.sesi.store');
            Route::put('ujian-terpusat/{kegiatanUjianCbt}/sesi/{sesiKegiatanUjianCbt}', [SesiKegiatanUjianCbtController::class, 'update'])->name('ujian-terpusat.sesi.update');
            Route::delete('ujian-terpusat/{kegiatanUjianCbt}/sesi/{sesiKegiatanUjianCbt}', [SesiKegiatanUjianCbtController::class, 'destroy'])->name('ujian-terpusat.sesi.destroy');
            Route::post('ujian-terpusat/{kegiatanUjianCbt}/ruang', [RuangKegiatanUjianCbtController::class, 'store'])->name('ujian-terpusat.ruang.store');
            Route::put('ujian-terpusat/{kegiatanUjianCbt}/ruang/{ruangKegiatanUjianCbt}', [RuangKegiatanUjianCbtController::class, 'update'])->name('ujian-terpusat.ruang.update');
            Route::delete('ujian-terpusat/{kegiatanUjianCbt}/ruang/{ruangKegiatanUjianCbt}', [RuangKegiatanUjianCbtController::class, 'destroy'])->name('ujian-terpusat.ruang.destroy');
            Route::post('ujian-terpusat/{kegiatanUjianCbt}/penetapan-ruang', [PembagianPesertaUjianTerpusatController::class, 'atur'])->name('ujian-terpusat.peserta.atur');
            Route::post('ujian-terpusat/{kegiatanUjianCbt}/pembagian-peserta/{kelompokPeserta}/bangkitkan', [PembagianPesertaUjianTerpusatController::class, 'bangkitkan'])->name('ujian-terpusat.peserta.bangkitkan');
            Route::delete('ujian-terpusat/{kegiatanUjianCbt}/pembagian-peserta/{kelompokPeserta}', [PembagianPesertaUjianTerpusatController::class, 'destroy'])->name('ujian-terpusat.peserta.destroy');
            Route::post('ujian-terpusat/{kegiatanUjianCbt}/jadwal', [JadwalUjianTerpusatController::class, 'store'])->name('ujian-terpusat.jadwal.store');
            Route::put('ujian-terpusat/{kegiatanUjianCbt}/jadwal/{jadwalUjianCbt}', [JadwalUjianTerpusatController::class, 'update'])->name('ujian-terpusat.jadwal.update');
            Route::delete('ujian-terpusat/{kegiatanUjianCbt}/jadwal/{jadwalUjianCbt}', [JadwalUjianTerpusatController::class, 'destroy'])->name('ujian-terpusat.jadwal.destroy');
            Route::put('ujian-terpusat/{kegiatanUjianCbt}/jadwal/{jadwalUjianCbt}/pengawas/{ruangKegiatanUjianCbt}', [PelaksanaanNilaiUjianTerpusatController::class, 'updatePengawas'])->name('ujian-terpusat.pengawas.update');
            Route::patch('ujian-terpusat/{kegiatanUjianCbt}/jadwal/{jadwalUjianCbt}/pengawas/{ruangKegiatanUjianCbt}/ganti', [PelaksanaanNilaiUjianTerpusatController::class, 'gantiPengawas'])->name('ujian-terpusat.pengawas.ganti');
        });
        Route::resource('ujian-cbt', UjianCbtController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:cbt.kelola');
        Route::middleware(['izin:cbt.asesmen_kelola,cbt.kelola', 'akses_ujian_cbt'])->group(function () {
            Route::get('ujian-cbt/{ujianCbt}/soal', [SoalUjianCbtController::class, 'edit'])->name('ujian-cbt.soal.edit');
            Route::put('ujian-cbt/{ujianCbt}/soal', [SoalUjianCbtController::class, 'update'])->name('ujian-cbt.soal.update');
        });
        Route::middleware(['izin:cbt.asesmen_kelola,cbt.soal_kelola,cbt.panitia,cbt.terpusat_lihat,cbt.kelola', 'akses_ujian_cbt'])->group(function () {
            Route::get('ujian-cbt/{ujianCbt}/monitoring', [MonitoringUjianCbtController::class, 'index'])->name('ujian-cbt.monitoring.index');
            Route::get('ujian-cbt/{ujianCbt}/hasil', [RekapHasilUjianCbtController::class, 'index'])->name('ujian-cbt.hasil.index');
        });
        Route::middleware(['izin:cbt.asesmen_kelola,cbt.soal_kelola,cbt.kelola', 'akses_ujian_cbt'])->group(function () {
            Route::post('ujian-cbt/{ujianCbt}/koreksi-otomatis', [KoreksiOtomatisUjianCbtController::class, 'store'])->name('ujian-cbt.koreksi-otomatis.store');
            Route::get('ujian-cbt/{ujianCbt}/koreksi-manual', [KoreksiManualUjianCbtController::class, 'index'])->name('ujian-cbt.koreksi-manual.index');
            Route::put('ujian-cbt/{ujianCbt}/koreksi-manual', [KoreksiManualUjianCbtController::class, 'update'])->name('ujian-cbt.koreksi-manual.update');
            Route::post('ujian-cbt/{ujianCbt}/terapkan-nilai', [TerapkanNilaiCbtController::class, 'store'])->name('ujian-cbt.terapkan-nilai.store');
        });
        Route::middleware(['izin:cbt.kelola', 'ujian_terpusat'])->group(function () {
            Route::get('ujian-cbt/{ujianCbt}/peserta', [PesertaUjianCbtController::class, 'index'])->name('ujian-cbt.peserta.index');
            Route::post('ujian-cbt/{ujianCbt}/peserta/generate', [PesertaUjianCbtController::class, 'storeMassal'])->name('ujian-cbt.peserta.generate');
            Route::put('ujian-cbt/{ujianCbt}/peserta', [PesertaUjianCbtController::class, 'update'])->name('ujian-cbt.peserta.update');
            Route::get('ujian-cbt/{ujianCbt}/ruang', [RuangUjianCbtController::class, 'index'])->name('ujian-cbt.ruang.index');
            Route::get('ujian-cbt/{ujianCbt}/ruang/cetak', [RuangUjianCbtController::class, 'cetak'])->name('ujian-cbt.ruang.cetak');
            Route::post('ujian-cbt/{ujianCbt}/ruang', [RuangUjianCbtController::class, 'store'])->name('ujian-cbt.ruang.store');
            Route::post('ujian-cbt/{ujianCbt}/ruang/generate', [RuangUjianCbtController::class, 'storeMassal'])->name('ujian-cbt.ruang.generate');
            Route::post('ujian-cbt/{ujianCbt}/ruang/bagi-otomatis', [RuangUjianCbtController::class, 'bagiOtomatis'])->name('ujian-cbt.ruang.bagi-otomatis');
            Route::put('ujian-cbt/{ujianCbt}/ruang/peserta', [RuangUjianCbtController::class, 'updatePeserta'])->name('ujian-cbt.ruang.peserta.update');
            Route::post('ujian-cbt/{ujianCbt}/ruang/{ruangUjianCbt}/bukti', [RuangUjianCbtController::class, 'updateBukti'])->name('ujian-cbt.ruang.bukti.update');
            Route::get('ujian-cbt/{ujianCbt}/ruang/{ruangUjianCbt}/bukti/{jenis}', [RuangUjianCbtController::class, 'downloadBukti'])->name('ujian-cbt.ruang.bukti.download');
            Route::delete('ujian-cbt/{ujianCbt}/ruang/{ruangUjianCbt}/bukti/{jenis}', [RuangUjianCbtController::class, 'destroyBukti'])->name('ujian-cbt.ruang.bukti.destroy');
            Route::put('ujian-cbt/{ujianCbt}/ruang/{ruangUjianCbt}', [RuangUjianCbtController::class, 'update'])->name('ujian-cbt.ruang.update');
            Route::delete('ujian-cbt/{ujianCbt}/ruang/{ruangUjianCbt}', [RuangUjianCbtController::class, 'destroy'])->name('ujian-cbt.ruang.destroy');
            Route::put('ujian-cbt/{ujianCbt}/ruang/{ruangUjianCbt}/kunci', [RuangUjianCbtController::class, 'kunci'])->name('ujian-cbt.ruang.kunci');
            Route::put('ujian-cbt/{ujianCbt}/ruang/{ruangUjianCbt}/buka-kunci', [RuangUjianCbtController::class, 'bukaKunci'])->name('ujian-cbt.ruang.buka-kunci');
            Route::post('ujian-cbt/{ujianCbt}/sesi', [SesiUjianCbtController::class, 'store'])->name('ujian-cbt.sesi.store');
            Route::put('ujian-cbt/{ujianCbt}/sesi/{sesiUjianCbt}', [SesiUjianCbtController::class, 'update'])->name('ujian-cbt.sesi.update');
            Route::delete('ujian-cbt/{ujianCbt}/sesi/{sesiUjianCbt}', [SesiUjianCbtController::class, 'destroy'])->name('ujian-cbt.sesi.destroy');
        });
        Route::middleware('izin:cbt.presensi,cbt.kelola')->group(function () {
            Route::get('presensi-ujian-cbt', [PresensiUjianCbtController::class, 'index'])->name('presensi-ujian-cbt.index');
            Route::get('presensi-ujian-cbt/{ujianCbt}/{ruangUjianCbt}', [PresensiUjianCbtController::class, 'show'])->name('presensi-ujian-cbt.show');
            Route::post('presensi-ujian-cbt/{ujianCbt}/{ruangUjianCbt}/scan', [PresensiUjianCbtController::class, 'scan'])->name('presensi-ujian-cbt.scan');
            Route::put('presensi-ujian-cbt/{ujianCbt}/{ruangUjianCbt}/peserta/{pesertaUjianCbt}', [PresensiUjianCbtController::class, 'updateManual'])->name('presensi-ujian-cbt.manual');
        });
        Route::get('ujian-cbt', [UjianCbtController::class, 'index'])
            ->middleware('izin:cbt.lihat,cbt.kelola,cbt.soal_kelola,cbt.presensi,cbt.asesmen_kelola,cbt.panitia,cbt.terpusat_lihat')
            ->name('ujian-cbt.index');
        Route::get('ujian-cbt/{ujianCbt}', [UjianCbtController::class, 'show'])
            ->middleware('izin:cbt.lihat,cbt.kelola')
            ->name('ujian-cbt.show');
        Route::resource('soal-cbt', SoalCbtController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:cbt.kelola,cbt.soal_kelola');
        Route::resource('soal-cbt', SoalCbtController::class)
            ->only(['index', 'show'])
            ->middleware('izin:cbt.lihat,cbt.kelola,cbt.soal_kelola');
        Route::middleware('izin:perangkat_ajar.upload')->group(function () {
            Route::get('perangkat-ajar-saya', [PerangkatAjarSayaController::class, 'index'])->name('perangkat-ajar-saya.index');
            Route::get('perangkat-ajar-saya/create', [PerangkatAjarSayaController::class, 'create'])->name('perangkat-ajar-saya.create');
            Route::post('perangkat-ajar-saya', [PerangkatAjarSayaController::class, 'store'])->name('perangkat-ajar-saya.store');
            Route::get('perangkat-ajar-saya/{perangkatAjar}/edit', [PerangkatAjarSayaController::class, 'edit'])->name('perangkat-ajar-saya.edit');
            Route::put('perangkat-ajar-saya/{perangkatAjar}', [PerangkatAjarSayaController::class, 'update'])->name('perangkat-ajar-saya.update');
        });
        Route::middleware('izin:perangkat_ajar.upload,perangkat_ajar.lihat,perangkat_ajar.periksa')->group(function () {
            Route::get('perangkat-ajar-saya/{perangkatAjar}', [PerangkatAjarSayaController::class, 'show'])->name('perangkat-ajar-saya.show');
            Route::get('perangkat-ajar-saya/{perangkatAjar}/unduh', [PerangkatAjarSayaController::class, 'download'])->name('perangkat-ajar-saya.download');
            Route::get('riwayat-file-perangkat-ajar/{riwayatFilePerangkatAjar}/unduh', [PerangkatAjarSayaController::class, 'downloadRiwayat'])->name('perangkat-ajar-saya.download-riwayat');
        });
        Route::middleware('izin:perangkat_ajar.lihat,perangkat_ajar.periksa')->group(function () {
            Route::get('pemeriksaan-perangkat-ajar', [PemeriksaanPerangkatAjarController::class, 'index'])->name('pemeriksaan-perangkat-ajar.index');
            Route::get('pemeriksaan-perangkat-ajar/guru/{pegawai}', [PemeriksaanPerangkatAjarController::class, 'show'])->name('pemeriksaan-perangkat-ajar.show');
            Route::get('pemeriksaan-perangkat-ajar/dokumen/{perangkatAjar}/pratinjau', [PemeriksaanPerangkatAjarController::class, 'preview'])->name('pemeriksaan-perangkat-ajar.preview');
        });
        Route::middleware('izin:perangkat_ajar.periksa')->group(function () {
            Route::get('pemeriksaan-perangkat-ajar/dokumen/{perangkatAjar}/edit', [PemeriksaanPerangkatAjarController::class, 'edit'])->name('pemeriksaan-perangkat-ajar.edit');
            Route::put('pemeriksaan-perangkat-ajar/dokumen/{perangkatAjar}', [PemeriksaanPerangkatAjarController::class, 'update'])->name('pemeriksaan-perangkat-ajar.update');
        });

        Route::resource('barang', BarangController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:barang.kelola');
        Route::get('katalog-barang', [KatalogBarangController::class, 'index'])
            ->middleware('akun_pegawai')
            ->name('katalog-barang.index');
        Route::middleware('akun_pegawai')->group(function () {
            Route::get('pengajuan-barang-saya', [PengajuanBarangSayaController::class, 'index'])->name('pengajuan-barang-saya.index');
            Route::get('pengajuan-barang-saya/create/{barang}', [PengajuanBarangSayaController::class, 'create'])->name('pengajuan-barang-saya.create');
            Route::post('pengajuan-barang-saya', [PengajuanBarangSayaController::class, 'store'])->name('pengajuan-barang-saya.store');
            Route::get('pengajuan-barang-saya/{pengajuanBarang}', [PengajuanBarangSayaController::class, 'show'])->name('pengajuan-barang-saya.show');
            Route::patch('pengajuan-barang-saya/{pengajuanBarang}/batalkan', [PengajuanBarangSayaController::class, 'batalkan'])->name('pengajuan-barang-saya.batalkan');
        });
        Route::get('dashboard-sarana-prasarana', [DashboardSaranaPrasaranaController::class, 'index'])
            ->middleware('izin:barang.lihat,barang.kelola,barang.peminjaman_kelola')
            ->name('dashboard-sarana-prasarana.index');
        Route::get('laporan-inventaris-bulanan', [LaporanInventarisBulananController::class, 'index'])
            ->middleware('izin:barang.lihat,barang.kelola')
            ->name('laporan-inventaris-bulanan.index');
        Route::get('laporan-inventaris-bulanan/cetak', [LaporanInventarisBulananController::class, 'cetak'])
            ->middleware('izin:barang.lihat,barang.kelola')
            ->name('laporan-inventaris-bulanan.cetak');
        Route::middleware('izin:barang.kelola')->group(function () {
            Route::get('penerimaan-barang/import', [ImportPenerimaanBarangController::class, 'create'])->name('penerimaan-barang.import.create');
            Route::get('penerimaan-barang/import/template', [ImportPenerimaanBarangController::class, 'template'])->name('penerimaan-barang.import.template');
            Route::post('penerimaan-barang/import/pratinjau', [ImportPenerimaanBarangController::class, 'unggah'])->name('penerimaan-barang.import.unggah');
            Route::get('penerimaan-barang/import/pratinjau/{token}', [ImportPenerimaanBarangController::class, 'pratinjau'])->name('penerimaan-barang.import.pratinjau');
            Route::post('penerimaan-barang/import/konfirmasi', [ImportPenerimaanBarangController::class, 'konfirmasi'])->name('penerimaan-barang.import.konfirmasi');
        });
        Route::resource('penerimaan-barang', PenerimaanBarangController::class)
            ->only(['create', 'store'])
            ->middleware('izin:barang.kelola');
        Route::resource('penerimaan-barang', PenerimaanBarangController::class)
            ->only(['index', 'show'])
            ->middleware('izin:barang.lihat,barang.kelola');
        Route::resource('barang', BarangController::class)
            ->only(['index', 'show'])
            ->middleware('izin:barang.lihat,barang.kelola');
        Route::resource('kategori-barang', KategoriBarangController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:barang.kelola');
        Route::resource('kategori-barang', KategoriBarangController::class)
            ->only(['index', 'show'])
            ->middleware('izin:barang.lihat,barang.kelola');
        Route::resource('satuan-barang', SatuanBarangController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:barang.kelola');
        Route::resource('satuan-barang', SatuanBarangController::class)
            ->only(['index', 'show'])
            ->middleware('izin:barang.lihat,barang.kelola');
        Route::resource('lokasi-barang', LokasiBarangController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:barang.kelola');
        Route::resource('lokasi-barang', LokasiBarangController::class)
            ->only(['index', 'show'])
            ->middleware('izin:barang.lihat,barang.kelola');
        Route::get('pengaturan-inventaris', [PengaturanInventarisController::class, 'index'])
            ->middleware('izin:barang.kelola')
            ->name('pengaturan-inventaris.index');
        Route::put('pengaturan-inventaris', [PengaturanInventarisController::class, 'update'])
            ->middleware('izin:barang.kelola')
            ->name('pengaturan-inventaris.update');
        Route::resource('sumber-perolehan-barang', SumberPerolehanBarangController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:barang.kelola');
        Route::resource('sumber-perolehan-barang', SumberPerolehanBarangController::class)
            ->only(['index', 'show'])
            ->middleware('izin:barang.lihat,barang.kelola');
        Route::resource('unit-barang', UnitBarangController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:barang.kelola');
        Route::resource('unit-barang', UnitBarangController::class)
            ->only(['index', 'show'])
            ->middleware('izin:barang.lihat,barang.kelola');
        Route::get('label-barcode-inventaris', [LabelBarcodeInventarisController::class, 'index'])
            ->middleware('izin:barang.lihat,barang.kelola')
            ->name('label-barcode-inventaris.index');
        Route::get('stok-barang', [SaldoStokBarangController::class, 'index'])
            ->middleware('izin:barang.lihat,barang.kelola')
            ->name('saldo-stok-barang.index');
        Route::resource('mutasi-stok-barang', MutasiStokBarangController::class)
            ->only(['create', 'store'])
            ->middleware('izin:barang.kelola');
        Route::resource('mutasi-stok-barang', MutasiStokBarangController::class)
            ->only(['index', 'show'])
            ->middleware('izin:barang.lihat,barang.kelola');
        Route::middleware('izin:barang.peminjaman_kelola')->group(function () {
            Route::get('pengajuan-barang', [PengajuanBarangController::class, 'index'])->name('pengajuan-barang.index');
            Route::get('pengajuan-barang/{pengajuanBarang}', [PengajuanBarangController::class, 'show'])->name('pengajuan-barang.show');
            Route::patch('pengajuan-barang/{pengajuanBarang}/penuhi', [PengajuanBarangController::class, 'penuhi'])->name('pengajuan-barang.penuhi');
            Route::patch('pengajuan-barang/{pengajuanBarang}/tolak', [PengajuanBarangController::class, 'tolak'])->name('pengajuan-barang.tolak');
            Route::get('pengembalian-barang', [PengembalianBarangController::class, 'index'])->name('pengembalian-barang.index');
            Route::get('pengembalian-barang/identifikasi', [PengembalianBarangController::class, 'identifikasi'])->name('pengembalian-barang.identifikasi');
            Route::get('peminjaman-barang/create', [PeminjamanBarangController::class, 'create'])->name('peminjaman-barang.create');
            Route::post('peminjaman-barang', [PeminjamanBarangController::class, 'store'])->name('peminjaman-barang.store');
            Route::get('peminjaman-barang/identifikasi-peminjam', [PeminjamanBarangController::class, 'identifikasiPeminjam'])->name('peminjaman-barang.identifikasi-peminjam');
            Route::get('peminjaman-barang/identifikasi-barang', [PeminjamanBarangController::class, 'identifikasiBarang'])->name('peminjaman-barang.identifikasi-barang');
            Route::get('peminjaman-barang/{peminjamanBarang}/pengembalian/create', [PengembalianBarangController::class, 'create'])->name('pengembalian-barang.create');
            Route::post('peminjaman-barang/{peminjamanBarang}/pengembalian', [PengembalianBarangController::class, 'store'])->name('pengembalian-barang.store');
        });
        Route::resource('peminjaman-barang', PeminjamanBarangController::class)
            ->only(['index', 'show'])
            ->middleware('izin:barang.lihat,barang.peminjaman_kelola');
        Route::get('rekap-peminjaman-barang', [RekapPeminjamanBarangController::class, 'index'])
            ->middleware('izin:barang.lihat,barang.peminjaman_kelola')
            ->name('rekap-peminjaman-barang.index');
        Route::get('rekap-peminjaman-barang/cetak', [RekapPeminjamanBarangController::class, 'cetak'])
            ->middleware('izin:barang.lihat,barang.peminjaman_kelola')
            ->name('rekap-peminjaman-barang.cetak');

        Route::resource('skema-bobot-nilai', SkemaBobotNilaiController::class)
            ->middleware('izin:nilai.skema_kelola');
        Route::resource('komponen-nilai', KomponenNilaiController::class)
            ->middleware('izin:nilai.komponen_kelola');
        Route::middleware('izin:nilai.input')->group(function () {
            Route::get('input-nilai', [InputNilaiController::class, 'index'])->name('input-nilai.index');
            Route::post('input-nilai', [InputNilaiController::class, 'store'])->name('input-nilai.store');
            Route::patch('publikasi-nilai/{guruMataPelajaran}/{semester}/publikasikan', [PublikasiNilaiController::class, 'publikasikan'])
                ->name('publikasi-nilai.publikasikan');
            Route::patch('publikasi-nilai/{guruMataPelajaran}/{semester}/jadikan-draf', [PublikasiNilaiController::class, 'jadikanDraf'])
                ->name('publikasi-nilai.jadikan-draf');
        });
        Route::get('rekap-nilai-rapor', [RekapNilaiRaporController::class, 'index'])
            ->middleware('izin:nilai.rekap')
            ->name('rekap-nilai-rapor.index');
        Route::resource('pengaturan-absensi', PengaturanAbsensiController::class)
            ->middleware('izin:absensi.pengaturan_kelola');
        Route::resource('pengaturan-absensi-pegawai', PengaturanAbsensiPegawaiController::class)
            ->middleware('izin:absensi.pengaturan_kelola');
        Route::resource('kegiatan-ibadah', KegiatanIbadahController::class)
            ->parameters(['kegiatan-ibadah' => 'kegiatanIbadah'])
            ->middleware('izin:ibadah.pengaturan_kelola');
        Route::resource('jadwal-kegiatan-ibadah', JadwalKegiatanIbadahController::class)
            ->parameters(['jadwal-kegiatan-ibadah' => 'jadwalKegiatanIbadah'])
            ->except(['show'])
            ->middleware('izin:ibadah.pengaturan_kelola');
        Route::middleware('izin:ibadah.pengaturan_kelola')->group(function () {
            Route::get('pengaturan-berhalangan-ibadah', [PengaturanBerhalanganIbadahController::class, 'index'])
                ->name('pengaturan-berhalangan-ibadah.index');
            Route::put('pengaturan-berhalangan-ibadah', [PengaturanBerhalanganIbadahController::class, 'update'])
                ->name('pengaturan-berhalangan-ibadah.update');
            Route::post('pengaturan-berhalangan-ibadah/pendamping', [PengaturanBerhalanganIbadahController::class, 'storePendamping'])
                ->name('pengaturan-berhalangan-ibadah.pendamping.store');
            Route::delete('pengaturan-berhalangan-ibadah/pendamping/{penugasanPendampingIbadahSiswi}', [PengaturanBerhalanganIbadahController::class, 'destroyPendamping'])
                ->name('pengaturan-berhalangan-ibadah.pendamping.destroy');
        });
        Route::middleware('izin:ibadah.scan')->group(function () {
            Route::get('scan-kegiatan-ibadah', [ScanKegiatanIbadahController::class, 'index'])->name('scan-kegiatan-ibadah.index');
            Route::post('scan-kegiatan-ibadah', [ScanKegiatanIbadahController::class, 'store'])->name('scan-kegiatan-ibadah.store');
        });
        Route::get('scan-berhalangan-ibadah', [ScanBerhalanganIbadahController::class, 'index'])
            ->name('scan-berhalangan-ibadah.index');
        Route::post('scan-berhalangan-ibadah', [ScanBerhalanganIbadahController::class, 'store'])
            ->name('scan-berhalangan-ibadah.store');
        Route::get('konfirmasi-berhalangan-ibadah', [KonfirmasiBerhalanganIbadahController::class, 'index'])
            ->name('konfirmasi-berhalangan-ibadah.index');
        Route::get('konfirmasi-berhalangan-ibadah/{periodeBerhalanganIbadah}', [KonfirmasiBerhalanganIbadahController::class, 'show'])
            ->name('konfirmasi-berhalangan-ibadah.show');
        Route::put('konfirmasi-berhalangan-ibadah/{periodeBerhalanganIbadah}', [KonfirmasiBerhalanganIbadahController::class, 'update'])
            ->name('konfirmasi-berhalangan-ibadah.update');
        Route::get('rekap-berhalangan-ibadah', [RekapBerhalanganIbadahController::class, 'index'])
            ->name('rekap-berhalangan-ibadah.index');
        Route::get('rekap-berhalangan-ibadah/cetak', [RekapBerhalanganIbadahController::class, 'cetak'])
            ->name('rekap-berhalangan-ibadah.cetak');
        Route::get('rekap-kegiatan-ibadah', [RekapKegiatanIbadahController::class, 'index'])
            ->middleware('izin:ibadah.rekap')
            ->name('rekap-kegiatan-ibadah.index');
        Route::get('rekap-kegiatan-ibadah-bulanan', [RingkasanKegiatanIbadahBulananController::class, 'index'])
            ->middleware('izin:ibadah.rekap')
            ->name('rekap-kegiatan-ibadah.bulanan');
        Route::middleware('izin:ibadah.koreksi')->group(function () {
            Route::get('rekap-kegiatan-ibadah/{anggotaKelas}/koreksi', [KoreksiKegiatanIbadahController::class, 'edit'])
                ->name('rekap-kegiatan-ibadah.koreksi.edit');
            Route::put('rekap-kegiatan-ibadah/{anggotaKelas}/koreksi', [KoreksiKegiatanIbadahController::class, 'update'])
                ->name('rekap-kegiatan-ibadah.koreksi.update');
        });
        Route::resource('jadwal-piket-guru', JadwalPiketGuruController::class)
            ->parameters(['jadwal-piket-guru' => 'jadwalPiketGuru'])
            ->except(['show'])
            ->middleware('izin:piket_guru.kelola');
        Route::get('jadwal-piket-saya', [JadwalPiketSayaController::class, 'index'])
            ->middleware('izin:piket_guru.lihat_pribadi')
            ->name('jadwal-piket-saya.index');
        Route::get('piket-kehadiran-siswa', [PiketKehadiranSiswaController::class, 'index'])
            ->middleware('izin:piket_guru.catat_kehadiran')
            ->name('piket-kehadiran-siswa.index');
        Route::put('piket-kehadiran-siswa/{anggotaKelas}', [PiketKehadiranSiswaController::class, 'update'])
            ->middleware('izin:piket_guru.catat_kehadiran')
            ->name('piket-kehadiran-siswa.update');
        Route::middleware('izin:absensi.scan')->group(function () {
            Route::get('scan-absensi', [ScanAbsensiController::class, 'index'])->name('scan-absensi.index');
            Route::post('scan-absensi', [ScanAbsensiController::class, 'store'])->name('scan-absensi.store');
            Route::get('scan-absensi-pegawai', [ScanAbsensiPegawaiController::class, 'index'])->name('scan-absensi-pegawai.index');
            Route::post('scan-absensi-pegawai', [ScanAbsensiPegawaiController::class, 'store'])->name('scan-absensi-pegawai.store');
        });
        Route::get('rekap-absensi-harian', [RekapAbsensiHarianController::class, 'index'])
            ->middleware('izin:absensi.lihat,absensi.koreksi,absensi.koreksi_hari_ini,absensi.laporan')
            ->name('rekap-absensi-harian.index');
        Route::post('rekap-absensi-harian/proses-poin-keterlambatan', [RekapAbsensiHarianController::class, 'prosesPoinKeterlambatan'])
            ->middleware('izin:poin_siswa.pengaturan,poin_siswa.verifikasi_bk')
            ->name('rekap-absensi-harian.proses-poin-keterlambatan');
        Route::middleware('izin:absensi.koreksi,absensi.koreksi_hari_ini')->group(function () {
            Route::get('rekap-absensi-harian/{anggotaKelas}/koreksi', [RekapAbsensiHarianController::class, 'editKoreksi'])->name('rekap-absensi-harian.koreksi.edit');
            Route::put('rekap-absensi-harian/{anggotaKelas}/koreksi', [RekapAbsensiHarianController::class, 'updateKoreksi'])->name('rekap-absensi-harian.koreksi.update');
        });
        Route::get('rekap-absensi-pegawai-harian', [RekapAbsensiPegawaiHarianController::class, 'index'])
            ->middleware('izin:absensi.lihat,absensi.koreksi,absensi.laporan,absensi_pegawai.pribadi')
            ->name('rekap-absensi-pegawai-harian.index');
        Route::get('absensi-pegawai-saya/rekap', [RekapAbsensiPegawaiHarianController::class, 'pribadi'])
            ->middleware('izin:absensi_pegawai.pribadi')
            ->name('absensi-pegawai-saya.rekap');
        Route::middleware('izin:absensi.koreksi')->group(function () {
            Route::get('rekap-absensi-pegawai-harian/{pegawai}/koreksi', [RekapAbsensiPegawaiHarianController::class, 'editKoreksi'])->name('rekap-absensi-pegawai-harian.koreksi.edit');
            Route::put('rekap-absensi-pegawai-harian/{pegawai}/koreksi', [RekapAbsensiPegawaiHarianController::class, 'updateKoreksi'])->name('rekap-absensi-pegawai-harian.koreksi.update');
        });
        Route::get('laporan-absensi', [LaporanAbsensiController::class, 'index'])
            ->middleware('izin:absensi.laporan')
            ->name('laporan-absensi.index');
        Route::get('notifikasi-absensi-siswa', [NotifikasiAbsensiSiswaController::class, 'index'])
            ->middleware('izin:absensi.laporan')
            ->name('notifikasi-absensi-siswa.index');
        Route::get('laporan-absensi/export', [LaporanAbsensiController::class, 'exportExcel'])
            ->middleware('izin:laporan.export')
            ->name('laporan-absensi.export');
        Route::get('laporan-absensi-pegawai-bulanan', [LaporanAbsensiPegawaiBulananController::class, 'index'])
            ->middleware('izin:absensi.laporan,absensi_pegawai.pribadi')
            ->name('laporan-absensi-pegawai-bulanan.index');
        Route::get('absensi-pegawai-saya/laporan', [LaporanAbsensiPegawaiBulananController::class, 'pribadi'])
            ->middleware('izin:absensi_pegawai.pribadi')
            ->name('absensi-pegawai-saya.laporan');
        Route::get('absensi-pegawai-saya/laporan/cetak', [LaporanAbsensiPegawaiBulananController::class, 'cetakPribadi'])
            ->middleware('izin:absensi_pegawai.pribadi')
            ->name('absensi-pegawai-saya.cetak');
        Route::middleware('izin:laporan.export,absensi_pegawai.pribadi')->group(function () {
            Route::get('laporan-absensi-pegawai-bulanan/cetak', [LaporanAbsensiPegawaiBulananController::class, 'cetak'])->name('laporan-absensi-pegawai-bulanan.cetak');
            Route::get('laporan-absensi-pegawai-bulanan/{pegawai}/cetak', [LaporanAbsensiPegawaiBulananController::class, 'cetakPegawai'])->name('laporan-absensi-pegawai-bulanan.cetak-pegawai');
        });

        Route::middleware('izin:kenaikan_kelas.kelola')->group(function () {
            Route::get('kenaikan-kelas', [KenaikanKelasController::class, 'index'])->name('kenaikan-kelas.index');
            Route::post('kenaikan-kelas', [KenaikanKelasController::class, 'store'])->name('kenaikan-kelas.store');
        });

        Route::resource('kategori-pembinaan-siswa', KategoriPembinaanSiswaController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:bk.kelola');
        Route::resource('kategori-pembinaan-siswa', KategoriPembinaanSiswaController::class)
            ->only(['index', 'show'])
            ->middleware('izin:bk.lihat,bk.kelola');

        Route::resource('laporan-pembinaan-siswa', LaporanPembinaanSiswaController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:bk.kelola,poin_siswa.lapor');
        Route::resource('laporan-pembinaan-siswa', LaporanPembinaanSiswaController::class)
            ->only(['index', 'show'])
            ->middleware('izin:bk.lihat,bk.kelola,poin_siswa.lapor,poin_siswa.lihat,poin_siswa.sahkan_wakil');
        Route::get('laporan-saya', [LaporanPembinaanSiswaController::class, 'index'])
            ->middleware('izin:poin_siswa.lapor')
            ->name('laporan-saya.index');
        Route::get('laporan-saya/{laporanPembinaanSiswa}', [LaporanPembinaanSiswaController::class, 'show'])
            ->middleware('izin:poin_siswa.lapor')
            ->name('laporan-saya.show');
        Route::get('pembinaan-siswa-wali', [LaporanPembinaanSiswaController::class, 'index'])
            ->middleware('izin:guru_wali.lihat,poin_siswa.lihat')
            ->name('pembinaan-siswa-wali.index');
        Route::get('pembinaan-siswa-wali/{laporanPembinaanSiswa}', [LaporanPembinaanSiswaController::class, 'show'])
            ->middleware('izin:guru_wali.lihat,poin_siswa.lihat')
            ->name('pembinaan-siswa-wali.show');
        Route::get('pusat-verifikasi-pelanggaran', [PusatVerifikasiPelanggaranController::class, 'index'])
            ->middleware('izin:poin_siswa.lihat,poin_siswa.verifikasi_bk,poin_siswa.sahkan_wakil')
            ->name('pusat-verifikasi-pelanggaran.index');

        Route::post('laporan-pembinaan-siswa/{laporanPembinaanSiswa}/bukti', [BuktiLaporanPembinaanController::class, 'store'])
            ->middleware('izin:bk.kelola,poin_siswa.lapor,poin_siswa.verifikasi_bk')
            ->name('bukti-laporan-pembinaan.store');
        Route::get('bukti-laporan-pembinaan/{buktiLaporanPembinaanSiswa}/unduh', [BuktiLaporanPembinaanController::class, 'download'])
            ->middleware('izin:bk.lihat,bk.kelola,poin_siswa.lapor,poin_siswa.lihat,poin_siswa.verifikasi_bk')
            ->name('bukti-laporan-pembinaan.download');
        Route::delete('bukti-laporan-pembinaan/{buktiLaporanPembinaanSiswa}', [BuktiLaporanPembinaanController::class, 'destroy'])
            ->middleware('izin:bk.kelola,poin_siswa.lapor,poin_siswa.verifikasi_bk')
            ->name('bukti-laporan-pembinaan.destroy');
        Route::post('laporan-pembinaan-siswa/{laporanPembinaanSiswa}/saksi', [SaksiLaporanPembinaanController::class, 'store'])
            ->middleware('izin:bk.kelola,poin_siswa.lapor,poin_siswa.verifikasi_bk')
            ->name('saksi-laporan-pembinaan.store');
        Route::delete('saksi-laporan-pembinaan/{saksiLaporanPembinaanSiswa}', [SaksiLaporanPembinaanController::class, 'destroy'])
            ->middleware('izin:bk.kelola,poin_siswa.lapor,poin_siswa.verifikasi_bk')
            ->name('saksi-laporan-pembinaan.destroy');
        Route::post('laporan-pembinaan-siswa/{laporanPembinaanSiswa}/klarifikasi', [KlarifikasiSiswaPembinaanController::class, 'store'])
            ->middleware('izin:poin_siswa.verifikasi_bk')
            ->name('klarifikasi-siswa-pembinaan.store');

        Route::post('laporan-pembinaan-siswa/{laporanPembinaanSiswa}/verifikasi-bk', [VerifikasiPelanggaranSiswaController::class, 'verifikasiBk'])
            ->middleware('izin:poin_siswa.verifikasi_bk')
            ->name('verifikasi-pelanggaran.bk');
        Route::post('laporan-pembinaan-siswa/{laporanPembinaanSiswa}/pengesahan-wakil', [VerifikasiPelanggaranSiswaController::class, 'pengesahanWakil'])
            ->middleware('izin:poin_siswa.sahkan_wakil')
            ->name('verifikasi-pelanggaran.wakil');
        Route::get('pengaturan-poin-keterlambatan', [PengaturanPoinKeterlambatanController::class, 'index'])
            ->middleware('izin:poin_siswa.pengaturan')
            ->name('pengaturan-poin-keterlambatan.index');
        Route::get('pengaturan-poin-keterlambatan/{tahunPelajaran}/edit', [PengaturanPoinKeterlambatanController::class, 'edit'])
            ->middleware('izin:poin_siswa.pengaturan')
            ->name('pengaturan-poin-keterlambatan.edit');
        Route::put('pengaturan-poin-keterlambatan/{tahunPelajaran}', [PengaturanPoinKeterlambatanController::class, 'update'])
            ->middleware('izin:poin_siswa.pengaturan')
            ->name('pengaturan-poin-keterlambatan.update');

        Route::get('pengaturan-peringatan-dini-poin', [PengaturanPeringatanDiniPoinController::class, 'index'])
            ->middleware('izin:poin_siswa.pengaturan')
            ->name('pengaturan-peringatan-dini-poin.index');
        Route::get('pengaturan-peringatan-dini-poin/{tahunPelajaran}/edit', [PengaturanPeringatanDiniPoinController::class, 'edit'])
            ->middleware('izin:poin_siswa.pengaturan')
            ->name('pengaturan-peringatan-dini-poin.edit');
        Route::put('pengaturan-peringatan-dini-poin/{tahunPelajaran}', [PengaturanPeringatanDiniPoinController::class, 'update'])
            ->middleware('izin:poin_siswa.pengaturan')
            ->name('pengaturan-peringatan-dini-poin.update');

        Route::get('peringatan-dini-siswa', [PeringatanDiniSiswaController::class, 'index'])
            ->middleware('izin:poin_siswa.lihat')
            ->name('peringatan-dini-siswa.index');
        Route::post('peringatan-dini-siswa/proses', [PeringatanDiniSiswaController::class, 'proses'])
            ->middleware('izin:poin_siswa.pengaturan')
            ->name('peringatan-dini-siswa.proses');

        Route::get('tindak-lanjut-siswa', [PendampinganSiswaController::class, 'index'])
            ->middleware('izin:poin_siswa.lihat')
            ->name('pendampingan-siswa.index');
        Route::get('tindak-lanjut-siswa-wali', [PendampinganSiswaController::class, 'index'])
            ->middleware('izin:guru_wali.lihat,poin_siswa.lihat')
            ->name('pendampingan-siswa-wali.index');
        Route::get('tindak-lanjut-siswa-wali/{pendampinganSiswa}/edit', [PendampinganSiswaController::class, 'edit'])
            ->middleware('izin:guru_wali.lihat,poin_siswa.lihat,poin_siswa.pendampingan_kelola')
            ->name('pendampingan-siswa-wali.edit');
        Route::put('tindak-lanjut-siswa-wali/{pendampinganSiswa}', [PendampinganSiswaController::class, 'update'])
            ->middleware('izin:guru_wali.lihat,poin_siswa.lihat,poin_siswa.pendampingan_kelola')
            ->name('pendampingan-siswa-wali.update');
        Route::middleware('izin:poin_siswa.pendampingan_kelola')->group(function () {
            Route::get('tindak-lanjut-siswa/tambah', [PendampinganSiswaController::class, 'create'])
                ->name('pendampingan-siswa.create');
            Route::post('tindak-lanjut-siswa', [PendampinganSiswaController::class, 'store'])
                ->name('pendampingan-siswa.store');
            Route::get('tindak-lanjut-siswa/{pendampinganSiswa}/edit', [PendampinganSiswaController::class, 'edit'])
                ->name('pendampingan-siswa.edit');
            Route::put('tindak-lanjut-siswa/{pendampinganSiswa}', [PendampinganSiswaController::class, 'update'])
                ->name('pendampingan-siswa.update');
        });

        Route::get('rekap-poin-siswa', [RekapPoinSiswaController::class, 'index'])
            ->middleware('izin:poin_siswa.lihat')
            ->name('rekap-poin-siswa.index');
        Route::get('rekap-poin-siswa-wali', [RekapPoinSiswaController::class, 'index'])
            ->middleware('izin:guru_wali.lihat,poin_siswa.lihat')
            ->name('rekap-poin-siswa-wali.index');
        Route::get('rekap-poin-siswa-wali/{siswa}', [RekapPoinSiswaController::class, 'show'])
            ->middleware('izin:guru_wali.lihat,poin_siswa.lihat')
            ->name('rekap-poin-siswa-wali.show');
        Route::get('rekap-poin-siswa/{siswa}', [RekapPoinSiswaController::class, 'show'])
            ->middleware('izin:poin_siswa.lihat')
            ->name('rekap-poin-siswa.show');
        Route::get('rekap-poin-siswa/{siswa}/laporan', [DokumenPoinSiswaController::class, 'laporan'])
            ->middleware('izin:poin_siswa.lihat')
            ->name('dokumen-poin-siswa.laporan');
        Route::get('rekap-poin-siswa/{siswa}/surat', [DokumenPoinSiswaController::class, 'surat'])
            ->middleware('izin:poin_siswa.lihat')
            ->name('dokumen-poin-siswa.surat');
        Route::get('rekap-poin-siswa/{siswa}/surat/cetak', [DokumenPoinSiswaController::class, 'cetakSurat'])
            ->middleware('izin:poin_siswa.lihat')
            ->name('dokumen-poin-siswa.cetak-surat');
        Route::middleware('izin:poin_siswa.reward_kelola')->group(function () {
            Route::get('pengurangan-poin-siswa', [PenguranganPoinSiswaController::class, 'index'])->name('pengurangan-poin-siswa.index');
            Route::post('pengurangan-poin-siswa', [PenguranganPoinSiswaController::class, 'store'])->name('pengurangan-poin-siswa.store');
        });
        Route::get('sanksi-poin-siswa', [SanksiPoinSiswaController::class, 'index'])
            ->middleware('izin:poin_siswa.lihat,poin_siswa.sanksi_kelola')
            ->name('sanksi-poin-siswa.index');
        Route::get('sanksi-poin-siswa/{sanksiPoinSiswa}', [SanksiPoinSiswaController::class, 'show'])
            ->middleware('izin:poin_siswa.lihat,poin_siswa.sanksi_kelola')
            ->name('sanksi-poin-siswa.show');
        Route::put('sanksi-poin-siswa/{sanksiPoinSiswa}', [SanksiPoinSiswaController::class, 'update'])
            ->middleware('izin:poin_siswa.lihat,poin_siswa.sanksi_kelola')
            ->name('sanksi-poin-siswa.update');
        Route::post('sanksi-poin-siswa/{sanksiPoinSiswa}/bukti', [BuktiPelaksanaanSanksiController::class, 'store'])
            ->middleware('izin:poin_siswa.lihat,poin_siswa.sanksi_kelola')
            ->name('bukti-pelaksanaan-sanksi.store');
        Route::get('bukti-pelaksanaan-sanksi/{buktiPelaksanaanSanksi}/unduh', [BuktiPelaksanaanSanksiController::class, 'download'])
            ->middleware('izin:poin_siswa.lihat,poin_siswa.sanksi_kelola')
            ->name('bukti-pelaksanaan-sanksi.download');
        Route::delete('bukti-pelaksanaan-sanksi/{buktiPelaksanaanSanksi}', [BuktiPelaksanaanSanksiController::class, 'destroy'])
            ->middleware('izin:poin_siswa.lihat,poin_siswa.sanksi_kelola')
            ->name('bukti-pelaksanaan-sanksi.destroy');
        Route::patch('pengurangan-poin-siswa/{penguranganPoinSiswa}/putusan', [PenguranganPoinSiswaController::class, 'putuskan'])
            ->middleware('izin:poin_siswa.putus_konflik')
            ->name('pengurangan-poin-siswa.putuskan');

        Route::resource('jenis-pelanggaran-siswa', JenisPelanggaranSiswaController::class)
            ->except(['show'])
            ->middleware('izin:poin_siswa.pengaturan');
        Route::resource('aturan-sanksi-poin', AturanSanksiPoinController::class)
            ->except(['show'])
            ->middleware('izin:poin_siswa.pengaturan');
        Route::get('pengaturan-batas-proses-pelanggaran', [PengaturanBatasProsesPelanggaranController::class, 'index'])
            ->middleware('izin:poin_siswa.pengaturan')
            ->name('pengaturan-batas-proses-pelanggaran.index');
        Route::get('pengaturan-batas-proses-pelanggaran/{tahunPelajaran}/edit', [PengaturanBatasProsesPelanggaranController::class, 'edit'])
            ->middleware('izin:poin_siswa.pengaturan')
            ->name('pengaturan-batas-proses-pelanggaran.edit');
        Route::put('pengaturan-batas-proses-pelanggaran/{tahunPelajaran}', [PengaturanBatasProsesPelanggaranController::class, 'update'])
            ->middleware('izin:poin_siswa.pengaturan')
            ->name('pengaturan-batas-proses-pelanggaran.update');

        Route::get('penugasan-guru-wali', [PenugasanGuruWaliController::class, 'index'])
            ->middleware('izin:guru_wali.kelola')
            ->name('penugasan-guru-wali.index');
        Route::post('penugasan-guru-wali', [PenugasanGuruWaliController::class, 'store'])
            ->middleware('izin:guru_wali.kelola')
            ->name('penugasan-guru-wali.store');
        Route::delete('penugasan-guru-wali/{penugasanGuruWali}', [PenugasanGuruWaliController::class, 'destroy'])
            ->middleware('izin:guru_wali.kelola')
            ->name('penugasan-guru-wali.destroy');
        Route::get('siswa-wali-saya', [SiswaWaliSayaController::class, 'index'])
            ->middleware('izin:guru_wali.lihat')
            ->name('siswa-wali-saya.index');
        Route::get('siswa-wali-saya/{siswa}', [SiswaWaliSayaController::class, 'show'])
            ->middleware('izin:guru_wali.lihat')
            ->name('siswa-wali-saya.show');
        Route::middleware('izin:bk.kelola')->group(function () {
            Route::get('laporan-pembinaan-siswa/{laporanPembinaanSiswa}/tindak-lanjut/create', [TindakLanjutPembinaanSiswaController::class, 'create'])->name('tindak-lanjut-pembinaan-siswa.create');
            Route::post('laporan-pembinaan-siswa/{laporanPembinaanSiswa}/tindak-lanjut', [TindakLanjutPembinaanSiswaController::class, 'store'])->name('tindak-lanjut-pembinaan-siswa.store');
            Route::get('tindak-lanjut-pembinaan-siswa/{tindakLanjutPembinaanSiswa}/edit', [TindakLanjutPembinaanSiswaController::class, 'edit'])->name('tindak-lanjut-pembinaan-siswa.edit');
            Route::put('tindak-lanjut-pembinaan-siswa/{tindakLanjutPembinaanSiswa}', [TindakLanjutPembinaanSiswaController::class, 'update'])->name('tindak-lanjut-pembinaan-siswa.update');
            Route::delete('tindak-lanjut-pembinaan-siswa/{tindakLanjutPembinaanSiswa}', [TindakLanjutPembinaanSiswaController::class, 'destroy'])->name('tindak-lanjut-pembinaan-siswa.destroy');
        });

        Route::middleware('izin:kelas.kelola')->group(function () {
            Route::post('penempatan-siswa/masukkan', [PenempatanSiswaController::class, 'storeMassal'])->name('penempatan-siswa.store-massal');
            Route::post('kelas/{kelas}/anggota-kelas', [AnggotaKelasController::class, 'store'])->name('anggota-kelas.store');
            Route::patch('anggota-kelas/{anggotaKelas}', [AnggotaKelasController::class, 'update'])->name('anggota-kelas.update');
            Route::delete('anggota-kelas/{anggotaKelas}', [AnggotaKelasController::class, 'destroy'])->name('anggota-kelas.destroy');
        });
        Route::get('penempatan-siswa', [PenempatanSiswaController::class, 'index'])
            ->middleware('izin:kelas.lihat,kelas.kelola')
            ->name('penempatan-siswa.index');
        Route::resource('kelas', KelasController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:kelas.kelola')
            ->parameters([
                'kelas' => 'kelas',
            ]);
        Route::resource('kelas', KelasController::class)
            ->only(['index', 'show'])
            ->middleware('izin:kelas.lihat,kelas.kelola')
            ->parameters([
                'kelas' => 'kelas',
            ]);
    });
});
