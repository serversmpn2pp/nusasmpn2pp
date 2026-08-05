<?php

use App\Http\Controllers\AksesUjianCbtController;
use App\Http\Controllers\AkunPegawaiController;
use App\Http\Controllers\AkunSiswaController;
use App\Http\Controllers\AnggotaKelasController;
use App\Http\Controllers\AturanSanksiPoinController;
use App\Http\Controllers\AutentikasiController;
use App\Http\Controllers\BarangController;
use App\Http\Controllers\BerandaController;
use App\Http\Controllers\BuktiLaporanPembinaanController;
use App\Http\Controllers\BuktiPelaksanaanSanksiController;
use App\Http\Controllers\DashboardSaranaPrasaranaController;
use App\Http\Controllers\DokumenPoinSiswaController;
use App\Http\Controllers\GuruMataPelajaranController;
use App\Http\Controllers\InputNilaiController;
use App\Http\Controllers\JadwalKelasSayaController;
use App\Http\Controllers\JadwalPelajaranController;
use App\Http\Controllers\JadwalSayaController;
use App\Http\Controllers\JadwalUjianCbtController;
use App\Http\Controllers\JamPelajaranController;
use App\Http\Controllers\JenisPelanggaranSiswaController;
use App\Http\Controllers\JenisPerangkatAjarController;
use App\Http\Controllers\JenisUjianCbtController;
use App\Http\Controllers\KartuPegawaiController;
use App\Http\Controllers\KartuPelajarController;
use App\Http\Controllers\KartuPesertaUjianCbtController;
use App\Http\Controllers\KategoriBarangController;
use App\Http\Controllers\KategoriPembinaanSiswaController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\KelasWaliController;
use App\Http\Controllers\KenaikanKelasController;
use App\Http\Controllers\KlarifikasiSiswaPembinaanController;
use App\Http\Controllers\KomponenNilaiController;
use App\Http\Controllers\KoreksiHasilScanLjkOmrController;
use App\Http\Controllers\KoreksiManualUjianCbtController;
use App\Http\Controllers\KoreksiOtomatisUjianCbtController;
use App\Http\Controllers\KunciJawabanUjianOmrController;
use App\Http\Controllers\LabelBarcodeInventarisController;
use App\Http\Controllers\LaporanAbsensiController;
use App\Http\Controllers\LaporanAbsensiPegawaiBulananController;
use App\Http\Controllers\LaporanInventarisBulananController;
use App\Http\Controllers\LaporanPembinaanSiswaController;
use App\Http\Controllers\LembarJawabUjianOmrController;
use App\Http\Controllers\LokasiBarangController;
use App\Http\Controllers\MataPelajaranController;
use App\Http\Controllers\MonitoringUjianCbtController;
use App\Http\Controllers\MutasiStokBarangController;
use App\Http\Controllers\NotifikasiAbsensiSiswaController;
use App\Http\Controllers\NotifikasiPenggunaController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\PemeriksaanPerangkatAjarController;
use App\Http\Controllers\PeminjamanBarangController;
use App\Http\Controllers\PendampinganSiswaController;
use App\Http\Controllers\PenempatanSiswaController;
use App\Http\Controllers\PengaturanAbsensiController;
use App\Http\Controllers\PengaturanAbsensiPegawaiController;
use App\Http\Controllers\PengaturanBatasProsesPelanggaranController;
use App\Http\Controllers\PengaturanPeringatanDiniPoinController;
use App\Http\Controllers\PengaturanPoinKeterlambatanController;
use App\Http\Controllers\PengembalianBarangController;
use App\Http\Controllers\PenguranganPoinSiswaController;
use App\Http\Controllers\PenugasanGuruWaliController;
use App\Http\Controllers\PeranController;
use App\Http\Controllers\PerangkatAjarSayaController;
use App\Http\Controllers\PergantianGuruMataPelajaranController;
use App\Http\Controllers\PeringatanDiniSiswaController;
use App\Http\Controllers\PesertaUjianCbtController;
use App\Http\Controllers\ProfilPegawaiController;
use App\Http\Controllers\PusatVerifikasiPelanggaranController;
use App\Http\Controllers\RekapAbsensiHarianController;
use App\Http\Controllers\RekapAbsensiPegawaiHarianController;
use App\Http\Controllers\RekapHasilUjianCbtController;
use App\Http\Controllers\RekapNilaiRaporController;
use App\Http\Controllers\RekapPeminjamanBarangController;
use App\Http\Controllers\RekapPoinSiswaController;
use App\Http\Controllers\RuangUjianCbtController;
use App\Http\Controllers\SaksiLaporanPembinaanController;
use App\Http\Controllers\SaldoStokBarangController;
use App\Http\Controllers\SanksiPoinSiswaController;
use App\Http\Controllers\SatuanBarangController;
use App\Http\Controllers\ScanAbsensiController;
use App\Http\Controllers\ScanAbsensiPegawaiController;
use App\Http\Controllers\ScanLjkUjianOmrController;
use App\Http\Controllers\SesiUjianCbtController;
use App\Http\Controllers\SiswaController;
use App\Http\Controllers\SiswaWaliSayaController;
use App\Http\Controllers\SkemaBobotNilaiController;
use App\Http\Controllers\SoalCbtController;
use App\Http\Controllers\SoalUjianCbtController;
use App\Http\Controllers\StatusKelengkapanPanitiaCbtController;
use App\Http\Controllers\TahunPelajaranController;
use App\Http\Controllers\TerapkanNilaiCbtController;
use App\Http\Controllers\TerapkanNilaiOmrController;
use App\Http\Controllers\TindakLanjutPembinaanSiswaController;
use App\Http\Controllers\UjianCbtController;
use App\Http\Controllers\UjianOmrController;
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

Route::prefix('cbt')->name('cbt.')->group(function () {
    Route::get('masuk', [AksesUjianCbtController::class, 'createLogin'])->name('login');
    Route::post('masuk', [AksesUjianCbtController::class, 'storeLogin'])->name('login.store');
    Route::post('keluar', [AksesUjianCbtController::class, 'logout'])->name('logout');
    Route::get('ujian', [AksesUjianCbtController::class, 'show'])->name('ujian.show');
    Route::post('ujian/mulai', [AksesUjianCbtController::class, 'mulai'])->name('ujian.mulai');
    Route::get('ujian/kerjakan', [AksesUjianCbtController::class, 'kerjakan'])->name('ujian.kerjakan');
    Route::post('ujian/simpan', [AksesUjianCbtController::class, 'simpan'])->name('ujian.simpan');
    Route::get('ujian/selesai', [AksesUjianCbtController::class, 'selesai'])->name('ujian.selesai');
});

Route::middleware('auth')->group(function () {
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

        Route::resource('peran', PeranController::class)
            ->only(['index'])
            ->middleware('izin:peran.lihat,peran.kelola');
        Route::resource('peran', PeranController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:peran.kelola');

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
        Route::get('kelas-wali', [KelasWaliController::class, 'index'])
            ->middleware('izin:kelas.lihat')
            ->name('kelas-wali.index');
        Route::resource('jenis-perangkat-ajar', JenisPerangkatAjarController::class)
            ->middleware('izin:perangkat_ajar.jenis_kelola');
        Route::resource('jenis-ujian-cbt', JenisUjianCbtController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:cbt.kelola');
        Route::resource('jenis-ujian-cbt', JenisUjianCbtController::class)
            ->only(['index', 'show'])
            ->middleware('izin:cbt.lihat,cbt.kelola');
        Route::get('jadwal-ujian-cbt', [JadwalUjianCbtController::class, 'index'])
            ->middleware('izin:cbt.lihat,cbt.kelola')
            ->name('jadwal-ujian-cbt.index');
        Route::get('status-kelengkapan-panitia-cbt', [StatusKelengkapanPanitiaCbtController::class, 'index'])
            ->middleware('izin:cbt.lihat,cbt.kelola')
            ->name('status-kelengkapan-panitia-cbt.index');
        Route::middleware('izin:cbt.kelola')->group(function () {
            Route::post('jadwal-ujian-cbt/kegiatan', [JadwalUjianCbtController::class, 'storeKegiatan'])->name('jadwal-ujian-cbt.kegiatan.store');
            Route::put('jadwal-ujian-cbt/kegiatan/{kegiatanUjianCbt}', [JadwalUjianCbtController::class, 'updateKegiatan'])->name('jadwal-ujian-cbt.kegiatan.update');
            Route::delete('jadwal-ujian-cbt/kegiatan/{kegiatanUjianCbt}', [JadwalUjianCbtController::class, 'destroyKegiatan'])->name('jadwal-ujian-cbt.kegiatan.destroy');
            Route::post('jadwal-ujian-cbt/jadwal', [JadwalUjianCbtController::class, 'storeJadwal'])->name('jadwal-ujian-cbt.jadwal.store');
            Route::put('jadwal-ujian-cbt/jadwal/{jadwalUjianCbt}', [JadwalUjianCbtController::class, 'updateJadwal'])->name('jadwal-ujian-cbt.jadwal.update');
            Route::delete('jadwal-ujian-cbt/jadwal/{jadwalUjianCbt}', [JadwalUjianCbtController::class, 'destroyJadwal'])->name('jadwal-ujian-cbt.jadwal.destroy');
            Route::put('jadwal-ujian-cbt/jadwal/{jadwalUjianCbt}/kunci', [JadwalUjianCbtController::class, 'kunciJadwal'])->name('jadwal-ujian-cbt.jadwal.kunci');
            Route::put('jadwal-ujian-cbt/jadwal/{jadwalUjianCbt}/buka-kunci', [JadwalUjianCbtController::class, 'bukaKunciJadwal'])->name('jadwal-ujian-cbt.jadwal.buka-kunci');
        });
        Route::resource('ujian-cbt', UjianCbtController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:cbt.kelola');
        Route::middleware('izin:cbt.kelola')->group(function () {
            Route::get('ujian-cbt/{ujianCbt}/soal', [SoalUjianCbtController::class, 'edit'])->name('ujian-cbt.soal.edit');
            Route::put('ujian-cbt/{ujianCbt}/soal', [SoalUjianCbtController::class, 'update'])->name('ujian-cbt.soal.update');
            Route::get('ujian-cbt/{ujianCbt}/peserta', [PesertaUjianCbtController::class, 'index'])->name('ujian-cbt.peserta.index');
            Route::post('ujian-cbt/{ujianCbt}/peserta/generate', [PesertaUjianCbtController::class, 'storeMassal'])->name('ujian-cbt.peserta.generate');
            Route::put('ujian-cbt/{ujianCbt}/peserta', [PesertaUjianCbtController::class, 'update'])->name('ujian-cbt.peserta.update');
            Route::get('ujian-cbt/{ujianCbt}/monitoring', [MonitoringUjianCbtController::class, 'index'])->name('ujian-cbt.monitoring.index');
            Route::post('ujian-cbt/{ujianCbt}/koreksi-otomatis', [KoreksiOtomatisUjianCbtController::class, 'store'])->name('ujian-cbt.koreksi-otomatis.store');
            Route::get('ujian-cbt/{ujianCbt}/koreksi-manual', [KoreksiManualUjianCbtController::class, 'index'])->name('ujian-cbt.koreksi-manual.index');
            Route::put('ujian-cbt/{ujianCbt}/koreksi-manual', [KoreksiManualUjianCbtController::class, 'update'])->name('ujian-cbt.koreksi-manual.update');
            Route::post('ujian-cbt/{ujianCbt}/terapkan-nilai', [TerapkanNilaiCbtController::class, 'store'])->name('ujian-cbt.terapkan-nilai.store');
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
            Route::get('ujian-cbt/{ujianCbt}/kartu-peserta', [KartuPesertaUjianCbtController::class, 'index'])->name('ujian-cbt.kartu-peserta.index');
            Route::post('ujian-cbt/{ujianCbt}/sesi', [SesiUjianCbtController::class, 'store'])->name('ujian-cbt.sesi.store');
            Route::put('ujian-cbt/{ujianCbt}/sesi/{sesiUjianCbt}', [SesiUjianCbtController::class, 'update'])->name('ujian-cbt.sesi.update');
            Route::delete('ujian-cbt/{ujianCbt}/sesi/{sesiUjianCbt}', [SesiUjianCbtController::class, 'destroy'])->name('ujian-cbt.sesi.destroy');
        });
        Route::get('ujian-cbt/{ujianCbt}/hasil', [RekapHasilUjianCbtController::class, 'index'])
            ->middleware('izin:cbt.lihat,cbt.kelola')
            ->name('ujian-cbt.hasil.index');
        Route::resource('ujian-cbt', UjianCbtController::class)
            ->only(['index', 'show'])
            ->middleware('izin:cbt.lihat,cbt.kelola');
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
        Route::get('dashboard-sarana-prasarana', [DashboardSaranaPrasaranaController::class, 'index'])
            ->middleware('izin:barang.lihat,barang.kelola,barang.peminjaman_kelola')
            ->name('dashboard-sarana-prasarana.index');
        Route::get('laporan-inventaris-bulanan', [LaporanInventarisBulananController::class, 'index'])
            ->middleware('izin:barang.lihat,barang.kelola')
            ->name('laporan-inventaris-bulanan.index');
        Route::get('laporan-inventaris-bulanan/cetak', [LaporanInventarisBulananController::class, 'cetak'])
            ->middleware('izin:barang.lihat,barang.kelola')
            ->name('laporan-inventaris-bulanan.cetak');
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
        });
        Route::get('rekap-nilai-rapor', [RekapNilaiRaporController::class, 'index'])
            ->middleware('izin:nilai.rekap')
            ->name('rekap-nilai-rapor.index');
        Route::middleware('izin:omr.kelola')->group(function () {
            Route::get('ujian-omr/{ujianOmr}/versi-soal/{versiSoalUjianOmr}/kunci-jawaban', [KunciJawabanUjianOmrController::class, 'edit'])->name('ujian-omr.kunci-jawaban.edit');
            Route::put('ujian-omr/{ujianOmr}/versi-soal/{versiSoalUjianOmr}/kunci-jawaban', [KunciJawabanUjianOmrController::class, 'update'])->name('ujian-omr.kunci-jawaban.update');
            Route::post('ujian-omr/{ujianOmr}/lembar-jawab/generate', [LembarJawabUjianOmrController::class, 'store'])->name('ujian-omr.lembar-jawab.generate');
            Route::get('ujian-omr/{ujianOmr}/scan', [ScanLjkUjianOmrController::class, 'index'])->name('ujian-omr.scan.index');
            Route::post('ujian-omr/{ujianOmr}/scan', [ScanLjkUjianOmrController::class, 'store'])->name('ujian-omr.scan.store');
            Route::get('ujian-omr/{ujianOmr}/scan/{batchScan}', [ScanLjkUjianOmrController::class, 'show'])->name('ujian-omr.scan.show');
            Route::post('ujian-omr/{ujianOmr}/scan/{batchScan}/terapkan-nilai', [TerapkanNilaiOmrController::class, 'store'])->name('ujian-omr.scan.terapkan-nilai');
            Route::get('ujian-omr/{ujianOmr}/scan/{batchScan}/hasil/{hasilScan}/periksa', [KoreksiHasilScanLjkOmrController::class, 'edit'])->name('ujian-omr.scan.hasil.periksa');
            Route::put('ujian-omr/{ujianOmr}/scan/{batchScan}/hasil/{hasilScan}/periksa', [KoreksiHasilScanLjkOmrController::class, 'update'])->name('ujian-omr.scan.hasil.koreksi');
            Route::get('ujian-omr/{ujianOmr}/scan/{batchScan}/hasil/{hasilScan}/pratinjau', [ScanLjkUjianOmrController::class, 'pratinjau'])->name('ujian-omr.scan.pratinjau');
        });
        Route::get('ujian-omr/{ujianOmr}/lembar-jawab/cetak', [LembarJawabUjianOmrController::class, 'cetak'])
            ->middleware('izin:omr.lihat,omr.kelola')
            ->name('ujian-omr.lembar-jawab.cetak');
        Route::resource('ujian-omr', UjianOmrController::class)
            ->parameters(['ujian-omr' => 'ujianOmr'])
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:omr.kelola');
        Route::resource('ujian-omr', UjianOmrController::class)
            ->parameters(['ujian-omr' => 'ujianOmr'])
            ->only(['index', 'show'])
            ->middleware('izin:omr.lihat,omr.kelola');

        Route::resource('pengaturan-absensi', PengaturanAbsensiController::class)
            ->middleware('izin:absensi.pengaturan_kelola');
        Route::resource('pengaturan-absensi-pegawai', PengaturanAbsensiPegawaiController::class)
            ->middleware('izin:absensi.pengaturan_kelola');
        Route::middleware('izin:absensi.scan')->group(function () {
            Route::get('scan-absensi', [ScanAbsensiController::class, 'index'])->name('scan-absensi.index');
            Route::post('scan-absensi', [ScanAbsensiController::class, 'store'])->name('scan-absensi.store');
            Route::get('scan-absensi-pegawai', [ScanAbsensiPegawaiController::class, 'index'])->name('scan-absensi-pegawai.index');
            Route::post('scan-absensi-pegawai', [ScanAbsensiPegawaiController::class, 'store'])->name('scan-absensi-pegawai.store');
        });
        Route::get('rekap-absensi-harian', [RekapAbsensiHarianController::class, 'index'])
            ->middleware('izin:absensi.lihat,absensi.koreksi,absensi.laporan')
            ->name('rekap-absensi-harian.index');
        Route::post('rekap-absensi-harian/proses-poin-keterlambatan', [RekapAbsensiHarianController::class, 'prosesPoinKeterlambatan'])
            ->middleware('izin:poin_siswa.pengaturan,poin_siswa.verifikasi_bk')
            ->name('rekap-absensi-harian.proses-poin-keterlambatan');
        Route::middleware('izin:absensi.koreksi')->group(function () {
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
            ->middleware('izin:bk.lihat,bk.kelola,poin_siswa.lapor,poin_siswa.lihat');
        Route::get('pembinaan-siswa-wali', [LaporanPembinaanSiswaController::class, 'index'])
            ->middleware('izin:guru_wali.lihat,poin_siswa.lihat')
            ->name('pembinaan-siswa-wali.index');
        Route::get('pembinaan-siswa-wali/{laporanPembinaanSiswa}', [LaporanPembinaanSiswaController::class, 'show'])
            ->middleware('izin:guru_wali.lihat,poin_siswa.lihat')
            ->name('pembinaan-siswa-wali.show');
        Route::get('pusat-verifikasi-pelanggaran', [PusatVerifikasiPelanggaranController::class, 'index'])
            ->middleware('izin:poin_siswa.lihat,poin_siswa.verifikasi_bk')
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
